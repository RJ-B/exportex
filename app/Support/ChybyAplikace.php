<?php

namespace App\Support;

use App\Models\ErrorLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Chyby aplikace pro portál Sim&Ren (`simren:chyby`, shim `<projekt> chyby`)
 * – převzato ze šablony SimRen-sro/simren-laravel-starter.
 *
 * Portál z každé skupiny chyb (otisk z ErrorLogger) dělá incident
 * a vyřešení jde oběma směry:
 *  - Vyřešeno v portálu → `vyresit()` (kdo a poznámka přijdou stdinem),
 *  - Vrátit v portálu → `vratit()`,
 *  - Vyřešeno / Vrátit zpět v administraci (Logy → Chyby) portál přečte,
 *  - nový výskyt chybu vrátí mezi nevyřešené sám (ErrorLogger) – v portálu
 *    se pak incident znovu otevře.
 *
 * Shim pozná, že aplikace příkaz má, podle tohohle souboru – nepřejmenovávat.
 */
class ChybyAplikace
{
    public const VERZE = 1;

    /** Nejvýš tolik skupin v jednom výpisu (nejnovější výskyt první). */
    public const LIMIT = 200;

    /** Kolik trace se posílá (zbytek je v administraci). */
    public const TRACE = 20000;

    /**
     * Nevyřešené chyby a chyby změněné od `$od` (výskyt, vyřešení, vrácení).
     * Bez `$od` navíc vyřešené za posledních 7 dní.
     *
     * @return array<string, mixed>
     */
    public static function vypis(?int $od = null): array
    {
        $odCas = $od !== null ? Carbon::createFromTimestamp($od) : now()->subDays(7);

        // Změněné = výskyt, vyřešení nebo vrácení (updated_at); tabulka bez něj podle časů výskytu a vyřešení.
        $zmena = Schema::hasColumn('error_logs', 'updated_at') ? ['updated_at'] : ['last_seen_at', 'resolved_at'];

        $chyby = ErrorLog::query()
            ->where(function ($q) use ($zmena, $odCas) {
                $q->whereNull('resolved_at');

                foreach ($zmena as $sloupec) {
                    $q->orWhere($sloupec, '>=', $odCas);
                }
            })
            ->orderByDesc('last_seen_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ErrorLog $e) => static::radek($e))
            ->values()
            ->all();

        return [
            'verze' => self::VERZE,
            'ok' => true,
            'prostredi' => app()->environment(),
            'cas' => now()->toIso8601String(),
            'chyby' => $chyby,
        ];
    }

    /** @return array<string, mixed> */
    public static function vyresit(string $otisk, ?string $kdo, ?string $poznamka): array
    {
        $chyba = static::najdi($otisk);

        if (! $chyba) {
            return ['ok' => false, 'chyba' => 'Chyba s tímhle otiskem v aplikaci není (smazaná úklidem?).'];
        }

        $chyba->forceFill(array_filter([
            'resolved_at' => $chyba->resolved_at ?? now(),
        ]) + static::reseni(filled($kdo) ? mb_substr(trim($kdo), 0, 160) : 'portál', filled($poznamka) ? mb_substr(trim($poznamka), 0, 2000) : null))->save();

        return ['ok' => true, 'otisk' => $otisk, 'vyreseno' => Carbon::parse($chyba->resolved_at)->toIso8601String()];
    }

    /** @return array<string, mixed> */
    public static function vratit(string $otisk): array
    {
        $chyba = static::najdi($otisk);

        if (! $chyba) {
            return ['ok' => false, 'chyba' => 'Chyba s tímhle otiskem v aplikaci není (smazaná úklidem?).'];
        }

        $chyba->forceFill(['resolved_at' => null] + static::reseni(null, null))->save();

        return ['ok' => true, 'otisk' => $otisk, 'vyreseno' => null];
    }

    /**
     * Kdo a poznámka – jen sloupce, které projekt má (migrace chyby_reseni).
     *
     * @return array<string, mixed>
     */
    protected static function reseni(?string $kdo, ?string $poznamka): array
    {
        return array_filter([
            'resolved_by' => Schema::hasColumn('error_logs', 'resolved_by') ? null : false,
            'resolved_by_name' => Schema::hasColumn('error_logs', 'resolved_by_name') ? $kdo : false,
            'resolution_note' => Schema::hasColumn('error_logs', 'resolution_note') ? $poznamka : false,
        ], fn ($v) => $v !== false);
    }

    /** @return array<string, mixed> */
    protected static function radek(ErrorLog $e): array
    {
        $vyreseno = $e->resolved_at !== null;
        $kdo = null;

        if ($vyreseno) {
            $kdo = $e->getAttribute('resolved_by_name');

            if ($kdo === null && $e->getAttribute('resolved_by')) {
                $uzivatel = rescue(fn () => $e->resolvedBy, null, false);
                $kdo = $uzivatel ? (method_exists($uzivatel, 'getFilamentName') ? $uzivatel->getFilamentName() : ($uzivatel->name ?? null)) : null;
            }
        }

        $soubor = $e->getAttribute('file');

        return [
            'otisk' => $e->fingerprint,
            'vyjimka' => $e->exception,
            'zprava' => $e->message,
            'soubor' => $soubor ? str_replace(base_path().'/', '', (string) $soubor) : null,
            'radek' => $e->getAttribute('line'),
            'url' => $e->getAttribute('url'),
            'metoda' => $e->getAttribute('method'),
            'pocet' => (int) ($e->getAttribute('occurrences') ?? 1),
            'poprve' => static::cas($e->getAttribute('first_seen_at') ?? $e->created_at),
            'naposledy' => static::cas($e->getAttribute('last_seen_at') ?? $e->updated_at),
            'vyreseno' => static::cas($e->resolved_at),
            'vyresil' => $kdo,
            'poznamka' => $vyreseno ? $e->getAttribute('resolution_note') : null,
            'trace' => $e->getAttribute('trace') !== null ? mb_substr(str_replace(base_path().'/', '', (string) $e->trace), 0, self::TRACE) : null,
        ];
    }

    protected static function cas(mixed $hodnota): ?string
    {
        return $hodnota ? Carbon::parse($hodnota)->toIso8601String() : null;
    }

    protected static function najdi(string $otisk): ?ErrorLog
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $otisk)) {
            return null;
        }

        return ErrorLog::query()->where('fingerprint', $otisk)->first();
    }
}
