<?php

namespace Database\Seeders;

use RuntimeException;

/**
 * Zábrana pro seedery s testovacími daty: mimo `local` a `testing` vyhodí
 * výjimku. Testovací seeder už jednou přepsal produkční data – Laravel se
 * v produkci na `db:seed` jen zeptá a `--force` otázku přeskočí, tohle ne.
 * Ne tiché return: kdo seeder pustí omylem na serveru, musí se to dozvědět.
 *
 * Použití: první řádek run() testovacího seederu je `$this->jenLokalne();`.
 * Referenční data (číselníky, která potřebuje i čerstvá instalace) zábranu
 * nemají – jen ta ale migrace ani seedery do šablony nedávají (CistaInstalaceTest).
 */
trait JenLokalne
{
    protected function jenLokalne(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(static::class.' zakládá testovací data a smí běžet jen lokálně (prostředí '.app()->environment().'). Na serveru nikdy.');
        }
    }
}
