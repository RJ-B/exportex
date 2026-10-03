<?php

namespace App\Support;

use App\Models\Nastaveni;

/**
 * Základní údaje (Nastavení → Základní údaje): název, kontakt, provozovatel.
 *
 * Potřebuje je skoro každý projekt – patička, stránka „Připravujeme“, e-maily,
 * doklady – a měnit je má klient sám, ne vývojář v kódu.
 */
class ZakladniUdaje
{
    /** Pole v pořadí formuláře. */
    public const POLE = ['nazev', 'email', 'telefon', 'provozovna', 'firma', 'ico', 'dic', 'adresa', 'rejstrik'];

    /** @return array<string, ?string> */
    public static function nacti(): array
    {
        $udaje = [];

        foreach (self::POLE as $pole) {
            $udaje[$pole] = rescue(fn () => self::cti($pole), null, false);
        }

        $udaje['nazev'] = $udaje['nazev'] ?: config('app.name');

        return $udaje;
    }

    public static function uloz(array $data): void
    {
        foreach (self::POLE as $pole) {
            if (array_key_exists($pole, $data)) {
                $hodnota = trim((string) $data[$pole]);
                self::zapis($pole, $hodnota === '' ? null : $hodnota);
            }
        }
    }

    public static function get(string $pole): ?string
    {
        return self::nacti()[$pole] ?? null;
    }

    // ---- Úložiště. Projekt s vlastním modelem nastavení mění jen tyhle dvě metody. ----

    private static function cti(string $pole): ?string
    {
        return Nastaveni::hodnota('zaklad.'.$pole);
    }

    private static function zapis(string $pole, ?string $hodnota): void
    {
        Nastaveni::nastav('zaklad.'.$pole, $hodnota);
    }
}
