<?php

namespace App\Platby\Filament\Pages;

use App\Platby\Filament\PlatbaResource;
use App\Platby\NastaveniPlateb;
use App\Platby\Platba;
use App\Platby\Platby;
use App\Platby\StavPlatby;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Throwable;

/** Detail platby: údaje, historie stavů a akce Ověřit stav, Zrušit, Vrátit peníze. */
class ViewPlatba extends ViewRecord
{
    protected static string $resource = PlatbaResource::class;

    public function getTitle(): string
    {
        return $this->record->popis;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            View::make('filament.platby.detail')->viewData(fn () => [
                'platba' => $this->record->fresh(['udalosti.uzivatel']),
            ]),
        ]);
    }

    private function brana()
    {
        return rescue(fn () => NastaveniPlateb::proPlatbu($this->record), null, false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('overit')
                ->label(__('platby.view_platba.overit_stav_u_brany'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => filled($this->record->externi_id))
                ->action(function (Platby $platby) {
                    try {
                        $platba = $platby->overStav($this->record, 'administrace');
                        Notification::make()->title('Stav: '.$platba->stav->popis())->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title(__('platby.view_platba.stav_nejde_overit'))->body($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                }),
            Action::make('odkaz')
                ->label(__('platby.view_platba.odkaz_k_zaplaceni'))
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn () => $this->record->stav === StavPlatby::Ceka)
                ->modalHeading(__('platby.view_platba.odkaz_k_zaplaceni'))
                ->modalDescription(fn () => $this->record->odkazKZaplaceni())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('platby.view_platba.zavrit')),
            Action::make('zrusit')
                ->label(__('platby.view_platba.zrusit_platbu'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->stav->otevrena())
                ->requiresConfirmation()
                ->modalHeading(__('platby.view_platba.zrusit_platbu_2'))
                ->modalDescription(__('platby.view_platba.zakaznik_ji_pak_uz_nezaplati_kdyz'))
                ->modalSubmitActionLabel(__('platby.view_platba.zrusit_platbu'))
                ->schema([Textarea::make('duvod')->label(__('platby.view_platba.duvod'))->rows(2)->maxLength(300)])
                ->action(function (array $data, Platby $platby) {
                    try {
                        $platby->zrus($this->record, 'administrace', $data['duvod'] ?? null);
                        Notification::make()->title(__('platby.view_platba.platba_je_zrusena'))->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title(__('platby.view_platba.zrusit_nejde'))->body($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                }),
            Action::make('vratit')
                ->label(__('platby.view_platba.vratit_penize'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->record->zbyvaVratit() > 0)
                ->disabled(fn () => ! ($this->brana()?->umiVratit() ?? false))
                ->tooltip(fn () => ($this->brana()?->umiVratit() ?? false) ? null : __('platby.view_platba.tahle_brana_vraceni_pres_api_neumi'))
                ->requiresConfirmation()
                ->modalHeading(__('platby.view_platba.vratit_penize_zakaznikovi'))
                ->modalDescription(fn () => __('platby.view_platba.penize_odejdou_zpet_stejnou_cestou_jakou', ['kc' => Platba::kc($this->record->zbyvaVratit(), $this->record->mena)]))
                ->modalSubmitActionLabel(__('platby.view_platba.vratit_penize'))
                ->schema([
                    TextInput::make('castka')->label(__('platby.view_platba.castka_kc'))->required()
                        ->default(fn () => number_format($this->record->zbyvaVratit() / 100, 2, ',', ''))
                        ->rule('regex:/^\s*\d[\d\s]*([,.]\d{1,2})?\s*$/')->validationMessages(['regex' => __('platby.view_platba.castka_v_korunach_napr_250_nebo')]),
                    Textarea::make('duvod')->label(__('platby.view_platba.duvod'))->rows(2)->maxLength(300),
                ])
                ->action(function (array $data, Platby $platby) {
                    try {
                        $platby->vrat($this->record, Platby::halere($data['castka']), $data['duvod'] ?? null);
                        Notification::make()->title(__('platby.view_platba.penize_se_vraci'))->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title(__('platby.view_platba.vratit_nejde'))->body($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                }),
        ];
    }
}
