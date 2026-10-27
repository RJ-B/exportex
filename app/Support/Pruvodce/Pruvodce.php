<?php

namespace App\Support\Pruvodce;

use App\Models\Nastaveni;
use Illuminate\Support\Collection;

/**
 * Průvodce nastavením na Přehledu: kroky z config/pruvodce.php a jestli jsou
 * hotové. Box se ukáže správci, dokud není vše hotovo a dokud ho neskryje.
 * Skrytí platí pro celý web (`pruvodce.skryty` v nastavení). Jiná sada kroků
 * (např. pro firmu v SaaS) = potomek s vlastní SADA a úložištěm skrytí.
 */
class Pruvodce
{
    /** Klíč v configu se seznamem tříd kroků. */
    protected const SADA = 'pruvodce.kroky';

    /** @return Collection<int, Krok> kroky, které se projektu týkají */
    public static function kroky(): Collection
    {
        return collect((array) config(static::SADA, []))
            ->map(fn (string $trida) => app($trida))
            ->filter(fn (Krok $k) => $k->zobrazit())
            ->values();
    }

    /** @return Collection<int, array{klic: string, nazev: string, popis: string, url: ?string, hotovo: bool}> */
    public static function stav(): Collection
    {
        return static::kroky()->map(fn (Krok $k) => [
            'klic' => $k->klic(),
            'nazev' => $k->nazev(),
            'popis' => $k->popis(),
            'url' => rescue(fn () => $k->url(), null, report: false),
            // Krok, který nejde zjistit (API nedostupné), není hotový – box nezmizí omylem.
            'hotovo' => rescue(fn () => $k->hotovo(), false, report: false),
        ]);
    }

    public static function hotovo(): bool
    {
        return static::stav()->every(fn (array $k) => $k['hotovo']);
    }

    public static function skryty(): bool
    {
        return Nastaveni::hodnota('pruvodce.skryty') === '1';
    }

    public static function skryj(bool $skryt = true): void
    {
        Nastaveni::nastav('pruvodce.skryty', $skryt ? '1' : null);
    }
}
