<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Můj účet správce u účtu, který už heslo má: heslo se nemění, obnova hesla
 * nevznikne a jednorázový odkaz (podepsaný, 5 minut, jen jednou) přihlásí
 * rovnou do administrace.
 */
class PrihlaseniZCrmTest extends TestCase
{
    use RefreshDatabase;

    private function spust(array $data): array
    {
        Artisan::call('simren:spravce', ['data' => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=')]);

        return json_decode(trim(Artisan::output()), true);
    }

    private function sHeslem(): User
    {
        return User::forceCreate(['email' => 'r.jirak@simren.cz', 'jmeno' => 'R', 'prijmeni' => 'J', 'password' => 'moje-heslo-123', 'role' => 'superadmin']);
    }

    public function test_ucet_s_heslem_dostane_prihlaseni_heslo_zustane_a_reset_nevznikne(): void
    {
        $user = $this->sHeslem();

        $v = $this->spust(['email' => 'r.jirak@simren.cz', 'jmeno' => 'Rostislav', 'prijmeni' => 'Jirák']);

        $this->assertFalse($v['created']);
        $this->assertTrue($v['ma_heslo']);
        $this->assertArrayNotHasKey('reset_url', $v);
        $this->assertStringContainsString('/prihlaseni-spravce/', $v['login_url']);
        $this->assertStringContainsString('signature=', $v['login_url']);
        $this->assertTrue(Hash::check('moje-heslo-123', $user->fresh()->password));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());

        $this->get($v['login_url'])->assertRedirect(Filament::getPanel('admin')->getUrl());
        $this->assertAuthenticatedAs($user->fresh());

        // Jen jednou.
        auth()->logout();
        $this->get($v['login_url'])->assertRedirect();
        $this->assertGuest();
    }

    public function test_upraveny_nebo_cizi_odkaz_neprihlasi(): void
    {
        $this->sHeslem();
        $v = $this->spust(['email' => 'r.jirak@simren.cz', 'jmeno' => 'R', 'prijmeni' => 'J']);

        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $v['login_url']))->assertForbidden();
        $this->get(str_replace('/prihlaseni-spravce/', '/prihlaseni-spravce/x', $v['login_url']))->assertForbidden();
        $this->assertGuest();
    }

    public function test_ucet_bez_superadmina_se_neprihlasi(): void
    {
        $user = $this->sHeslem();
        $v = $this->spust(['email' => 'r.jirak@simren.cz', 'jmeno' => 'R', 'prijmeni' => 'J']);
        $user->forceFill(['role' => 'klient'])->save();

        $this->get($v['login_url'])->assertRedirect();
        $this->assertGuest();
    }

    /** Druhý krok (passkey, Authenticator) odkaz neobejde – jde na přihlášení. */
    public function test_se_zapnutym_druhym_krokem_jde_na_prihlaseni(): void
    {
        $user = $this->sHeslem();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $v = $this->spust(['email' => 'r.jirak@simren.cz', 'jmeno' => 'R', 'prijmeni' => 'J']);

        $this->get($v['login_url'])->assertRedirect();
        $this->assertGuest();
    }

    public function test_novy_ucet_dal_nastavuje_heslo(): void
    {
        $v = $this->spust(['email' => 'novy@simren.cz', 'jmeno' => 'N', 'prijmeni' => 'V']);

        $this->assertTrue($v['created']);
        $this->assertFalse($v['ma_heslo']);
        $this->assertArrayNotHasKey('login_url', $v);
        $this->assertStringContainsString('/nastaveni-hesla/', $v['reset_url']);
    }
}
