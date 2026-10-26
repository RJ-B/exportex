<?php

namespace App\Support;

use App\Models\ErrorLog;

/**
 * Chyby aplikace pro portál Sim&Ren (`simren:chyby`, shim `<projekt> chyby`).
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
 * Nic tajného ven nejde: zprávy chyb už ErrorLogger čistí od e-mailů a čísel
 * jen v otisku, query string URL neukládá; trace je cesta ke kódu.
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
        $odCas = $od !== null ? CasAplikace::zVenku((int) $od) : now()->subDays(7);

        $chyby = ErrorLog::query()
            ->where(fn ($q) => $q->whereNull('resolved_at')->orWhere('updated_at', '>=', $odCas))
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
            return ['ok' => false, 'chyba' => __('obecne.chyby_aplikace.chyba_s_timhle_otiskem_v_aplikaci')];
        }

        $chyba->forceFill([
            'resolved_at' => $chyba->resolved_at ?? now(),
            'resolved_by' => null,
            'resolved_by_name' => filled($kdo) ? mb_substr(trim($kdo), 0, 160) : __('obecne.chyby_aplikace.portal'),
            'resolution_note' => filled($poznamka) ? mb_substr(trim($poznamka), 0, 2000) : null,
        ])->save();

        return ['ok' => true, 'otisk' => $otisk, 'vyreseno' => $chyba->resolved_at->toIso8601String()];
    }

    /** @return array<string, mixed> */
    public static function vratit(string $otisk): array
    {
        $chyba = static::najdi($otisk);

        if (! $chyba) {
            return ['ok' => false, 'chyba' => __('obecne.chyby_aplikace.chyba_s_timhle_otiskem_v_aplikaci')];
        }

        $chyba->forceFill(['resolved_at' => null, 'resolved_by' => null, 'resolved_by_name' => null, 'resolution_note' => null])->save();

        return ['ok' => true, 'otisk' => $otisk, 'vyreseno' => null];
    }

    /** @return array<string, mixed> */
    protected static function radek(ErrorLog $e): array
    {
        return [
            'otisk' => $e->fingerprint,
            'vyjimka' => $e->exception,
            'zprava' => $e->message,
            'soubor' => $e->shortFile(),
            'radek' => $e->line,
            'url' => $e->url,
            'metoda' => $e->method,
            'pocet' => (int) $e->occurrences,
            'poprve' => $e->first_seen_at?->toIso8601String(),
            'naposledy' => $e->last_seen_at?->toIso8601String(),
            'vyreseno' => $e->resolved_at?->toIso8601String(),
            'vyresil' => $e->resolved_at ? $e->resolvedByLabel() : null,
            'poznamka' => $e->resolved_at ? $e->resolution_note : null,
            'trace' => $e->trace !== null ? mb_substr(str_replace(base_path().'/', '', $e->trace), 0, self::TRACE) : null,
        ];
    }

    protected static function najdi(string $otisk): ?ErrorLog
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $otisk)) {
            return null;
        }

        return ErrorLog::query()->where('fingerprint', $otisk)->first();
    }
}
