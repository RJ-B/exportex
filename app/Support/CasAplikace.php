<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Pravidlo času pro celý ekosystém Sim&Ren:
 *
 * - aplikace běží v pásmu `app.timezone` = Europe/Prague a v databázi leží MÍSTNÍ
 *   čas bez pásma (DATETIME/TIMESTAMP),
 * - mezi aplikacemi (API, webhooky, JSON, shim) chodí čas jen jako ISO 8601
 *   s posunem (`toIso8601String()`, „+02:00“ nebo „Z“), nikdy holý,
 * - co přijde zvenku, se před uložením i zobrazením převede do pásma aplikace.
 *
 * Eloquent ukládá datum BEZ převodu pásma (`fromDateTime` jen formátuje), takže
 * „2026-10-08T18:24:07Z“ z GitHubu by se zapsalo jako 18:24, i když u nás bylo
 * 20:24. Pojistka `zapni()` proto převede každé datum vzniklé přes Date (tím jde
 * Eloquent při ukládání, `now()`, `Date::parse()`) do pásma aplikace – okamžik
 * zůstává stejný, mění se jen zápis.
 *
 * Pojistka nepokryje přímé `Carbon::parse()` a `Carbon::createFromTimestamp()`
 * (v Carbonu 3 vrací UTC) při ZOBRAZENÍ – na to je `zVenku()`.
 */
final class CasAplikace
{
    public static function zapni(): void
    {
        Date::useCallable(static fn ($cas) => $cas instanceof CarbonInterface
            ? $cas->setTimezone(self::pasmo())
            : $cas);
    }

    /**
     * Čas zvenku (ISO 8601 s pásmem, holý místní čas, unixový čas jako int) jako
     * Carbon v pásmu aplikace. Prázdné nebo nečitelné = null.
     */
    public static function zVenku(mixed $hodnota): ?Carbon
    {
        if ($hodnota === null || $hodnota === '') {
            return null;
        }

        try {
            $cas = match (true) {
                $hodnota instanceof \DateTimeInterface => Carbon::instance($hodnota),
                is_int($hodnota) => Carbon::createFromTimestamp($hodnota),
                is_string($hodnota) => Carbon::parse($hodnota, self::pasmo()),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }

        return $cas?->setTimezone(self::pasmo());
    }

    private static function pasmo(): string
    {
        return (string) (config('app.timezone') ?: date_default_timezone_get());
    }
}
