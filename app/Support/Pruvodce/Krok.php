<?php

namespace App\Support\Pruvodce;

/**
 * Jeden krok průvodce nastavením (config/pruvodce.php). Texty jen přes __(),
 * vysvětlivka stručně – jedna věta, co a proč. `hotovo()` se ptá při každém
 * zobrazení Přehledu, ať je levné (žádné pomalé volání bez cache).
 */
abstract class Krok
{
    abstract public function nazev(): string;

    abstract public function popis(): string;

    /** Kam klient klikne, aby krok udělal. */
    abstract public function url(): ?string;

    abstract public function hotovo(): bool;

    /** Týká se krok tohoto projektu (doplněk zapnutý, uživatel na stránku smí…)? */
    public function zobrazit(): bool
    {
        return true;
    }

    public function klic(): string
    {
        return class_basename(static::class);
    }
}
