<?php

namespace App\Support\Prenos;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Hodnoty zašifrované klíčem aplikace (`Crypt`, cast `encrypted`): klíče brány,
 * tokeny napojení, tajemství Authenticatoru… Na webu s jiným `APP_KEY` jsou
 * nečitelné a čtení končí výjimkou (přihlášení s druhým krokem by spadlo).
 *
 * Mimo server se nikdy neposílají čitelně – dešifrovat je pro přenos by
 * znamenalo poslat tajemství přes portál. Proto:
 *  - přenos částí (`simren:prenos export`) je vynechá a výsledek řekne kolik
 *    a odkud (`sifrovane_vynechane`); na cíli zůstane jeho vlastní hodnota,
 *  - po importu celé databáze (`simren:prenos po-importu`) se nečitelné
 *    hodnoty vymažou (NULL) a výsledek je vyjmenuje – údaj se na cíli zadá
 *    znovu (Authenticator, klíče brány). Se stejným APP_KEY (přesun produkce)
 *    jsou čitelné a nic se nemění.
 */
class Sifrovane
{
    /** Obálka Laravel Crypt je base64 JSONu, který začíná {"iv": */
    private const ZACATEK = 'eyJpdiI6';

    /** Tabulky, které po importu vyprázdní shim (fronta, sezení, mezipaměť) – nehledá se v nich. */
    private const PROVOZNI = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public static function je(mixed $hodnota): bool
    {
        if (! is_string($hodnota) || ! str_starts_with($hodnota, self::ZACATEK)) {
            return false;
        }

        $obalka = json_decode((string) base64_decode($hodnota, true), true);

        return is_array($obalka) && isset($obalka['iv'], $obalka['value']) && array_key_exists('mac', $obalka);
    }

    public static function citelne(string $hodnota): bool
    {
        try {
            Crypt::decryptString($hodnota);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Řádek bez šifrovaných hodnot (sloupec úplně pryč – na cíli se tak při
     * aktualizaci nepřepíše a nový řádek dostane výchozí hodnotu).
     *
     * @param  array<string, int>  $vynechane  „tabulka.sloupec“ => počet
     */
    public static function bez(string $tabulka, array $radek, array &$vynechane): array
    {
        foreach ($radek as $sloupec => $hodnota) {
            if (self::je($hodnota)) {
                unset($radek[$sloupec]);
                $vynechane[$tabulka.'.'.$sloupec] = ($vynechane[$tabulka.'.'.$sloupec] ?? 0) + 1;
            }
        }

        return $radek;
    }

    /**
     * Po importu celé databáze z jiného webu: šifrované hodnoty, které tenhle
     * APP_KEY nepřečte, vymaže (jen ve sloupcích, které NULL dovolí).
     *
     * @return array{vymazano: array<string, int>, nevymazano: array<string, int>}
     */
    public static function uklidNecitelnych(): array
    {
        $vysledek = ['vymazano' => [], 'nevymazano' => []];

        foreach (Schema::getTableListing(schemaQualified: false) as $tabulka) {
            if (in_array($tabulka, self::PROVOZNI, true)) {
                continue;
            }

            foreach (Schema::getColumns($tabulka) as $sloupec) {
                $typ = strtolower((string) ($sloupec['type_name'] ?? $sloupec['type'] ?? ''));

                if (! preg_match('/char|text|clob|json/', $typ)) {
                    continue;
                }

                $nazev = $sloupec['name'];

                try {
                    $hodnoty = DB::table($tabulka)->where($nazev, 'like', self::ZACATEK.'%')->distinct()->pluck($nazev);
                } catch (Throwable) {
                    continue;
                }

                foreach ($hodnoty as $hodnota) {
                    if (! self::je($hodnota) || self::citelne($hodnota)) {
                        continue;
                    }

                    $klic = $tabulka.'.'.$nazev;

                    if ($sloupec['nullable'] ?? false) {
                        $pocet = DB::table($tabulka)->where($nazev, $hodnota)->update([$nazev => null]);
                        $vysledek['vymazano'][$klic] = ($vysledek['vymazano'][$klic] ?? 0) + $pocet;
                    } else {
                        $vysledek['nevymazano'][$klic] = ($vysledek['nevymazano'][$klic] ?? 0) + DB::table($tabulka)->where($nazev, $hodnota)->count();
                    }
                }
            }
        }

        return $vysledek;
    }
}
