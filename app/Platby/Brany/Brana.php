<?php

namespace App\Platby\Brany;

use App\Platby\ChybaBrany;
use App\Platby\MetodaPlatby;
use App\Platby\Platba;
use App\Platby\Rezim;
use Illuminate\Http\Request;

/**
 * Jednotné rozhraní platební brány. Průchod je u všech stejný:
 * založ → přesměruj zákazníka → návrat a webhook → VŽDY ověř stav dotazem
 * (stav()) → změna stavu v Platby::prejdi(). Webhooku ani návratu se nevěří.
 *
 * Nová brána = třída s tímhle rozhraním + řádek v NastaveniPlateb.
 */
interface Brana
{
    /** comgate | moone | simulace */
    public function kod(): string;

    public function nazev(): string;

    public function rezim(): Rezim;

    /** @throws ChybaBrany */
    public function zaloz(Platba $platba): ZalozenaPlatba;

    /** @throws ChybaBrany */
    public function stav(Platba $platba): StavZBrany;

    /**
     * Které metody jde při založení předvolit a brána je teď má (Apple Pay,
     * Google Pay). Nevíme-li (brána neodpovídá), prázdné – aplikace pak
     * nabídne jen kartu a nic se nerozbije.
     *
     * @return list<MetodaPlatby>
     */
    public function predvolby(): array;

    public function umiZrusit(): bool;

    /** @throws ChybaBrany */
    public function zrus(Platba $platba): void;

    public function umiVratit(): bool;

    /**
     * @param  int  $castka  haléře
     *
     * @throws ChybaBrany
     */
    public function vrat(Platba $platba, int $castka): void;

    /**
     * Ověří přístupové údaje bez pohybu peněz (Ověřit spojení v administraci).
     *
     * @throws ChybaBrany
     */
    public function overSpojeni(): string;

    /** Má webhook správné tajemství (u brány, která nějaké posílá)? Obsah se dál ověřuje dotazem. */
    public function webhookPravy(Request $request): bool;

    /**
     * Z webhooku vytáhne, o kterou platbu jde – jen k vyhledání, stavu se nevěří.
     *
     * @return array{externi_id: ?string, verejne_id: ?string, stav: ?string}
     */
    public static function zWebhooku(Request $request): array;
}
