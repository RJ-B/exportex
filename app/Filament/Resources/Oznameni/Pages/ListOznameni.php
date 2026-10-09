<?php

namespace App\Filament\Resources\Oznameni\Pages;

use App\Filament\Resources\Oznameni\OznameniResource;
use App\Filament\Resources\Oznameni\SkupinaResource;
use App\Support\Oznameni\NastaveniOznameni;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListOznameni extends ListRecords
{
    protected static string $resource = OznameniResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('skupiny')
                ->label('Skupiny příjemců')
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->url(SkupinaResource::getUrl()),
            // Rozhodnutí Sim&Ren (právo, souhlasy) – jen superadmin.
            Action::make('nastaveni')
                ->label('Nastavení')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->visible(fn () => auth()->user()?->jeSuperadmin())
                ->modalHeading('Nastavení oznámení')
                ->modalDescription('Výchozí je konzervativní: nabídky vypnuté, oznámení z portálu jen správcům.')
                ->fillForm(fn () => [
                    'marketing' => NastaveniOznameni::marketing(),
                    'portal_koncovym' => NastaveniOznameni::portalKoncovym(),
                ])
                ->schema([
                    Toggle::make('marketing')
                        ->label('Klient smí posílat nabídky a akce (marketing)')
                        ->helperText('Obchodní sdělení – e-mailem jen lidem, kteří výslovně souhlasili. Zapnout až po domluvě s klientem.'),
                    Toggle::make('portal_koncovym')
                        ->label('Oznámení z portálu Sim&Ren i koncovým uživatelům')
                        ->helperText('Nové verze, odstávky a výpadky. Vypnuté = jen správcům aplikace. (Příjem z portálu připravujeme.)'),
                ])
                ->action(function (array $data) {
                    NastaveniOznameni::uloz((bool) $data['marketing'], (bool) $data['portal_koncovym']);
                    Notification::make()->title('Nastavení oznámení uloženo')->success()->send();
                }),
            CreateAction::make()->label('Napsat oznámení'),
        ];
    }
}
