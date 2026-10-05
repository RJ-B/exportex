<?php

namespace Tests\Feature;

use App\Support\Zdravi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kontrola zdraví pro portál: /zdravi rozhoduje o vrácení nasazení,
 * simren:zdravi --json čte portál přes SSH.
 */
class ZdraviTest extends TestCase
{
    use RefreshDatabase;

    public function test_zdravy_web_vraci_200_bez_podrobnosti(): void
    {
        $this->get('/zdravi')->assertOk()->assertSeeText('ok')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_spadla_databaze_vraci_503(): void
    {
        config(['database.connections.rozbita' => ['driver' => 'sqlite', 'database' => '/neexistuje/nikde.sqlite']]);
        $puvodni = config('database.default');
        config(['database.default' => 'rozbita']);

        try {
            $this->get('/zdravi')->assertStatus(503)->assertSeeText('chyba')->assertDontSeeText('neexistuje');
        } finally {
            config(['database.default' => $puvodni]);
        }
    }

    public function test_diagnostika_pro_portal(): void
    {
        // Vlastní storage, ať se nepočítají chyby ze skutečného logu projektu.
        $storage = sys_get_temp_dir().'/zdravi-'.uniqid();
        mkdir($storage.'/logs', 0777, true);
        mkdir($storage.'/framework/cache', 0777, true);
        $this->app->useStoragePath($storage);

        $log = storage_path('logs/zdravi-test.log');
        file_put_contents($log, implode("\n", [
            '['.now()->subHours(3)->format('Y-m-d H:i:s').'] testing.ERROR: stará chyba',
            '['.now()->subMinutes(20)->format('Y-m-d H:i:s').'] testing.ERROR: SQLSTATE spadlo spojení',
            '['.now()->subMinutes(5)->format('Y-m-d H:i:s').'] testing.INFO: jen informace',
            '['.now()->subMinutes(2)->format('Y-m-d H:i:s').'] testing.CRITICAL: došla paměť',
        ])."\n");
        Zdravi::znackaPlanovace();

        try {
            $this->artisan('simren:zdravi', ['--json' => true])->assertSuccessful();
            $d = Zdravi::diagnostika();
        } finally {
            array_map('unlink', array_merge(glob($storage.'/*/*.*') ?: [], glob($storage.'/framework/cache/*') ?: []));
            @rmdir($storage.'/logs');
            @rmdir($storage.'/framework/cache');
            @rmdir($storage.'/framework');
            @rmdir($storage);
        }

        $this->assertTrue($d['ok']);
        $this->assertSame(1, $d['verze']);
        $this->assertSame(2, $d['chyby']['za_hodinu']);
        $this->assertSame('došla paměť', $d['chyby']['posledni']['zprava']);
        $this->assertLessThan(5, $d['planovac']['pred_s']);
        $this->assertSame(0, $d['fronta']['selhane']);
    }

    public function test_planovac_ma_znacku(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('zdravi-planovac');
    }
}
