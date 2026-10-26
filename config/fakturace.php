<?php

/*
 * Doplněk Fakturace (docs/fakturace.md) – zapíná se v config/sablona.php
 * (doplnky.fakturace). Klíče do .env zapisuje portál sám, když v CRM projekt
 * doplněk má: test → testovací napojení (doklady TEST-…, mimo účetnictví),
 * produkce → ostré napojení s vlastními klíči. Nikdo je neopisuje ručně.
 */
return [
    // Adresa fakturace (portál zapisuje bez /api/v1; starší .env s /api/v1 platí taky).
    'url' => rtrim((string) env('FAKTURACE_URL', 'https://fakturace.simren.cz'), '/'),

    'token' => env('FAKTURACE_TOKEN'),

    // Tajemství podpisu webhooků (X-Fakturace-Signature-V2).
    'webhook_tajemstvi' => env('FAKTURACE_WEBHOOK_SECRET'),

    // test | produkce – zapisuje portál s klíči.
    'rezim' => env('FAKTURACE_REZIM'),
];
