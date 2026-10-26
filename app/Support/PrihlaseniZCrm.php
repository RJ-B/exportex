<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Jednorázové přihlášení správce z CRM („Můj účet správce“ u účtu, který už
 * heslo má – simren:spravce). Heslo se nemění a reset se nezakládá; CRM
 * člověka pošle rovnou do administrace přihlášeného.
 *
 * Odkaz je podepsaný (APP_KEY) a platí MINUT minut. Token v něm se nikde
 * neukládá – v cache (sdílená mezi příkazovou řádkou a webem) je jen jeho
 * otisk SHA-256 s id uživatele; prvním použitím se smaže, takže odkaz platí
 * jen jednou. Přihlásí jen superadmina, pro kterého vznikl.
 */
final class PrihlaseniZCrm
{
    public const MINUT = 5;

    public static function odkaz(User $user): string
    {
        $token = Str::random(64);
        Cache::put(self::klic($token), $user->getKey(), now()->addMinutes(self::MINUT));

        return URL::temporarySignedRoute('prihlaseni-z-crm', now()->addMinutes(self::MINUT), ['token' => $token]);
    }

    /** Uživatel z odkazu – jen jednou (otisk se hned smaže), jinak null. */
    public static function prevezmi(string $token): ?User
    {
        if (strlen($token) !== 64) {
            return null;
        }

        $id = Cache::pull(self::klic($token));

        return $id === null ? null : User::find($id);
    }

    private static function klic(string $token): string
    {
        return 'prihlaseni-z-crm:'.hash('sha256', $token);
    }
}
