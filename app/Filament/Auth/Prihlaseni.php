<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Přihlášení do administrace. Oproti Filamentu jediná změna: kdo zadá
 * SPRÁVNÉ heslo k účtu, který do administrace nesmí (uživatel aplikace,
 * ne správce), dostane „Tento účet nemá přístup do administrace“ místo
 * matoucího „přihlašovací údaje neodpovídají“.
 *
 * Přístup se kontroluje až po ověření hesla (Login::authenticate), takže
 * bez hesla se nedá zjistit, který účet existuje – špatné heslo i neznámý
 * e-mail dostanou dál stejnou hlášku. Hláška jde přes stejný Timebox
 * a událost Failed jako ostatní nepovedená přihlášení.
 */
class Prihlaseni extends Login
{
    private bool $bezPristupu = false;

    public function authenticate(): ?LoginResponse
    {
        $this->bezPristupu = false;

        return parent::authenticate();
    }

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        $smi = parent::isUserAllowedToAccessPanel($user);
        $this->bezPristupu = ! $smi;

        return $smi;
    }

    protected function throwFailureValidationException(): never
    {
        if ($this->bezPristupu) {
            throw ValidationException::withMessages([
                'data.email' => __('administrace.prihlaseni.tento_ucet_nema_pristup_do_administrace'),
            ]);
        }

        parent::throwFailureValidationException();
    }
}
