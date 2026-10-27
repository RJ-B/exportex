<?php

namespace Tests\Feature;

use App\Filament\Pages\EmailovaSchranka;
use App\Models\MailLog;
use App\Models\Nastaveni;
use App\Models\User;
use App\Support\Posta\Propojeni;
use App\Support\Posta\SchrankaWebu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nastavení → E-mailová schránka: poskytovatel s návodem, adresa, jméno, heslo
 * (heslo jen do Pošty, web ho nikde nenechá), zkušební e-mail, DNS a návrat na
 * systémovou schránku. API Pošty `/api/v1/schranka`.
 */
class EmailovaSchrankaTest extends TestCase
{
    use RefreshDatabase;

    private const POSTA = 'https://posta.test';

    private const HESLO = 'Tajne-heslo-schranky-91';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Nastaveni::nastav('posta.url', self::POSTA);
        Nastaveni::nastav('posta.token', Crypt::encryptString('pst_testovaci'));
        Propojeni::pouzij();
        Cache::flush();
    }

    private function stav(?array $schranka = null, array $zmeny = []): array
    {
        return array_replace([
            'schranka' => $schranka,
            'odesila' => $schranka['adresa'] ?? 'web@posta.simren.cz',
            'systemova' => $schranka === null,
            'smi_menit' => true,
            'proc_ne' => null,
            'dns' => null,
        ], $zmeny);
    }

    private function schranka(string $adresa = 'info@pekarna-novak.cz', array $zmeny = []): array
    {
        return array_replace(['adresa' => $adresa, 'jmeno' => 'Pekárna Novák', 'poskytovatel' => 'forpsi', 'prihlaseni' => 'heslo',
            'stav' => 'ok', 'stav_popis' => 'Ověřeno', 'stav_zprava' => null, 'overeno' => now()->toIso8601String(), 'aktivni' => true, 'smi_menit' => true,
            'domena' => ['nazev' => 'pekarna-novak.cz', 'stav' => 'chyba', 'bezplatna' => false]], $zmeny);
    }

    private function spravce(string $role = 'admin'): User
    {
        return User::forceCreate(['email' => $role.'@pekarna-novak.cz', 'jmeno' => 'Jana', 'prijmeni' => 'Nováková', 'role' => $role, 'password' => 'x']);
    }

    private function dns(): array
    {
        return ['domena' => 'pekarna-novak.cz', 'bezplatna' => false, 'stav' => 'chyba',
            'kontrola' => [['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => 'Chybí.'], ['zaznam' => 'DKIM', 'stav' => 'ok', 'zprava' => null], ['zaznam' => 'DMARC', 'stav' => 'chyba', 'zprava' => null]],
            'zaznamy' => [['ucel' => 'spf', 'typ' => 'TXT', 'jmeno' => '@', 'jmeno_cele' => 'pekarna-novak.cz', 'hodnota' => 'v=spf1 include:_spf.forpsi.com ~all', 'poznamka' => null]]];
    }

    public function test_nastaveni_schranky_heslo_jen_do_posty(): void
    {
        $ulozena = null;
        Http::fake([
            self::POSTA.'/api/v1/predvolby' => Http::response(['predvolby' => []]),
            self::POSTA.'/api/v1/schranka/dns' => Http::response($this->dns()),
            self::POSTA.'/api/v1/schranka' => function (Request $r) use (&$ulozena) {
                if ($r->method() === 'PUT') {
                    $ulozena = $this->schranka();

                    return Http::response(['vysledek' => 'zalozena', 'schranka' => $ulozena, 'overeni' => ['ok' => true, 'zprava' => 'Přihlášení v pořádku.'], 'stav' => $this->stav($ulozena)], 201);
                }

                return Http::response($this->stav($ulozena));
            },
        ]);

        $this->actingAs($this->spravce());
        Livewire::test(EmailovaSchranka::class)
            ->assertSee('web@posta.simren.cz')
            ->assertSet('data.poskytovatel', 'forpsi')
            ->assertSee('Forpsi')
            ->set('data.adresa', 'Info@Pekarna-Novak.cz')
            ->set('data.heslo', self::HESLO)
            ->call('uloz')
            ->assertHasNoErrors()
            ->assertSet('data.heslo', null)
            ->assertSee('info@pekarna-novak.cz')
            ->assertSee('v=spf1 include:_spf.forpsi.com ~all');

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === self::POSTA.'/api/v1/schranka'
            && $r['adresa'] === 'info@pekarna-novak.cz' && $r['heslo'] === self::HESLO && $r['poskytovatel'] === 'forpsi'
            && $r['kdo'] === 'Jana Nováková' && ! isset($r['smtp_host']));

        // Heslo web nikde nenechá – ani v databázi, ani v cache.
        foreach (['nastaveni', 'audit_logs', 'error_logs'] as $tabulka) {
            if (DB::getSchemaBuilder()->hasTable($tabulka)) {
                $this->assertStringNotContainsString(self::HESLO, json_encode(DB::table($tabulka)->get()));
            }
        }
        $this->assertStringNotContainsString(self::HESLO, json_encode(Cache::get('posta.schranka_webu')));
        $this->assertTrue(SchrankaWebu::nastavena());
    }

    public function test_nepovedene_prihlaseni_nic_neulozi_a_rekne_proc(): void
    {
        Http::fake([
            self::POSTA.'/api/v1/predvolby' => Http::response(['predvolby' => []]),
            self::POSTA.'/api/v1/schranka' => fn (Request $r) => $r->method() === 'PUT'
                ? Http::response(['message' => 'Přihlášení ke schránce nefunguje – nic se neuložilo.', 'overeni' => ['ok' => false, 'zprava' => 'Server heslo odmítl.']], 422)
                : Http::response($this->stav()),
        ]);

        $this->actingAs($this->spravce());
        Livewire::test(EmailovaSchranka::class)
            ->set('data.adresa', 'info@pekarna-novak.cz')
            ->set('data.heslo', self::HESLO)
            ->call('uloz')
            ->assertNotified(__('pruvodce.emailova_schranka.schranka_se_neulozila'));

        $this->assertFalse(SchrankaWebu::nastavena());
    }

    public function test_jiny_poskytovatel_posle_server_a_hlida_porty(): void
    {
        $ulozena = null;
        Http::fake([
            self::POSTA.'/api/v1/predvolby' => Http::response(['predvolby' => []]),
            self::POSTA.'/api/v1/schranka/dns' => Http::response(['domena' => 'gmail.com', 'bezplatna' => true, 'zaznamy' => []]),
            self::POSTA.'/api/v1/schranka' => function (Request $r) use (&$ulozena) {
                if ($r->method() === 'PUT') {
                    $ulozena = $this->schranka('pekarna@gmail.com');
                }

                return Http::response(['vysledek' => 'zalozena', 'schranka' => $ulozena, 'overeni' => ['ok' => true], 'stav' => $this->stav($ulozena)] + $this->stav($ulozena));
            },
        ]);

        $this->actingAs($this->spravce());
        $stranka = Livewire::test(EmailovaSchranka::class)
            ->set('data.poskytovatel', 'jiny')
            ->set('data.adresa', 'pekarna@gmail.com')
            ->set('data.heslo', self::HESLO)
            ->set('data.smtp_host', 'mail.example.cz')
            ->set('data.smtp_port', 8080)
            ->call('uloz')
            ->assertHasErrors(['data.smtp_port']);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PUT');

        $stranka->set('data.smtp_port', 587)->set('data.smtp_sifrovani', 'starttls')->call('uloz')->assertHasNoErrors()
            // Schránka na doméně poskytovatele: DNS jen jako informace.
            ->assertSee(__('pruvodce.dns.cizi_domena', ['domena' => 'gmail.com']));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['smtp_host'] === 'mail.example.cz' && $r['smtp_port'] === 587);
    }

    public function test_zkusebni_e_mail_a_navrat_na_systemovou(): void
    {
        $schranka = $this->schranka();
        Http::fake([
            self::POSTA.'/api/v1/predvolby' => Http::response(['predvolby' => []]),
            self::POSTA.'/api/v1/schranka/dns' => Http::response($this->dns()),
            self::POSTA.'/api/v1/schranka/zkouska' => Http::response(['id' => 'z1', 'stav' => 'prijato'], 202),
            self::POSTA.'/api/v1/schranka' => function (Request $r) use (&$schranka) {
                if ($r->method() === 'DELETE') {
                    $schranka = null;

                    return Http::response(['vysledek' => 'smazana', 'stav' => $this->stav()]);
                }

                return Http::response($this->stav($schranka));
            },
        ]);

        $this->actingAs($this->spravce());
        Livewire::test(EmailovaSchranka::class)
            ->assertSee(__('pruvodce.emailova_schranka.vratit_na_systemovou'))
            ->call('zkouska', 'jana@pekarna-novak.cz')
            ->assertNotified(__('pruvodce.emailova_schranka.zkusebni_e_mail_odeslan', ['komu' => 'jana@pekarna-novak.cz']))
            ->call('vrat')
            ->assertNotified(__('pruvodce.emailova_schranka.web_posila_ze_systemove'))
            ->assertSee('web@posta.simren.cz');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/schranka/zkouska') && $r['komu'] === 'jana@pekarna-novak.cz');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/api/v1/schranka'));
        $this->assertFalse(SchrankaWebu::nastavena());
    }

    public function test_testovaci_web_jen_cte_a_stranku_vidi_jen_spravce(): void
    {
        Http::fake([
            self::POSTA.'/api/v1/predvolby' => Http::response(['predvolby' => []]),
            self::POSTA.'/api/v1/schranka' => Http::response($this->stav(null, ['smi_menit' => false, 'proc_ne' => 'Testovací web posílá testovací adresou.'])),
        ]);

        $this->actingAs($this->spravce());
        Livewire::test(EmailovaSchranka::class)
            ->assertSee('Testovací web posílá testovací adresou.')
            ->assertDontSee(__('pruvodce.emailova_schranka.ulozit_a_overit'))
            ->call('uloz')
            ->assertForbidden();

        $this->actingAs($this->spravce('editor'));
        $this->assertFalse(EmailovaSchranka::canAccess());
    }

    public function test_bez_propojeni_s_postou(): void
    {
        Nastaveni::smaz('posta.token');
        Propojeni::pouzij();

        $this->actingAs($this->spravce());
        Livewire::test(EmailovaSchranka::class)->assertSee(__('pruvodce.emailova_schranka.web_zatim_nema_odesilani'));
        $this->assertFalse(SchrankaWebu::nastavena());
        $this->assertSame(0, MailLog::query()->count());
    }
}
