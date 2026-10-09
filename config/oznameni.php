<?php

/*
 * Oznámení (docs/oznameni.md). Výchozí hodnoty – co jde měnit bez nasazení
 * (marketing, oznámení z portálu koncovým uživatelům), přepíná superadmin
 * v Oznámení → Nastavení a uloží se do tabulky `nastaveni`.
 */
return [
    /*
     * Nabídky a akce (marketing) – obchodní sdělení. Ve výchozím stavu VYPNUTÉ:
     * zapnout až po rozhodnutí, že je klient bude posílat (a s jakým souhlasem).
     */
    'marketing' => false,

    /*
     * Oznámení z portálu Sim&Ren (nové verze, odstávky, výpadky) – komu.
     * false = jen správcům aplikace (admin), true = i koncovým uživatelům.
     * Příjem z portálu je krok 2 – zatím jen připravené nastavení.
     */
    'portal_koncovym' => false,

    'limity' => [
        // Od kolika příjemců se počet při odeslání musí opsat (potvrzení počtu).
        'potvrzeni_od' => 25,

        // Víc příjemců jedno oznámení z administrace mít nesmí (víc = domluvit se Sim&Ren).
        'max_prijemcu' => 5000,

        // Kolik e-mailů z jednoho oznámení odejde za minutu (fronta, po dávkách).
        'emailu_za_minutu' => 60,

        // Do kolika příjemců se centrum naplní hned při odeslání; víc = ve frontě.
        'hned_do' => 300,
    ],

    // Kolik posledních oznámení ukáže zvoneček (zbytek na stránce Oznámení).
    'zvonecek_pocet' => 8,
];
