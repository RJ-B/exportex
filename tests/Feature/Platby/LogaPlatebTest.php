<?php

namespace Tests\Feature\Platby;

use App\Platby\NastaveniPlateb;
use Tests\TestCase;

/**
 * Loga plateb (Visa, Mastercard povinně; Comgate / Mo.one podle brány, která se skutečně používá)
 * a oznámení o bráně – docs/platby.md → Loga plateb.
 */
class LogaPlatebTest extends TestCase
{
    use SPlatbami;

    private function loga(array $parametry = []): string
    {
        return view('platby._loga', $parametry)->render();
    }

    private function testovaciBrana(): void
    {
        config(['platby.simulace' => false, 'platby.test.moone.client_id' => 'id', 'platby.test.moone.client_secret' => 'tajne']);
        NastaveniPlateb::zapomen();
    }

    private function ostryComgate(): void
    {
        $this->produkce();
        NastaveniPlateb::uloz(['brana' => 'comgate', 'comgate_merchant' => '498621', 'comgate_secret' => 'tajne-heslo-obchodu', 'rezim' => NastaveniPlateb::REZIM_OSTRY]);
        NastaveniPlateb::oznacOvereno('comgate');
    }

    public function test_simulace_ukaze_jen_karty_a_penezenky_bez_loga_brany(): void
    {
        $html = $this->loga();

        $this->assertStringContainsString('img/platby/visa.svg', $html);
        $this->assertStringContainsString('img/platby/mastercard.svg', $html);
        $this->assertStringContainsString('img/platby/apple-pay.svg', $html);
        $this->assertStringContainsString('img/platby/google-pay.svg', $html);
        $this->assertStringNotContainsString('comgate', $html);
        $this->assertStringNotContainsString('moone', $html);
        $this->assertStringNotContainsString('title=', $html);
        $this->assertStringContainsString('aria-label="Platební metody a platební brána"', $html);
    }

    public function test_test_s_testovaci_branou_ukaze_moone_a_ne_comgate_ani_penezenky(): void
    {
        $this->testovaciBrana();
        NastaveniPlateb::uloz(['brana' => 'comgate']);   // vybraná brána klienta se na testu nepoužije

        $html = $this->loga(['pozadi' => 'svetle']);

        $this->assertSame('moone', NastaveniPlateb::kodBrany());
        $this->assertStringContainsString('img/platby/moone-logo-tmave.svg', $html);
        $this->assertStringNotContainsString('moone-logo-bile', $html);
        $this->assertStringNotContainsString('comgate', $html);
        $this->assertStringNotContainsString('apple-pay', $html);
        $this->assertStringContainsString('visa.svg', $html);
        $this->assertStringNotContainsString('visa-bile', $html);
    }

    public function test_ostry_comgate_ma_logo_s_odkazem_a_varianty_podle_pozadi(): void
    {
        $this->ostryComgate();

        $this->assertSame('comgate', NastaveniPlateb::kodBrany());

        $svetle = $this->loga(['pozadi' => 'svetle']);
        $this->assertStringContainsString('href="https://www.comgate.eu"', $svetle);
        $this->assertStringContainsString('img/platby/comgate.svg', $svetle);
        $this->assertStringNotContainsString('tmave-pozadi', $svetle);

        $tmave = $this->loga(['pozadi' => 'tmave']);
        $this->assertStringContainsString('img/platby/comgate-tmave-pozadi.svg', $tmave);
        $this->assertStringContainsString('img/platby/visa-bile.svg', $tmave);
        $this->assertStringNotContainsString('img/platby/visa.svg', $tmave);

        $auto = $this->loga();
        $this->assertStringContainsString('prefers-color-scheme: dark', $auto);
        $this->assertStringContainsString('comgate-tmave-pozadi.svg', $auto);
    }

    public function test_penezenky_zmizi_s_vypnutou_predvolbou(): void
    {
        $this->ostryComgate();
        NastaveniPlateb::uloz(['apple_pay' => false]);

        $html = $this->loga();

        $this->assertStringNotContainsString('apple-pay', $html);
        $this->assertStringContainsString('google-pay', $html);
    }

    public function test_text_o_brane_na_strankach_plateb(): void
    {
        $this->ostryComgate();

        $html = $this->loga(['text' => true]);
        $this->assertStringContainsString('Platbu zajišťuje Comgate', $html);
        $this->assertStringContainsString('licencovaná platební instituce působící pod dohledem České národní banky', $html);

        NastaveniPlateb::uloz(['rezim' => NastaveniPlateb::REZIM_TEST]);
        $this->testovaciBrana();
        $html = $this->loga(['text' => true]);
        $this->assertStringContainsString('Platbu zajišťuje Mo.one', $html);
        $this->assertStringContainsString('Platby zajišťuje platební brána Mo.one.', $html);
        $this->assertStringNotContainsString('licencovaná', $html);
    }

    public function test_bez_brany_nic(): void
    {
        config(['platby.simulace' => false, 'platby.test.moone.client_id' => null, 'platby.test.moone.client_secret' => null]);
        NastaveniPlateb::zapomen();

        $this->assertNull(NastaveniPlateb::kodBrany());
        $this->assertSame('', trim($this->loga()));
    }

    public function test_stranka_vysledku_ma_loga_a_text_o_brane(): void
    {
        $platba = $this->platby()->zaloz($this->pozadavek());

        $this->get(route('platby.vysledek', $platba))
            ->assertOk()
            ->assertSee('img/platby/visa.svg', false)
            ->assertSee('img/platby/mastercard.svg', false);
        $this->get(route('platby.simulace', $platba))->assertOk()->assertSee('img/platby/mastercard.svg', false);
    }

    public function test_ochrana_udaju_ma_odstavec_o_brane_jen_se_zapnutym_doplnkem(): void
    {
        $this->testovaciBrana();
        $this->get(route('ochrana-udaju'))->assertOk()->assertSee('Platby zajišťuje platební brána Mo.one.');

        $this->ostryComgate();
        $this->assertStringContainsString('Online platby kartou (Visa, Mastercard), Apple Pay a Google Pay zajišťuje platební brána Comgate.', view('pravni.ochrana-osobnich-udaju')->render());
    }

    public function test_svg_loga_nemaji_skripty(): void
    {
        foreach (glob(public_path('img/platby/*.svg')) as $soubor) {
            $this->assertDoesNotMatchRegularExpression('/<script|javascript:|\son[a-z]+\s*=|<foreignObject/i', file_get_contents($soubor), basename($soubor));
        }
    }
}
