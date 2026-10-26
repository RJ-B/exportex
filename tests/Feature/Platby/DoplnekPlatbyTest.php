<?php

namespace Tests\Feature\Platby;

use App\Platby\Filament\PlatbaResource;
use App\Platby\Filament\Stranky\PlatebniBrana;
use App\Support\MapaWebu;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Vypnutý doplněk Platby (výchozí stav šablony): aplikace platby nemá a nic z nich nenačítá. */
class DoplnekPlatbyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Vypnutý doplněk i v projektu, který ho má zapnutý: CRM ho zapíná změnou
     * výchozí hodnoty v config/sablona.php, env přebije obojí. Čte se při
     * startu aplikace (routy, migrace, panel) – proto před setUp().
     */
    public function createApplication()
    {
        putenv('SABLONA_DOPLNEK_PLATBY=0');

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        putenv('SABLONA_DOPLNEK_PLATBY');
        parent::tearDown();
    }

    public function test_vypnuty_doplnek_nema_routy_tabulky_administraci_ani_stitek(): void
    {
        $this->assertFalse(config('sablona.doplnky.platby'));

        $this->assertFalse(Route::has('platby.webhook'));
        $this->post('/platby/webhook/moone', ['transactionPublicID' => 'X'])->assertNotFound();
        $this->assertNotContains(database_path('migrations/platby'), app('migrator')->paths(), 'Migrace plateb se bez doplňku nenačítají.');

        $panel = Filament::getPanel('admin');
        $this->assertNotContains(PlatbaResource::class, $panel->getResources());
        $this->assertNotContains(PlatebniBrana::class, $panel->getPages());

        $this->get('/')->assertDontSee('TESTOVACÍ PLATBY');
        $this->assertStringNotContainsString('platby', MapaWebu::robots());
    }

    public function test_doplnek_je_v_popisu_sablony(): void
    {
        $text = (string) file_get_contents(base_path('.simren/sablona.yml'));

        $this->assertMatchesRegularExpression('/^doplnky:\s*\[[^\]]*\bplatby\b[^\]]*\]\s*$/m', $text);
    }
}
