<?php

namespace App\Enums;

use App\Models\Nastaveni;

/**
 * V jakém stavu je veřejný web. Přepíná jen superadmin (Provoz → Stav webu).
 *
 * Přihlášený člověk vidí web vždycky celý – klient i vývojáři si ho tak
 * prohlédnou dřív, než ho pustí ven. Administrace, /zdravi a /up běží
 * v každém stavu (middleware je jen na veřejných routách).
 *
 * Převzato z papir-mase (RezimWebu), kde se osvědčilo: přepínač patří
 * superadminovi – kdo spravuje obsah a omylem web vypne, nepozná proč
 * zákazníci přestali chodit.
 */
enum StavWebu: string
{
    case Online = 'online';
    case Pripravujeme = 'pripravujeme';
    case Udrzba = 'udrzba';

    public const KLIC = 'web.stav';

    public static function aktualni(): self
    {
        // Bez databáze (instalace, první migrace) web neschovávat.
        return self::tryFrom((string) rescue(fn () => Nastaveni::hodnota(self::KLIC), null, false)) ?? self::Online;
    }

    public function nazev(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Pripravujeme => 'Připravujeme',
            self::Udrzba => 'Údržba',
        };
    }

    public function popis(): string
    {
        return match ($this) {
            self::Online => 'Web je veřejný a funguje celý.',
            self::Pripravujeme => 'Návštěvníci vidí „Připravujeme“. Pro web, který ještě nebyl spuštěný.',
            self::Udrzba => 'Návštěvníci vidí „Web se právě upravuje“ (HTTP 503 – vyhledávače ho z indexu nevyhodí). Na chvíli, když se něco opravuje.',
        };
    }

    public function barva(): string
    {
        return match ($this) {
            self::Online => 'success',
            self::Pripravujeme => 'warning',
            self::Udrzba => 'danger',
        };
    }

    public function ikona(): string
    {
        return match ($this) {
            self::Online => 'heroicon-o-globe-alt',
            self::Pripravujeme => 'heroicon-o-sparkles',
            self::Udrzba => 'heroicon-o-wrench-screwdriver',
        };
    }
}
