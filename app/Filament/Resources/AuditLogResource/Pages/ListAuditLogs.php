<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Models\Nastaveni;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    public function getTitle(): string
    {
        return __('administrace.list_audit_logs.aktivita');
    }

    /**
     * Retence patří k Aktivitě, protože právě audit se drží nejdél a nejvíc
     * bobtná. Je to nastavení, ne konstanta v kódu: jak dlouho se dozadu
     * dohledává, ví provoz, ne programátor.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('retence')
                ->label(__('administrace.list_audit_logs.retence_logu'))
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalHeading(__('administrace.list_audit_logs.jak_dlouho_se_logy_drzi'))
                ->modalDescription(__('administrace.list_audit_logs.tyka_se_e_mailu_a_chyb'))
                ->modalSubmitActionLabel(__('administrace.list_audit_logs.ulozit'))
                ->fillForm(fn () => ['dni' => (int) Nastaveni::hodnota('logy.retence_dni', 30)])
                ->schema([
                    TextInput::make('dni')
                        ->label(__('administrace.list_audit_logs.pocet_dni'))
                        ->numeric()
                        ->required()
                        // Spodní hranice schválně: nula by při nejbližším běhu
                        // umazala úplně všechno, a to nevratně.
                        ->minValue(7)
                        ->maxValue(3650)
                        ->helperText(__('administrace.list_audit_logs.nejmene_7_dni')),
                ])
                ->action(function (array $data) {
                    Nastaveni::nastav('logy.retence_dni', (int) $data['dni']);

                    Notification::make()
                        ->title(__('administrace.list_audit_logs.retence_nastavena_na_dni', ['dni' => $data['dni']]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
