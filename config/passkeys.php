<?php

/*
 * Passkeys (WebAuthn) pro druhý krok přihlášení – App\Bezpecnost\DruhyKrok.
 * Trasy balíčku jsou vypnuté (Passkeys::ignoreRoutes v AppServiceProvider):
 * přihlášení bez hesla aplikace nemá – passkey je jen druhý krok po hesle.
 * Vlastní trasy jsou v routes/web.php (ZarizeniController).
 */
return [
    // Passkey patří doméně z APP_URL (a jejím subdoménám). Lokálně musí být
    // APP_URL na localhost – na IP adrese (127.0.0.1) WebAuthn nefunguje.
    // PASSKEYS_RP_ID jen výjimečně (společný rodič víc adres webu).
    'relying_party_id' => env('PASSKEYS_RP_ID', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

    // Původ ceremonie skládá DruhyKrok::povolPuvod() z adresy požadavku
    // (jen pod doménou z APP_URL); tohle je výchozí hodnota.
    'allowed_origins' => [rtrim((string) env('APP_URL', 'http://localhost'), '/')],

    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', env('APP_KEY')),

    'timeout' => 60000,

    'guard' => 'web',

    'middleware' => ['web'],

    'management_middleware' => [],

    'throttle' => 'throttle:6,1',

    'redirect' => '/admin',
];
