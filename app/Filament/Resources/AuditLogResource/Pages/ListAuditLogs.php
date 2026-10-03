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
        return 'Aktivita';
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
                ->label('Retence logů')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalHeading('Jak dlouho se logy drží')
                ->modalDescription('Týká se e-mailů a chyb; aktivita se drží rok. Úklid běží denně ve 3:30 a mazání je nevratné.')
                ->modalSubmitActionLabel('Uložit')
                ->fillForm(fn () => ['dni' => (int) Nastaveni::hodnota('logy.retence_dni', 30)])
                ->schema([
                    TextInput::make('dni')
                        ->label('Počet dní')
                        ->numeric()
                        ->required()
                        // Spodní hranice schválně: nula by při nejbližším běhu
                        // umazala úplně všechno, a to nevratně.
                        ->minValue(7)
                        ->maxValue(3650)
                        ->helperText('Nejméně 7 dní.'),
                ])
                ->action(function (array $data) {
                    Nastaveni::nastav('logy.retence_dni', (int) $data['dni']);

                    Notification::make()
                        ->title('Retence nastavena na '.$data['dni'].' dní.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
