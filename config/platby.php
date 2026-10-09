<?php

/*
 * Doplněk Platby (docs/platby.md) – zapíná se v config/sablona.php (doplnky.platby).
 * Volba brány a režimu i údaje brány klienta jsou v administraci (Nastavení →
 * Platební brána, šifrovaně), ne tady. Tady jen to, co patří serveru.
 */
return [
    /*
     * Lokálně a v testech místo brány simulace s tlačítky Zaplatit / Zamítnout.
     * Mimo APP_ENV local/testing se nepoužije nikdy.
     */
    'simulace' => (bool) env('PLATBY_SIMULACE', true),

    /*
     * Testovací brána Sim&Ren – Mo.one na testovacím prostředí. Platí přes ni
     * testovací web a produkce v Testovacím režimu; peníze se nestrhnou.
     * Údaje dává do .env portál (nikdy do repa).
     */
    'test' => [
        'moone' => [
            'url' => env('PLATBY_TEST_MOONE_URL', 'https://api-test.znpay.tech'),
            'client_id' => env('PLATBY_TEST_MOONE_CLIENT_ID'),
            'client_secret' => env('PLATBY_TEST_MOONE_CLIENT_SECRET'),
        ],
    ],

    /* Ostré adresy bran (údaje klienta jsou v administraci). */
    'moone' => [
        'url' => env('PLATBY_MOONE_URL', 'https://api.znpay.tech'),
    ],

    'comgate' => [
        'url' => env('PLATBY_COMGATE_URL', 'https://payments.comgate.cz/v2.0'),
    ],

    /* Potvrzení o zaplacení (a vrácení) zákazníkovi e-mailem přes Poštu. */
    'email_zakaznikovi' => true,

    /*
     * Pojistka za webhook: plánovač se ptá brány na platby, které čekají déle
     * než `overovat_po` minut, nejdéle `overovat_nejdele` hodin od založení.
     */
    'overovat_po' => 2,
    'overovat_nejdele' => 48,
];
