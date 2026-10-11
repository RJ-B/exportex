<?php

/*
 * Co jde přenést z jednoho webu do druhého (test → produkce) tlačítkem v portálu
 * Přenést data z testu. App\Support\Prenos – popis režimů a pravidel tam.
 *
 * Projekt sem přidá své tabulky (obsah webu, katalog, články…) jako další část:
 *
 *   'obsah' => [
 *       'nazev' => 'Obsah webu',
 *       'popis' => 'Služby, reference a jejich fotky',
 *       'tabulky' => [                                    // rodiče před dětmi
 *           'reference' => ['klic' => ['slug']],
 *           'reference_fotky' => ['klic' => ['reference_id', 'cesta'], 'vazby' => ['reference_id' => 'reference']],
 *       ],
 *   ],
 *
 * klic   – podle čeho se řádek pozná na druhém webu (ne id – to se liší); víc možností
 *          za sebou [['puvodni_id'], ['nazev']] – platí první, kterou má zdroj vyplněnou,
 * pk     – primární klíč (výchozí 'id'; null, když je klíčem sám `klic`),
 * vazby  – sloupec => tabulka, na jejíž id odkazuje (přečísluje se),
 * jen / krome – filtr řádků: sloupec => vzory jako v LIKE (%),
 * vychozi – zaškrtnuté v portálu, citlive – portál před přenosem varuje,
 * rezimy – povolené režimy (výchozí doplnit, aktualizovat, nahradit).
 *
 * Fotky a soubory, na které přenášené řádky odkazují, jdou s nimi samy.
 * Nikdy se nepřenáší provozní tabulky (logy, fronta, sezení) ani klíče Pošty
 * a platební brány v nastavení (Casti::NIKDY_*).
 */
return [
    'casti' => [
        'udaje' => [
            'nazev' => 'Údaje firmy',
            'popis' => 'Název, kontakty, IČO, adresa a údaje pro ochranu osobních údajů',
            'tabulky' => [
                'nastaveni' => ['klic' => ['klic'], 'pk' => null, 'jen' => ['klic' => ['zaklad.%', 'gdpr.%']]],
            ],
        ],
        'nastaveni' => [
            'nazev' => 'Nastavení webu',
            'popis' => 'Hlavička a patička, zapnuté sekce, SEO a měření',
            'tabulky' => [
                'nastaveni' => ['klic' => ['klic'], 'pk' => null, 'krome' => ['klic' => ['zaklad.%', 'gdpr.%', 'logy.%', 'oznameni.%', 'obsah.%']]],
            ],
        ],
        // Exportex: texty a seznamy sekcí (Obsah webu v administraci) – každá sekce
        // je jeden klíč obsah.<sekce> (JSON v obou jazycích), nahrané fotky jdou s ním.
        'obsah' => [
            'nazev' => 'Obsah webu',
            'popis' => 'Texty a seznamy sekcí v obou jazycích (úvod, sortiment, jak to funguje, trasa, doklady, o nás, ukázky zakázek, kontakt) a jejich fotky',
            'tabulky' => [
                'nastaveni' => ['klic' => ['klic'], 'pk' => null, 'jen' => ['klic' => ['obsah.%']]],
            ],
        ],
        'uzivatele' => [
            'nazev' => 'Uživatelé a hesla',
            'popis' => 'Účty z testu i s hesly – obvykle se do produkce nepřenáší',
            'vychozi' => false,
            'citlive' => true,
            'rezimy' => ['doplnit', 'aktualizovat'],
            'tabulky' => [
                'users' => ['klic' => ['email']],
            ],
        ],
    ],
];
