<?php

/*
 * Podoba administrace podle šablony Sim&Ren. Nastavuje se v projektu (commit),
 * ne v .env – je to vlastnost projektu, ne serveru.
 */
return [
    /*
     * Obsah webu (Hlavička a patička, Kontakt a formulář, SEO a měření a stránky webu):
     *   'menu'  – jednoduchý web (prezentace): sekce přímo v menu pod nadpisem Obsah webu
     *   'sekce' – web s provozem (poptávky, rezervace, objednávky): jedna položka
     *             Obsah webu, uvnitř vodorovná lišta se sekcemi
     *   null    – aplikace bez webu: Obsah webu není, Pošta je v Nastavení
     *
     * V menu samostatně je jen to, s čím se pracuje denně; v Obsahu webu to,
     * co se nastaví jednou a mění se zřídka.
     */
    'obsah_webu' => match (env('SABLONA_OBSAH_WEBU')) {   // env jen pro testy podob
        'sekce' => 'sekce',
        'aplikace' => null,
        default => 'menu',
    },

    /*
     * Veřejná stránka s kontaktním formulářem (název routy). Když ji web má,
     * je Pošta v Obsahu webu jako „Kontakt a formulář“; bez ní v Nastavení.
     */
    'routa_kontaktu' => 'kontakt',

    /*
     * Kam po nastavení hesla z CRM (Můj účet správce) – stránka se rovnou
     * přihlásí a pošle sem. null = administrace (Filament).
     */
    'po_nastaveni_hesla' => null,
];
