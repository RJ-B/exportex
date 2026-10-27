<?php

namespace App\Support\Pruvodce\Kroky;

use App\Filament\Pages\HlavickaPaticka;
use App\Support\Pruvodce\Krok;
use App\Support\ZakladniUdaje;

/** Údaje firmy a kontakt (patička, e-maily, doklady). */
class UdajeFirmy extends Krok
{
    public function nazev(): string
    {
        return __('pruvodce.kroky.udaje_firmy');
    }

    public function popis(): string
    {
        return __('pruvodce.kroky.udaje_firmy_popis');
    }

    public function url(): ?string
    {
        return HlavickaPaticka::getUrl();
    }

    public function hotovo(): bool
    {
        $u = ZakladniUdaje::nacti();

        return filled($u['email']) && filled($u['firma']) && filled($u['ico']) && filled($u['adresa']);
    }
}
