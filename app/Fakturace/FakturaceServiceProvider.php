<?php

namespace App\Fakturace;

use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Doplněk Fakturace. Se zapnutým `sablona.doplnky.fakturace` načte webhook
 * (/webhooky/fakturace) a do administrace přidá Nastavení → Fakturace (stav
 * napojení, bankovní účet pro doklady). Vypnutý doplněk = nic z toho.
 *
 * Napojení se nenastavuje ručně: v CRM má projekt doplněk Fakturace a portál
 * zapíše klíče do .env (test → testovací napojení, produkce → ostré).
 */
class FakturaceServiceProvider extends ServiceProvider
{
    public static function zapnuto(): bool
    {
        return (bool) config('sablona.doplnky.fakturace');
    }

    public function register(): void
    {
        if (self::zapnuto()) {
            $this->app->singleton(Fakturace::class);
        }
    }

    public function boot(): void
    {
        if (self::zapnuto() && ! $this->app->routesAreCached()) {
            Route::group([], base_path('routes/fakturace.php'));
        }
    }

    /** Administrace fakturace do panelu (volá AdminPanelProvider). */
    public static function panel(Panel $panel): Panel
    {
        return $panel->discoverPages(in: app_path('Fakturace/Filament/Stranky'), for: 'App\\Fakturace\\Filament\\Stranky');
    }
}
