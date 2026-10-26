<?php

namespace App\Support\Jazyky;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;

/**
 * Jazyk aplikace (config/jazyky.php, docs/jazyky.md).
 *
 * Kdo jazyk určí (první, který je zapnutý):
 *   1. přihlášený uživatel (`users.jazyk`, volba v Profilu),
 *   2. prohlížeč (Accept-Language) – jen se zapnutým `jazyky.podle_prohlizece`,
 *   3. výchozí jazyk aplikace (jazyky.vychozi = APP_LOCALE).
 */
class Jazyky
{
    /** @return array<string, string> kód => název jazyka v tom jazyce */
    public static function zapnute(): array
    {
        return (array) config('jazyky.zapnute', [self::vychozi() => self::vychozi()]);
    }

    public static function je(?string $kod): bool
    {
        return $kod !== null && $kod !== '' && array_key_exists($kod, self::zapnute());
    }

    /** Výchozí jazyk (config jazyky.vychozi) – ne app.locale, ten App::setLocale v požadavku přepisuje. */
    public static function vychozi(): string
    {
        return (string) config('jazyky.vychozi', 'cs');
    }

    /** Výběr jazyka má smysl jen při víc zapnutých jazycích. */
    public static function vyber(): bool
    {
        return count(self::zapnute()) > 1;
    }

    public static function urci(Request $request): string
    {
        $uzivatel = $request->user();
        $kandidati = [$uzivatel instanceof User ? $uzivatel->jazyk : null];

        if (config('jazyky.podle_prohlizece') && $request->headers->has('Accept-Language')) {
            $kandidati[] = $request->getPreferredLanguage(array_keys(self::zapnute()));
        }

        foreach ($kandidati as $kod) {
            if (self::je($kod)) {
                return $kod;
            }
        }

        return self::vychozi();
    }

    /** Kód s regionem (og:locale, formáty): cs → cs_CZ, en → en_US. */
    public static function regionalni(?string $kod = null): string
    {
        $kod ??= App::getLocale();

        return match ($kod) {
            'cs' => 'cs_CZ',
            'en' => 'en_US',
            default => str_contains($kod, '_') ? $kod : $kod.'_'.strtoupper($kod),
        };
    }

    /** Jazyk pro texty, data (Carbon) i čísla a měny (Number). */
    public static function nastav(string $kod): void
    {
        App::setLocale($kod);
        Carbon::setLocale($kod);
        Number::useLocale($kod);
    }
}
