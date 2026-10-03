<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Účet správce z CRM: vznikne s heslem, které nikdo nezná; nastaví si ho odkazem.
 */
class ZalozSpravceTest extends TestCase
{
    use RefreshDatabase;

    private function spust(array $data): array
    {
        Artisan::call('simren:spravce', ['data' => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=')]);

        return json_decode(trim(Artisan::output()), true);
    }

    public function test_zalozi_superadmina_s_neznamym_heslem_a_vrati_odkaz(): void
    {
        $v = $this->spust(['email' => 'R.Jirak@simren.cz', 'jmeno' => 'Rostislav', 'prijmeni' => 'Jirák']);

        $user = User::where('email', 'r.jirak@simren.cz')->first();
        $this->assertTrue($v['created']);
        $this->assertSame('superadmin', $user->role);
        $this->assertNotEmpty($user->password);
        $this->assertSame('Rostislav Jirák', $user->getFilamentName());
        $this->assertStringContainsString('/nastaveni-hesla/', $v['reset_url']);
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_existujici_ucet_nezmeni_heslo(): void
    {
        $user = User::forceCreate(['email' => 'r.jirak@simren.cz', 'jmeno' => 'R', 'prijmeni' => 'J', 'password' => 'puvodni-heslo', 'role' => 'klient']);

        $v = $this->spust(['email' => 'r.jirak@simren.cz', 'jmeno' => 'Rostislav', 'prijmeni' => 'Jirák']);

        $this->assertFalse($v['created']);
        $this->assertTrue(Hash::check('puvodni-heslo', $user->fresh()->password));
        $this->assertSame('superadmin', $user->fresh()->role);
    }

    public function test_bez_nastaveni_hesla_se_prihlasit_nejde(): void
    {
        $this->spust(['email' => 'r.jirak@simren.cz', 'jmeno' => 'R', 'prijmeni' => 'J']);

        $this->assertFalse(auth()->attempt(['email' => 'r.jirak@simren.cz', 'password' => '']));
    }

    public function test_neplatny_vstup_nic_nezalozi(): void
    {
        $v = $this->spust(['email' => 'neni-email', 'jmeno' => 'X', 'prijmeni' => 'Y']);

        $this->assertArrayHasKey('error', $v);
        $this->assertSame(0, User::count());
    }

    public function test_klient_do_administrace_nesmi(): void
    {
        $klient = User::forceCreate(['email' => 'k@example.cz', 'password' => 'x', 'role' => 'klient']);

        $this->assertFalse($klient->canAccessPanel(Filament::getPanel('admin')));
    }
}
