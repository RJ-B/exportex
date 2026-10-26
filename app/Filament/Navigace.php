<?php

namespace App\Filament;

/**
 * Skupiny menu administrace (kostra stejná ve všech projektech). Název se
 * překládá až v požadavku – panel skupinu registruje přes closure
 * (NavigationGroup::make(fn () => Navigace::firma())) a stránka vrací
 * stejný text z getNavigationGroup(), takže se shodují v každém jazyce.
 */
final class Navigace
{
    public static function firma(): string
    {
        return __('administrace.navigace.firma');
    }

    public static function obsahWebu(): string
    {
        return __('administrace.navigace.obsah_webu');
    }

    public static function nastaveni(): string
    {
        return __('administrace.navigace.nastaveni');
    }

    public static function spravaAplikace(): string
    {
        return __('administrace.navigace.sprava_aplikace');
    }
}
