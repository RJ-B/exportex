<?php

namespace App\Filament\Resources\Oznameni\Pages;

use App\Filament\Resources\Oznameni\OznameniResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

/** Nové oznámení vznikne jako koncept; odeslat jde z úpravy (náhled, zkouška, potvrzení počtu). */
class CreateOznameni extends CreateRecord
{
    protected static string $resource = OznameniResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Napsat oznámení';
    }

    protected function getRedirectUrl(): string
    {
        return OznameniResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Koncept uložen – teď ho můžete prohlédnout, vyzkoušet a odeslat';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Uložit koncept');
    }
}
