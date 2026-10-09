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
                ->label('Ověřit stav u brány')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => filled($this->record->externi_id))
                ->action(function (Platby $platby) {
                    try {
                        $platba = $platby->overStav($this->record, 'administrace');
                        Notification::make()->title('Stav: '.$platba->stav->popis())->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Stav nejde ověřit')->body($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                }),
            Action::make('odkaz')
                ->label('Odkaz k zaplacení')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn () => $this->record->stav === StavPlatby::Ceka)
                ->modalHeading('Odkaz k zaplacení')
                ->modalDescription(fn () => $this->record->odkazKZaplaceni())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Zavřít'),
            Action::make('zrusit')
                ->label('Zrušit platbu')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->stav->otevrena())
                ->requiresConfirmation()
                ->modalHeading('Zrušit platbu?')
                ->modalDescription('Zákazník ji pak už nezaplatí. Když ji mezitím zaplatil, zůstane zaplacená.')
                ->modalSubmitActionLabel('Zrušit platbu')
                ->schema([Textarea::make('duvod')->label('Důvod')->rows(2)->maxLength(300)])
                ->action(function (array $data, Platby $platby) {
                    try {
                        $platby->zrus($this->record, 'administrace', $data['duvod'] ?? null);
                        Notification::make()->title('Platba je zrušená')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Zrušit nejde')->body($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                }),
            Action::make('vratit')
                ->label('Vrátit peníze')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->record->zbyvaVratit() > 0)
                ->disabled(fn () => ! ($this->brana()?->umiVratit() ?? false))
                ->tooltip(fn () => ($this->brana()?->umiVratit() ?? false) ? null : 'Tahle brána vrácení přes API neumí – vrať peníze v její aplikaci.')
                ->requiresConfirmation()
                ->modalHeading('Vrátit peníze zákazníkovi?')
                ->modalDescription(fn () => 'Peníze odejdou zpět stejnou cestou, jakou zákazník platil. Vrátit jde nejvýš '.Platba::kc($this->record->zbyvaVratit(), $this->record->mena).'. Vrácení nejde vzít zpět.')
                ->modalSubmitActionLabel('Vrátit peníze')
                ->schema([
                    TextInput::make('castka')->label('Částka (Kč)')->required()
                        ->default(fn () => number_format($this->record->zbyvaVratit() / 100, 2, ',', ''))
                        ->rule('regex:/^\s*\d[\d\s]*([,.]\d{1,2})?\s*$/')->validationMessages(['regex' => 'Částka v korunách, např. 250 nebo 99,50.']),
                    Textarea::make('duvod')->label('Důvod')->rows(2)->maxLength(300),
                ])
                ->action(function (array $data, Platby $platby) {
                    try {
                        $platby->vrat($this->record, Platby::halere($data['castka']), $data['duvod'] ?? null);
                        Notification::make()->title('Peníze se vrací')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Vrátit nejde')->body($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                }),
        ];
    }
}
