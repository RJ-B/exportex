<?php

namespace App\Support;

/**
 * Hesla ze staré aplikace při převodu dat – bez toho, aby je kdokoli znal.
 *
 * Laravel ověřuje bcrypt `$2y$`. Ze starých aplikací chodí:
 * - PHP `password_hash()` (bcrypt `$2y$`) – beze změny,
 * - Spring `DelegatingPasswordEncoder` (`{bcrypt}$2a$…`) – prefix pryč, `$2a$` → `$2y$`,
 * - `$2a$` / `$2b$` odjinud (Node, Python) – jen verze na `$2y$`; digest je pro
 *   stejné heslo stejný, mění se jen označení (Laravel jinak hlásí „This password
 *   does not use the Bcrypt algorithm“),
 * - WordPress (`$P$` phpass, `$wp$2y$` od 6.8), Argon, MD5, `{noop}` – Laravel je
 *   neověří; takový účet dostane null a heslo si nastaví znovu (zapomenuté heslo
 *   nebo odkaz ze simren:spravce). Hash se nikdy nepřevádí na čitelné heslo.
 */
final class PrevzetiHesla
{
    /** Hash, který Laravel ověří, nebo null (heslo je potřeba nastavit znovu). */
    public static function prevezmi(?string $hash): ?string
    {
        $h = trim((string) $hash);

        if (str_starts_with($h, '{bcrypt}')) {
            $h = substr($h, strlen('{bcrypt}'));
        }

        if (preg_match('/\A\$2[aby]\$(0[4-9]|[12]\d|3[01])\$[.\/A-Za-z0-9]{53}\z/', $h) !== 1) {
            return null;
        }

        return '$2y$'.substr($h, 4);
    }

    /** Odkud hash je – do souhrnu převodu (kolik účtů si bude heslo nastavovat znovu). */
    public static function druh(?string $hash): string
    {
        $h = trim((string) $hash);

        return match (true) {
            $h === '' => __('obecne.prevzeti_hesla.zadne'),
            str_starts_with($h, '{bcrypt}') => 'Spring bcrypt',
            str_starts_with($h, '$wp$') || str_starts_with($h, '$P$') || str_starts_with($h, '$H$') => 'WordPress',
            str_starts_with($h, '$argon2') => 'Argon2',
            str_starts_with($h, '$2') => 'bcrypt',
            default => __('obecne.prevzeti_hesla.nezname'),
        };
    }
}
