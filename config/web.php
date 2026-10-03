<?php

/*
|--------------------------------------------------------------------------
| Web Exportex – co se nemění z administrace
|--------------------------------------------------------------------------
|
| Kanonická adresa, popisy pro sdílení a vyhledávače a pevná část
| strukturovaných dat. Firemní údaje jsou v Základních údajích (Obsah webu →
| Hlavička a patička), texty sekcí v App\Support\ObsahWebu – v šablonách nesmí
| být natvrdo žádný telefon, e-mail ani adresa.
|
*/

return [

    // Kanonická adresa (canonical, hreflang, og:url, JSON-LD, sitemap, robots).
    // Bez lomítka na konci; kanonická verze je bez www.
    'url' => 'https://exportex.cz',

    // Výchozí popis pro vyhledávače – přepíše ho Obsah webu → SEO a měření.
    'popis' => 'Textil z Uzbekistánu a Střední Asie do EU: froté, ložní prádlo, úplety, konfekce, tkaniny a příze. Kontrola kvality na místě, nulové clo, doprava.',

    // Popis pro náhled odkazu na sociálních sítích (og:description).
    'og_popis' => 'Sourcing, kontrola kvality, doklady o původu, nulové clo a doprava. Jeden odpovědný partner v Praze — od specifikace po vykládku.',
    'twitter_popis' => 'Sourcing, kontrola kvality, doklady o původu, nulové clo a doprava. Jeden odpovědný partner v Praze.',
    'og_obrazek_popis' => 'Přádelna partnerského provozu ve Střední Asii',

    // Barva lišty prohlížeče ve světlém režimu (výchozí vzhled webu).
    'barva' => '#F4F6FA',

    // Pevná část strukturovaných dat (schema.org). Kontakty a provozovatel jdou
    // ze Základních údajů, sortiment z Obsahu webu → Sortiment.
    'jsonld' => [
        'popis' => 'Český sourcingový a importní partner pro textil z Uzbekistánu a Střední Asie. Výběr a prověření výroby, kontrola kvality, doklady o původu, celní odbavení a doprava do EU.',
        'popis_webu' => 'Sourcing, kontrola kvality, doklady o původu, nulové clo a doprava textilu ze Střední Asie do EU.',
        'nazev_webu' => 'exportex',
        'zalozeno' => '2022-10-24',
        'sluzba' => 'Sourcing a dovoz textilu ze Střední Asie',
        'typ_sluzby' => 'Sourcing, kontrola kvality a dovoz textilu',
        'oblast' => ['Evropská unie', 'Evropa'],
        'zna' => [
            'textil', 'frotté', 'ložní prádlo', 'pletené úplety', 'konfekce', 'tkaniny',
            'bavlněná příze', 'dovoz z Uzbekistánu', 'GSP+', 'OEKO-TEX Standard 100',
        ],
    ],

    'autor' => 'codeing.cz',
    'autor_web' => 'https://codeing.cz',

    // Domény, které se trvale přesměrují na kanonickou adresu (dřív to dělal GitHub Pages).
    'alternativni_domeny' => ['www.exportex.cz'],

];
