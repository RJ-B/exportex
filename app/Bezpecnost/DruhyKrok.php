<?php

namespace App\Bezpecnost;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Druhý krok přihlášení: passkey, nebo kód z Authenticatoru (TOTP).
 *
 * Heslo zůstává vždy první – přihlášení jen passkeyem aplikace nemá (trasy
 * balíčku laravel/passkeys jsou vypnuté). Kdo si v Zabezpečení zapne passkey,
 * Authenticator nebo obojí, po hesle dostane výzvu: nejdřív passkey, a když
 * ho zařízení neumí nebo selže, kód z Authenticatoru (nebo obnovovací kód).
 *
 * Passkey patří doméně z APP_URL (a jejím subdoménám) – lokálně jen
 * `localhost`, ne 127.0.0.1. Na jiné adrese se passkey nenabízí a platí kód.
 */
final class DruhyKrok
{
    /** Výzva k přihlášení passkeyem (čeká na odpověď prohlížeče). */
    public const SESSION_PRIHLASENI = 'druhy_krok.prihlaseni';

    /** Výzva k přidání passkeye tohoto zařízení (Zabezpečení). */
    public const SESSION_PRIDANI = 'druhy_krok.pridani';

    /** Kdy naposledy prošlo heslo znovu (správa zařízení v Zabezpečení). */
    public const SESSION_HESLO = 'druhy_krok.heslo_v';

    /** Jak dlouho platí heslo znovu pro správu zařízení. */
    public const HESLO_MINUT = 10;

    /** Podstrčené ověření odpovědi prohlížeče (jen testy – skutečný podpis v testu nevznikne). */
    private static ?Closure $overeni = null;

    /** @param  (Closure(User, string): bool)|null  $overeni */
    public static function fakeOvereni(?Closure $overeni): void
    {
        self::$overeni = $overeni;
    }

    public static function zapnuty(User $user): bool
    {
        return self::maPasskey($user) || self::maAuthenticator($user);
    }

    public static function maPasskey(User $user): bool
    {
        return $user->passkeys()->exists();
    }

    public static function maAuthenticator(User $user): bool
    {
        return filled($user->app_authentication_secret);
    }

    /** Krátký popis pro seznam uživatelů. */
    public static function popis(User $user): string
    {
        $co = array_filter([
            self::maPasskey($user) ? 'passkey' : null,
            self::maAuthenticator($user) ? __('administrace.druhy_krok.authenticator') : null,
        ]);

        return $co === [] ? __('administrace.druhy_krok.jen_heslo') : implode(' + ', $co);
    }

    /** Zruší druhý krok (správce pro člověka, který přišel o telefon i kódy). */
    public static function zrus(User $user): void
    {
        $user->passkeys()->delete();
        $user->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
        ParovaniZarizeni::where('user_id', $user->getKey())->delete();
    }

    /** Jde passkey použít na adrese tohoto požadavku (doména z APP_URL nebo její subdoména)? */
    public static function passkeyNaTetoAdrese(): bool
    {
        $rp = strtolower((string) Passkeys::relyingPartyId());
        $host = strtolower(request()->getHost());

        if ($rp === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $host === $rp || str_ends_with($host, '.'.$rp);
    }

    /**
     * Původ ceremonie = adresa, na které člověk je (subdoména firmy u SaaS),
     * ale jen pod doménou z APP_URL. Schéma z APP_URL – za proxy by request
     * hlásil http.
     */
    public static function povolPuvod(): void
    {
        if (! self::passkeyNaTetoAdrese()) {
            return;
        }

        $schema = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: request()->getScheme();

        config(['passkeys.allowed_origins' => [$schema.'://'.request()->getHttpHost()]]);
    }

    /**
     * Nová výzva k přihlášení passkeyem pro účet, který prošel heslem.
     * Uloží ji do relace a vrací ji ve tvaru pro prohlížeč.
     */
    public static function vyzva(User $user): array
    {
        $moznosti = app(GenerateVerificationOptions::class)($user);
        $prohlizec = WebAuthn::toBrowserArray($moznosti);

        session([self::SESSION_PRIHLASENI => [
            'uzivatel' => $user->getKey(),
            'moznosti' => WebAuthn::toJson($moznosti),
            'prohlizec' => $prohlizec,
        ]]);

        return $prohlizec;
    }

    /** Výzva, která v relaci čeká na tenhle účet (pro prohlížeč), nebo null. */
    public static function cekajiciVyzva(User $user): ?array
    {
        $ulozene = session(self::SESSION_PRIHLASENI);

        return is_array($ulozene) && ($ulozene['uzivatel'] ?? null) === $user->getKey() ? $ulozene['prohlizec'] : null;
    }

    /** Ověří odpověď prohlížeče na výzvu. Výzva platí jen jednou. */
    public static function overPrihlaseni(User $user, string $odpoved): bool
    {
        $ulozene = session()->pull(self::SESSION_PRIHLASENI);

        if (! is_array($ulozene) || ($ulozene['uzivatel'] ?? null) !== $user->getKey() || ! self::passkeyNaTetoAdrese()) {
            return false;
        }

        if (self::$overeni !== null) {
            return (self::$overeni)($user, $odpoved);
        }

        self::povolPuvod();

        try {
            app(VerifyPasskey::class)(
                WebAuthn::fromJson($odpoved, PublicKeyCredential::class),
                WebAuthn::fromJson($ulozene['moznosti'], PublicKeyCredentialRequestOptions::class),
                $user,
            );
        } catch (Throwable $e) {
            Log::warning('Přihlášení passkeyem se nepovedlo', ['uzivatel' => $user->email, 'duvod' => class_basename($e).': '.$e->getMessage()]);

            return false;
        }

        return true;
    }

    /** Heslo znovu prošlo nedávno (správa zařízení). */
    public static function hesloNedavno(): bool
    {
        return now()->timestamp - (int) session(self::SESSION_HESLO, 0) < self::HESLO_MINUT * 60;
    }

    public static function oznacHeslo(): void
    {
        session([self::SESSION_HESLO => now()->timestamp]);
    }

    /** Výzva k registraci passkeye (pro prohlížeč); uloží ji do relace pod klíčem. */
    public static function vyzvaKRegistraci(User $user, string $klic = self::SESSION_PRIDANI): array
    {
        $moznosti = app(GenerateRegistrationOptions::class)($user);

        session([$klic => WebAuthn::toJson($moznosti)]);

        return WebAuthn::toBrowserArray($moznosti);
    }

    /** Uloží nový passkey z odpovědi prohlížeče. Výjimka, když nesedí. */
    public static function uloz(User $user, string $nazev, array $odpoved, string $klic = self::SESSION_PRIDANI): Passkey
    {
        $ulozene = session()->pull($klic);

        abort_if($ulozene === null, 419, __('administrace.druhy_krok.pridani_vyprselo_zkuste_to_znovu'));

        self::povolPuvod();

        return app(StorePasskey::class)(
            $user,
            $nazev,
            WebAuthn::fromJson(json_encode($odpoved) ?: '{}', PublicKeyCredential::class),
            WebAuthn::fromJson($ulozene, PublicKeyCredentialCreationOptions::class),
        );
    }
}
