<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Fronta úloh (queue:work) jen když je práce.
 *
 * Dřív měla každá aplikace v cronu vlastní řádek `queue:work --stop-when-empty`
 * – každou minutu start PHP, i když ve frontě nic nebylo, a na sdíleném
 * serveru jich v celou minutu startovaly desítky. Teď worker pouští plánovač
 * (routes/console.php) a jen když `maPraci()`; v cronu zůstává jen
 * schedule:run (manifest: `fronta_spousti: planovac`).
 *
 * Worker běží na pozadí jako samostatný proces s `-d disable_functions=`:
 * HestiaCP zakazuje pcntl_* i v příkazovém řádku a Worker Laravelu se ptá
 * jen extension_loaded('pcntl') – bez toho by spadl na pcntl_async_signals().
 */
class FrontaUloh
{
    /** Worker skončí, když je fronta prázdná, nejpozději po tolika sekundách. */
    public const MAX_SEKUND = 50;

    public const POKUSU = 3;

    /** Čeká ve frontě úloha, kterou už jde zpracovat? */
    public static function maPraci(): bool
    {
        $nazev = (string) config('queue.default');
        $spojeni = (array) config('queue.connections.'.$nazev, []);

        try {
            return match ($spojeni['driver'] ?? null) {
                'sync', 'null', null => false,
                // Jen úlohy, na které došla řada; rezervovaná úloha mrtvého workeru
                // se po retry_after vrátí taky (jinak by v ní fronta uvízla).
                'database' => DB::connection($spojeni['connection'] ?? null)
                    ->table($spojeni['table'] ?? 'jobs')
                    ->whereIn('queue', self::fronty())
                    ->where(fn ($q) => $q
                        ->where(fn ($q) => $q->whereNull('reserved_at')->where('available_at', '<=', now()->getTimestamp()))
                        ->orWhere('reserved_at', '<=', now()->getTimestamp() - (int) ($spojeni['retry_after'] ?? 90)))
                    ->exists(),
                default => collect(self::fronty())->contains(fn (string $f) => Queue::connection($nazev)->size($f) > 0),
            };
        } catch (Throwable) {
            // Nezjištěno (databáze…) – radši pustit worker, ať fronta nestojí potichu.
            return true;
        }
    }

    /** Příkaz workeru pro Schedule::exec() – stejné PHP, jako běží plánovač. */
    public static function prikaz(): string
    {
        return implode(' ', [
            escapeshellarg(PHP_BINARY),
            '-d disable_functions=',
            escapeshellarg(base_path('artisan')),
            'queue:work',
            escapeshellarg((string) config('queue.default')),
            '--queue='.escapeshellarg(implode(',', self::fronty())),
            '--stop-when-empty',
            '--max-time='.self::MAX_SEKUND,
            '--tries='.self::POKUSU,
        ]);
    }

    /** @return list<string> */
    public static function fronty(): array
    {
        $fronta = (string) (config('queue.connections.'.config('queue.default').'.queue') ?: 'default');

        return array_values(array_filter(array_map('trim', explode(',', $fronta))));
    }
}
