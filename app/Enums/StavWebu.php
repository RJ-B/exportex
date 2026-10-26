<?php

namespace App\Enums;

use App\Models\Nastaveni;

/**
 * V jakém stavu je veřejný web. Přepíná jen superadmin (Správa aplikace → Stav webu)
 * a portál Sim&Ren tlačítkem údržby u instalace (`simren:udrzba`, jen Online ↔ Údržba).
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

    /**
     * Čitelný záznam do Aktivity: „Stav webu: Údržba zapnuta“, ne „Změněno:
     * Nastaveni – web.stav“, ze kterého se nepozná, co se s webem stalo.
     */
    public static function popisZmeny(mixed $pred, mixed $po): string
    {
        $novy = self::tryFrom((string) $po) ?? self::Online;
        $stary = self::tryFrom((string) $pred);

        return 'Stav webu: '.match (true) {
            $novy === self::Udrzba => __('modely.stav_webu.udrzba_zapnuta'),
            $stary === self::Udrzba => __('modely.stav_webu.udrzba_vypnuta', ['nazev' => $novy->nazev()]),
            default => $novy->nazev(),
        };
    }

    public function nazev(): string
    {
        return match ($this) {
            self::Online => __('modely.stav_webu.online'),
            self::Pripravujeme => __('modely.stav_webu.pripravujeme'),
            self::Udrzba => __('modely.stav_webu.udrzba'),
        };
    }

    public function popis(): string
    {
        return match ($this) {
            self::Online => __('modely.stav_webu.web_je_verejny_a_funguje_cely'),
            self::Pripravujeme => __('modely.stav_webu.navstevnici_vidi_pripravujeme_pro_web'),
            self::Udrzba => __('modely.stav_webu.navstevnici_vidi_web_se_prave_upravuje'),
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
