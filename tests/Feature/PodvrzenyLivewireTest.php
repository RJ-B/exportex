<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Tests\TestCase;

/**
 * Sken z internetu na Livewire (podvržené zamčené vlastnosti, nesmyslná data)
 * je 400 bez zápisu do Logy → Chyby i do laravel.log – obrana Livewire
 * funguje, chyba to není (PodvrzenyLivewire).
 */
class PodvrzenyLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);

        Route::post('/livewire-test/update', fn () => throw new CannotUpdateLockedPropertyException('userUndertakingMultiFactorAuthentication'));
        Route::post('/livewire-test2/update', fn () => $this->hydratePropertyUpdate());
        Route::post('/livewire-test3/update', fn () => throw new \RuntimeException('Košík nenalezen'));
        Route::post('/formular', fn () => throw new CannotUpdateLockedPropertyException('otevrenoV'));
    }

    /** Jméno jako v Livewire (HandleSynths) – ať trace vypadá jako převod hodnoty od klienta. */
    public function hydratePropertyUpdate(): never
    {
        throw new \TypeError('Filament\Notifications\Collection::{closure}(): Argument #1 ($notification) must be of type array, int given');
    }

    public function test_podvrzeny_pozadavek_je_400_bez_zapisu(): void
    {
        Log::spy();

        $this->post('/livewire-test/update')->assertStatus(400);
        $this->post('/livewire-test2/update')->assertStatus(400);

        $this->assertSame(0, ErrorLog::count());
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('log');
    }

    public function test_zamek_mimo_livewire_a_chyba_komponenty_se_hlasi_dal(): void
    {
        $this->post('/formular')->assertStatus(419); // Livewire sám vrací 419, ale zapíše se
        $this->post('/livewire-test3/update')->assertStatus(500); // běžná chyba v komponentě

        $this->assertEqualsCanonicalizing(
            [CannotUpdateLockedPropertyException::class, \RuntimeException::class],
            ErrorLog::pluck('exception')->all(),
        );
    }
}
