<?php

namespace Tests\Feature;

use App\Filament\Resources\Zpravy\ZpravaResource;
use App\Mail\NovaZprava;
use App\Models\User;
use App\Models\Zprava;
use App\Support\NastaveniWebu;
use App\Support\OchranaFormulare;
use App\Support\SekceWebu;
use App\Support\ZakladniUdaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Kontaktní formulář šablony: validace, ochrana proti botům, limit, upozornění.
 * Exportex: poptávkový formulář v sekci Kontakt na úvodní stránce, s firmou
 * a jazykem webu; web posílá JSON. Dřív formsubmit.co – teď nic mimo náš server.
 */
class KontaktTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $zmeny = [], int $zobrazenoPred = 10): array
    {
        return array_merge([
            'jmeno' => ' Jana ', 'prijmeni' => 'Nováková', 'firma' => 'Textil Brno s.r.o.', 'email' => 'Jana@Example.CZ ',
            'telefon' => '+420 777 123 456', 'zprava' => 'Dobrý den, poptáváme 2 000 ručníků 500 g/m² v bílé.',
            'jazyk' => 'cs',
            OchranaFormulare::HONEYPOT => '',
            OchranaFormulare::CAS => Crypt::encryptString((string) (time() - $zobrazenoPred)),
        ], $zmeny);
    }

    public function test_zprava_se_ulozi_a_odejde_upozorneni(): void
    {
        Mail::fake();
        ZakladniUdaje::uloz(['email' => 'info@pekarna.cz']);

        // Formulář je na úvodní stránce v sekci Kontakt, /kontakt na ni přesměruje.
        $this->get('/')->assertOk()->assertSee('data-past="'.OchranaFormulare::HONEYPOT.'"', false)->assertSee('action="'.route('kontakt.odeslat').'"', false);
        $this->get('/kontakt')->assertRedirect(url('/').'#kontakt');
        $this->post('/kontakt', $this->data())->assertRedirect(url('/').'#kontakt')->assertSessionHas('odeslano');

        $zprava = Zprava::sole();
        $this->assertSame(['Jana', 'Nováková', 'Textil Brno s.r.o.', 'jana@example.cz', 'cs'], [$zprava->jmeno, $zprava->prijmeni, $zprava->firma, $zprava->email, $zprava->jazyk]);
        $this->assertNotNull($zprava->ip_adresa);
        Mail::assertSent(NovaZprava::class, fn ($m) => $m->hasTo('info@pekarna.cz') && $m->hasReplyTo('jana@example.cz')
            && $m->hasSubject('Nová poptávka z webu Exportex: Jana Nováková, Textil Brno s.r.o.'));
    }

    public function test_prijemce_z_nastaveni_formulare(): void
    {
        Mail::fake();
        ZakladniUdaje::uloz(['email' => 'info@pekarna.cz']);
        NastaveniWebu::uloz(['formular_prijemce' => 'objednavky@pekarna.cz']);

        $this->post('/kontakt', $this->data());

        Mail::assertSent(NovaZprava::class, fn ($m) => $m->hasTo('objednavky@pekarna.cz'));
    }

    public function test_bot_se_tvari_odeslane_ale_nic_se_neulozi(): void
    {
        Mail::fake();

        // Vyplněné skryté pole.
        $this->post('/kontakt', $this->data([OchranaFormulare::HONEYPOT => 'https://spam.example']))->assertSessionHas('odeslano');
        // Odesláno hned po zobrazení.
        $this->post('/kontakt', $this->data([], zobrazenoPred: 1))->assertSessionHas('odeslano');
        // Podvržený čas.
        RateLimiter::clear('formular-min:127.0.0.1');
        $this->post('/kontakt', $this->data([OchranaFormulare::CAS => (string) time()]))->assertSessionHas('odeslano');

        $this->assertSame(0, Zprava::count());
        Mail::assertNothingSent();
    }

    public function test_validace_s_ceskymi_hlaskami(): void
    {
        $this->from('/kontakt')->post('/kontakt', $this->data(['jmeno' => '', 'firma' => ' ', 'email' => 'neni-email', 'zprava' => 'krátká', 'telefon' => 'volejte']))
            ->assertRedirect('/kontakt')
            ->assertSessionHasErrors([
                'jmeno' => 'Vyplňte jméno.',
                'firma' => 'Vyplňte firmu.',
                'email' => 'Tohle nevypadá jako e-mailová adresa.',
                'zprava' => 'Zpráva je moc krátká – napište aspoň pár slov.',
                'telefon' => 'Telefon může obsahovat jen číslice, mezery a znaky + ( ) / -.',
            ]);
        $this->assertSame(0, Zprava::count());
    }

    public function test_limit_odeslani_z_jedne_ip(): void
    {
        Mail::fake();

        foreach (range(1, 3) as $i) {
            $this->post('/kontakt', $this->data(['zprava' => "Zpráva číslo {$i} s dost dlouhým textem."]))->assertSessionHas('odeslano');
        }

        $this->from('/kontakt')->post('/kontakt', $this->data())
            ->assertRedirect('/kontakt')
            ->assertSessionHasErrors(['formular']);
        $this->assertSame(3, Zprava::count());
    }

    public function test_vypnuty_formular_navstevnik_nevidi(): void
    {
        SekceWebu::nastav('formular', false);

        $this->get('/kontakt')->assertNotFound();
        $this->post('/kontakt', $this->data())->assertNotFound();
        $this->assertSame(0, Zprava::count());
    }

    public function test_bezpecnostni_hlavicky_na_webu_i_v_administraci(): void
    {
        foreach (['/' => 'DENY', '/admin/login' => 'SAMEORIGIN'] as $url => $ramec) {
            $this->get($url)
                ->assertHeader('X-Frame-Options', $ramec)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // Exportex: přísná CSP jako dřív v <meta> – jen vlastní adresa, žádný vložený skript.
        // Měření (a cookie lišta šablony s vloženým skriptem) ji rozšíří, až když se zapne.
        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self';", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("connect-src 'self';", $csp);
        $this->assertStringNotContainsString('formsubmit', $csp);
        $this->assertNull($this->get('/admin/login')->headers->get('Content-Security-Policy'));

        NastaveniWebu::uloz(['ga4_id' => 'G-TEST12345']);
        $this->assertStringContainsString('https://www.googletagmanager.com', $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_zpravy_v_administraci(): void
    {
        Zprava::create(['jmeno' => 'Jana', 'prijmeni' => 'Nováková', 'firma' => 'Textil Brno s.r.o.', 'email' => 'jana@example.cz', 'zprava' => 'Dobrý den, ručníky.', 'jazyk' => 'en']);
        $spravce = tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => 'admin'])->save());

        $this->assertSame('1', ZpravaResource::getNavigationBadge());
        $this->actingAs($spravce)->get(ZpravaResource::getUrl())->assertOk()->assertSee('Jana Nováková')->assertSee('Textil Brno s.r.o.')->assertSee('EN');

        \Livewire\Livewire::test(\App\Filament\Resources\Zpravy\Pages\ListZpravy::class)
            ->callTableAction('detail', Zprava::sole());
        $this->assertNotNull(Zprava::sole()->precteno_at);
        $this->assertNull(ZpravaResource::getNavigationBadge());

        $klient = tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => 'klient'])->save());
        $this->actingAs($klient)->get(ZpravaResource::getUrl())->assertForbidden();
    }

    // ---- Exportex ----

    public function test_upozorneni_jde_na_mikysku_jako_driv(): void
    {
        Mail::fake();

        $this->postJson('/kontakt', $this->data(['jazyk' => 'en']))->assertOk()->assertJson(['ok' => true]);

        Mail::assertSent(NovaZprava::class, fn ($m) => $m->hasTo('mikyska@exportex.cz') && $m->hasSubject('Nová poptávka z webu Exportex: Jana Nováková, Textil Brno s.r.o. [EN]'));
        $text = (new NovaZprava(Zprava::sole()))->render();
        $this->assertStringContainsString('Firma: Textil Brno s.r.o.', $text);
        $this->assertStringContainsString('odpovězte prosím anglicky', $text);
        $this->assertStringContainsString('2 000 ručníků', $text);
    }

    public function test_formular_na_webu_posila_json(): void
    {
        Mail::fake();

        $this->postJson('/kontakt', $this->data())->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, Zprava::count());

        $this->postJson('/kontakt', $this->data(['firma' => '', 'prijmeni' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['firma' => 'Vyplňte firmu.', 'prijmeni' => 'Vyplňte příjmení.']);

        // Bot dostane stejné „odesláno“ jako člověk.
        $this->postJson('/kontakt', $this->data([], zobrazenoPred: 1))->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, Zprava::count());

        // Limit z jedné IP (3 za minutu): JSON 429, ne přesměrování.
        $this->postJson('/kontakt', $this->data())->assertStatus(429)->assertJson(['error' => 'throttle']);
    }

    public function test_neznamy_jazyk_je_cestina(): void
    {
        Mail::fake();

        $this->postJson('/kontakt', $this->data(['jazyk' => 'de']))->assertOk();

        $this->assertSame('cs', Zprava::sole()->jazyk);
    }

    public function test_formsubmit_je_pryc(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('formsubmit', $html);
        $this->assertStringNotContainsString('config.js', $html);
        $this->assertFileDoesNotExist(public_path('assets/js/config.js'));
        $this->assertStringNotContainsString('https://formsubmit', file_get_contents(public_path('assets/js/main.js')));
    }
}
