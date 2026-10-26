<?php

namespace App\Filament\Resources\MailLogResource\Pages;

use App\Filament\Resources\MailLogResource;
use Filament\Resources\Pages\ListRecords;

class ListMailLogs extends ListRecords
{
    protected static string $resource = MailLogResource::class;

    public function getTitle(): string
    {
        return __('administrace.list_mail_logs.e_maily');
    }
}
