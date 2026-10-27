<?php

namespace App\Support\Pruvodce\Kroky;

use App\Filament\Pages\HlavickaPaticka;
use App\Support\NastaveniWebu;
use App\Support\Pruvodce\Krok;

/** Logo (z něj vznikne i ikona v záložce prohlížeče). */
class IkonaALogo extends Krok
{
    public function nazev(): string
    {
        return __('pruvodce.kroky.ikona_a_logo');
    }

    public function popis(): string
    {
        return __('pruvodce.kroky.ikona_a_logo_popis');
    }

    public function url(): ?string
    {
        return HlavickaPaticka::getUrl();
    }

    public function hotovo(): bool
    {
        return filled(NastaveniWebu::get('logo')) || filled(NastaveniWebu::get('ikona'));
    }
}
