<?php

namespace App\Filament\Resources\Oznameni\Pages;

use App\Filament\Resources\Oznameni\AkceOznameni;
use App\Filament\Resources\Oznameni\OznameniResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** Koncept: úprava, náhled, zkouška sobě, odeslání / naplánování. */
class EditOznameni extends EditRecord
{
    use AkceOznameni;

    protected static string $resource = OznameniResource::class;

    public function getTitle(): string
    {
        return __('administrace.edit_oznameni.koncept_oznameni');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->nahledAction(),
            $this->testSobeAction(),
            $this->odeslatAction(),
            DeleteAction::make()->label(__('administrace.edit_oznameni.smazat_koncept')),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label(__('administrace.edit_oznameni.ulozit_koncept'));
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('administrace.edit_oznameni.koncept_ulozen');
    }
}
