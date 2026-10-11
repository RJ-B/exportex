<?php

namespace App\Support\Prenos;

use App\Support\Posta\Propojeni;

/**
 * Úklid po importu CELÉ databáze z jiného webu (`simren:prenos po-importu`,
 * shim ho spustí po `data-import` – přenos dat z testu do nové produkce,
 * přesun produkce). Databáze je zdroje, takže i napojení v ní patří zdroji:
 *
 *  - Pošta: klíče zdroje se zapomenou (stejně by nešly použít – jiná adresa
 *    webu, jiný APP_KEY); portál web propojí znovu sám vlastní aplikací v Poště.
 *  - Fakturace: klíče jsou v .env webu (zapisuje je portál), ten se nepřenáší.
 *    Projekt se sekcí Faktury (docs/sablona-sekce-faktury ve fakturaci) sem
 *    přidá úklid zrcadla dokladů: testovací doklady (řada TEST-…) z testu
 *    do produkce nepatří – zůstávají ve fakturaci u testovacího napojení.
 *
 * Bez výjimek ven – co nejde, vrátí v přehledu.
 */
class PoImportu
{
    /** @return array<string, mixed> */
    public function proved(): array
    {
        app(Propojeni::class)->zapomen();

        return ['posta' => 'zapomenuto – portál web propojí znovu'];
    }
}
