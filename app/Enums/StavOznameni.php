<?php

namespace App\Enums;

/** Kde oznámení je: koncept → naplánováno → odesílá se → odesláno. */
enum StavOznameni: string
{
    case Koncept = 'koncept';
    case Naplanovano = 'naplanovano';
    case Odesila = 'odesila';
    case Odeslano = 'odeslano';

    public function nazev(): string
    {
        return match ($this) {
            self::Koncept => __('modely.stav_oznameni.koncept'),
            self::Naplanovano => __('modely.stav_oznameni.naplanovano'),
            self::Odesila => __('modely.stav_oznameni.odesila_se'),
            self::Odeslano => __('modely.stav_oznameni.odeslano'),
        };
    }

    public function barva(): string
    {
        return match ($this) {
            self::Koncept => 'gray',
            self::Naplanovano => 'info',
            self::Odesila => 'warning',
            self::Odeslano => 'success',
        };
    }

    /** Jde ještě upravit? Odeslané už ne – lidé ho mají v centru a v e-mailu. */
    public function upravitelne(): bool
    {
        return in_array($this, [self::Koncept, self::Naplanovano], true);
    }
}
