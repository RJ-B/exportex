<?php

namespace Tests\Feature;

use App\Models\Nastaveni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Přenos obsahu webu Exportex z testu do produkce (config/prenos.php, část
 * „Obsah webu“): obsah je v nastavení pod obsah.* jako JSON s cestami
 * k nahraným fotkám – fotky jdou s ním, ostatní části zůstávají, jak byly.
 */
class PrenosObsahuWebuTest extends TestCase
{
    use RefreshDatabase;

    private string $id = 'p0123456789abcdef0123456789abcdee';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/prenos'));
        parent::tearDown();
    }

    private function prikaz(array $parametry): array
    {
        Artisan::call('simren:prenos', $parametry);

        return json_decode(trim(Artisan::output()), true);
    }

    private function sekce(string $klic, string $nadpis, ?string $foto = null): void
    {
        Nastaveni::nastav($klic, json_encode(['nadpis' => $nadpis, 'foto' => $foto], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function nadpis(string $klic): ?string
    {
        return json_decode((string) DB::table('nastaveni')->where('klic', $klic)->value('hodnota'), true)['nadpis'] ?? null;
    }

    /** Test: obsah s fotkou, údaje firmy a klíč Pošty → export → databáze přestavěná na „produkci“. */
    private function zTestuDoProdukce(): void
    {
        Storage::disk('public')->put('fotky/uvod.webp', 'FOTKA-Z-TESTU');
        $this->sekce('obsah.uvod', 'Nadpis z testu', 'fotky/uvod.webp');
        $this->sekce('obsah.onas', 'Druhá sekce z testu');
        Nastaveni::nastav('zaklad.nazev', 'Firma z testu');
        Nastaveni::nastav('posta.token', 'tajny-token-testu');

        $v = $this->prikaz(['akce' => 'export', 'id' => $this->id]);
        $this->assertTrue($v['ok'], $v['chyba'] ?? '');
        $this->assertSame(2, $v['casti']['obsah']);
        $this->assertSame(1, $v['soubory']);

        DB::table('nastaveni')->delete();
        Storage::disk('public')->deleteDirectory(dirname('fotky/uvod.webp'));
        $this->sekce('obsah.uvod', 'Nadpis z produkce');
        $this->sekce('obsah.kontakt', 'Jen v produkci');
        Nastaveni::nastav('zaklad.nazev', 'Firma z produkce');
    }

    public function test_casti_nabizi_obsah_a_uzivatele_jsou_citlivi_a_vypnuti(): void
    {
        $casti = collect($this->prikaz(['akce' => 'casti'])['casti'])->keyBy('klic');

        $this->assertTrue($casti['obsah']['vychozi']);
        $this->assertFalse($casti['uzivatele']['vychozi']);
        $this->assertTrue($casti['uzivatele']['citlive']);
        $this->assertNotContains('nahradit', $casti['uzivatele']['rezimy']);
    }

    public function test_nahled_jen_spocita_a_nic_nezmeni(): void
    {
        $this->zTestuDoProdukce();

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:aktualizovat', '--nanecisto' => true]);

        $this->assertTrue($v['nanecisto']);
        $this->assertSame(['nove' => 1, 'zmenene' => 1, 'jen_v_cili' => 1],
            array_intersect_key($v['casti']['obsah'], array_flip(['nove', 'zmenene', 'jen_v_cili'])));
        $this->assertSame(1, $v['soubory']);
        $this->assertSame('Nadpis z produkce', $this->nadpis('obsah.uvod'));
        $this->assertFalse(Storage::disk('public')->exists('fotky/uvod.webp'));
    }

    public function test_doplnit_prida_chybejici_sekci_a_upravenou_necha(): void
    {
        $this->zTestuDoProdukce();

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:doplnit']);

        $this->assertTrue($v['ok'], $v['chyba'] ?? '');
        $this->assertSame('Nadpis z produkce', $this->nadpis('obsah.uvod'));
        $this->assertSame('Druhá sekce z testu', $this->nadpis('obsah.onas'));
        $this->assertSame('Jen v produkci', $this->nadpis('obsah.kontakt'));
    }

    public function test_aktualizovat_prepise_obsah_i_s_fotkou_a_jine_casti_necha(): void
    {
        $this->zTestuDoProdukce();

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:aktualizovat']);

        $this->assertTrue($v['ok'], $v['chyba'] ?? '');
        $this->assertSame('Nadpis z testu', $this->nadpis('obsah.uvod'));
        $this->assertSame('Jen v produkci', $this->nadpis('obsah.kontakt'));
        $this->assertSame('FOTKA-Z-TESTU', Storage::disk('public')->get('fotky/uvod.webp'));
        $this->assertSame('Firma z produkce', Nastaveni::hodnota('zaklad.nazev'));
        $this->assertDatabaseMissing('nastaveni', ['klic' => 'posta.token']);
    }

    public function test_nahradit_smaze_jen_obsah_ktery_v_testu_neni(): void
    {
        $this->zTestuDoProdukce();

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:nahradit,udaje:aktualizovat']);

        $this->assertTrue($v['ok'], $v['chyba'] ?? '');
        $this->assertNull($this->nadpis('obsah.kontakt'));
        $this->assertSame('Nadpis z testu', $this->nadpis('obsah.uvod'));
        $this->assertSame('Firma z testu', Nastaveni::hodnota('zaklad.nazev'));
    }

    public function test_opakovany_prenos_nic_nezdvoji(): void
    {
        $this->zTestuDoProdukce();

        $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:aktualizovat']);
        $pocet = DB::table('nastaveni')->count();
        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:aktualizovat']);

        $this->assertSame(['nove' => 0, 'zmenene' => 0, 'stejne' => 2],
            array_intersect_key($v['casti']['obsah'], array_flip(['nove', 'zmenene', 'stejne'])));
        $this->assertSame($pocet, DB::table('nastaveni')->count());
    }
}
