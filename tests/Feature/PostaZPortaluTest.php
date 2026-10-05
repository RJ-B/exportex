<?php

namespace Tests\Feature;

use App\Models\MailLog;
use App\Models\Nastaveni;
use App\Support\Posta\PrikazZPortalu;
use App\Support\Posta\Propojeni;
use App\Support\Posta\StaraSchranka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Propojení s Poštou bez klikání: portál pošle klíče příkazem posta:z-portalu
 * (JSON na stdin, nikdy v argumentech), .env jako záloha, když v nastavení
 * nic není, a zadržená zpráva z testovacího režimu Pošty.
 */
class PostaZPortaluTest extends TestCase
{
    use RefreshDatabase;

    private const POSTA = 'https://posta.test';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        PrikazZPortalu::$vstup = null;
        parent::tearDown();
    }

    /** Spustí příkaz se vstupem na „stdin“ a vrátí jeho JSON výstup. */
    private function prikaz(array $vstup): array
    {
        PrikazZPortalu::$vstup = json_encode($vstup);
        Artisan::call('posta:z-portalu');

        return json_decode(trim(Artisan::output()), true);
    }

    private function klice(array $zmeny = []): array
    {
        return $zmeny + [
            'akce' => 'propoj',
            'url' => self::POSTA,
            'token' => 'pst_z_portalu',
            'webhook_tajemstvi' => 'tajemstvi-z-portalu',
            'aplikace' => ['nazev' => 'Pekárna Novák (test)', 'slug' => 'pekarna-test-simren-cz', 'adresy' => [['adresa' => 'posta@simren.cz', 'jmeno' => 'Sim&Ren']],
                'token_plati_do' => now()->addYear()->toIso8601String(), 'rezim' => 'test'],
            'prevzeti' => null,
        ];
    }

    public function test_zjisti_hlasi_stav_bez_tajemstvi(): void
    {
        $this->staraSchranka();

        $vysledek = $this->prikaz(['akce' => 'zjisti']);

        $this->assertTrue($vysledek['podporovano']);
        $this->assertFalse($vysledek['propojeno']);
        $this->assertSame('info@pekarna-novak.cz', $vysledek['stara_schranka']['adresa']);
        $this->assertStringNotContainsString('Stare-heslo', Artisan::output());
    }

    public function test_propoj_ulozi_klice_sifrovane_a_vystup_je_bez_tokenu(): void
    {
        $vysledek = $this->prikaz($this->klice());

        $this->assertTrue($vysledek['ok'], (string) ($vysledek['chyba'] ?? ''));
        $this->assertSame('pekarna-test-simren-cz', $vysledek['aplikace']);
        $this->assertStringNotContainsString('pst_z_portalu', Artisan::output());
        $this->assertStringNotContainsString('tajemstvi-z-portalu', Artisan::output());

        $this->assertTrue(Propojeni::propojeno());
        $this->assertSame('nastaveni', Propojeni::zdroj());
        $this->assertSame('pst_z_portalu', Propojeni::token());
        $this->assertStringNotContainsString('pst_z_portalu', (string) Nastaveni::hodnota('posta.token'));
        $this->assertSame('posta@simren.cz', Propojeni::odesilatel()['adresa']);
        $this->assertTrue(Propojeni::popis()['z_portalu']);

        // Hned posílá přes Poštu – bez klikání.
        Http::fake([self::POSTA.'/api/v1/zpravy' => Http::response(['id' => '01K6PORTAL0000000000000000', 'stav' => 've_fronte'], 202)]);
        Propojeni::pouzij();
        Mail::forgetMailers();
        Mail::raw('Dobrý den, objednávka je připravená.', fn ($m) => $m->to('jana.novakova@seznam.cz')->subject('Objednávka'));

        Http::assertSent(fn (Request $r) => $r->url() === self::POSTA.'/api/v1/zpravy'
            && $r->hasHeader('Authorization', 'Bearer pst_z_portalu') && $r['od']['adresa'] === 'posta@simren.cz');
    }

    public function test_propoj_prevezme_starou_schranku(): void
    {
        $this->staraSchranka();

        Http::fake([self::POSTA.'/api/v1/prevzeti-schranky' => Http::response([
            'vysledek' => 'zalozena',
            'schranka' => ['adresa' => 'info@pekarna-novak.cz', 'stav' => 'ok', 'overeni' => ['ok' => true, 'zprava' => 'Přihlášení v pořádku.']],
            'aplikace' => ['adresy' => [['adresa' => 'info@pekarna-novak.cz', 'jmeno' => 'Pekárna Novák']]],
        ])]);

        $vysledek = $this->prikaz($this->klice(['prevzeti' => ['adresa' => 'info@pekarna-novak.cz']]));

        $this->assertTrue($vysledek['prevzeti']['ok']);
        Http::assertSent(fn (Request $r) => $r->url() === self::POSTA.'/api/v1/prevzeti-schranky'
            && $r->hasHeader('Authorization', 'Bearer pst_z_portalu') && $r['heslo'] === 'Stare-heslo-ř9');
        $this->assertSame('info@pekarna-novak.cz', Propojeni::odesilatel()['adresa']);
        $this->assertStringNotContainsString('Stare-heslo', Artisan::output());
        $this->assertNotNull(StaraSchranka::zjisti(), 'stará schránka zůstává do zkušebního e-mailu');
    }

    public function test_spatne_klice_odmitne_a_nic_neulozi(): void
    {
        $vysledek = $this->prikaz($this->klice(['token' => 'neco-jineho']));

        $this->assertFalse($vysledek['ok']);
        $this->assertFalse(Propojeni::propojeno());

        PrikazZPortalu::$vstup = 'neni json';
        $this->assertSame(1, Artisan::call('posta:z-portalu'));
    }

    public function test_odpoj_zapomene_klice(): void
    {
        $this->prikaz($this->klice());
        $this->assertTrue(Propojeni::propojeno());

        $this->assertTrue($this->prikaz(['akce' => 'odpoj'])['ok']);

        $this->assertFalse(Propojeni::propojeno());
        Http::assertNothingSent();
    }

    public function test_env_plati_jen_bez_nastaveni(): void
    {
        config(['posta.token' => 'pst_z_env', 'posta.webhook_tajemstvi' => 'tajemstvi-env', 'posta.od' => 'info@pekarna-novak.cz']);

        $this->assertTrue(Propojeni::propojeno());
        $this->assertSame('env', Propojeni::zdroj());
        $this->assertSame(['tajemstvi-env'], Propojeni::webhookTajemstvi());
        $this->assertSame('info@pekarna-novak.cz', Propojeni::odesilatel()['adresa']);

        // Klíče z portálu (nastavení) mají přednost.
        $this->prikaz($this->klice());
        $this->assertSame('pst_z_portalu', Propojeni::token());
        $this->assertSame('nastaveni', Propojeni::zdroj());
        $this->assertSame(['tajemstvi-z-portalu'], Propojeni::webhookTajemstvi());
        $this->assertSame('posta@simren.cz', Propojeni::odesilatel()['adresa']);
    }

    public function test_zadrzena_zprava_z_testovaciho_rezimu_se_neopakuje(): void
    {
        $this->prikaz($this->klice());
        $log = MailLog::forceCreate(['to_email' => 'petr@email.cz', 'subject' => 'Akce', 'status' => MailLog::STATUS_QUEUED, 'posta_id' => '01K6ZADRZENO00000000000000']);

        $telo = json_encode(['udalost' => 'zprava.zadrzena', 'zprava' => ['id' => '01K6ZADRZENO00000000000000', 'stav' => 'zadrzeno', 'chyba' => 'Zadrženo (test): testovací aplikace doručuje jen na interní adresy.']]);
        $cas = now()->getTimestamp();
        $this->call('POST', '/posta/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_POSTA_DELIVERY' => 'd-zadrzeno',
            'HTTP_X_POSTA_SIGNATURE_V2' => 't='.$cas.',v1='.hash_hmac('sha256', $cas.'.'.$telo, 'tajemstvi-z-portalu'),
        ], $telo)->assertOk();

        $log->refresh();
        $this->assertSame(MailLog::STATUS_HELD, $log->status);
        $this->assertSame('Zadrženo (test)', $log->statusLabel());
        $this->assertFalse($log->isFailed());
    }

    private function staraSchranka(): void
    {
        Nastaveni::nastav('posta.uzivatel', 'info@pekarna-novak.cz');
        Nastaveni::nastav('posta.heslo', Crypt::encryptString('Stare-heslo-ř9'));
        Nastaveni::nastav('posta.host', 'smtp.seznam.cz');
        Nastaveni::nastav('posta.port', '465');
        Nastaveni::nastav('posta.sifrovani', 'smtps');
        Nastaveni::nastav('posta.jmeno', 'Pekárna Novák');
        Nastaveni::nastav('posta.kontrola', '{"nastavena":true,"ok":true}');
    }
}
