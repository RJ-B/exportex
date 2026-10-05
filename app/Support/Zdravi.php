<?php

namespace App\Support;

use App\Support\Posta\StavPosty;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Zdraví aplikace pro portál Sim&Ren.
 *
 * Tvrdé kontroly (databáze, cache, zápis do storage) rozhodují, jestli web
 * funguje: /zdravi podle nich vrací 200 nebo 503 a nasazení se při 503
 * vrátí na předchozí verzi. Měkké (plánovač, fronta, chyby v logu, disk)
 * web neshodí – portál je čte přes SSH příkazem `simren:zdravi --json`
 * a hlásí. Podrobnosti proto nikdy nejdou přes HTTP ven.
 *
 * Soubor je samostatný, aby šel do staršího projektu zkopírovat při
 * převodu: tahle třída, příkaz Console/Commands/Zdravi.php, trasa
 * v bootstrap/app.php a značka plánovače v routes/console.php.
 */
final class Zdravi
{
    public const VERZE = 1;

    /** Značka, kterou každou minutu zapisuje plánovač (vůči storage/). */
    public const ZNACKA_PLANOVACE = 'framework/cache/zdravi-planovac';

    /** Z logu se čte jen konec – kvůli chybám za hodinu stačí. */
    private const LOG_KONEC = 2 * 1024 * 1024;

    /** GET /zdravi – jen 200 nebo 503, bez podrobností. */
    public function odpoved(): Response
    {
        $ok = collect(self::tvrde())->every(fn (array $k) => $k['ok']);

        return response($ok ? 'ok' : 'chyba', $ok ? 200 : 503, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Volá plánovač každou minutu. */
    public static function znackaPlanovace(): void
    {
        @file_put_contents(storage_path(self::ZNACKA_PLANOVACE), (string) time());
    }

    /** @return array<string, array{ok: bool, zprava: string}> */
    public static function tvrde(): array
    {
        return [
            'databaze' => self::zkus(function () {
                DB::select('select 1');

                return 'odpovídá';
            }),
            'cache' => self::zkus(function () {
                $klic = 'zdravi:'.Str::random(12);
                Cache::put($klic, 1, 30);
                $precteno = Cache::get($klic);
                Cache::forget($klic);

                if ($precteno !== 1) {
                    throw new \RuntimeException('zapsaná hodnota se nepřečetla');
                }

                return 'zapisuje a čte';
            }),
            'storage' => self::zkus(function () {
                if (@file_put_contents(storage_path('framework/cache/zdravi-zapis'), (string) time()) === false) {
                    throw new \RuntimeException('do storage/framework/cache nejde zapisovat');
                }

                return 'zapisovatelný';
            }),
        ];
    }

    /** Úplná diagnostika pro portál. */
    public static function diagnostika(): array
    {
        $tvrde = self::tvrde();

        return [
            'verze' => self::VERZE,
            'ok' => collect($tvrde)->every(fn (array $k) => $k['ok']),
            'tvrde' => $tvrde,
            'planovac' => self::planovac(),
            'fronta' => self::fronta(),
            'chyby' => self::chyby(),
            'disk' => self::disk(),
            // Pošta (posta.simren.cz): ok=false = pošta neodchází (nepropojeno, Pošta
            // nedostupná, nedoručené nad prahem) – portál otevře incident „Neodchází pošta“.
            'posta' => rescue(fn () => StavPosty::proZdravi(), null, false),
            'prostredi' => app()->environment(),
            'ladeni' => (bool) config('app.debug'),
        ];
    }

    /** @return array{naposledy: ?string, pred_s: ?int} */
    private static function planovac(): array
    {
        $soubor = storage_path(self::ZNACKA_PLANOVACE);
        $cas = is_file($soubor) ? (int) @file_get_contents($soubor) : 0;

        return [
            'naposledy' => $cas > 0 ? Carbon::createFromTimestamp($cas)->toIso8601String() : null,
            'pred_s' => $cas > 0 ? max(0, time() - $cas) : null,
        ];
    }

    /**
     * Fronta a selhané úlohy – ze spojení, kde opravdu leží (v aplikaci
     * s databází na firmu to nebývá výchozí spojení).
     *
     * @return array{cekajici: ?int, nejstarsi_s: ?int, selhane: ?int}
     */
    private static function fronta(): array
    {
        $vysledek = ['cekajici' => null, 'nejstarsi_s' => null, 'selhane' => null];
        $fronta = (array) config('queue.connections.'.config('queue.default'), []);

        try {
            if (($fronta['driver'] ?? null) === 'database') {
                $spojeni = $fronta['connection'] ?? null;
                $tabulka = $fronta['table'] ?? 'jobs';

                if (Schema::connection($spojeni)->hasTable($tabulka)) {
                    $nejstarsi = DB::connection($spojeni)->table($tabulka)->whereNull('reserved_at')->min('available_at');
                    $vysledek['cekajici'] = DB::connection($spojeni)->table($tabulka)->count();
                    $vysledek['nejstarsi_s'] = $nejstarsi ? max(0, time() - (int) $nejstarsi) : null;
                }
            }

            $selhane = config('queue.failed.database');
            $tabulka = config('queue.failed.table', 'failed_jobs');

            if (Schema::connection($selhane)->hasTable($tabulka)) {
                $vysledek['selhane'] = DB::connection($selhane)->table($tabulka)->count();
            }
        } catch (Throwable) {
            // Diagnostika fronty nesmí shodit zbytek – null = nezjištěno.
        }

        return $vysledek;
    }

    /**
     * Chyby v logu za poslední hodinu (ERROR a horší).
     *
     * @return array{za_hodinu: int, posledni: ?array{cas: string, zprava: string}}
     */
    private static function chyby(): array
    {
        $od = now()->subHour();
        $pocet = 0;
        $posledni = null;

        foreach (glob(storage_path('logs/*.log')) ?: [] as $soubor) {
            if (@filemtime($soubor) < $od->getTimestamp()) {
                continue;
            }

            $h = @fopen($soubor, 'r');
            if (! $h) {
                continue;
            }

            $velikost = (int) @filesize($soubor);
            fseek($h, max(0, $velikost - self::LOG_KONEC));
            $text = (string) stream_get_contents($h);
            fclose($h);

            preg_match_all('/^\[(\d{4}-\d\d-\d\d[ T]\d\d:\d\d:\d\d)[^\]]*\] [\w-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $text, $shody, PREG_SET_ORDER);

            foreach ($shody as [, $cas, , $zprava]) {
                $kdy = rescue(fn () => Carbon::parse($cas), null, false);

                if (! $kdy || $kdy->lt($od)) {
                    continue;
                }

                $pocet++;

                if (! $posledni || $kdy->gte(Carbon::parse($posledni['cas']))) {
                    $posledni = ['cas' => $kdy->toIso8601String(), 'zprava' => Str::limit(trim($zprava), 300)];
                }
            }
        }

        return ['za_hodinu' => $pocet, 'posledni' => $posledni];
    }

    /** @return array{volne_procent: ?int} */
    private static function disk(): array
    {
        $volne = @disk_free_space(storage_path());
        $celkem = @disk_total_space(storage_path());

        return ['volne_procent' => $volne && $celkem ? (int) floor($volne / $celkem * 100) : null];
    }

    /** @return array{ok: bool, zprava: string} */
    private static function zkus(callable $kontrola): array
    {
        try {
            return ['ok' => true, 'zprava' => $kontrola()];
        } catch (Throwable $e) {
            return ['ok' => false, 'zprava' => Str::limit($e->getMessage(), 200)];
        }
    }
}
