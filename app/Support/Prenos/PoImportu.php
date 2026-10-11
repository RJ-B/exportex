<?php

namespace App\Support\Prenos;

use App\Support\Posta\Odchozi;
use App\Support\Posta\Propojeni;
use Throwable;

/**
 * Úklid po importu CELÉ databáze z jiného webu (`simren:prenos po-importu`,
 * shim ho spustí po `data-import` – přenos dat z testu do nové produkce,
 * přesun produkce). Databáze je zdroje, takže i napojení v ní patří zdroji:
 *
 *  - Pošta: klíče zdroje se zapomenou (stejně by nešly použít – jiná adresa
 *    webu, jiný APP_KEY); portál web propojí znovu sám vlastní aplikací v Poště.
 *    Odchozí fronta zdroje se zahodí – zprávy testu nemají odejít z produkce.
 *  - Šifrované hodnoty, které tenhle APP_KEY nepřečte (klíče brány, Authenticator),
 *    se vymažou a výsledek je vyjmenuje – zadají se znovu (Sifrovane).
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
        $vysledek = [];

        app(Propojeni::class)->zapomen();
        $vysledek['posta'] = 'zapomenuto – portál web propojí znovu';
        $vysledek['posta_fronta'] = rescue(fn () => Odchozi::query()->delete(), 'nešlo smazat', false);

        try {
            $vysledek['sifrovane'] = Sifrovane::uklidNecitelnych();
        } catch (Throwable $e) {
            $vysledek['sifrovane'] = ['chyba' => $e->getMessage()];
        }

        return $vysledek;
    }
}
