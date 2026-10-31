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

    /*
     * Logo a ikona pevně ve vzhledu webu (cesta v public/, např.
     * 'assets/img/logo.webp' a 'assets/img/ikona-192.png') – web s vlastním
     * designem, kde je logo součást vzhledu. Administrace (levé menu, přihlášení,
     * ikona v záložce) pak bere tyhle soubory sama, v Hlavičce a patičce se logo
     * nenahrává a krok průvodce „Ikona a logo“ se neukazuje. Ikona je čtverec
     * (PNG/WebP aspoň 192 px); bez ní vznikne z loga. null = logo se nahrává
     * v administraci (App\Support\LogoWebu).
     */
    // Exportex: logo i ikona („e“ z loga) jsou součást vzhledu webu.
    'vzhled' => [
        'logo' => 'assets/img/logo.webp',
        'ikona' => 'assets/img/icon-192.png',
    ],

    /*
     * Další domény webu (stará doména, překlep, jiná koncovka), které se trvale
     * přesměrují na doménu z APP_URL. `www.` se přesměruje vždy samo.
     * Příklad: ['rootklima.eu', 'samsungklima.cz'].
     */
    'dalsi_domeny' => [],

    /*
     * Další databáze, bez kterých web nefunguje (spojení z config/database.php,
     * např. sdílené účty 'identita'). /zdravi je kontroluje stejně jako hlavní:
     * když neodpovídá, vrátí 503 a nasazení se vrátí. Spojení 'stara' (převod
     * dat) sem nepatří – po převodu se z .env smaže.
     */
    'databaze' => [],

    /*
     * Vyhledávání na webu (pro strukturovaná data – Google pak u výsledku ukáže
     * pole hledání). null = web vyhledávání nemá. Příklad:
     * ['routa' => 'hledat', 'parametr' => 'q'] → https://web.cz/hledat?q=…
     */
    'vyhledavani' => null,

    /*
     * Volitelné doplňky šablony (v CRM se volí v okně „Realizace: projekt a web“,
     * popis v .simren/sablona.yml). Zapíná se tady (commit), ne v .env –
     * je to vlastnost projektu. Vypnutý doplněk aplikace nemá: nenačte jeho
     * routy, migrace, administraci ani plánovač. Env jen pro testy.
     *
     *   platby    – platební brána (Comgate, Mo.one), docs/platby.md
     *   fakturace – napojení na fakturace.simren.cz (klíče zapíše portál),
     *               stav napojení a bankovní účet pro doklady, docs/fakturace.md
     */
    'doplnky' => [
        'platby' => (bool) env('SABLONA_DOPLNEK_PLATBY', false),
        'fakturace' => (bool) env('SABLONA_DOPLNEK_FAKTURACE', false),
    ],

    /*
     * Posun plánovaných úloh v hodině (0–59 min, App\Support\Rozvrh). Zapisuje ho
     * portál po serverech, ať údržba všech aplikací serveru nestartuje naráz.
     * Bez hodnoty se spočítá z adresy webu.
     */
    'rozvrh_posun' => env('SIMREN_ROZVRH_POSUN'),
];
