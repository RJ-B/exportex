<?php

namespace App\Platby;

use App\Platby\Prikazy\OverPlatby;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Doplněk Platby. Se zapnutým `sablona.doplnky.platby` načte routy, migrace,
 * příkaz a plánovač; administraci přidá AdminPanelProvider přes panel().
 * Vypnutý doplněk = nic z toho (aplikace platby nemá).
 */
class PlatbyServiceProvider extends ServiceProvider
{
    public static function zapnuto(): bool
    {
        return (bool) config('sablona.doplnky.platby');
    }

    public function register(): void
    {
        if (self::zapnuto()) {
            $this->app->singleton(Platby::class);
        }
    }

    public function boot(): void
    {
        if (! self::zapnuto()) {
            return;
        }

        $this->loadMigrationsFrom(database_path('migrations/platby'));

        if (! $this->app->routesAreCached()) {
            Route::group([], base_path('routes/platby.php'));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([OverPlatby::class]);
        }

        // Pojistka za webhook – jen když nějaká platba čeká (maCekajici jen čte).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn () => app(Platby::class)->overCekajici())
                ->everyMinute()
                ->name('platby-overeni')
                ->withoutOverlapping(10, false)
                ->when(fn () => Platby::maCekajici());
        });
    }

    /** Administrace plateb do panelu (volá AdminPanelProvider). */
    public static function panel(Panel $panel): Panel
    {
        return $panel
            ->discoverResources(in: app_path('Platby/Filament'), for: 'App\\Platby\\Filament')
            ->discoverPages(in: app_path('Platby/Filament/Stranky'), for: 'App\\Platby\\Filament\\Stranky')
            // Štítek „TESTOVACÍ PLATBY“ vpravo nahoře, dokud brána neběží ostře.
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.platby.stitek'));
    }
}
