<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Dvojjazyčný text na webu (přepínač CZ / EN v hlavičce, assets/js/main.js).
 *
 * Prvek nese obě podoby v atributech `data-cs` a `data-en`, viditelný obsah je
 * česky; skript při přepnutí prohodí innerHTML. Hodnota atributu je tedy HTML –
 * obyčejný text se escapuje dvakrát (jednou na HTML, podruhé do atributu).
 *
 *   <h2 {{ Preklad::attr($cs, $en) }}>{{ $cs }}</h2>
 */
class Preklad
{
    /** Atributy pro obyčejný text. */
    public static function attr(?string $cs, ?string $en): HtmlString
    {
        return self::html(e((string) $cs), e((string) ($en ?: $cs)));
    }

    /** Atributy pro hotové (už escapované) HTML – nadpis se zvýrazněnou částí apod. */
    public static function html(string $csHtml, string $enHtml): HtmlString
    {
        return new HtmlString('data-cs="'.e($csHtml).'" data-en="'.e($enHtml).'"');
    }

    /** Text se značkou ® jako horním indexem (OEKO-TEX®) – escapované HTML. */
    public static function znacka(?string $text): string
    {
        return str_replace('®', '<sup>®</sup>', e((string) $text));
    }

    /** Telefon do odkazu tel: (+420734479684). */
    public static function tel(?string $telefon): string
    {
        return preg_replace('/[^0-9+]/', '', (string) $telefon);
    }

    /** Telefon pro WhatsApp a Telegram – jen číslice s předvolbou (420734479684). */
    public static function cislice(?string $telefon): string
    {
        return preg_replace('/\D/', '', (string) $telefon);
    }
}
