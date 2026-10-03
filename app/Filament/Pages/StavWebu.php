<?php

namespace App\Filament\Pages;

use App\Enums\StavWebu as Stav;
use App\Models\Nastaveni;
use App\Support\TextyStavuWebu;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Provoz → Stav webu: Online / Připravujeme / Údržba. Jen superadmin.
 *
 * Kdo spravuje obsah, web nevypíná – kdyby ho omylem přepnul, návštěvníci
 * by viděli „Připravujeme“ a nikdo by nevěděl proč.
 */
class StavWebu extends Page
{
    protected string $view = 'filament.pages.stav-webu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|\UnitEnum|null $navigationGroup = 'Provoz';

    protected static ?string $navigationLabel = 'Stav webu';

    protected static ?string $title = 'Stav webu';

    protected static ?string $slug = 'stav-webu';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSuperadmin() ?? false;
    }

    /** Odznak, když web není Online – ať se na údržbu nezapomene. */
    public static function getNavigationBadge(): ?string
    {
        $stav = Stav::aktualni();

        return $stav === Stav::Online ? null : $stav->nazev();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Stav::aktualni()->barva();
    }

    public function mount(): void
    {
        $this->form->fill(TextyStavuWebu::ulozene());
    }

    /** Co návštěvník čte, když web není Online. Prázdné = výchozí text. */
    public function form(Schema $schema): Schema
    {
        $vychozi = TextyStavuWebu::VYCHOZI;

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Texty pro návštěvníky')
                    ->description('Prázdné pole = výchozí text (je vidět šedě).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pripravujeme_nadpis')->label('Připravujeme – nadpis')->placeholder($vychozi['pripravujeme_nadpis']),
                        TextInput::make('udrzba_nadpis')->label('Údržba – nadpis')->placeholder($vychozi['udrzba_nadpis']),
                        Textarea::make('pripravujeme_text')->label('Připravujeme – text')->rows(3)->placeholder($vychozi['pripravujeme_text']),
                        Textarea::make('udrzba_text')->label('Údržba – text')->rows(3)->placeholder($vychozi['udrzba_text']),
                    ]),
            ]);
    }

    public function uloz(): void
    {
        abort_unless(static::canAccess(), 403);

        TextyStavuWebu::uloz($this->form->getState());

        Notification::make()->title('Texty uloženy')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'aktualni' => Stav::aktualni(),
            'stavy' => Stav::cases(),
            'zmena' => Nastaveni::hodnota(Stav::KLIC.'.zmena'),
        ];
    }

    public function prepnoutAction(): Action
    {
        return Action::make('prepnout')
            ->label(fn (array $arguments): string => 'Přepnout na '.Stav::from($arguments['stav'])->nazev())
            ->color(fn (array $arguments): string => Stav::from($arguments['stav'])->barva())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Přepnout web na „'.Stav::from($arguments['stav'])->nazev().'“?')
            ->modalDescription(fn (array $arguments): string => Stav::from($arguments['stav'])->popis())
            ->modalSubmitActionLabel('Přepnout')
            ->action(function (array $arguments): void {
                // Akci jde vyvolat i mimo tlačítko (Livewire) – kontrola i tady.
                abort_unless(static::canAccess(), 403);

                $stav = Stav::from($arguments['stav']);

                Nastaveni::nastav(Stav::KLIC, $stav->value);
                Nastaveni::nastav(Stav::KLIC.'.zmena', auth()->user()->getFilamentName().' · '.now()->format('j. n. Y G:i'));

                Notification::make()->title('Web je teď ve stavu '.$stav->nazev())->status($stav->barva())->send();
            });
    }
}
