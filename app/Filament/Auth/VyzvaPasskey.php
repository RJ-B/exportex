<?php

namespace App\Filament\Auth;

use App\Bezpecnost\DruhyKrok;
use App\Models\User;
use Filament\Forms\Components\Field;

/**
 * Výzva k potvrzení passkeyem ve druhém kroku přihlášení. Tlačítko spustí
 * dialog prohlížeče (Touch ID, Face ID, Windows Hello, telefon), odpověď
 * se uloží do pole a přihlášení se odešle; ověří ji až server
 * (PasskeyDruhyKrok, DruhyKrok::overPrihlaseni).
 *
 * Když zařízení passkey neumí nebo selže, nabídne kód z Authenticatoru
 * (přepne výběr způsobu ve výzvě) – jen tomu, kdo ho má zapnutý.
 */
class VyzvaPasskey extends Field
{
    protected string $view = 'filament.auth.vyzva-passkey';

    protected ?User $uzivatel = null;

    public function uzivatel(User $uzivatel): static
    {
        $this->uzivatel = $uzivatel;

        return $this;
    }

    /** Výzva pro prohlížeč, nebo null (vypršela – přihlásit znovu). */
    public function getMoznosti(): ?array
    {
        return $this->uzivatel ? DruhyKrok::cekajiciVyzva($this->uzivatel) : null;
    }

    public function maKod(): bool
    {
        return $this->uzivatel !== null && DruhyKrok::maAuthenticator($this->uzivatel);
    }
}
