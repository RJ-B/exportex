<?php

namespace Tests\Feature\Platby;

use App\Platby\Filament\Stranky\PlatebniBrana;
use App\Platby\MetodaPlatby;
use App\Platby\NastaveniPlateb;
use App\Platby\StavPlatby;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Apple Pay a Google Pay přes platební stránku brány (úroveň 1): co jde
 * nabídnout (NastaveniPlateb::metody), platba se založí s předvolbou
 * a brána otevře rovnou peněženku. Nastavení v administraci i brána můžou
 * metodu vypnout – pak se platí jako dřív kartou s výběrem na bráně.
 */
class PredvolbaTest extends TestCase
{
    use SPlatbami;

    private const COMGATE = 'https://payments.comgate.cz/v2.0';

    /** @var list<array{id: string}> co vrátí Comgate method.json */
    private array $metodyComgate = [['id' => 'CARD_CZ_CSOB_2'], ['id' => 'BANK_CZ_RB']];

    private int $cislo = 1000;

    private function comgate(): void
    {
        $this->produkce();
        NastaveniPlateb::uloz(['brana' => 'comgate', 'comgate_merchant' => '498621', 'comgate_secret' => 'tajne-heslo-obchodu']);
        NastaveniPlateb::oznacOvereno('comgate');
        NastaveniPlateb::uloz(['rezim' => NastaveniPlateb::REZIM_OSTRY]);

        Http::fake(fn (Request $r) => match (true) {
            $r->url() === self::COMGATE.'/payment.json' => Http::response(['code' => 0, 'message' => 'OK', 'transId' => $id = 'AP12-CD34-'.(++$this->cislo), 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id='.$id], 201),
            str_starts_with($r->url(), self::COMGATE.'/method.json') => Http::response(['methods' => $this->metodyComgate]),
            default => Http::response(['code' => 1400, 'message' => 'Bad request'], 400),
        });
    }

    public function test_simulace_nabidne_obe_penezenky_a_predvolba_dorazi_na_platebni_stranku(): void
    {
        $this->assertSame(['karta' => true, 'apple_pay' => true, 'google_pay' => true], NastaveniPlateb::metody());

        $platba = $this->platby()->zaloz($this->pozadavek(['metoda' => MetodaPlatby::ApplePay]));

        $this->assertSame(MetodaPlatby::ApplePay, $platba->predvolba);
        $this->assertStringContainsString('předvoleno Apple Pay', $platba->udalosti->first()->poznamka);
        $this->get($platba->odkazKZaplaceni())->assertRedirect(route('platby.simulace', $platba));
        $this->get(route('platby.simulace', $platba))->assertOk()->assertSee('Předvolená metoda: <strong>Apple Pay</strong>', false);
    }

    public function test_vypnuta_v_administraci_se_nenabidne_a_platba_jde_bez_predvolby(): void
    {
        NastaveniPlateb::uloz(['google_pay' => false]);

        $this->assertSame(['karta' => true, 'apple_pay' => true, 'google_pay' => false], NastaveniPlateb::metody());

        // Starší aplikace (nebo stav z doby před vypnutím) Google Pay pošle – platba
        // se nerozbije, jen zákazník vybere metodu na bráně.
        $platba = $this->platby()->zaloz($this->pozadavek(['metoda' => MetodaPlatby::GooglePay]));

        $this->assertNull($platba->predvolba);
        $this->assertSame(StavPlatby::Ceka, $platba->stav);
        $this->assertStringContainsString('Google Pay teď nejde', $platba->udalosti->first()->poznamka);
        $this->get(route('platby.simulace', $platba))->assertDontSee('Předvolená metoda');
    }

    public function test_jina_predvolba_stejneho_predmetu_zalozi_novou_platbu(): void
    {
        $karta = $this->platby()->zaloz($this->pozadavek(['klic' => 'objednavka:42']));
        $znovu = $this->platby()->zaloz($this->pozadavek(['klic' => 'objednavka:42']));
        $applePay = $this->platby()->zaloz($this->pozadavek(['klic' => 'objednavka:42', 'metoda' => MetodaPlatby::ApplePay]));

        $this->assertTrue($karta->is($znovu), 'Dvojklik vrátí stejnou platbu.');
        $this->assertFalse($karta->is($applePay));
        $this->assertSame(MetodaPlatby::ApplePay, $applePay->predvolba);
        $this->assertSame(StavPlatby::Zrusena, $karta->refresh()->stav);
    }

    public function test_comgate_predvoli_apple_pay_kodem_applepay_redirect(): void
    {
        $this->comgate();

        $this->assertSame(['karta' => true, 'apple_pay' => true, 'google_pay' => true], NastaveniPlateb::metody());

        $platba = $this->platby()->zaloz($this->pozadavek(['metoda' => MetodaPlatby::ApplePay]));

        $this->assertSame(MetodaPlatby::ApplePay, $platba->predvolba);
        Http::assertSent(fn (Request $r) => $r->url() === self::COMGATE.'/payment.json' && $r['method'] === 'APPLEPAY_REDIRECT');

        $google = $this->platby()->zaloz($this->pozadavek(['reference' => '2026-0043', 'metoda' => MetodaPlatby::GooglePay]));
        $this->assertSame(MetodaPlatby::GooglePay, $google->predvolba);
        Http::assertSent(fn (Request $r) => $r->url() === self::COMGATE.'/payment.json' && $r['method'] === 'GOOGLEPAY_REDIRECT');

        // Výpis metod se drží v mezipaměti – aplikace se ptá často.
        Http::assertSentCount(3);
    }

    public function test_comgate_bez_platby_kartou_penezenky_nenabidne(): void
    {
        $this->metodyComgate = [['id' => 'BANK_CZ_RB'], ['id' => 'BANK_CZ_CS']];
        $this->comgate();

        $this->assertSame(['karta' => true, 'apple_pay' => false, 'google_pay' => false], NastaveniPlateb::metody());

        $platba = $this->platby()->zaloz($this->pozadavek(['metoda' => MetodaPlatby::ApplePay]));

        $this->assertNull($platba->predvolba);
        Http::assertSent(fn (Request $r) => $r->url() === self::COMGATE.'/payment.json' && $r['method'] === 'ALL-BANK_CZ_OTHER-BANK_CZ_AB_CVAK');
    }

    public function test_comgate_s_vlastnim_kodem_penezenky_ve_vypisu(): void
    {
        $this->metodyComgate = [['id' => 'APPLEPAY_REDIRECT'], ['id' => 'BANK_CZ_RB']];
        $this->comgate();

        $this->assertSame(['karta' => true, 'apple_pay' => true, 'google_pay' => false], NastaveniPlateb::metody());
    }

    public function test_mo_one_predvolbu_neumi_a_bez_brany_nic(): void
    {
        $this->testovaciUdaje();
        Cache::flush();

        $this->assertSame(['karta' => true, 'apple_pay' => false, 'google_pay' => false], NastaveniPlateb::metody());

        config(['platby.test.moone.client_id' => null]);
        NastaveniPlateb::zapomen();

        $this->assertSame(['karta' => false, 'apple_pay' => false, 'google_pay' => false], NastaveniPlateb::metody());
    }

    public function test_prepinace_v_administraci(): void
    {
        $this->actingAs($this->spravce());

        $this->get('/admin/platebni-brana')->assertOk()->assertSee('Nabízet Apple Pay')->assertSee('Nabízet Google Pay');

        Livewire::test(PlatebniBrana::class)
            ->fillForm(['apple_pay' => false])
            ->call('uloz');

        $this->assertFalse(NastaveniPlateb::predvolbaZapnuta(MetodaPlatby::ApplePay));
        $this->assertTrue(NastaveniPlateb::predvolbaZapnuta(MetodaPlatby::GooglePay));
    }
}
