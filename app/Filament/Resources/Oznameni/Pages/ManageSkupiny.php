<?php

namespace App\Filament\Resources\Oznameni\Pages;

use App\Filament\Resources\Oznameni\SkupinaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSkupiny extends ManageRecords
{
    protected static string $resource = SkupinaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('administrace.manage_skupiny.nova_skupina'))];
    }
}
