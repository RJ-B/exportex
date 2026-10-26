<?php

namespace Tests\Feature;

use App\Models\Nastaveni;
use App\Support\ZakladniUdaje;
use App\Support\Zdravi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Čerstvá instalace nesmí „mít data“.
 *
 * Přenos dat z testu do produkce (CRM → portál → shim `data-import`) pustí
 * jen do databáze, kde mají řádky nejvýš tabulky frameworku a provozních logů.
 * Řádek kdekoli jinde – třeba výchozí údaj firmy z migrace nebo výsledek
 * kontroly pošty z plánovače – a přenos se odmítne.
 */
class CistaInstalaceTest extends TestCase
{
    use RefreshDatabase;

    /** Stejný seznam jako PRENOS_CISTE_TABULKY v portal/deploy/portal-deploy. */
    private const CISTE_TABULKY = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'error_logs', 'audit_logs', 'mail_logs'];

    public function test_po_migracich_planovaci_a_prvnich_navstevach_nema_data(): void
    {
        // Co na nové produkci proběhne bez člověka: plánovač, kontrola zdraví, návštěvy.
        foreach (['posta:fronta', 'posta:obnov-token', 'logs:prune'] as $prikaz) {
            $this->artisan($prikaz);
        }
        Zdravi::znackaPlanovace();
        $this->artisan('simren:zdravi', ['--json' => true]);

        foreach (['/', '/kontakt', '/ochrana-osobnich-udaju', '/zasady-cookies', '/zdravi', '/up', '/admin/login'] as $adresa) {
            $this->get($adresa);
        }

        $plne = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $t) => in_array($t, self::CISTE_TABULKY, true))
            ->filter(fn (string $t) => DB::table($t)->exists())
            ->values()->all();

        $this->assertSame([], $plne, 'čerstvá instalace má data – přenos z testu by se odmítl');
    }

    public function test_vychozi_hodnoty_projektu_z_configu_bez_zapisu(): void
    {
        config(['nastaveni.vychozi' => ['zaklad.nazev' => 'Root Klima', 'zaklad.ico' => '08232288']]);

        $this->assertSame('Root Klima', ZakladniUdaje::get('nazev'));
        $this->assertSame('08232288', ZakladniUdaje::get('ico'));
        $this->assertSame(0, Nastaveni::query()->count());

        // Co správce uloží, platí; vymazané pole zůstane prázdné (nevrátí se výchozí).
        ZakladniUdaje::uloz(['nazev' => 'Root klima s.r.o.', 'ico' => '']);
        $this->assertSame('Root klima s.r.o.', ZakladniUdaje::get('nazev'));
        $this->assertNull(ZakladniUdaje::get('ico'));
    }
}
