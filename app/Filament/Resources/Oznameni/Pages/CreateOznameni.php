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
        return __('administrace.create_oznameni.napsat_oznameni');
    }

    protected function getRedirectUrl(): string
    {
        return OznameniResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('administrace.create_oznameni.koncept_ulozen_ted_ho_muzete_prohlednout');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(__('administrace.create_oznameni.ulozit_koncept'));
    }
}
