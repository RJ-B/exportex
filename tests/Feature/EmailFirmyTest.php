<?php

namespace Tests\Feature;

use App\Filament\Pages\Email;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nastavení → E-mail: schránky firmy z Pošty a odkud se posílá k jakému účelu
 * (jedna pro všechno, nebo zvlášť), přidání schránky heslem (heslo jen do Pošty),
 * testovací web jen čte. Účel u zpráv (formuláře, účty…) – odesílatele vybere Pošta.
 */
class EmailFirmyTest extends TestCase
{
    use RefreshDatabase;

    private const POSTA = 'https://posta.test';

    private const HESLO = 'Heslo-schranky-7';

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

    private function firma(array $zmeny = []): array
    {
        $schranka = fn (string $a) => ['adresa' => $a, 'jmeno' => null, 'stav' => 'ok', 'stav_popis' => 'Ověřeno', 'aktivni' => true, 'firmy' => true, 'smi_menit' => true];

        return array_replace([
            'vlastnik' => ['nazev' => 'Pekárna Novák s.r.o.', 'ico' => '27074358'], 'zadost' => null, 'rezim' => 'produkce', 'smi_menit' => true, 'proc_ne' => null,
            'ucely' => [['klic' => 'obecne', 'nazev' => 'Pro všechno', 'popis' => ''], ['klic' => 'formulare', 'nazev' => 'Formuláře', 'popis' => 'Zprávy z webu.'],
                ['klic' => 'ucty', 'nazev' => 'Účty', 'popis' => ''], ['klic' => 'doklady', 'nazev' => 'Doklady', 'popis' => '']],
            'schranky' => [$schranka('faktury@pekarna-novak.cz'), $schranka('info@pekarna-novak.cz')],
            'prirazeni' => ['vlastnik' => ['obecne' => 'info@pekarna-novak.cz'], 'aplikace' => []],
            'vysledne' => ['obecne' => 'info@pekarna-novak.cz', 'formulare' => 'info@pekarna-novak.cz', 'ucty' => 'info@pekarna-novak.cz', 'doklady' => 'info@pekarna-novak.cz'],
        ], $zmeny);
    }

    private function spravce(string $role = 'admin'): User
    {
        return User::forceCreate(['email' => $role.'@pekarna-novak.cz', 'jmeno' => 'Jana', 'prijmeni' => 'Nováková', 'role' => $role, 'password' => 'x']);
    }

    public function test_ucely_firmy_a_nova_schranka(): void
    {
        Http::fake([
            self::POSTA.'/api/v1/vlastnik' => Http::response($this->firma()),
            self::POSTA.'/api/v1/predvolby' => Http::response(['predvolby' => [['klic' => 'seznam', 'nazev' => 'Seznam Email Profi', 'oauth' => null, 'oauth_dostupne' => false, 'napoveda' => null]]]),
            self::POSTA.'/api/v1/vlastnik/ucely' => Http::response($this->firma()),
            self::POSTA.'/api/v1/vlastnik/schranky' => Http::response(['vysledek' => 'zalozena', 'schranka' => [], 'overeni' => ['ok' => true, 'zprava' => 'OK']], 201),
        ]);

        $this->actingAs($this->spravce());
        Livewire::test(Email::class)
            ->assertSee('Pekárna Novák s.r.o.')
            ->assertSee('faktury@pekarna-novak.cz')
            ->assertSet('data.ucely.obecne', 'info@pekarna-novak.cz')
            ->assertSet('data.rozpis', false)
            ->set('data.rozpis', true)
            ->set('data.ucely.doklady', 'faktury@pekarna-novak.cz')
            ->call('ulozUcely')
            ->assertNotified('Uloženo – e-maily odcházejí podle nového nastavení')
            ->set('data.nova.adresa', 'Objednavky@pekarna-novak.cz')
            ->set('data.nova.heslo', self::HESLO)
            ->call('pridej')
            ->assertNotified('Schránka přidána – odesílání funguje');

        // Posílá se jen změna; heslo jen do Pošty, web ho nikam neuloží.
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === self::POSTA.'/api/v1/vlastnik/ucely'
            && $r['rozsah'] === 'vlastnik' && (array) $r['ucely'] === ['doklady' => 'faktury@pekarna-novak.cz']);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::POSTA.'/api/v1/vlastnik/schranky'
            && $r['adresa'] === 'objednavky@pekarna-novak.cz' && $r['heslo'] === self::HESLO);
        $this->assertFalse(Nastaveni::query()->get()->contains(fn ($n) => str_contains((string) $n->hodnota, self::HESLO)));
    }

    public function test_testovaci_web_jen_cte_a_klient_nesmi(): void
    {
        Http::fake([self::POSTA.'/api/v1/vlastnik' => Http::response($this->firma(['rezim' => 'test', 'smi_menit' => false,
            'proc_ne' => 'Testovací web posílá jen testovací adresou – e-mail se nastavuje na ostrém webu.']))]);

        $this->actingAs($this->spravce('klient'));
        Livewire::test(Email::class)->assertForbidden();

        $this->actingAs($this->spravce());
        Livewire::test(Email::class)->assertSee('Testovací web posílá jen testovací adresou')
            ->assertDontSee('Přidat a ověřit')
            ->assertFormFieldIsDisabled('ucely.obecne');
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
