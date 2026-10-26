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
            ->label(__('administrace.akce_oznameni.nahled'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->mountUsing(fn () => $this->ulozRozpracovane())
            ->modalHeading(__('administrace.akce_oznameni.nahled_oznameni'))
            ->modalWidth('3xl')
            ->modalContent(fn () => view('filament.oznameni.nahled', ['oznameni' => $this->record, 'email' => $this->nahledEmailu()]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('administrace.akce_oznameni.zavrit'));
    }

    protected function testSobeAction(): Action
    {
        return Action::make('test')
            ->label(__('administrace.akce_oznameni.poslat_zkousku_sobe'))
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->action(function () {
                $this->ulozRozpracovane();

                try {
                    app(Odeslani::class)->testSobe($this->record, auth()->user());
                    Notification::make()->title(__('administrace.akce_oznameni.zkouska_odesla_na', ['email' => auth()->user()->email]))->body(__('administrace.akce_oznameni.nikomu_jinemu_nic_neodeslo'))->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title(__('administrace.akce_oznameni.zkousku_se_nepodarilo_poslat'))->body($e->getMessage())->danger()->send();
                }
            });
    }

    protected function odeslatAction(): Action
    {
        return Action::make('odeslat')
            ->label(fn () => $this->record->naplanovano_na?->isFuture() ? __('administrace.akce_oznameni.naplanovat') : __('administrace.akce_oznameni.odeslat'))
            ->icon('heroicon-o-paper-airplane')
            ->mountUsing(function (Action $action, ?Schema $schema) {
                $this->ulozRozpracovane();

                if ($chyby = app(Odeslani::class)->chyby($this->record)) {
                    Notification::make()->title(__('administrace.akce_oznameni.oznameni_zatim_nejde_odeslat'))->body(implode(' ', $chyby))->danger()->persistent()->send();
                    $action->cancel();
                }

                $this->poctyOdeslani = Cileni::pocty($this->record);
                $schema?->fill();
            })
            ->modalHeading(fn () => $this->record->naplanovano_na?->isFuture()
                ? __('administrace.akce_oznameni.naplanovat_na', ['format' => $this->record->naplanovano_na->format(__('formaty.datum_cas'))])
                : __('administrace.akce_oznameni.odeslat_oznameni'))
            ->modalWidth('lg')
            ->schema(fn () => [
                // Až při vykreslení – počty vznikají v mountUsing, schéma se skládá dřív.
                View::make('filament.oznameni.pocty')->viewData(fn () => ['pocty' => $this->poctyOdeslani, 'oznameni' => $this->record]),
                TextInput::make('potvrzeni')
                    ->label(__('administrace.akce_oznameni.pro_potvrzeni_opiste_pocet_prijemcu'))
                    ->visible(fn () => ($this->poctyOdeslani['prijemcu'] ?? 0) >= NastaveniOznameni::limit('potvrzeni_od'))
                    ->required()
                    ->in(fn () => [(string) ($this->poctyOdeslani['prijemcu'] ?? '')])
                    ->validationMessages(['in' => __('administrace.akce_oznameni.pocet_nesedi_opiste_ho_presne')]),
            ])
            ->modalSubmitActionLabel(fn () => $this->record->naplanovano_na?->isFuture() ? __('administrace.akce_oznameni.naplanovat') : __('administrace.akce_oznameni.odeslat_ted'))
            ->action(function (Action $action) {
                try {
                    app(Odeslani::class)->odeslat($this->record, auth()->user());
                } catch (OznameniNejdeOdeslat $e) {
                    Notification::make()->title(__('administrace.akce_oznameni.oznameni_nejde_odeslat'))->body(implode(' ', $e->chyby))->danger()->persistent()->send();
                    $action->halt();
                }

                $this->record->refresh();
                Notification::make()
                    ->title($this->record->naplanovano_na?->isFuture() ? __('administrace.akce_oznameni.naplanovano_na', ['format' => $this->record->naplanovano_na->format(__('formaty.datum_cas'))]) : __('administrace.akce_oznameni.oznameni_odchazi'))
                    ->body($this->record->maKanal(KanalOznameni::Email) ? __('administrace.akce_oznameni.e_maily_odchazeji_po_davkach_pres') : null)
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
