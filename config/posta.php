<?php

/*
 * Pošta – společné odesílání e-mailů Sim&Ren (posta.simren.cz).
 *
 * Aplikace neposílá přes vlastní SMTP: předá zprávu Poště (mail transport
 * `posta`, App\Support\Posta). Propojení (token, tajemství webhooků, přidělené
 * adresy) zařídí portál (posta:z-portalu), nebo tlačítko Propojit s poštou
 * v administraci; ukládá se šifrovaně v `nastaveni`. .env jen jako záloha.
 */
return [
    // Adresa Pošty – výchozí pro tlačítko Propojit s poštou (jde změnit v okně).
    'url' => env('POSTA_URL', 'https://posta.simren.cz'),

    /*
     * Propojení z .env – platí jen, když v nastavení žádné není. Weby z portálu
     * dostanou klíče do nastavení samy (posta:z-portalu); .env je záloha pro
     * ruční nasazení. Token a tajemství jen do .env serveru, nikdy do gitu.
     * POSTA_OD = odesílatel (musí být přidělený v Poště; bez něj platí první přidělená).
     */
    'token' => env('POSTA_TOKEN'),
    'webhook_tajemstvi' => env('POSTA_WEBHOOK_TAJEMSTVI'),
    'od' => env('POSTA_OD'),

    // Jak dlouho čekat na odpověď API, než se zpráva uloží do odchozí fronty.
    'timeout' => 10,

    /*
     * Odchozí fronta: když Pošta neodpovídá, zpráva se uloží lokálně
     * (posta_odchozi) a posta:fronta ji zkouší znovu se stejným
     * Idempotency-Key – Pošta ji tak nikdy nepošle dvakrát.
     */
    'fronta' => [
        'opakovani_minut' => [1, 2, 5, 10, 30, 60],
        'vzdat_po_hodinach' => 72,
    ],

    /*
     * Zdraví pro portál (simren:zdravi --json → posta): kdy hlásit, že pošta
     * neodchází – Pošta nedostupná déle než tolik minut, nebo nedoručených
     * zpráv za 24 hodin aspoň tolik.
     */
    'nedostupna_minut' => 30,
    'prah_nedorucenych' => 5,

    // Stav zprávy se z Pošty dotáže, když webhook nepřišel do tolika minut.
    'dotaz_na_stav_po_minutach' => 15,
];
