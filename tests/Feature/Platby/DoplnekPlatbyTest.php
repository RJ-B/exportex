<?php

namespace Tests\Feature\Platby;

use App\Platby\Filament\PlatbaResource;
use App\Platby\Filament\Stranky\PlatebniBrana;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Vypnutý doplněk Platby (výchozí stav šablony): aplikace platby nemá a nic z nich nenačítá. */
class DoplnekPlatbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_vypnuty_doplnek_nema_routy_tabulky_administraci_ani_stitek(): void
    {
        $this->assertFalse(config('sablona.doplnky.platby'), 'Šablona má doplněk ve výchozím stavu vypnutý.');

        $this->assertFalse(Route::has('platby.webhook'));
        $this->post('/platby/webhook/moone', ['transactionPublicID' => 'X'])->assertNotFound();
        $this->assertNotContains(database_path('migrations/platby'), app('migrator')->paths(), 'Migrace plateb se bez doplňku nenačítají.');

        $panel = Filament::getPanel('admin');
        $this->assertNotContains(PlatbaResource::class, $panel->getResources());
        $this->assertNotContains(PlatebniBrana::class, $panel->getPages());

        $this->get('/')->assertDontSee('TESTOVACÍ PLATBY');
    }

    public function test_doplnek_je_v_popisu_sablony(): void
    {
        if (! is_file(base_path('.simren/sablona.yml'))) {
            $this->markTestSkipped('Projekt není šablona v katalogu CRM (nemá .simren/sablona.yml).');
        }

        $text = (string) file_get_contents(base_path('.simren/sablona.yml'));

        $this->assertMatchesRegularExpression('/^doplnky:\s*\[[^\]]*\bplatby\b[^\]]*\]\s*$/m', $text);
    }
}
