<?php

namespace App\Providers\Filament;

use App\Fakturace\FakturaceServiceProvider;
use App\Filament\Auth\Prihlaseni;
use App\Filament\Navigace;
use App\Filament\Pages\MojeOznameni;
use App\Filament\Pages\Prehled;
use App\Filament\Rozlozeni;
use App\Filament\Widgets\StavAplikace;
use App\Http\Middleware\BezpecnostniHlavicky;
use App\Http\Middleware\KanonickaDomena;
use App\Http\Middleware\NastavJazyk;
use App\Platby\PlatbyServiceProvider;
use App\Support\IkonaWebu;
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
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Rozložení (logo v menu, drobečky v liště, bez globálního hledání, mobil),
        // Profil a Zabezpečení s druhým krokem přihlášení – App\Filament\Rozlozeni.
        return Rozlozeni::nastav($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Prihlaseni::class)
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
            // (Uživatelé) a dole za čarou Správa aplikace jen pro superadmina
            // (Logy, Stav webu). Nastavení a Správa aplikace se otevírají zřídka –
            // ve výchozím stavu sbalené.
            ->navigationGroups([
                NavigationGroup::make(fn () => Navigace::obsahWebu()),
                NavigationGroup::make(fn () => Navigace::nastaveni())->collapsed(),
                NavigationGroup::make(fn () => Navigace::spravaAplikace())->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('zobrazit-web')
                    ->label(fn () => __('administrace.navigace.zobrazit_web'))
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->sort(-1)
                    ->visible(fn () => config('sablona.obsah_webu') !== null),
            ])
            // Ikona v záložce z loga webu (IkonaWebu), bez loga výchozí.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => IkonaWebu::html())
            // Vlastní vzhled – Tailwind třídy ve vlastních šablonách nefungují.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.styly'))
            // Vlastní vzhled – Tailwind třídy ve vlastních šablonách nefungují.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.styly'))
            // Výběr místo nativní roletky tam, kde Filament kreslí <select> natvrdo.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => new HtmlString(
                '<link rel="stylesheet" href="'.asset('css/vyber.css').'?v='.@filemtime(public_path('css/vyber.css')).'">'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => new HtmlString(
                '<script src="'.asset('js/vyber.js').'?v='.@filemtime(public_path('js/vyber.js')).'" defer></script>'))
            // Oznámení (docs/oznameni.md): pruh nahoře (odstávky, výpadky) a zvoneček
            // vedle uživatele – stejné jako na webu, „Zobrazit všechna“ vede na Moje oznámení.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('oznameni._zdroje'))
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('oznameni.pruh'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('oznameni.zvonecek', ['vse' => MojeOznameni::getUrl()]))
            ->userMenuItems([
                Action::make('moje-oznameni')
                    ->label(fn () => __('obecne.admin_panel_provider.moje_oznameni'))
                    ->icon(Heroicon::OutlinedBell)
                    ->url(fn () => MojeOznameni::getUrl()),
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Prehled::class,
            ])
            // Doplněk Platby (docs/platby.md): Platby, Nastavení → Platební brána, štítek TESTOVACÍ PLATBY.
            ->when(PlatbyServiceProvider::zapnuto(), fn (Panel $panel) => PlatbyServiceProvider::panel($panel))
            // Doplněk Fakturace (docs/fakturace.md): Nastavení → Fakturace (stav napojení, bankovní účet pro doklady).
            ->when(FakturaceServiceProvider::zapnuto(), fn (Panel $panel) => FakturaceServiceProvider::panel($panel))
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                StavAplikace::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                NastavJazyk::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                KanonickaDomena::class,
                BezpecnostniHlavicky::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
