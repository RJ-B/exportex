<?php

namespace App\Filament\Resources\Oznameni;

use App\Enums\KanalOznameni;
use App\Mail\OznameniMail;
use App\Models\OznameniPrijemce;
use App\Support\Oznameni\Cileni;
use App\Support\Oznameni\NastaveniOznameni;
use App\Support\Oznameni\Odeslani;
use App\Support\Oznameni\OznameniNejdeOdeslat;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Throwable;

/**
 * Akce u oznámení (úprava i detail): náhled, zkouška sobě, odeslání
 * s potvrzením počtu příjemců. Na stránce úpravy se nejdřív uloží formulář.
 */
trait AkceOznameni
{
    /** Počty příjemců spočítané při otevření okna Odeslat. */
    public array $poctyOdeslani = [];

    protected function ulozRozpracovane(): void
    {
        if (method_exists($this, 'save')) {
            $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        }

        $this->record->refresh();
    }

    protected function nahledAction(): Action
    {
        return Action::make('nahled')
            ->label('Náhled')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->mountUsing(fn () => $this->ulozRozpracovane())
            ->modalHeading('Náhled oznámení')
            ->modalWidth('3xl')
            ->modalContent(fn () => view('filament.oznameni.nahled', ['oznameni' => $this->record, 'email' => $this->nahledEmailu()]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zavřít');
    }

    protected function testSobeAction(): Action
    {
        return Action::make('test')
            ->label('Poslat zkoušku sobě')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->action(function () {
                $this->ulozRozpracovane();

                try {
                    app(Odeslani::class)->testSobe($this->record, auth()->user());
                    Notification::make()->title('Zkouška odešla na '.auth()->user()->email)->body('Nikomu jinému nic neodešlo.')->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Zkoušku se nepodařilo poslat')->body($e->getMessage())->danger()->send();
                }
            });
    }

    protected function odeslatAction(): Action
    {
        return Action::make('odeslat')
            ->label(fn () => $this->record->naplanovano_na?->isFuture() ? 'Naplánovat' : 'Odeslat')
            ->icon('heroicon-o-paper-airplane')
            ->mountUsing(function (Action $action, ?Schema $schema) {
                $this->ulozRozpracovane();

                if ($chyby = app(Odeslani::class)->chyby($this->record)) {
                    Notification::make()->title('Oznámení zatím nejde odeslat')->body(implode(' ', $chyby))->danger()->persistent()->send();
                    $action->cancel();
                }

                $this->poctyOdeslani = Cileni::pocty($this->record);
                $schema?->fill();
            })
            ->modalHeading(fn () => $this->record->naplanovano_na?->isFuture()
                ? 'Naplánovat na '.$this->record->naplanovano_na->format('j. n. Y H:i').'?'
                : 'Odeslat oznámení?')
            ->modalWidth('lg')
            ->schema(fn () => [
                // Až při vykreslení – počty vznikají v mountUsing, schéma se skládá dřív.
                View::make('filament.oznameni.pocty')->viewData(fn () => ['pocty' => $this->poctyOdeslani, 'oznameni' => $this->record]),
                TextInput::make('potvrzeni')
                    ->label('Pro potvrzení opište počet příjemců')
                    ->visible(fn () => ($this->poctyOdeslani['prijemcu'] ?? 0) >= NastaveniOznameni::limit('potvrzeni_od'))
                    ->required()
                    ->in(fn () => [(string) ($this->poctyOdeslani['prijemcu'] ?? '')])
                    ->validationMessages(['in' => 'Počet nesedí – opište ho přesně.']),
            ])
            ->modalSubmitActionLabel(fn () => $this->record->naplanovano_na?->isFuture() ? 'Naplánovat' : 'Odeslat teď')
            ->action(function (Action $action) {
                try {
                    app(Odeslani::class)->odeslat($this->record, auth()->user());
                } catch (OznameniNejdeOdeslat $e) {
                    Notification::make()->title('Oznámení nejde odeslat')->body(implode(' ', $e->chyby))->danger()->persistent()->send();
                    $action->halt();
                }

                $this->record->refresh();
                Notification::make()
                    ->title($this->record->naplanovano_na?->isFuture() ? 'Naplánováno na '.$this->record->naplanovano_na->format('j. n. Y H:i') : 'Oznámení odchází')
                    ->body($this->record->maKanal(KanalOznameni::Email) ? 'E-maily odcházejí po dávkách přes Poštu – výsledek uvidíte v detailu.' : null)
                    ->success()->send();

                $this->redirect(OznameniResource::getUrl('view', ['record' => $this->record]));
            });
    }

    /** E-mail tak, jak ho uvidí příjemce (bez odkazu na odhlášení konkrétního člověka). */
    protected function nahledEmailu(): ?string
    {
        if (! $this->record->maKanal(KanalOznameni::Email)) {
            return null;
        }

        $prijemce = (new OznameniPrijemce)->setRelation('oznameni', $this->record)->setRelation('user', auth()->user());

        return rescue(fn () => (new OznameniMail($prijemce))->render(), null);
    }
}
