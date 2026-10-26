<?php

/*
 * Výchozí hodnoty nastavení projektu – údaje firmy, texty webu, stav webu. Platí, dokud je správce v administraci nezmění;
 * do databáze se nic nezapisuje (Nastaveni::hodnota je čte, když řádek není).
 *
 * NIKDY je nedávat do migrace ani seederu (insertOrIgnore do `nastaveni`):
 * čerstvá produkce by pak „měla data“ a portál by odmítl přenos dat z testu.
 * Hesla a klíče sem nepatří – jsou v gitu.
 *
 * Klíče jsou stejné jako v tabulce `nastaveni`:
 *   zaklad.nazev, zaklad.email, zaklad.telefon, zaklad.firma, zaklad.ico, zaklad.adresa…
 *   web.tagline, web.paticka_poznamka, web.formular_prijemce…
 *   gdpr.ucinnost_od, gdpr.formular_udaje…
 *   (pošta: propojení s Poštou se nastavuje v administraci – sem nepatří, jsou to klíče)
 *   web.stav (online | pripravujeme | udrzba)
 */
return [
    'vychozi' => [
        // Údaje exportex s.r.o., se kterými web vznikl (do 10/2026 je doplňovala migrace 2026_10_03_200000_udaje_exportex).
        'zaklad.nazev' => 'Exportex',
        'zaklad.email' => 'mikyska@exportex.cz',
        'zaklad.telefon' => '+420 734 479 684',
        'zaklad.firma' => 'exportex s.r.o.',
        'zaklad.ico' => '17671833',
        'zaklad.adresa' => 'Na Poříčí 1070/19, Nové Město, 110 00 Praha 1',
        'zaklad.rejstrik' => 'Zapsaná u Městského soudu v Praze, oddíl C, vložka 374806',
        'web.tagline' => 'textil z Uzbekistánu a Střední Asie do EU',
        'posta.host' => 'smtp.forpsi.com',
        'posta.port' => '465',
        'posta.sifrovani' => 'smtps',
        'posta.uzivatel' => 'mikyska@exportex.cz',
        'gdpr.ucinnost_od' => '2026-10-03',
        'gdpr.formular_udaje' => 'jméno a příjmení, název firmy, e-mailová adresa, telefonní číslo, pokud jej uvedete, a obsah poptávky',
        'gdpr.formular_doba' => 'po dobu jednání o poptávce a následně nejdéle 3 roky',
        'gdpr.smlouvy' => '1',
        'gdpr.dalsi_prijemci' => "INTERNET CZ, a.s. (Forpsi) – provoz e-mailové schránky a SMTP serveru, přes který odchází upozornění na poptávku z formuláře; údaje jsou zpracovány na serverech v EU\nContabo GmbH – poskytovatel serveru, na kterém web běží; server je umístěn v Německu, tedy v rámci EU\nSimRen s.r.o. – technická správa serveru a webu",
        'gdpr.doplnek' => '<p><strong>Další komunikace:</strong> pokud nám napíšete e-mailem, zavoláte nebo nás kontaktujete přes WhatsApp či Telegram, zpracujeme údaje, které nám sami sdělíte, ke stejnému účelu a na stejném právním základě jako zprávu z formuláře.</p><p><strong>Marketing:</strong> údaje z poptávek nevyužíváme k marketingu, nepředáváme je třetím stranám k jejich vlastním účelům a neprodáváme je. Pokud dojde k obchodu, mohou k nim mít přístup také poskytovatelé účetních služeb v roli zpracovatelů.</p>',

        // Titulek úvodu a popis pro vyhledávače jako do 10/2026 (SEO a měření je přepíše).

        'web.meta_popis' => 'Textil z Uzbekistánu a Střední Asie do EU: froté, ložní prádlo, úplety, konfekce, tkaniny a příze. Kontrola kvality na místě, nulové clo, doprava.',

        'seo.stranky' => '{"uvod": {"titulek": "Exportex — textil z Uzbekistánu a Střední Asie do EU"}}',
    ],
];
