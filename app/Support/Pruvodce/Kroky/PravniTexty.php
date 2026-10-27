<?php

namespace App\Support\Pruvodce\Kroky;

use App\Filament\Pages\OchranaOsobnichUdaju;
use App\Support\OchranaUdaju;
use App\Support\Pruvodce\Krok;

/** Právní texty – ochrana osobních údajů doplněná o údaje webu. */
class PravniTexty extends Krok
{
    public function nazev(): string
    {
        return __('pruvodce.kroky.pravni_texty');
    }

    public function popis(): string
    {
        return __('pruvodce.kroky.pravni_texty_popis');
    }

    public function url(): ?string
    {
        return OchranaOsobnichUdaju::getUrl();
    }

    public function hotovo(): bool
    {
        return OchranaUdaju::chybejici() === [];
    }
}
