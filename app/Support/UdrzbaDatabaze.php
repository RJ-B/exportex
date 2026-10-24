<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Denní úklid provozních tabulek, které Laravel sám nečistí: selhané úlohy
 * fronty, dokončené dávky, prošlé odkazy pro obnovu hesla a prošlé řádky
 * databázové cache. Jedna úloha plánovače (jeden proces PHP) místo čtyř.
 * Krok, který selže (chybí tabulka…), ostatní nezastaví.
 */
final class UdrzbaDatabaze
{
    /** Selhané úlohy fronty se drží 30 dní (rozbor chyby), dávky 2 dny. */
    public const SELHANE_HODIN = 720;

    public const DAVKY_HODIN = 48;

    /** @return array<string, int|string> co se uklidilo (nebo proč krok neprošel) */
    public static function spust(): array
    {
        $vysledek = [];

        foreach ([
            'selhane_ulohy' => fn () => Schema::hasTable((string) config('queue.failed.table', 'failed_jobs'))
                ? Artisan::call('queue:prune-failed', ['--hours' => self::SELHANE_HODIN]) : null,
            'davky' => fn () => Schema::hasTable((string) config('queue.batching.table', 'job_batches'))
                ? Artisan::call('queue:prune-batches', ['--hours' => self::DAVKY_HODIN, '--unfinished' => self::DAVKY_HODIN, '--cancelled' => self::DAVKY_HODIN]) : null,
            'obnova_hesla' => fn () => Schema::hasTable((string) config('auth.passwords.users.table', 'password_reset_tokens'))
                ? Artisan::call('auth:clear-resets') : null,
            'cache' => fn () => self::prosleCache(),
        ] as $krok => $akce) {
            try {
                $vysledek[$krok] = $akce() ?? 'bez tabulky';
            } catch (Throwable $e) {
                report($e);
                $vysledek[$krok] = 'chyba: '.mb_substr($e->getMessage(), 0, 200);
            }
        }

        return $vysledek;
    }

    /** Prošlé řádky databázové cache a zámků – ovladač database je sám nemaže, jen nečte. */
    private static function prosleCache(): ?int
    {
        $store = (string) config('cache.default');

        if (config("cache.stores.{$store}.driver") !== 'database') {
            return null;
        }

        $spojeni = DB::connection(config("cache.stores.{$store}.connection"));
        $ted = now()->getTimestamp();
        $smazano = 0;

        foreach ([config("cache.stores.{$store}.table", 'cache'), config("cache.stores.{$store}.lock_table", 'cache_locks')] as $tabulka) {
            if ($tabulka && Schema::connection($spojeni->getName())->hasTable($tabulka)) {
                $smazano += $spojeni->table($tabulka)->where('expiration', '<', $ted)->delete();
            }
        }

        return $smazano;
    }
}
