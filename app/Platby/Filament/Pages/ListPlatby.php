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
            'vse' => Tab::make(__('platby.list_platby.vse')),
            'ceka' => $tab(__('platby.list_platby.ceka'), [StavPlatby::Zalozena->value, StavPlatby::Ceka->value]),
            'zaplacene' => Tab::make(__('platby.list_platby.zaplacene'))->modifyQueryUsing(fn (Builder $query) => $query->where('stav', StavPlatby::Zaplacena->value)),
            'neuspesne' => $tab(__('platby.list_platby.neuspesne'), [StavPlatby::Zamitnuta->value, StavPlatby::Zrusena->value, StavPlatby::Chyba->value]),
            'vracene' => Tab::make(__('platby.list_platby.vracene'))->modifyQueryUsing(fn (Builder $query) => $query->whereIn('stav', [StavPlatby::CastecneVracena->value, StavPlatby::Vracena->value])),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nastaveni')
                ->label(__('platby.list_platby.platebni_brana'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => PlatebniBrana::getUrl()),
            Action::make('novaPlatba')
                ->label(__('platby.list_platby.nova_platba'))
                ->icon('heroicon-o-plus')
                ->modalHeading(__('platby.list_platby.odkaz_k_zaplaceni'))
                ->modalDescription(fn () => NastaveniPlateb::prehled()['chyba'] ?? NastaveniPlateb::prehled()['popis'])
                ->modalSubmitActionLabel(__('platby.list_platby.vytvorit_odkaz'))
                ->schema([
                    TextInput::make('popis')->label(__('platby.list_platby.za_co'))->required()->maxLength(190)->placeholder(__('platby.list_platby.zaloha_na_zahradni_altan')),
                    TextInput::make('castka')->label(__('platby.list_platby.castka_kc'))->required()->placeholder('2 500')
                        ->rule('regex:/^\s*\d[\d\s]*([,.]\d{1,2})?\s*$/')->validationMessages(['regex' => __('platby.list_platby.castka_v_korunach_napr_2_500')]),
                    TextInput::make('reference')->label(__('platby.list_platby.cislo_objednavky_faktury'))->maxLength(64),
                    TextInput::make('email')->label(__('platby.list_platby.e_mail_zakaznika'))->email()->required(),
                    TextInput::make('jmeno')->label(__('platby.list_platby.jmeno'))->maxLength(80),
                    TextInput::make('prijmeni')->label(__('platby.list_platby.prijmeni'))->maxLength(80),
                    Toggle::make('poslat')->label(__('platby.list_platby.poslat_odkaz_zakaznikovi_e_mailem'))->default(true),
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
                        Notification::make()->title(__('platby.list_platby.platbu_nejde_vytvorit'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    if ($data['poslat'] ?? false) {
                        try {
                            Mail::to($platba->email, $platba->celeJmeno() ?: null)->send(new OdkazKZaplaceni($platba));
                        } catch (Throwable $e) {
                            app(ErrorLogger::class)->capture($e, ['platba' => $platba->verejne_id, 'kdy' => __('platby.list_platby.odkaz_k_zaplaceni_2')]);
                        }
                    }

                    Notification::make()->title(__('platby.list_platby.odkaz_k_zaplaceni_je_pripraveny'))
                        ->body($platba->odkazKZaplaceni())->success()->persistent()->send();

                    $this->redirect(PlatbaResource::getUrl('view', ['record' => $platba]));
                }),
        ];
    }
}
