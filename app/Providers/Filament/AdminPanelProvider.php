<?php

namespace App\Providers\Filament;

use App\Filament\Pages\MojeOznameni;
use App\Filament\Pages\Prehled;
use App\Filament\Widgets\StavAplikace;
use App\Http\Middleware\BezpecnostniHlavicky;
use App\Platby\PlatbyServiceProvider;
use App\Support\ZakladniUdaje;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Název ze Základních údajů – klient si ho nastaví sám, není to APP_NAME.
            ->brandName(fn () => ZakladniUdaje::get('nazev'))
            // Nastavení hesla odkazem – účty správců vznikají bez hesla (simren:spravce).
            ->passwordReset()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // Bez toho spadne stránka clusterované sekce (Logy) na „::class on null“.
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            // Kostra menu stejná ve všech projektech: Přehled a Zobrazit web,
            // sekce projektu, s nimiž se pracuje denně (poptávky, rezervace…;
            // jejich skupiny přidej PŘED 'Obsah webu'), Obsah webu (co se nastaví
            // jednou – podoba podle config sablona.obsah_webu), Nastavení
            // (Uživatelé) a Provoz jen pro superadmina (Logy, Stav webu).
            // Nastavení a Provoz se otevírají zřídka – ve výchozím stavu sbalené.
            ->navigationGroups([
                NavigationGroup::make('Obsah webu'),
                NavigationGroup::make('Nastavení')->collapsed(),
                NavigationGroup::make('Provoz')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Zobrazit web')
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->sort(-1)
                    ->visible(fn () => config('sablona.obsah_webu') !== null),
            ])
            // Vlastní vzhled – Tailwind třídy ve vlastních šablonách nefungují.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.styly'))
            // Oznámení (docs/oznameni.md): pruh nahoře (odstávky, výpadky) a zvoneček
            // vedle uživatele – stejné jako na webu, „Zobrazit všechna“ vede na Moje oznámení.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('oznameni._zdroje'))
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('oznameni.pruh'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('oznameni.zvonecek', ['vse' => MojeOznameni::getUrl()]))
            ->userMenuItems([
                Action::make('moje-oznameni')
                    ->label('Moje oznámení')
                    ->icon(Heroicon::OutlinedBell)
                    ->url(fn () => MojeOznameni::getUrl()),
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Prehled::class,
            ])
            // Doplněk Platby (docs/platby.md): Platby, Nastavení → Platební brána, štítek TESTOVACÍ PLATBY.
            ->when(PlatbyServiceProvider::zapnuto(), fn (Panel $panel) => PlatbyServiceProvider::panel($panel))
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                StavAplikace::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                BezpecnostniHlavicky::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
