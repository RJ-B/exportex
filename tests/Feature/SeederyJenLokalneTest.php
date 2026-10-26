<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UkazkovaDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Testovací data se nikdy nesmí dostat na server – seeder už jednou přepsal
 * produkční data. `db:seed --force` na serveru nezaloží nic a testovací
 * seeder mimo local/testing spadne dřív, než cokoli zapíše.
 */
class SeederyJenLokalneTest extends TestCase
{
    use RefreshDatabase;

    private const CISTE_TABULKY = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'error_logs', 'audit_logs', 'mail_logs'];

    private function prostredi(string $jmeno): void
    {
        $this->app->detectEnvironment(fn () => $jmeno);
    }

    /** @return list<string> tabulky s řádky (mimo frameworku a logů) */
    private function plneTabulky(): array
    {
        return collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $t) => in_array($t, self::CISTE_TABULKY, true))
            ->filter(fn (string $t) => DB::table($t)->exists())
            ->values()->all();
    }

    public function test_vychozi_seeder_na_serveru_nezalozi_nic(): void
    {
        $this->prostredi('production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame([], $this->plneTabulky());
    }

    public function test_testovaci_seeder_na_serveru_spadne_a_nic_nezmeni(): void
    {
        foreach (['production', 'staging'] as $prostredi) {
            $this->prostredi($prostredi);

            try {
                // Jak by to omylem pustil člověk na serveru – i s --force.
                $this->artisan('db:seed', ['--class' => UkazkovaDataSeeder::class, '--force' => true])->run();
                $this->fail("Testovací seeder proběhl v prostředí {$prostredi}.");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('jen lokálně', $e->getMessage());
            }

            $this->assertSame([], $this->plneTabulky());
        }
    }

    public function test_lokalne_zalozi_ukazkova_data_a_jde_pustit_znovu(): void
    {
        $this->prostredi('local');

        $this->seed(UkazkovaDataSeeder::class);
        $this->seed(UkazkovaDataSeeder::class);

        $this->assertSame(2, DB::table('zpravy')->count());
        $this->assertSame(1, DB::table('zpravy')->whereNull('precteno_at')->count());
        $this->assertSame(2, DB::table('users')->count());
        // Oznámení: odeslané provozní, pruh s odstávkou a koncept – podruhé nic navíc (klíče).
        $this->assertSame(3, DB::table('oznameni')->count());
        $this->assertSame(1, DB::table('oznameni_skupiny')->count());
    }

    public function test_vsechny_seedery_mimo_vychoziho_maji_zabranu(): void
    {
        // Nový seeder s testovacími daty musí mít JenLokalne – jinak ho tenhle test chytí.
        foreach (File::files(database_path('seeders')) as $soubor) {
            $trida = 'Database\\Seeders\\'.$soubor->getFilenameWithoutExtension();

            if (! class_exists($trida) || in_array($trida, [DatabaseSeeder::class], true) || ! is_subclass_of($trida, \Illuminate\Database\Seeder::class)) {
                continue;
            }

            $this->assertContains(\Database\Seeders\JenLokalne::class, class_uses_recursive($trida), "{$trida} nemá zábranu JenLokalne.");
        }

        // Výchozí seeder testovací seedery nevolá (db:seed --force na serveru).
        $this->assertDoesNotMatchRegularExpression('/->call\(/', (string) file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }
}
