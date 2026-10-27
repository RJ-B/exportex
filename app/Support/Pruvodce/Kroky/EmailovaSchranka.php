<?php

namespace App\Support\Pruvodce\Kroky;

use App\Filament\Pages\EmailovaSchranka as Stranka;
use App\Support\Posta\SchrankaWebu;
use App\Support\Pruvodce\Krok;

/** Vlastní e-mailová schránka, ze které web posílá. */
class EmailovaSchranka extends Krok
{
    public function nazev(): string
    {
        return __('pruvodce.kroky.emailova_schranka');
    }

    public function popis(): string
    {
        return __('pruvodce.kroky.emailova_schranka_popis');
    }

    public function url(): ?string
    {
        return Stranka::getUrl();
    }

    public function hotovo(): bool
    {
        return SchrankaWebu::nastavena();
    }
}
