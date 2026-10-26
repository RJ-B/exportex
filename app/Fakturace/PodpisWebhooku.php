<?php

namespace App\Fakturace;

/**
 * Podpis webhooku fakturace s časem (V2), hlavička X-Fakturace-Signature-V2:
 *
 *   t=<unix>,v1=<hex>[,v1=<hex>]   hex = HMAC-SHA256(tajemství, "<t>.<tělo>")
 *
 * Starší než 5 minut se odmítá – zachycenou zprávu nejde poslat znovu. Při
 * výměně tajemství přijde podpisů víc; stačí, když sedí jeden. Starý podpis
 * bez času se schválně nepřijímá.
 */
final class PodpisWebhooku
{
    public const MAX_STARI = 300;

    public static function sedi(string $telo, string $hlavicka, string $tajemstvi): bool
    {
        if ($tajemstvi === '') {
            return false;
        }

        $cas = null;
        $podpisy = [];

        foreach (explode(',', $hlavicka) as $cast) {
            [$klic, $hodnota] = array_pad(explode('=', trim($cast), 2), 2, '');

            if ($klic === 't' && ctype_digit($hodnota)) {
                $cas = (int) $hodnota;
            } elseif ($klic === 'v1') {
                $podpisy[] = $hodnota;
            }
        }

        if ($cas === null || $podpisy === [] || abs(time() - $cas) > self::MAX_STARI) {
            return false;
        }

        $ocekavany = hash_hmac('sha256', $cas.'.'.$telo, $tajemstvi);

        foreach ($podpisy as $podpis) {
            if (hash_equals($ocekavany, $podpis)) {
                return true;
            }
        }

        return false;
    }

    /** Hlavička pro testy. */
    public static function podepis(string $telo, string $tajemstvi, ?int $cas = null): string
    {
        $cas ??= time();

        return 't='.$cas.',v1='.hash_hmac('sha256', $cas.'.'.$telo, $tajemstvi);
    }
}
