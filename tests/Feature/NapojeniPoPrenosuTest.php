<?php

namespace Tests\Feature;

use App\Models\Nastaveni;
use App\Models\User;
use App\Support\Posta\PrikazZPortalu;
use App\Support\Posta\Propojeni;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Napojení patří instalaci: databáze nahraná z jiného webu (test z produkce,
 * produkce s přenosem dat z testu) cizí klíče Pošty nepoužije a portál web
 * propojí znovu (zjisti hlásí propojeno: false). Po importu celé databáze
 * `simren:prenos po-importu` klíče zdroje zapomene.
 */
class NapojeniPoPrenosuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['app.url' => 'https://pekarna.test.simren.cz']);
    }

    protected function tearDown(): void
    {
        PrikazZPortalu::$vstup = null;
        parent::tearDown();
    }

    private function prikaz(array $vstup): array
    {
        PrikazZPortalu::$vstup = json_encode($vstup);
        Artisan::call('posta:z-portalu');

        return json_decode(trim(Artisan::output()), true);
    }

    private function propoj(): void
    {
        $this->prikaz([
            'akce' => 'propoj', 'url' => 'https://posta.test', 'token' => 'pst_testu', 'webhook_tajemstvi' => 'tajemstvi-testu',
            'aplikace' => ['nazev' => 'Pekárna (test)', 'slug' => 'pekarna-test-simren-cz', 'adresy' => [['adresa' => 'posta@simren.cz', 'jmeno' => null]]],
        ]);
    }

    public function test_zjisti_hlasi_aplikaci_v_poste(): void
    {
        $this->propoj();

        $zjisteno = $this->prikaz(['akce' => 'zjisti']);
        $this->assertTrue($zjisteno['propojeno']);
        $this->assertSame('pekarna-test-simren-cz', $zjisteno['aplikace']);
    }

    public function test_klice_z_databaze_jineho_webu_se_nepouziji(): void
    {
        $this->propoj();

        // Stejná databáze v produkci (přenos dat z testu, stejný APP_KEY): klíče testu neplatí.
        config(['app.url' => 'https://objednavky.pekarna-novak.cz']);

        $this->assertFalse(Propojeni::propojeno());
        $this->assertNull(Propojeni::token());
        $this->assertSame([], Propojeni::webhookTajemstvi());
        $this->assertSame([], Propojeni::adresy());
        $zjisteno = $this->prikaz(['akce' => 'zjisti']);
        $this->assertFalse($zjisteno['propojeno']);
        $this->assertNull($zjisteno['aplikace']);

        // Portál propojí produkci vlastní aplikací – pak platí její klíče.
        $this->prikaz([
            'akce' => 'propoj', 'url' => 'https://posta.test', 'token' => 'pst_produkce',
            'aplikace' => ['nazev' => 'Pekárna', 'slug' => 'objednavky-pekarna-novak-cz', 'adresy' => [['adresa' => 'info@pekarna-novak.cz', 'jmeno' => null]]],
        ]);
        $this->assertSame('pst_produkce', Propojeni::token());
    }

    public function test_po_importu_zapomene_klice_zdroje(): void
    {
        $this->propoj();

        Artisan::call('simren:prenos', ['akce' => 'po-importu']);
        $vystup = json_decode(trim(Artisan::output()), true);

        $this->assertTrue($vystup['ok']);
        $this->assertFalse(Propojeni::propojeno());
    }

    public function test_po_importu_zahodi_frontu_posty_a_vymaze_necitelne_tajemstvi(): void
    {
        $jana = User::factory()->create();

        // Šifrované jiným APP_KEY (databáze z testu) a tímhle klíčem (čitelné).
        $cizi = new Encrypter(random_bytes(32), 'aes-256-cbc');
        DB::table('users')->where('id', $jana->id)->update(['remember_token' => $cizi->encryptString('z-testu')]);
        Nastaveni::nastav('platby.comgate_secret', $cizi->encryptString('klic-brany-testu'));
        Nastaveni::nastav('platby.moone_secret', Crypt::encryptString('citelny'));

        Artisan::call('simren:prenos', ['akce' => 'po-importu']);
        $vystup = json_decode(trim(Artisan::output()), true);

        $this->assertTrue($vystup['ok']);
        $vymazano = $vystup['sifrovane']['vymazano'];
        ksort($vymazano);
        $this->assertSame(['nastaveni.hodnota' => 1, 'users.remember_token' => 1], $vymazano);
        $this->assertNull($jana->fresh()->remember_token);
        $this->assertNull(Nastaveni::hodnota('platby.comgate_secret'));
        $this->assertSame('citelny', Crypt::decryptString(Nastaveni::hodnota('platby.moone_secret')));
    }
}
