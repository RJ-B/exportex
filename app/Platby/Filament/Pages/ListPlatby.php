<?php

namespace App\Platby\Filament\Pages;

use App\Platby\ChybaBrany;
use App\Platby\Filament\PlatbaResource;
use App\Platby\Filament\Stranky\PlatebniBrana;
use App\Platby\Mail\OdkazKZaplaceni;
use App\Platby\NastaveniPlateb;
use App\Platby\Platba;
use App\Platby\Platby;
use App\Platby\PlatbyNedostupne;
use App\Platby\PozadavekPlatby;
use App\Platby\StavPlatby;
use App\Services\ErrorLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ListPlatby extends ListRecords
{
    protected static string $resource = PlatbaResource::class;

    /** Vše první (pravidlo filtrů), pak co čeká a co se nepovedlo. */
    public function getTabs(): array
    {
        $tab = fn (string $nazev, array $stavy) => Tab::make($nazev)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('stav', $stavy))
            ->badge(fn () => Platba::query()->whereIn('stav', $stavy)->count() ?: null);

        return [
            'vse' => Tab::make('Vše'),
            'ceka' => $tab('Čeká', [StavPlatby::Zalozena->value, StavPlatby::Ceka->value]),
            'zaplacene' => Tab::make('Zaplacené')->modifyQueryUsing(fn (Builder $query) => $query->where('stav', StavPlatby::Zaplacena->value)),
            'neuspesne' => $tab('Neúspěšné', [StavPlatby::Zamitnuta->value, StavPlatby::Zrusena->value, StavPlatby::Chyba->value]),
            'vracene' => Tab::make('Vrácené')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('stav', [StavPlatby::CastecneVracena->value, StavPlatby::Vracena->value])),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nastaveni')
                ->label('Platební brána')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => PlatebniBrana::getUrl()),
            Action::make('novaPlatba')
                ->label('Nová platba')
                ->icon('heroicon-o-plus')
                ->modalHeading('Odkaz k zaplacení')
                ->modalDescription(fn () => NastaveniPlateb::prehled()['chyba'] ?? NastaveniPlateb::prehled()['popis'])
                ->modalSubmitActionLabel('Vytvořit odkaz')
                ->schema([
                    TextInput::make('popis')->label('Za co')->required()->maxLength(190)->placeholder('Záloha na zahradní altán'),
                    TextInput::make('castka')->label('Částka (Kč)')->required()->placeholder('2 500')
                        ->rule('regex:/^\s*\d[\d\s]*([,.]\d{1,2})?\s*$/')->validationMessages(['regex' => 'Částka v korunách, např. 2 500 nebo 1250,50.']),
                    TextInput::make('reference')->label('Číslo (objednávky, faktury)')->maxLength(64),
                    TextInput::make('email')->label('E-mail zákazníka')->email()->required(),
                    TextInput::make('jmeno')->label('Jméno')->maxLength(80),
                    TextInput::make('prijmeni')->label('Příjmení')->maxLength(80),
                    Toggle::make('poslat')->label('Poslat odkaz zákazníkovi e-mailem')->default(true),
                ])
                ->action(function (array $data, Platby $platby) {
                    try {
                        $platba = $platby->zaloz(new PozadavekPlatby(
                            castka: Platby::halere($data['castka']),
                            popis: $data['popis'],
                            email: $data['email'],
                            jmeno: $data['jmeno'] ?: null,
                            prijmeni: $data['prijmeni'] ?: null,
                            reference: $data['reference'] ?: null,
                        ));
                    } catch (PlatbyNedostupne|ChybaBrany|\InvalidArgumentException $e) {
                        Notification::make()->title('Platbu nejde vytvořit')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    if ($data['poslat'] ?? false) {
                        try {
                            Mail::to($platba->email, $platba->celeJmeno() ?: null)->send(new OdkazKZaplaceni($platba));
                        } catch (Throwable $e) {
                            app(ErrorLogger::class)->capture($e, ['platba' => $platba->verejne_id, 'kdy' => 'odkaz k zaplacení']);
                        }
                    }

                    Notification::make()->title('Odkaz k zaplacení je připravený')
                        ->body($platba->odkazKZaplaceni())->success()->persistent()->send();

                    $this->redirect(PlatbaResource::getUrl('view', ['record' => $platba]));
                }),
        ];
    }
}
