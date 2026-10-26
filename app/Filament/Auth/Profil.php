<?php

namespace App\Filament\Auth;

use App\Support\Jazyky\Jazyky;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * Profil (uživatelské menu vpravo nahoře): jméno a příjmení zvlášť, e-mail
 * a heslo, jazyk (jen při víc zapnutých jazycích, docs/jazyky.md). Druhý krok přihlášení není tady, ale v Zabezpečení (vedle
 * v uživatelském menu) – passkeye a Authenticator na jednom místě.
 */
class Profil extends EditProfile
{
    public static function getLabel(): string
    {
        return __('administrace.profil.profil');
    }

    public static function isSimple(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('jmeno')->label(__('administrace.profil.jmeno'))->required()->maxLength(60),
            TextInput::make('prijmeni')->label(__('administrace.profil.prijmeni'))->required()->maxLength(60),
            $this->getEmailFormComponent(),
            Select::make('jazyk')->label(__('administrace.profil.jazyk'))
                ->options(Jazyky::zapnute())
                ->placeholder(__('administrace.profil.jazyk_vychozi', ['jazyk' => Jazyky::zapnute()[Jazyky::vychozi()] ?? Jazyky::vychozi()]))
                ->visible(Jazyky::vyber()),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    /** Druhý krok spravuje Zabezpečení. */
    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        return null;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['name'] = trim(($data['jmeno'] ?? '').' '.($data['prijmeni'] ?? ''));

        return $data;
    }
}
