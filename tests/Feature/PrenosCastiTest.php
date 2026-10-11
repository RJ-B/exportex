<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Nastaveni;
use App\Models\User;
use App\Support\Prenos\Casti;
use App\Support\Prenos\Prenos;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Přenos vybraných částí dat (portál → Přenést data z testu): export na zdroji,
 * import na cíli nanečisto i doopravdy, režimy doplnit / aktualizovat / nahradit,
 * vazby přes id, fotky a to, co se nepřenáší nikdy.
 *
 * Zdroj i cíl jsou tady jedna databáze: export → přestavět data na „produkci“ → import.
 */
class PrenosCastiTest extends TestCase
{
    use RefreshDatabase;

    private string $id = 'p0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        Schema::create('projekty_test', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('nazev');
            $t->string('fotka')->nullable();
            $t->timestamps();
        });
        Schema::create('projekty_test_fotky', function (Blueprint $t) {
            $t->id();
            $t->foreignId('projekt_id')->constrained('projekty_test')->cascadeOnDelete();
            $t->string('cesta');
        });

        config(['prenos.casti.obsah' => [
            'nazev' => 'Obsah webu',
            'tabulky' => [
                'projekty_test' => ['klic' => ['slug']],
                'projekty_test_fotky' => ['klic' => ['projekt_id', 'cesta'], 'vazby' => ['projekt_id' => 'projekty_test']],
            ],
        ]]);
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

    private function projekt(string $slug, string $nazev, ?string $fotka = null): int
    {
        return DB::table('projekty_test')->insertGetId(['slug' => $slug, 'nazev' => $nazev, 'fotka' => $fotka]);
    }

    /** Data „testu“ vyexportovat a databázi přestavět na „produkci“. */
    private function exportujAPrestav(callable $produkce): void
    {
        $this->assertTrue($this->prikaz(['akce' => 'export', 'id' => $this->id])['ok']);

        DB::table('projekty_test_fotky')->delete();
        DB::table('projekty_test')->delete();
        DB::table('nastaveni')->delete();
        DB::table('users')->delete();
        Storage::disk('public')->deleteDirectory('projekty');
        $produkce();
    }

    private function zTestu(): void
    {
        Storage::disk('public')->put('projekty/strecha.webp', 'TEST-FOTKA');
        $a = $this->projekt('strecha', 'Střecha z testu', 'projekty/strecha.webp');
        DB::table('projekty_test_fotky')->insert(['projekt_id' => $a, 'cesta' => 'projekty/strecha.webp']);
        $this->projekt('izolace', 'Izolace z testu');
        Nastaveni::nastav('zaklad.nazev', 'Firma z testu');
        Nastaveni::nastav('web.tagline', 'Slogan z testu');
        Nastaveni::nastav('posta.token', 'sifra-testu');
        Nastaveni::nastav('web.stav', 'pripravujeme');
    }

    public function test_casti_vypise_co_aplikace_umi_prenest(): void
    {
        $v = $this->prikaz(['akce' => 'casti']);

        $this->assertTrue($v['ok']);
        $klice = array_column($v['casti'], 'klic');
        foreach (['udaje', 'nastaveni', 'uzivatele', 'obsah'] as $cast) {
            $this->assertContains($cast, $klice);
        }
        $uzivatele = collect($v['casti'])->firstWhere('klic', 'uzivatele');
        $this->assertFalse($uzivatele['vychozi']);
        $this->assertTrue($uzivatele['citlive']);
        $this->assertNotContains('nahradit', $uzivatele['rezimy']);
    }

    public function test_doplnit_prida_jen_chybejici_a_existujici_necha(): void
    {
        $this->zTestu();
        $this->exportujAPrestav(function () {
            $this->projekt('strecha', 'Střecha upravená v produkci');
            $this->projekt('jen-v-produkci', 'Jen v produkci');
            Nastaveni::nastav('zaklad.nazev', 'Firma z produkce');
        });

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:doplnit,udaje:doplnit']);

        $this->assertTrue($v['ok'], $v['chyba'] ?? '');
        $this->assertSame(['nove' => 2, 'ponechane' => 1, 'jen_v_cili' => 1],
            array_intersect_key($v['casti']['obsah'], array_flip(['nove', 'ponechane', 'jen_v_cili'])));
        $this->assertSame('Střecha upravená v produkci', DB::table('projekty_test')->where('slug', 'strecha')->value('nazev'));
        $this->assertDatabaseHas('projekty_test', ['slug' => 'izolace']);
        $this->assertDatabaseHas('projekty_test', ['slug' => 'jen-v-produkci']);
        // Fotka existující „střechy“ se připojila k produkčnímu řádku (přečíslované id).
        $strecha = DB::table('projekty_test')->where('slug', 'strecha')->value('id');
        $this->assertDatabaseHas('projekty_test_fotky', ['projekt_id' => $strecha, 'cesta' => 'projekty/strecha.webp']);
        $this->assertSame('TEST-FOTKA', Storage::disk('public')->get('projekty/strecha.webp'));
        $this->assertSame('Firma z produkce', Nastaveni::hodnota('zaklad.nazev'));
    }

    public function test_aktualizovat_prepise_existujici_a_necha_co_je_jen_v_cili(): void
    {
        $this->zTestu();
        $this->exportujAPrestav(function () {
            $this->projekt('strecha', 'Střecha upravená v produkci');
            $this->projekt('jen-v-produkci', 'Jen v produkci');
            Storage::disk('public')->put('projekty/strecha.webp', 'STARA-FOTKA');
        });

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:aktualizovat']);

        $this->assertSame(1, $v['casti']['obsah']['zmenene']);
        $this->assertSame('Střecha z testu', DB::table('projekty_test')->where('slug', 'strecha')->value('nazev'));
        $this->assertDatabaseHas('projekty_test', ['slug' => 'jen-v-produkci']);
        $this->assertSame('TEST-FOTKA', Storage::disk('public')->get('projekty/strecha.webp'));
    }

    public function test_nahradit_smaze_cast_v_cili_a_nahradi_kopii(): void
    {
        $this->zTestu();
        $this->exportujAPrestav(function () {
            $this->projekt('jen-v-produkci', 'Jen v produkci');
            Nastaveni::nastav('web.tagline', 'Slogan z produkce');
        });

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:nahradit']);

        $this->assertSame(1, $v['casti']['obsah']['smazane']);
        $this->assertSame(['izolace', 'strecha'], DB::table('projekty_test')->orderBy('slug')->pluck('slug')->all());
        // Jiná část zůstala, jak byla.
        $this->assertSame('Slogan z produkce', Nastaveni::hodnota('web.tagline'));
    }

    public function test_nanecisto_jen_spocita_a_nic_nezmeni(): void
    {
        $this->zTestu();
        $this->exportujAPrestav(fn () => $this->projekt('jen-v-produkci', 'Jen v produkci'));

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:nahradit,nastaveni:doplnit', '--nanecisto' => true]);

        $this->assertTrue($v['nanecisto']);
        $this->assertSame(3, $v['casti']['obsah']['nove']);   // 2 projekty + fotka
        $this->assertSame(1, $v['casti']['obsah']['smazane']);
        $this->assertSame(1, $v['soubory']);
        $this->assertSame(['jen-v-produkci'], DB::table('projekty_test')->pluck('slug')->all());
        $this->assertNull(DB::table('nastaveni')->where('klic', 'web.tagline')->value('hodnota'));
        $this->assertFalse(Storage::disk('public')->exists('projekty/strecha.webp'));
        $this->assertSame(0, AuditLog::where('event', 'data.prevzata')->count());
    }

    public function test_klice_posty_plateb_a_stav_webu_se_neprenasi_nikdy(): void
    {
        $this->zTestu();
        $this->exportujAPrestav(fn () => null);

        $data = json_decode(File::get(Prenos::adresar($this->id).'/data.json'), true);
        $klice = collect($data['casti'])->flatMap(fn ($c) => collect($c['tabulky']['nastaveni'] ?? [])->pluck('klic'))->all();
        $this->assertContains('web.tagline', $klice);
        $this->assertNotContains('posta.token', $klice);
        $this->assertNotContains('web.stav', $klice);

        $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'nastaveni:aktualizovat']);
        $this->assertSame('Slogan z testu', Nastaveni::hodnota('web.tagline'));
        $this->assertDatabaseMissing('nastaveni', ['klic' => 'posta.token']);
        $this->assertSame(1, AuditLog::where('event', 'data.prevzata')->count());
    }

    public function test_neznama_cast_nebo_nepovoleny_rezim_import_odmitne(): void
    {
        $this->zTestu();
        $this->exportujAPrestav(fn () => null);

        $this->assertFalse($this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'neco:doplnit'])['ok']);
        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'uzivatele:nahradit']);
        $this->assertFalse($v['ok']);
        $this->assertStringContainsString('nahradit', $v['chyba']);
        $this->assertFalse($this->prikaz(['akce' => 'export', 'id' => '../../etc'])['ok']);
    }

    public function test_vic_moznosti_klice_paruje_podle_puvodniho_id_i_po_prejmenovani(): void
    {
        Schema::table('projekty_test', fn (Blueprint $t) => $t->string('puvodni_id')->nullable()->unique());
        config(['prenos.casti.obsah.tabulky.projekty_test.klic' => [['puvodni_id'], ['slug']]]);

        DB::table('projekty_test')->insert(['slug' => 'strecha', 'nazev' => 'Střecha', 'puvodni_id' => 'p1']);
        $this->projekt('nova', 'Nová bez původního id');
        $this->exportujAPrestav(function () {
            // V produkci někdo slug přejmenoval – podle puvodni_id je to pořád tentýž projekt.
            DB::table('projekty_test')->insert(['slug' => 'strecha-upravena', 'nazev' => 'Střecha', 'puvodni_id' => 'p1']);
        });

        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'obsah:aktualizovat']);

        $this->assertTrue($v['ok'], $v['chyba'] ?? '');
        $this->assertSame(['nove' => 1, 'zmenene' => 1], array_intersect_key($v['casti']['obsah'], ['nove' => 0, 'zmenene' => 0]));
        $this->assertSame(['nova', 'strecha'], DB::table('projekty_test')->orderBy('slug')->pluck('slug')->all());
    }

    public function test_provozni_tabulka_v_casti_se_odmitne(): void
    {
        config(['prenos.casti.spatne' => ['tabulky' => ['sessions' => ['klic' => ['id']]]]]);

        $this->expectException(\InvalidArgumentException::class);
        Casti::vse();
    }

    public function test_sifrovane_hodnoty_se_vynechaji_a_vysledek_to_rekne(): void
    {
        // Tajemství Authenticatoru je zašifrované APP_KEY testu – v produkci (jiný klíč)
        // by bylo nečitelné a přihlášení by spadlo. Čitelně ze serveru ven nesmí.
        config(['prenos.casti.uzivatele.vychozi' => true]);
        $jana = User::factory()->create(['email' => 'jana@pekarna.cz', 'name' => 'Jana']);
        DB::table('users')->where('id', $jana->id)->update(['remember_token' => Crypt::encryptString('tajemstvi-testu')]);

        $export = $this->prikaz(['akce' => 'export', 'id' => $this->id]);

        $this->assertSame(['users.remember_token' => 1], $export['sifrovane_vynechane']);
        $balik = File::get(Prenos::adresar($this->id).'/data.json');
        $this->assertStringNotContainsString('eyJpdiI6', $balik);
        $this->assertStringContainsString('jana@pekarna.cz', $balik);

        // Na cíli se šifrovaná hodnota nepřepíše (zůstane jeho vlastní).
        DB::table('users')->where('id', $jana->id)->update(['remember_token' => 'token-produkce', 'name' => 'Jana z produkce']);
        $v = $this->prikaz(['akce' => 'import', 'id' => $this->id, '--volby' => 'uzivatele:aktualizovat']);

        $this->assertSame(['users.remember_token' => 1], $v['sifrovane_vynechane']);
        $this->assertSame('token-produkce', DB::table('users')->where('id', $jana->id)->value('remember_token'));
        $this->assertSame('Jana', DB::table('users')->where('id', $jana->id)->value('name'));
    }
}
