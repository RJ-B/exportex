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
                ->label(__('administrace.list_oznameni.skupiny_prijemcu'))
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->url(SkupinaResource::getUrl()),
            // Rozhodnutí Sim&Ren (právo, souhlasy) – jen superadmin.
            Action::make('nastaveni')
                ->label(__('administrace.list_oznameni.nastaveni'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->visible(fn () => auth()->user()?->jeSuperadmin())
                ->modalHeading(__('administrace.list_oznameni.nastaveni_oznameni'))
                ->modalDescription(__('administrace.list_oznameni.vychozi_je_konzervativni_nabidky_vypnute'))
                ->fillForm(fn () => [
                    'marketing' => NastaveniOznameni::marketing(),
                    'portal_koncovym' => NastaveniOznameni::portalKoncovym(),
                ])
                ->schema([
                    Toggle::make('marketing')
                        ->label(__('administrace.list_oznameni.klient_smi_posilat_nabidky_a_akce'))
                        ->helperText(__('administrace.list_oznameni.obchodni_sdeleni_e_mailem_jen_lidem')),
                    Toggle::make('portal_koncovym')
                        ->label(__('administrace.list_oznameni.oznameni_z_portalu_sim_ren_i'))
                        ->helperText(__('administrace.list_oznameni.nove_verze_odstavky_a_vypadky_vypnute')),
                ])
                ->action(function (array $data) {
                    NastaveniOznameni::uloz((bool) $data['marketing'], (bool) $data['portal_koncovym']);
                    Notification::make()->title(__('administrace.list_oznameni.nastaveni_oznameni_ulozeno'))->success()->send();
                }),
            CreateAction::make()->label(__('administrace.list_oznameni.napsat_oznameni')),
        ];
    }
}
