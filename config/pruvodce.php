<?php

use App\Support\Pruvodce\Kroky;

/*
 * Průvodce nastavením (Přehled → Dokončete nastavení, App\Support\Pruvodce\Pruvodce):
 * kroky, které klient musí udělat, aby web plně fungoval. Box zmizí sám, když jsou
 * všechny hotové; správce ho jde i skrýt.
 *
 * Každý krok je třída nad App\Support\Pruvodce\Krok (název, krátká vysvětlivka, odkaz
 * a kdy je hotovo). Projekt kroky doplní nebo ubere jen tady – pořadí = pořadí v boxu.
 * Krok, který se projektu netýká (doplněk vypnutý), se skryje sám (`zobrazit()`).
 */
return [
    'kroky' => [
        Kroky\UdajeFirmy::class,
        Kroky\EmailovaSchranka::class,
        Kroky\PravniTexty::class,
        Kroky\IkonaALogo::class,
        Kroky\SeoGoogle::class,
        Kroky\DruhyKrokPrihlaseni::class,
        Kroky\Platby::class,
    ],
];
