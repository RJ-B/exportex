<?php

namespace Tests\Feature;

use App\Bezpecnost\DruhyKrok;
use App\Bezpecnost\ParovaniZarizeni;
use App\Filament\Pages\Zabezpeceni;
use App\Filament\Resources\Uzivatele\Pages\ListUzivatele;
use App\Models\User;
use App\Support\Prenos\Casti;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Druhý krok přihlášení (App\Bezpecnost\DruhyKrok): po hesle passkey, nebo kód
 * z Authenticatoru (s obnovovacími kódy). Nejdřív se nabídne passkey, kód je
 * záloha; přihlášení bez hesla aplikace nemá.
 */
class DruhyKrokTest extends TestCase
{
    use RefreshDatabase;

    private const HESLO = 'tajne-heslo-123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost', 'passkeys.relying_party_id' => 'localhost']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        DruhyKrok::fakeOvereni(null);

        parent::tearDown();
    }

    private function spravce(array $udaje = []): User
    {
        return tap(User::factory()->create(['password' => self::HESLO, ...$udaje]), fn ($u) => $u->forceFill(['role' => 'admin'])->save());
    }

    private function sPasskeyem(User $user): User
    {
        DB::table('passkeys')->insert([
            'user_id' => $user->id,
            'name' => 'MacBook',
            'credential_id' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
            'credential' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function sAuthenticatorem(User $user): string
    {
        $tajemstvi = (new Google2FA)->generateSecretKey();
        $user->forceFill(['app_authentication_secret' => $tajemstvi, 'app_authentication_recovery_codes' => [bcrypt('obnovovaci-kod-1')]])->save();

        return $tajemstvi;
    }

    private function poHesle(User $user)
    {
        return Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', self::HESLO)
            ->call('authenticate');
    }

    public function test_bez_druheho_kroku_staci_heslo(): void
    {
        $user = $this->spravce();

        $this->poHesle($user)->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticator_chce_po_hesle_kod(): void
    {
        $user = $this->spravce();
        $tajemstvi = $this->sAuthenticatorem($user);

        $prihlaseni = $this->poHesle($user);
        $this->assertGuest();
        $this->assertNotNull($prihlaseni->get('userUndertakingMultiFactorAuthentication'));

        $prihlaseni->set('data.multiFactor.app.code', '000000')->call('authenticate')->assertHasErrors();
        $this->assertGuest();

        $prihlaseni->set('data.multiFactor.app.code', (new Google2FA)->getCurrentOtp($tajemstvi))->call('authenticate');
        $this->assertAuthenticatedAs($user);
    }

    public function test_obnovovaci_kod_projde_misto_kodu_z_aplikace(): void
    {
        $user = $this->spravce();
        $this->sAuthenticatorem($user);

        $this->poHesle($user)
            ->set('data.multiFactor.app.useRecoveryCode', true)
            ->set('data.multiFactor.app.recoveryCode', 'obnovovaci-kod-1')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $user->fresh()->app_authentication_recovery_codes);
    }

    public function test_passkey_se_nabidne_prvni_a_kod_je_zaloha(): void
    {
        $user = $this->sPasskeyem($this->spravce());
        $this->sAuthenticatorem($user);

        $prihlaseni = $this->poHesle($user)
            ->assertSet('data.multiFactor.provider', 'passkey')
            ->assertSee('Použít passkey')
            ->assertSee('Použít kód z Authenticatoru');

        $this->assertGuest();
        $this->assertNotNull(DruhyKrok::cekajiciVyzva($user), 'Výzva pro prohlížeč čeká v relaci.');

        // Passkey nejde → přepnout na kód.
        $prihlaseni->set('data.multiFactor.provider', 'app')->assertSee('šestimístný kód');
    }

    public function test_prihlaseni_passkeyem(): void
    {
        $user = $this->sPasskeyem($this->spravce());
        DruhyKrok::fakeOvereni(fn (User $kdo, string $odpoved) => $kdo->is($user) && $odpoved === '{"podpis":"platny"}');

        $prihlaseni = $this->poHesle($user);

        $prihlaseni->set('data.multiFactor.passkey.credential', '{"podpis":"spatny"}')->call('authenticate')
            ->assertHasErrors(['data.multiFactor.passkey.credential']);
        $this->assertGuest();
        $this->assertNotNull(DruhyKrok::cekajiciVyzva($user), 'Po neúspěchu čeká nová výzva.');

        $prihlaseni->set('data.multiFactor.passkey.credential', '{"podpis":"platny"}')->call('authenticate');
        $this->assertAuthenticatedAs($user);
    }

    public function test_passkey_patri_domene_z_app_url(): void
    {
        $user = $this->sPasskeyem($this->spravce());

        $this->assertTrue(DruhyKrok::passkeyNaTetoAdrese());

        config(['passkeys.relying_party_id' => 'jiny-web.cz']);
        $this->assertFalse(DruhyKrok::passkeyNaTetoAdrese());

        // Na cizí adrese se passkey nenabídne; bez Authenticatoru stačí heslo.
        $this->poHesle($user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_odkaz_na_heslo_neobejde_druhy_krok(): void
    {
        $user = $this->spravce();
        $this->sAuthenticatorem($user);
        $token = Password::broker()->createToken($user);
        $nove = self::HESLO.'-nove';

        $this->post(route('nastaveni-hesla.ulozit'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $nove,
            'password_confirmation' => $nove,
        ])->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

        $this->assertGuest();
    }

    public function test_zabezpeceni_je_v_uzivatelskem_menu_a_zmeny_chteji_heslo(): void
    {
        $user = $this->sPasskeyem($this->spravce());

        $this->actingAs($user)->get('/admin')
            ->assertOk()
            ->assertSee(Zabezpeceni::getUrl(), false)
            ->assertSee('Zabezpečení');

        $this->get(Zabezpeceni::getUrl())->assertOk()->assertSee('MacBook')->assertSee('Kód z Authenticatoru');

        // Přidání passkeye bez hesla znovu neprojde.
        $this->getJson(route('zarizeni.pridat.moznosti'))->assertForbidden();

        $passkey = $user->passkeys()->first();

        Livewire::test(Zabezpeceni::class)
            ->callAction(TestAction::make('odebrat')->arguments(['id' => $passkey->id]), ['heslo' => 'spatne'])
            ->assertNotified('Heslo nesedí');
        $this->assertTrue($user->passkeys()->exists());

        Livewire::test(Zabezpeceni::class)
            ->callAction(TestAction::make('odebrat')->arguments(['id' => $passkey->id]), ['heslo' => self::HESLO])
            ->assertNotified('Zařízení odebráno');
        $this->assertFalse($user->passkeys()->exists());

        // Heslo teď platí – výzva k přidání passkeye se vydá.
        $this->getJson(route('zarizeni.pridat.moznosti'))->assertOk()->assertJsonPath('options.rp.id', 'localhost');
    }

    public function test_parovani_telefonu_odkazem(): void
    {
        $user = $this->spravce();

        $this->actingAs($user);
        $stranka = Livewire::test(Zabezpeceni::class)
            ->callAction(TestAction::make('sparovatTelefon'), ['heslo' => self::HESLO]);

        $odkaz = $stranka->get('parovaniOdkaz');
        $this->assertNotNull($odkaz);
        $this->assertStringContainsString('<svg', (string) $stranka->get('parovaniQr'));

        $token = basename(parse_url($odkaz, PHP_URL_PATH));
        $this->assertDatabaseMissing('parovani_zarizeni', ['token_otisk' => $token]);
        $this->assertDatabaseHas('parovani_zarizeni', ['token_otisk' => ParovaniZarizeni::otisk($token)]);

        // Telefon odkaz otevře bez přihlášení; nikoho nepřihlásí.
        auth()->logout();
        $this->get(route('zarizeni.parovani', $token))->assertOk()->assertSee('Spárovat tento telefon')->assertSee($user->email);
        $this->getJson(route('zarizeni.parovani.moznosti', $token))->assertOk()->assertJsonPath('options.user.name', $user->email);
        $this->assertGuest();

        $this->get(route('zarizeni.parovani', 'neplatny-token'))->assertOk()->assertSee('Odkaz už neplatí');
        $this->getJson(route('zarizeni.parovani.moznosti', 'neplatny-token'))->assertStatus(410);
    }

    public function test_spravce_zrusi_druhy_krok_cloveku_bez_telefonu(): void
    {
        $spravce = $this->spravce();
        $clovek = $this->sPasskeyem($this->spravce());
        $this->sAuthenticatorem($clovek);

        $this->actingAs($spravce);
        Livewire::test(ListUzivatele::class)
            ->assertSee('passkey + Authenticator')
            ->callAction(TestAction::make('zrusitDruhyKrok')->table($clovek))
            ->assertNotified('Druhý krok zrušen');

        $clovek->refresh();
        $this->assertFalse(DruhyKrok::zapnuty($clovek));
        $this->assertNull($clovek->app_authentication_secret);
    }

    public function test_tajemstvi_druheho_kroku_se_neprenasi(): void
    {
        $this->assertContains('passkeys', Casti::NIKDY_TABULKY);
        $this->assertContains('parovani_zarizeni', Casti::NIKDY_TABULKY);
        $this->assertSame(
            ['email' => 'jana@example.cz'],
            Casti::bezTajnych('users', ['email' => 'jana@example.cz', 'app_authentication_secret' => 'x', 'app_authentication_recovery_codes' => 'y']),
        );
    }
}
