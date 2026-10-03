<?php

namespace App\Filament\Resources\Uzivatele\Pages;

use App\Filament\Resources\Uzivatele\UzivatelResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUzivatel extends CreateRecord
{
    protected static string $resource = UzivatelResource::class;

    /** Role není hromadně zapisovatelná (ať ji nenastaví registrace) – zapisuje se tady. */
    protected function handleRecordCreation(array $data): Model
    {
        $role = $data['role'] ?? 'klient';
        unset($data['role']);

        $user = new User($data);
        $user->forceFill(['role' => $role, 'email_verified_at' => now()])->save();

        return $user;
    }

    /** Účet vzniká bez hesla – kdo smí do administrace, dostane hned odkaz. */
    protected function afterCreate(): void
    {
        if ($this->record->jeSpravce()) {
            UzivatelResource::posliOdkaz($this->record);

            Notification::make()
                ->title('Na '.$this->record->email.' odešel odkaz na nastavení hesla.')
                ->success()
                ->send();
        }
    }
}
