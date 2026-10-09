<?php

namespace App\Enums;

/**
 * Druh oznámení rozhoduje, komu smí jít a kudy (docs/oznameni.md → Pravidla).
 *
 *  - provozní a servisní se týkají účtu nebo služby, kterou člověk používá –
 *    smí každému, v centru je vypnout nejde (je to archiv toho, co se stalo),
 *  - novinky a nabídky jsou obchodní sdělení: e-mailem a pushem jen s výslovným
 *    souhlasem (záznam v oznameni_souhlasy), odhlášení jedním kliknutím.
 *    Nabídky (marketing) jsou navíc ve výchozím stavu vypnuté pro celou
 *    aplikaci (NastaveniOznameni::marketing).
 */
enum DruhOznameni: string
{
    /** Z kódu aplikace: „objednávka odeslána“, „platba přijata“. */
    case Provozni = 'provozni';

    /** Odstávky, výpadky, nové verze, změny podmínek. */
    case Servisni = 'servisni';

    /** Nové funkce a změny v nabídce. */
    case Novinky = 'novinky';

    /** Akce, slevy, nabídky (obchodní sdělení). */
    case Marketing = 'marketing';

    public function nazev(): string
    {
        return match ($this) {
            self::Provozni => 'Provozní',
            self::Servisni => 'Servisní',
            self::Novinky => 'Novinky',
            self::Marketing => 'Nabídky a akce',
        };
    }

    public function popis(): string
    {
        return match ($this) {
            self::Provozni => 'Týká se vašeho účtu nebo objednávky (třeba „objednávka odeslána“).',
            self::Servisni => 'Plánované odstávky, výpadky a důležité změny služby.',
            self::Novinky => 'Nové funkce a změny v nabídce.',
            self::Marketing => 'Akce, slevy a nabídky.',
        };
    }

    /** E-mailem a pushem jen s výslovným souhlasem (obchodní sdělení). */
    public function vyzadujeSouhlas(): bool
    {
        return in_array($this, [self::Novinky, self::Marketing], true);
    }

    /** Kanály, které si uživatel nevypne. */
    public function zamceneKanaly(): array
    {
        return $this->vyzadujeSouhlas() ? [] : [KanalOznameni::Centrum];
    }

    /** Výchozí předvolba, když si uživatel nic nenastavil. Bez souhlasu nikdy ven. */
    public function vychozi(KanalOznameni $kanal): bool
    {
        if ($kanal->vnejsi() && $this->vyzadujeSouhlas()) {
            return false;
        }

        return true;
    }

    /** Smí ho napsat člověk v administraci? Provozní posílá jen aplikace sama. */
    public function zAdministrace(): bool
    {
        return $this !== self::Provozni;
    }

    /** Pruh přes web jen u servisních (odstávka, výpadek) – novinky do pruhu nepatří. */
    public function smiPruh(): bool
    {
        return $this === self::Servisni;
    }

    public function barva(): string
    {
        return match ($this) {
            self::Provozni => 'gray',
            self::Servisni => 'warning',
            self::Novinky => 'info',
            self::Marketing => 'success',
        };
    }
}
