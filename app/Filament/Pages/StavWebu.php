<?php

namespace App\Filament\Pages;

use App\Enums\StavWebu as Stav;
use App\Filament\Navigace;
use App\Models\Nastaveni;
use App\Support\TextyStavuWebu;
use App\Support\UdrzbaWebu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Správa aplikace → Stav webu: Online / Připravujeme / Údržba. Jen superadmin.
 *
 * Kdo spravuje obsah, web nevypíná – kdyby ho omylem přepnul, návštěvníci
 * by viděli „Připravujeme“ a nikdo by nevěděl proč.
 */
class StavWebu extends Page
{
    protected string $view = 'filament.pages.stav-webu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return Navigace::spravaAplikace();
    }

    public static function getNavigationLabel(): string
    {
        return __('administrace.stav_webu.stav_webu');
    }

    public function getTitle(): string|Htmlable
    {
        return __('administrace.stav_webu.stav_webu');
    }

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
        $vychozi = TextyStavuWebu::vychozi();

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('administrace.stav_webu.texty_pro_navstevniky'))
                    ->description(__('administrace.stav_webu.prazdne_pole_vychozi_text_je_videt'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('pripravujeme_nadpis')->label(__('administrace.stav_webu.pripravujeme_nadpis'))->placeholder($vychozi['pripravujeme_nadpis']),
                        TextInput::make('udrzba_nadpis')->label(__('administrace.stav_webu.udrzba_nadpis'))->placeholder($vychozi['udrzba_nadpis']),
                        Textarea::make('pripravujeme_text')->label(__('administrace.stav_webu.pripravujeme_text'))->rows(3)->placeholder($vychozi['pripravujeme_text']),
                        Textarea::make('udrzba_text')->label(__('administrace.stav_webu.udrzba_text'))->rows(3)->placeholder($vychozi['udrzba_text']),
                    ]),
            ]);
    }

    public function uloz(): void
    {
        abort_unless(static::canAccess(), 403);

        TextyStavuWebu::uloz($this->form->getState());

        Notification::make()->title(__('administrace.stav_webu.texty_ulozeny'))->success()->send();
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
            ->label(fn (array $arguments): string => __('administrace.stav_webu.prepnout_na', ['from' => Stav::from($arguments['stav'])->nazev()]))
            ->color(fn (array $arguments): string => Stav::from($arguments['stav'])->barva())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('administrace.stav_webu.prepnout_web_na', ['from' => Stav::from($arguments['stav'])->nazev()]))
            ->modalDescription(fn (array $arguments): string => Stav::from($arguments['stav'])->popis())
            ->modalSubmitActionLabel(__('administrace.stav_webu.prepnout'))
            ->action(function (array $arguments): void {
                // Akci jde vyvolat i mimo tlačítko (Livewire) – kontrola i tady.
                abort_unless(static::canAccess(), 403);

                $stav = Stav::from($arguments['stav']);

                Nastaveni::nastav(Stav::KLIC, $stav->value);
                // Ruční přepnutí ruší text a konec údržby z CRM – platí jen pro tu jednu údržbu.
                UdrzbaWebu::smazPlan();
                Nastaveni::nastav(Stav::KLIC.'.zmena', auth()->user()->getFilamentName().' · '.now()->format(__('formaty.datum_cas_g')));

                Notification::make()->title(__('administrace.stav_webu.web_je_ted_ve_stavu', ['nazev' => $stav->nazev()]))->status($stav->barva())->send();
            });
    }
}
