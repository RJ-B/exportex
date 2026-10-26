<?php

namespace Tests\Feature\Fakturace;

use App\Fakturace\Filament\Stranky\Fakturace;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Vypnutý doplněk Fakturace (výchozí stav šablony): aplikace fakturaci nemá – žádný webhook ani stránka. */
class DoplnekFakturaceTest extends TestCase
{
    use RefreshDatabase;

    /** Doplněk se čte při startu aplikace (routy, panel) – proto env před setUp(). */
    public function createApplication()
    {
        putenv('SABLONA_DOPLNEK_FAKTURACE=0');

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        putenv('SABLONA_DOPLNEK_FAKTURACE');
        parent::tearDown();
    }

    public function test_vypnuty_doplnek_nema_webhook_ani_stranku(): void
    {
        $this->assertFalse(config('sablona.doplnky.fakturace'));
        $this->assertFalse(Route::has('fakturace.webhook'));
        $this->post('/webhooky/fakturace', ['event' => 'invoice.paid'])->assertNotFound();
        $this->assertNotContains(Fakturace::class, Filament::getPanel('admin')->getPages());
    }

    public function test_doplnek_je_v_popisu_sablony_a_v_configu_vypnuty(): void
    {
        $text = (string) file_get_contents(base_path('.simren/sablona.yml'));
        $this->assertMatchesRegularExpression('/^doplnky:\s*\[[^\]]*\bfakturace\b[^\]]*\]\s*$/m', $text);

        // CRM ho zapíná změnou výchozí hodnoty (DoplnkyRepa::zapnoutVTextu) – podoba musí sedět.
        $this->assertStringContainsString("'fakturace' => (bool) env('SABLONA_DOPLNEK_FAKTURACE', false),", (string) file_get_contents(config_path('sablona.php')));
    }
}
