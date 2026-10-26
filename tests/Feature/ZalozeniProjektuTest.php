<?php

namespace Tests\Feature;

use App\Console\Commands\LokalniDatabaze;
use Tests\TestCase;

/**
 * Založení projektu ze šablony tak, jak ho dělá portál (portal-provision):
 * zkopíruje .env.example, přepíše v něm proměnné (set_env) a podle manifestu
 * nasazuje. Co tu neprojde, spadne až na serveru – při prvním nasazení testu.
 */
class ZalozeniProjektuTest extends TestCase
{
    /** Stejná pravidla jako set_env v portal-provision: přepsat, odkomentovat, nebo připsat. */
    private static function setEnv(string $env, string $klic, string $hodnota): string
    {
        if (preg_match("/^{$klic}=/m", $env)) {
            return preg_replace("/^{$klic}=.*$/m", "{$klic}={$hodnota}", $env);
        }

        if (preg_match("/^# *{$klic}=/m", $env)) {
            return preg_replace("/^# *{$klic}=.*$/m", "{$klic}={$hodnota}", $env, 1);
        }

        return $env."{$klic}={$hodnota}\n";
    }

    /** @return array<string, string> */
    private static function promenne(string $env): array
    {
        preg_match_all('/^([A-Z0-9_]+)=(.*)$/m', $env, $m);

        return array_combine($m[1], $m[2]);
    }

    public function test_env_example_miri_na_mariadb_ne_na_sqlite(): void
    {
        $p = self::promenne(file_get_contents(base_path('.env.example')));

        // Kdo .env jen zkopíruje (převzetí webu, ruční instalace), nesmí skončit na SQLite:
        // php8.5 na serverech pdo_sqlite nemá a data by ležela v souboru v repu.
        $this->assertSame('mariadb', $p['DB_CONNECTION']);
        $this->assertSame('', $p['DB_DATABASE'], 'jméno databáze doplní portál nebo composer setup');
        $this->assertSame('cs', $p['APP_LOCALE']);
        $this->assertSame('Europe/Prague', $p['APP_TIMEZONE']);
    }

    public function test_env_jak_ho_zalozi_portal(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        foreach ([
            'APP_NAME' => '"pokus"', 'APP_ENV' => 'staging', 'APP_DEBUG' => 'false',
            'APP_URL' => 'https://pokus.test.simren.cz', 'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'mariadb', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
            'DB_DATABASE' => 'rafaelr_test_pokus', 'DB_USERNAME' => 'rafaelr_test_pokus', 'DB_PASSWORD' => 'Tajne123',
        ] as $klic => $hodnota) {
            $env = self::setEnv($env, $klic, $hodnota);
        }

        $p = self::promenne($env);

        // Každá proměnná právě jednou – dvojí řádek by tiše vyhrál ten druhý.
        preg_match_all('/^(DB_[A-Z_]+)=/m', $env, $m);
        $this->assertSame(array_unique($m[1]), $m[1]);
        $this->assertSame('rafaelr_test_pokus', $p['DB_DATABASE']);
        $this->assertSame('Tajne123', $p['DB_PASSWORD']);
        $this->assertDoesNotMatchRegularExpression('/^DB_CONNECTION=sqlite/m', $env);
    }

    public function test_composer_drzi_stejne_php_jako_manifest(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        preg_match('/^php:\s*"?([0-9.]+)"?/m', file_get_contents(base_path('.simren/projekt.yml')), $m);

        // Lock vyřešený pro jiné PHP, než běží na serveru, shodí až nasazení.
        $platforma = $composer['config']['platform']['php'] ?? '';
        $this->assertStringStartsWith($m[1].'.', $platforma);
        $this->assertSame('^'.$m[1], $composer['require']['php']);
    }

    public function test_manifest_nepotrebuje_krok_filament(): void
    {
        preg_match('/^kroky:\s*\[(.*)\]/m', file_get_contents(base_path('.simren/projekt.yml')), $m);
        $kroky = array_map('trim', explode(',', $m[1]));

        // Krok cache dělá i filament:optimize (shim 1.8.1); storage-link a migrate čeká portál.
        $this->assertNotContains('filament', $kroky);
        foreach (['composer', 'migrate', 'cache', 'storage-link'] as $krok) {
            $this->assertContains($krok, $kroky);
        }
    }

    public function test_lokalni_databaze_podle_slozky(): void
    {
        $soubor = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($soubor, "APP_NAME=x\nDB_DATABASE=\nDB_USERNAME=root\n");

        try {
            $this->artisan('simren:lokalni-databaze', ['--soubor' => $soubor, '--bez-pripojeni' => true])->assertSuccessful();
            $jmeno = LokalniDatabaze::jmeno(basename(base_path()));
            $this->assertStringContainsString("DB_DATABASE={$jmeno}\n", file_get_contents($soubor));

            // Vyplněné se nepřepisuje.
            file_put_contents($soubor, "DB_DATABASE=moje\n");
            $this->artisan('simren:lokalni-databaze', ['--soubor' => $soubor, '--bez-pripojeni' => true])->assertSuccessful();
            $this->assertSame("DB_DATABASE=moje\n", file_get_contents($soubor));
        } finally {
            @unlink($soubor);
        }

        $this->assertSame('rootklima_web', LokalniDatabaze::jmeno('RootKlima-web'));
        $this->assertSame('projekt', LokalniDatabaze::jmeno('---'));
    }
}
