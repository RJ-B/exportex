<?php

namespace App\Support\Pruvodce\Kroky;

use App\Bezpecnost\DruhyKrok;
use App\Filament\Pages\Zabezpeceni;
use App\Support\Pruvodce\Krok;

/** Druhý krok přihlášení přihlášeného správce (passkey nebo Authenticator). */
class DruhyKrokPrihlaseni extends Krok
{
    public function nazev(): string
    {
        return __('pruvodce.kroky.druhy_krok');
    }

    public function popis(): string
    {
        return __('pruvodce.kroky.druhy_krok_popis');
    }

    public function url(): ?string
    {
        return Zabezpeceni::getUrl();
    }

    public function hotovo(): bool
    {
        $user = auth()->user();

        return $user !== null && DruhyKrok::zapnuty($user);
    }
}
