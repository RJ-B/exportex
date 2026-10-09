<?php

namespace Tests\Feature\Platby;

use App\Models\AuditLog;
use App\Models\Nastaveni;
use App\Platby\Brany\MoOne;
use App\Platby\Brany\Simulace;
use App\Platby\Filament\Stranky\PlatebniBrana;
use App\Platby\NastaveniPlateb;
use App\Platby\PlatbyNedostupne;
use App\Platby\Rezim;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Přepínání test × produkce: test platí vždy testovací bránou Sim&Ren,
 * produkce ostře jen s ověřenými údaji klienta, nic se tiše nepřepíná,
 * tajemství se do formuláře nevrací, štítek TESTOVACÍ PLATBY.
 */
class TestAProdukceTest extends TestCase
{
    use SPlatbami;

    private function udajeKlienta(): void
    {
        NastaveniPlateb::uloz(['brana' => 'comgate', 'comgate_merchant' => '498621', 'comgate_secret' => 'tajne-heslo-obchodu']);
    }

    public function test_lokalne_simulace_bez_ni_testovaci_brana_a_bez_udaju_nic(): void
    {
        $this->assertInstanceOf(Simulace::class, NastaveniPlateb::aktivni());

        config(['platby.simulace' => false]);
        try {
            NastaveniPlateb::aktivni();
            $this->fail('Bez testovacích údajů se neplatí.');
        } catch (PlatbyNedostupne $e) {
            $this->assertStringContainsString('PLATBY_TEST_MOONE_CLIENT_ID', $e->getMessage());
        }

        $this->testovaciUdaje();
        $brana = NastaveniPlateb::aktivni();
        $this->assertInstanceOf(MoOne::class, $brana);
        $this->assertSame(Rezim::Testovaci, $brana->rezim());
    }

    public function test_testovaci_web_plati_vzdy_testovaci_branou_i_kdyz_je_vybrany_ostry_rezim(): void
    {
        $this->app['env'] = 'staging';
        $this->testovaciUdaje();
        $this->udajeKlienta();
        NastaveniPlateb::oznacOvereno('comgate');
        Nastaveni::nastav('platby.rezim', NastaveniPlateb::REZIM_OSTRY);   // třeba po přenosu dat
        NastaveniPlateb::zapomen();

        $brana = NastaveniPlateb::aktivni();

        $this->assertSame('moone', $brana->kod());
        $this->assertSame(Rezim::Testovaci, $brana->rezim());
        $this->assertTrue(NastaveniPlateb::stitek());
        $this->assertStringContainsString('jen na produkci', (string) NastaveniPlateb::procNeOstre());
    }

    public function test_produkce_ostre_jen_s_overenymi_udaji_a_bez_tichého_prepnuti(): void
    {
        $this->produkce();
        $this->testovaciUdaje();

        // Výchozí: testovací režim = testovací brána Sim&Ren.
        $this->assertSame(Rezim::Testovaci, NastaveniPlateb::aktivni()->rezim());

        $this->udajeKlienta();
        Nastaveni::nastav('platby.rezim', NastaveniPlateb::REZIM_OSTRY);
        NastaveniPlateb::zapomen();

        // Ostrý bez ověření = platby stojí (ne testovací brána).
        try {
            NastaveniPlateb::aktivni();
            $this->fail('Neověřený ostrý režim nesmí platit.');
        } catch (PlatbyNedostupne $e) {
            $this->assertStringContainsString('nejsou ověřené', $e->getMessage());
        }
        $this->assertFalse(NastaveniPlateb::stitek(), 'Nefunkční platby se netváří jako testovací.');

        NastaveniPlateb::oznacOvereno('comgate');
        $brana = NastaveniPlateb::aktivni();
        $this->assertSame('comgate', $brana->kod());
        $this->assertSame(Rezim::Ostry, $brana->rezim());
        $this->assertFalse(NastaveniPlateb::stitek());

        // Nový secret = ověření neplatí.
        NastaveniPlateb::uloz(['comgate_secret' => 'nove-heslo']);
        $this->expectException(PlatbyNedostupne::class);
        NastaveniPlateb::aktivni();
    }

    public function test_tajemstvi_sifrovane_a_mimo_formular_i_aktivitu(): void
    {
        $this->udajeKlienta();

        $ulozeno = Nastaveni::hodnota('platby.comgate.secret');
        $this->assertNotSame('tajne-heslo-obchodu', $ulozeno);
        $this->assertSame('tajne-heslo-obchodu', NastaveniPlateb::udajeKlienta('comgate')['tajemstvi']);
        $this->assertFalse(AuditLog::query()->where('new_values', 'like', '%'.$ulozeno.'%')->exists(), 'Zašifrované heslo nepatří ani do Aktivity.');

        Livewire::actingAs($this->spravce())->test(PlatebniBrana::class)
            ->assertSet('data.comgate_secret', null)
            ->assertSet('data.comgate_merchant', '498621')
            ->assertDontSee('tajne-heslo-obchodu');
    }

    public function test_administrace_overit_spojeni_a_prepnout_na_ostry(): void
    {
        $this->produkce();
        $this->testovaciUdaje();
        Http::fake(['https://payments.comgate.cz/v2.0/method.json*' => Http::response(['code' => 0, 'message' => 'OK', 'methods' => [['id' => 'ALL']]])]);
        $admin = $this->spravce();

        // Ostrý bez ověření nejde.
        Livewire::actingAs($admin)->test(PlatebniBrana::class)
            ->set('data.brana', 'comgate')
            ->set('data.comgate_merchant', '498621')
            ->set('data.comgate_secret', 'tajne-heslo-obchodu')
            ->set('data.rezim', NastaveniPlateb::REZIM_OSTRY)
            ->call('uloz')
            ->assertHasErrors(['data.rezim']);
        $this->assertSame(NastaveniPlateb::REZIM_TEST, NastaveniPlateb::rezimVolba());

        // Ověřit spojení → údaje uložené a ověřené → ostrý jde.
        Livewire::actingAs($admin)->test(PlatebniBrana::class)
            ->set('data.brana', 'comgate')
            ->set('data.comgate_merchant', '498621')
            ->set('data.comgate_secret', 'tajne-heslo-obchodu')
            ->callAction('overit')
            ->assertNotified('Spojení funguje')
            ->assertSet('data.comgate_secret', null)
            ->set('data.rezim', NastaveniPlateb::REZIM_OSTRY)
            ->call('uloz')
            ->assertHasNoErrors();

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('498621:tajne-heslo-obchodu')));
        $this->assertSame(Rezim::Ostry, NastaveniPlateb::aktivni()->rezim());
        $this->assertTrue(AuditLog::query()->where('event', 'platby.rezim')->where('summary', 'like', '%OSTRÝ%')->exists());
    }

    public function test_na_testu_ostry_rezim_v_administraci_nejde(): void
    {
        $this->app['env'] = 'staging';
        $this->udajeKlienta();
        NastaveniPlateb::oznacOvereno('comgate');

        Livewire::actingAs($this->spravce())->test(PlatebniBrana::class)
            ->assertFormFieldIsDisabled('rezim')
            ->set('data.rezim', NastaveniPlateb::REZIM_OSTRY)
            ->call('uloz');

        $this->assertSame(NastaveniPlateb::REZIM_TEST, NastaveniPlateb::rezimVolba());
    }

    public function test_stitek_testovaci_platby_na_webu_a_v_administraci(): void
    {
        $platba = $this->platby()->zaloz($this->pozadavek());
        $this->get(route('platby.vysledek', $platba))->assertSee('TESTOVACÍ PLATBY');
        $this->actingAs($this->spravce())->get('/admin')->assertSee('TESTOVACÍ PLATBY');

        // Ostře – štítek zmizí.
        $this->produkce();
        $this->udajeKlienta();
        NastaveniPlateb::oznacOvereno('comgate');
        NastaveniPlateb::uloz(['rezim' => NastaveniPlateb::REZIM_OSTRY]);

        $this->actingAs($this->spravce())->get('https://pekarna.cz/admin')->assertDontSee('TESTOVACÍ PLATBY');
    }
}
