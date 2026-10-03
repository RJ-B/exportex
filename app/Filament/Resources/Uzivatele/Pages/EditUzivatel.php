<?php

namespace App\Filament\Resources\Uzivatele\Pages;

use App\Filament\Resources\Uzivatele\UzivatelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUzivatel extends EditRecord
{
    protected static string $resource = UzivatelResource::class;

    /** Role není hromadně zapisovatelná – zapisuje se tady (a vlastní se nemění, viz formulář). */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (array_key_exists('role', $data)) {
            $record->forceFill(['role' => $data['role']]);
            unset($data['role']);
        }

        $record->fill($data)->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
