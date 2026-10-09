<?php

namespace App\Enums;

/** Jak vážné oznámení je – barva pruhu a značky v centru. */
enum ZavaznostOznameni: string
{
    case Info = 'info';
    case Varovani = 'varovani';
    case Kriticke = 'kriticke';
    /** Výpadek pominul – zelený pruh, ať lidé vědí, že už to jde. */
    case Vyreseno = 'vyreseno';

    public function nazev(): string
    {
        return match ($this) {
            self::Info => 'Informace',
            self::Varovani => 'Upozornění',
            self::Kriticke => 'Vážné',
            self::Vyreseno => 'Vyřešeno',
        };
    }

    public function barva(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Varovani => 'warning',
            self::Kriticke => 'danger',
            self::Vyreseno => 'success',
        };
    }

    /** Pruh jde zavřít – vážný ne, ten visí, dokud platí. */
    public function zaviratelny(): bool
    {
        return $this !== self::Kriticke;
    }
}
