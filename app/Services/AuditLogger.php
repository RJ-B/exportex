<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * Centrální service pro zápis audit logu. Volat odkudkoli kontrolery / akce
 * potřebují zaznamenat sledovanou změnu. Selhání nesmí přerušit hlavní operaci —
 * exception je zalogována, nikdy nepropaguje výš.
 */
class AuditLogger
{
    /**
     * Zaznamená libovolnou událost.
     *
     * @param  string  $event  Slug ve tvaru "doména.akce" – např. "user.updated"
     * @param  array<string, mixed>|null  $old  Hodnoty před změnou (jen sledovatelná pole)
     * @param  array<string, mixed>|null  $new  Hodnoty po změně
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        ?string $summary = null,
        ?array $old = null,
        ?array $new = null,
    ): void {
        try {
            $user = Auth::user();
            AuditLog::create([
                'user_id' => $user?->id,
                'user_role' => $user?->role,
                'event' => $event,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'summary' => $summary,
                'old_values' => self::sanitize($old),
                'new_values' => self::sanitize($new),
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
            ]);
        } catch (Throwable $e) {
            Log::error('AuditLogger failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Pro změny nastavení: porovná před a po a vrátí jen pole, která se reálně změnila.
     * Citlivá pole (hesla, secret keys) zamaskuje na "***".
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{old: array<string,mixed>, new: array<string,mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];
        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }
            $old[$key] = $oldValue;
            $new[$key] = $newValue;
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Skryje citlivá pole — hesla, secrety, API klíče a tokeny.
     *
     * Porovnává se `str_contains` na názvu klíče, takže `api_key` pokryje
     * i `sluzba.api_key`. Needly jsou schválně konkrétní: samotné „key" by
     * maskovalo i neškodné klíče jako `keywords`. Nové citlivé nastavení,
     * které se nechytí na žádný z nich, musí přibýt sem.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private static function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        $sensitive = [
            'password', 'secret', 'api_key', 'apikey', 'access_key', 'private_key', 'token',
        ];
        foreach ($values as $key => $value) {
            foreach ($sensitive as $needle) {
                if (str_contains(strtolower((string) $key), $needle) && $value !== null && $value !== '') {
                    $values[$key] = '***';
                }
            }
        }

        return $values;
    }
}
