<?php

namespace App\Filament\Resources\Uzivatele\Pages;

use App\Filament\Resources\Uzivatele\UzivatelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUzivatele extends ListRecords
{
    protected static string $resource = UzivatelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nový uživatel')];
    }
}
