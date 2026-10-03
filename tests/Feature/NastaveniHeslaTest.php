<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Nastavení hesla z CRM (Můj účet správce): odkaz → heslo → rovnou přihlášený v aplikaci. */
class NastaveniHeslaTest extends TestCase
{
    use RefreshDatabase;

    /** Odkaz, jak ho vrátí simren:spravce (CRM ho přes portál dostane a přesměruje na něj). */
    private function odkaz(): string
    {
        $data = rtrim(strtr(base64_encode(json_encode(['email' => 'novak@simren.cz', 'jmeno' => 'Jan', 'prijmeni' => 'Novák'])), '+/', '-_'), '=');
        Artisan::call('simren:spravce', ['data' => $data]);

        return json_decode(trim(Artisan::output()), true)['reset_url'];
    }

    public function test_odkaz_heslo_a_rovnou_prihlaseni(): void
    {
        $odkaz = $this->odkaz();
        $cesta = parse_url($odkaz, PHP_URL_PATH).'?'.parse_url($odkaz, PHP_URL_QUERY);

        $this->get($cesta)->assertOk()->assertSee('Nastav si heslo')->assertSee('novak@simren.cz')->assertSee('Jan Novák');

        parse_str((string) parse_url($odkaz, PHP_URL_QUERY), $q);
        $token = basename((string) parse_url($odkaz, PHP_URL_PATH));
        $heslo = Str::random(14);

        $this->post('/nastaveni-hesla', ['token' => $token, 'email' => $q['email'], 'password' => $heslo, 'password_confirmation' => $heslo])
            ->assertRedirect(\App\Http\Controllers\NastaveniHeslaController::kamPotom());

        $u = User::where('email', 'novak@simren.cz')->first();
        $this->assertAuthenticatedAs($u);
        $this->assertTrue(Hash::check($heslo, $u->password));

        // Odkaz platí jen jednou.
        auth()->logout();
        $this->get($cesta)->assertOk()->assertSee('Odkaz už neplatí');
    }

    public function test_neshodna_nebo_kratka_hesla_neprojdou(): void
    {
        $odkaz = $this->odkaz();
        parse_str((string) parse_url($odkaz, PHP_URL_QUERY), $q);
        $token = basename((string) parse_url($odkaz, PHP_URL_PATH));

        $this->post('/nastaveni-hesla', ['token' => $token, 'email' => $q['email'], 'password' => Str::random(14), 'password_confirmation' => Str::random(14)])
            ->assertSessionHasErrors('password');
        $kratke = Str::random(6);
        $this->post('/nastaveni-hesla', ['token' => $token, 'email' => $q['email'], 'password' => $kratke, 'password_confirmation' => $kratke])
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_cizi_token_nic_neudela(): void
    {
        $this->odkaz();
        $heslo = Str::random(14);

        $this->get('/nastaveni-hesla/'.Str::random(64).'?email=novak@simren.cz')->assertOk()->assertSee('Odkaz už neplatí');
        $this->post('/nastaveni-hesla', ['token' => Str::random(64), 'email' => 'novak@simren.cz', 'password' => $heslo, 'password_confirmation' => $heslo])
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    }
}
