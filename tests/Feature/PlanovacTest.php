<?php

namespace Tests\Feature;

use App\Models\MailLog;
use App\Models\Nastaveni;
use App\Support\FrontaUloh;
use App\Support\Posta\Odchozi;
use App\Support\Posta\Propojeni;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Plánovač na sdíleném serveru: v cronu má aplikace jen schedule:run,
 * všechno ostatní běží uvnitř něj a jen když je co dělat – žádný další
 * proces PHP každou minutu (dřív posta:fronta a queue:work z cronu).
 */
class PlanovacTest extends TestCase
{
    use RefreshDatabase;

    private function udalost(string $nazev): Event
    {
        $udalost = collect(app(Schedule::class)->events())->first(fn (Event $e) => $e->description === $nazev);
        $this->assertNotNull($udalost, "v plánovači chybí {$nazev}");

        return $udalost;
    }

    private function propoj(): void
    {
        Nastaveni::nastav('posta.url', 'https://posta.test');
        Nastaveni::nastav('posta.token', Crypt::encryptString('pst_testovaci'));
        Nastaveni::nastav('posta.webhook_tajemstvi', Crypt::encryptString('tajemstvi-webhooku-1'));
        Nastaveni::nastav('posta.adresy', json_encode([['adresa' => 'info@pekarna-novak.cz', 'jmeno' => 'Pekárna Novák']]));
        Propojeni::pouzij();
        Mail::forgetMailers();
    }

    public function test_posta_a_fronta_bez_noveho_procesu_kazdou_minutu(): void
    {
        $udalosti = collect(app(Schedule::class)->events());

        $this->assertFalse($udalosti->contains(fn (Event $e) => str_contains((string) $e->command, 'posta:')),
            'pošta se plánuje jako closure uvnitř schedule:run, ne jako nový proces');

        // queue:work jen jako úloha „fronta“ – na pozadí a jen když je práce.
        $fronty = $udalosti->filter(fn (Event $e) => str_contains((string) $e->command, 'queue:work'));
        $this->assertCount(1, $fronty);
        $this->assertSame('fronta', $fronty->first()->description);
    }

    public function test_posta_fronta_jen_kdyz_je_co_predat(): void
    {
        $udalost = $this->udalost('posta-fronta');
        $this->assertInstanceOf(CallbackEvent::class, $udalost);

        // Nepropojeno, prázdná fronta – nic se nespustí.
        $this->assertFalse($udalost->filtersPass($this->app));
        $this->propoj();
        $this->assertFalse($udalost->filtersPass($this->app));

        // Pošta nedostupná → zpráva čeká lokálně → plánovač ji předá, až na ni dojde řada.
        Http::fake(['https://posta.test/api/v1/zpravy' => Http::sequence()
            ->push(['message' => 'Server Error'], 503)
            ->push(['id' => '01K6POSTA0000000000000000P', 'stav' => 've_fronte'], 202)]);
        Mail::raw('Text', fn ($m) => $m->to('jana@seznam.cz')->subject('Rezervace'));

        $this->assertSame(1, Odchozi::count());
        $this->assertFalse($udalost->filtersPass($this->app), 'další pokus až po rozestupu');

        $this->travel(2)->minutes();
        $this->assertTrue($udalost->filtersPass($this->app));
        $udalost->run($this->app);

        $this->assertSame(0, Odchozi::count());
        $this->assertSame('01K6POSTA0000000000000000P', MailLog::sole()->posta_id);
        $this->assertFalse($udalost->filtersPass($this->app));

        // Bez webhooku po 15 minutách dotaz na stav.
        $this->travel(16)->minutes();
        $this->assertTrue($udalost->filtersPass($this->app));
    }

    public function test_fronta_uloh_jen_kdyz_je_prace(): void
    {
        $udalost = $this->udalost('fronta');
        $this->assertNotInstanceOf(CallbackEvent::class, $udalost);
        $this->assertTrue($udalost->runInBackground);
        $this->assertTrue($udalost->withoutOverlapping);
        $this->assertStringContainsString('-d disable_functions=', $udalost->command, 'Hestia zakazuje pcntl_* i v CLI');
        $this->assertStringContainsString('--stop-when-empty', $udalost->command);
        $this->assertStringContainsString('--max-time=50', $udalost->command);

        // sync: fronta neexistuje.
        $this->assertFalse(FrontaUloh::maPraci());

        config(['queue.default' => 'database']);
        $this->assertFalse(FrontaUloh::maPraci());
        $this->assertFalse($udalost->filtersPass($this->app));

        $uloha = fn (array $navic) => DB::table('jobs')->insert($navic + ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'created_at' => now()->getTimestamp()]);

        // Odložená úloha (za hodinu) worker nepotřebuje.
        $uloha(['available_at' => now()->addHour()->getTimestamp(), 'reserved_at' => null]);
        $this->assertFalse(FrontaUloh::maPraci());

        // Rezervovaná zpracovává jiný worker…
        $uloha(['available_at' => now()->getTimestamp(), 'reserved_at' => now()->getTimestamp()]);
        $this->assertFalse(FrontaUloh::maPraci());

        // …dokud nevyprší retry_after (mrtvý worker) – pak ji převezme další.
        $this->travel((int) config('queue.connections.database.retry_after', 90) + 1)->seconds();
        $this->assertTrue(FrontaUloh::maPraci());

        DB::table('jobs')->delete();
        $uloha(['available_at' => now()->getTimestamp(), 'reserved_at' => null]);
        $this->assertTrue($udalost->filtersPass($this->app));
    }
}
