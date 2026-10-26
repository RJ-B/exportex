<?php

/*
 * Jazyky aplikace (docs/jazyky.md). Texty jsou v lang/<kód>/*.php, v kódu jen
 * klíče: __('sprava.udaje_firmy.nazev_firmy'). Výchozí jazyk je APP_LOCALE
 * a chybějící překlad se ukáže v záložním (app.fallback_locale, APP_FALLBACK_LOCALE).
 *
 * Nový jazyk: zkopírovat lang/en do lang/<kód>, přeložit a přidat sem řádek.
 * `php artisan jazyky:kontrola` ukáže, co v něm chybí.
 */
return [
    // Výchozí jazyk (= app.locale z .env; app.locale se v požadavku přepíná, tohle ne).
    'vychozi' => env('APP_LOCALE', 'cs'),

    // Kód => název jazyka v tom jazyce (ukazuje se ve výběru jazyka).
    'zapnute' => [
        'cs' => 'Čeština',
        // 'en' => 'English',   // připraveno: texty jsou v lang/en, zapnout odkomentováním
    ],

    // Jazyk podle prohlížeče (Accept-Language), když si ho přihlášený nevybral.
    // Výchozí vypnuto = výchozí jazyk aplikace (web se nemění podle návštěvníka).
    'podle_prohlizece' => (bool) env('JAZYK_PODLE_PROHLIZECE', false),
];
