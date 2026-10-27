<?php

namespace Tests\Feature;

use App\Mail\NovaZprava;
use App\Models\Nastaveni;
use App\Models\User;
use App\Models\Zprava;
use App\Support\Posta\Propojeni;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Účel u zpráv (formuláře, účty…): web ho pošle Poště, odesílatele podle něj vybere Pošta.
 * Víc schránek firmy a jejich účely nastavuje Sim&Ren v portálu / Poště, web je jen posílá.
 */
class EmailFirmyTest extends TestCase
{
    use RefreshDatabase;

    private const POSTA = 'https://posta.test';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Nastaveni::nastav('posta.url', self::POSTA);
        Nastaveni::nastav('posta.token', Crypt::encryptString('pst_testovaci'));
        Nastaveni::nastav('posta.adresy', json_encode([['adresa' => 'info@pekarna-novak.cz', 'jmeno' => 'Pekárna Novák']]));
        Propojeni::pouzij();
        Mail::forgetMailers();
    }

    private function spravce(string $role = 'admin'): User
    {
        return User::forceCreate(['email' => $role.'@pekarna-novak.cz', 'jmeno' => 'Jana', 'prijmeni' => 'Nováková', 'role' => $role, 'password' => 'x']);
    }

    public function test_ucel_zpravy_podle_druhu(): void
    {
        Http::fake([self::POSTA.'/api/v1/zpravy' => Http::response(['id' => '01K6POSTA0000000000000000A', 'stav' => 've_fronte'], 202)]);

        $zprava = Zprava::forceCreate(['jmeno' => 'Jana', 'prijmeni' => 'Nováková', 'email' => 'jana@seznam.cz', 'zprava' => 'Dobrý den, máte otevřeno v sobotu?']);
        Mail::to('info@pekarna-novak.cz')->send(new NovaZprava($zprava));
        $this->spravce()->notify(tap(new ResetPassword('token-1'), fn ($n) => $n->url = 'https://pekarna-novak.cz/admin/password-reset'));
        Mail::raw('Bez účelu.', fn ($m) => $m->to('jana@seznam.cz')->subject('Zkouška'));

        $ucely = collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/zpravy'))->map(fn (Request $r) => $r['ucel'] ?? null)->values()->all();
        $this->assertSame(['formulare', 'ucty', 'obecne'], $ucely);
        Http::assertNotSent(fn (Request $r) => isset($r['hlavicky']['X-Posta-Ucel']));
    }
}
