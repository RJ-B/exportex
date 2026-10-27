<?php

namespace App\Support\Pruvodce\Kroky;

use App\Platby\Filament\Stranky\PlatebniBrana;
use App\Platby\NastaveniPlateb;
use App\Platby\PlatbyServiceProvider;
use App\Support\Pruvodce\Krok;

/** Platební brána – jen se zapnutým doplňkem Platby; hotovo = vybraná brána s vyplněnými a ověřenými údaji (na testu ostře neplatí nikdy). */
class Platby extends Krok
{
    public function zobrazit(): bool
    {
        return PlatbyServiceProvider::zapnuto();
    }

    public function nazev(): string
    {
        return __('pruvodce.kroky.platby');
    }

    public function popis(): string
    {
        return __('pruvodce.kroky.platby_popis');
    }

    public function url(): ?string
    {
        return PlatebniBrana::getUrl();
    }

    public function hotovo(): bool
    {
        $brana = NastaveniPlateb::brana();

        return $brana !== null && NastaveniPlateb::vyplneno($brana) && NastaveniPlateb::overeno($brana);
    }
}
