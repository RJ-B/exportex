<?php

namespace App\Filament\Resources\Oznameni\Pages;

use App\Enums\KanalOznameni;
use App\Enums\StavOznameni;
use App\Filament\Resources\Oznameni\AkceOznameni;
use App\Filament\Resources\Oznameni\OznameniResource;
use App\Models\Oznameni;
use App\Support\Oznameni\Cileni;
use App\Support\Oznameni\Odeslani;
use App\Support\Oznameni\Pruh;
use App\Support\Oznameni\Statistika;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/** Naplánované a odeslané: co a komu odešlo, čísla, zrušení plánu, ukončení pruhu. */
class ViewOznameni extends ViewRecord
{
    use AkceOznameni;

    protected static string $resource = OznameniResource::class;

    public function getTitle(): string
    {
        return $this->record->titulek;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            View::make('filament.oznameni.detail')->viewData([
                'oznameni' => $this->record,
                'komu' => Cileni::popis((array) $this->record->cileni),
                'cisla' => $this->record->stav === StavOznameni::Odeslano ? Statistika::pro($this->record) : null,
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->nahledAction(),
            Action::make('zrusitPlan')
                ->label('Zrušit plán')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => $this->record->stav === StavOznameni::Naplanovano)
                ->requiresConfirmation()
                ->modalDescription('Oznámení se vrátí do konceptu a neodejde, dokud ho znovu neodešlete.')
                ->action(function () {
                    app(Odeslani::class)->zrusitPlan($this->record);
                    $this->redirect(OznameniResource::getUrl('edit', ['record' => $this->record]));
                }),
            Action::make('ukoncitPruh')
                ->label('Ukončit pruh')
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn () => $this->record->maKanal(KanalOznameni::Pruh)
                    && Oznameni::query()->pruhPlati()->whereKey($this->record->getKey())->exists())
                ->requiresConfirmation()
                ->modalDescription('Pruh z webu i administrace hned zmizí. V centru oznámení zůstane.')
                ->action(function () {
                    $this->record->forceFill(['pruh_do' => now()])->save();
                    Pruh::zapomen();
                    Notification::make()->title('Pruh ukončen')->success()->send();
                }),
            Action::make('znovu')
                ->label('Použít znovu')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn () => $this->record->zdroj === 'administrace')
                ->action(function () {
                    $kopie = $this->record->replicate(['uuid', 'stav', 'odeslano_at', 'pocet_prijemcu', 'naplanovano_na', 'odeslal_id', 'vytvoril_id', 'zdroj_klic']);
                    $kopie->stav = StavOznameni::Koncept;
                    $kopie->pruh_od = null;
                    $kopie->pruh_do = null;
                    $kopie->save();

                    $this->redirect(OznameniResource::getUrl('edit', ['record' => $kopie]));
                }),
        ];
    }
}
