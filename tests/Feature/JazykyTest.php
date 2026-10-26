<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Jazyky\Jazyky;
use App\Support\Jazyky\Kontrola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vícejazyčnost (docs/jazyky.md): žádné texty natvrdo, překlady úplné,
 * jazyk podle uživatele → prohlížeče → výchozí.
 */
class JazykyTest extends TestCase
{
    use RefreshDatabase;

    /** Testy si jazyky nastaví samy – projekt může mít zapnutou jen češtinu. */
    protected function setUp(): void
    {
        parent::setUp();
        config(['jazyky.vychozi' => 'cs', 'jazyky.zapnute' => ['cs' => 'Čeština', 'en' => 'English'], 'jazyky.podle_prohlizece' => false]);
    }

    public function test_zadne_texty_natvrdo(): void
    {
        $natvrdo = array_map(fn ($n) => $n['soubor'].':'.$n['radek'].'  '.mb_strimwidth($n['text'], 0, 80, '…'), Kontrola::projekt()->natvrdo());

        $this->assertSame([], $natvrdo, "Texty natvrdo – převod: php artisan jazyky:prevod (docs/jazyky.md)\n".implode("\n", $natvrdo));
    }

    public function test_klice_z_kodu_jsou_ve_vychozim_jazyce(): void
    {
        $this->assertSame([], Kontrola::projekt()->chybejiciKlice(Jazyky::vychozi()));
    }

    public function test_zapnute_jazyky_maji_vsechny_texty_se_stejnymi_parametry(): void
    {
        // Jazyky podle configu projektu (setUp je přepsal pro ostatní testy).
        foreach (array_keys(((array) require config_path('jazyky.php'))['zapnute'] ?? []) as $jazyk) {
            if ($jazyk === Jazyky::vychozi()) {
                continue;
            }
            $preklad = Kontrola::projekt()->preklad($jazyk);

            $this->assertSame([], $preklad['chybi'], "V lang/{$jazyk} chybí (php artisan jazyky:kontrola)");
            $this->assertSame([], $preklad['parametry'], "V lang/{$jazyk} nesedí :parametry");
        }
    }

    public function test_cestina_je_vychozi(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('lang="cs"', false);
    }

    public function test_uzivatel_si_vybere_jazyk(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'jazyk' => 'en']);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('lang="en"', false)
            ->assertSee(__('administrace.navigace.nastaveni', [], 'en'));
        $this->assertSame('en', $admin->preferredLocale());
    }

    public function test_prohlizec_jen_kdyz_je_zapnuty(): void
    {
        $this->get('/admin/login', ['Accept-Language' => 'en'])->assertSee('lang="cs"', false);

        config(['jazyky.podle_prohlizece' => true]);
        $this->get('/admin/login', ['Accept-Language' => 'en-GB,en;q=0.9'])->assertSee('lang="en"', false);
    }

    public function test_texty_pro_skripty_jdou_v_jazyce_stranky(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'jazyk' => 'en']);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('window.simrenTexty', false)
            ->assertSee(__('js.passkeys.tenhle_prohlizec_passkey_neumi', [], 'en'), false);
    }

    public function test_jazyk_bez_prekladu_ukaze_cestinu_ne_klice(): void
    {
        config(['jazyky.zapnute' => ['cs' => 'Čeština', 'de' => 'Deutsch']]);
        $admin = User::factory()->create(['role' => 'admin', 'jazyk' => 'de']);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('lang="de"', false)
            ->assertSee(__('administrace.navigace.nastaveni', [], 'cs'))->assertDontSee('administrace.navigace.');
    }
}
