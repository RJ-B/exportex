<?php

namespace App\Filament;

use App\Filament\Auth\PasskeyDruhyKrok;
use App\Filament\Auth\Profil;
use App\Filament\Pages\Zabezpeceni;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

/**
 * Jednotné rozložení administrace Sim&Ren (README → Rozložení administrace):
 *
 *  - levé menu: nahoře logo a název aplikace, navigace, dole oddělená
 *    „Správa aplikace“ (jen superadmin – vidí ji, jen když v ní něco smí),
 *  - horní lišta: vlevo drobečková navigace, vpravo (firma →) zvoneček →
 *    avatar s uživatelským menu (Profil, Zabezpečení, Odhlásit),
 *  - bez globálního vyhledávání (hledá se v tabulkách),
 *  - mobil: vlevo logo, vpravo zvoneček a ☰ úplně vpravo; menu vyjede zprava
 *    a nahoře má účet (jméno, Profil, Zabezpečení, Odhlásit).
 *
 * K tomu druhý krok přihlášení: passkey, nebo kód z Authenticatoru
 * (App\Bezpecnost\DruhyKrok), spravuje se v Zabezpečení.
 *
 * Vzhled je v public/css/rozlozeni.css, drobečky skládá public/js/rozlozeni.js.
 */
final class Rozlozeni
{
    /** Starší název skupiny (česky) – v kódu Navigace::spravaAplikace(). */
    public const SPRAVA_APLIKACE = 'Správa aplikace'; // bez překladu

    public static function nastav(Panel $panel): Panel
    {
        return $panel
            ->globalSearch(false)
            ->brandLogo(fn () => view('filament.rozlozeni.znacka'))
            ->brandLogoHeight('2rem')
            ->profile(Profil::class, isSimple: false)
            ->multiFactorAuthentication([
                // Pořadí = pořadí ve výzvě: nejdřív passkey, kód jako záloha.
                PasskeyDruhyKrok::make(),
                AppAuthentication::make()->recoverable(),
            ])
            ->userMenuItems([
                // Nahoře jméno (hlavička bez odkazu), pod ním Profil, Zabezpečení, … Odhlásit.
                'profile' => fn (Action $action) => $action
                    ->label(fn () => filament()->getUserName(filament()->auth()->user()))
                    ->url(null),
                Action::make('profil')
                    ->label(fn () => __('administrace.rozlozeni.profil'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn () => filament()->getProfileUrl()),
                Action::make('zabezpeceni')
                    ->label(fn () => __('administrace.rozlozeni.zabezpeceni'))
                    ->icon(Heroicon::OutlinedFingerPrint)
                    ->url(fn () => Zabezpeceni::getUrl()),
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => self::css('css/rozlozeni.css'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => self::js('js/rozlozeni.js'))
            // Texty pro public/js (datum.js, passkeys.js) v jazyce stránky – před skripty.
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('jazyky.texty-js'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => self::js('js/passkeys.js'))
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn () => new HtmlString('<nav class="simren-drobecky" aria-label="'.e(__('administrace.rozlozeni.drobeckova_navigace')).'" wire:ignore></nav>'))
            ->renderHook(PanelsRenderHook::SIDEBAR_START, fn () => view('filament.rozlozeni.ucet-mobil'));
    }

    private static function css(string $soubor): HtmlString
    {
        return new HtmlString('<link rel="stylesheet" href="'.asset($soubor).'?v='.@filemtime(public_path($soubor)).'">');
    }

    private static function js(string $soubor): HtmlString
    {
        return new HtmlString('<script src="'.asset($soubor).'?v='.@filemtime(public_path($soubor)).'" defer></script>');
    }
}
