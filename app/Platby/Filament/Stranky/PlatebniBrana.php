<?php

namespace App\Platby\Filament\Stranky;

use App\Platby\NastaveniPlateb;
use App\Platby\Rezim;
use App\Services\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

/**
 * Nastavení → Platební brána (doplněk Platby): brána klienta (Comgate / Mo.one),
 * její přístupové údaje (šifrovaně, tajemství se do formuláře nevrací), Ověřit
 * spojení a přepnutí Testovací × Ostrý režim s pojistkou: ostře jen na
 * produkci a jen s ověřenými údaji.
 */
class PlatebniBrana extends Page
{
    protected string $view = 'filament.pages.formular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?string $navigationLabel = 'Platební brána';

    protected static ?string $title = 'Platební brána';

    protected static ?string $slug = 'platebni-brana';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'brana' => NastaveniPlateb::brana() ?? 'comgate',
            'rezim' => NastaveniPlateb::rezimVolba(),
            'comgate_merchant' => NastaveniPlateb::udajeKlienta('comgate')['id'],
            'moone_client_id' => NastaveniPlateb::udajeKlienta('moone')['id'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $tajne = fn (string $brana) => fn () => NastaveniPlateb::tajemstviUlozeno($brana) ? 'uloženo – vyplň jen pro změnu' : null;

        return $schema
            ->statePath('data')
            ->components([
                Html::make(fn () => view('filament.platby.prehled', ['prehled' => NastaveniPlateb::prehled()])),
                Section::make('Brána klienta')
                    ->description('Přes ni jdou ostré platby na produkci – peníze na účet klienta. Údaje se ukládají šifrovaně a tajný klíč se sem už nevrací.')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('brana')->label('Platební brána')->inline()->live()->required()
                            ->options(NastaveniPlateb::BRANY)
                            ->columnSpanFull(),
                        TextInput::make('comgate_merchant')->label('Identifikátor obchodu (merchant)')->maxLength(40)
                            ->visible(fn ($get) => $get('brana') === 'comgate')
                            ->helperText('Klientský portál Comgate → Integrace → Nastavení obchodu.'),
                        TextInput::make('comgate_secret')->label('Heslo obchodu (secret)')->password()->maxLength(200)
                            ->placeholder($tajne('comgate'))
                            ->visible(fn ($get) => $get('brana') === 'comgate')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null),
                        Html::make(fn () => new HtmlString('<p style="font-size: .875rem; color: inherit; opacity: .8;">V portálu Comgate nastav <strong>URL pro předání výsledku platby</strong> na <code style="user-select: all;">'
                            .e(route('platby.webhook', 'comgate')).'</code> (metoda POST). Návratové adresy posílá web s každou platbou sám.</p>'))
                            ->visible(fn ($get) => $get('brana') === 'comgate')
                            ->columnSpanFull(),
                        TextInput::make('moone_client_id')->label('Client ID')->maxLength(64)
                            ->visible(fn ($get) => $get('brana') === 'moone')
                            ->helperText('Aplikace Mo.one → PayPoint typu Platební brána → Vygenerovat přístupové údaje.'),
                        TextInput::make('moone_client_secret')->label('Client secret')->password()->maxLength(200)
                            ->placeholder($tajne('moone'))
                            ->visible(fn ($get) => $get('brana') === 'moone')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null),
                        Html::make(fn () => new HtmlString('<p style="font-size: .875rem; color: inherit; opacity: .8;">Výsledek platby posílá Mo.one samo na <code style="user-select: all;">'
                            .e(route('platby.webhook', 'moone')).'</code> – v Mo.one se nic nenastavuje. Vrácení peněz se dělá v aplikaci Mo.one (API ho nemá).</p>'))
                            ->visible(fn ($get) => $get('brana') === 'moone')
                            ->columnSpanFull(),
                        Html::make(fn ($get) => self::stavOvereni((string) $get('brana')))->columnSpanFull(),
                    ]),
                Section::make('Režim')
                    ->schema([
                        ToggleButtons::make('rezim')->label('Platby')->inline()->required()
                            ->options([NastaveniPlateb::REZIM_TEST => 'Testovací', NastaveniPlateb::REZIM_OSTRY => 'Ostrý'])
                            ->colors([NastaveniPlateb::REZIM_TEST => 'warning', NastaveniPlateb::REZIM_OSTRY => 'success'])
                            ->disabled(fn () => ! app()->isProduction())
                            ->helperText(fn () => app()->isProduction()
                                ? 'Testovací = testovací brána Sim&Ren, nic se nestrhne, na webu svítí „TESTOVACÍ PLATBY“. Ostrý = brána klienta – jen s vyplněnými a ověřenými údaji.'
                                : 'Testovací web platí vždy testovací bránou Sim&Ren – ostrý režim jde zapnout až na produkci.'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('overit')
                ->label('Ověřit spojení')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(fn () => $this->overit()),
        ];
    }

    /** Ověří zadané (jinak uložené) údaje brány bez pohybu peněz; povedené uloží jako ověřené. */
    public function overit(): void
    {
        $data = $this->form->getState();
        $brana = (string) $data['brana'];
        [$id, $tajemstvi] = $this->udajeZFormulare($brana, $data);

        if (blank($id) || blank($tajemstvi)) {
            Notification::make()->title('Vyplň přístupové údaje brány '.NastaveniPlateb::BRANY[$brana])->warning()->send();

            return;
        }

        try {
            $zprava = NastaveniPlateb::branaKlienta($brana, Rezim::Ostry, ['id' => $id, 'tajemstvi' => $tajemstvi])->overSpojeni();
        } catch (Throwable $e) {
            Notification::make()->title('Spojení s bránou nefunguje')->body($e->getMessage())->danger()->send();

            return;
        }

        // Povedlo se – údaje uložit (bez změny režimu) a označit jako ověřené.
        NastaveniPlateb::uloz(array_intersect_key($data, array_flip(['brana', 'comgate_merchant', 'comgate_secret', 'moone_client_id', 'moone_client_secret'])));
        NastaveniPlateb::oznacOvereno($brana);
        $this->vycistitTajemstvi();

        AuditLogger::record('platby.overeno', null, 'Platební brána '.NastaveniPlateb::BRANY[$brana].': spojení ověřeno');
        Notification::make()->title('Spojení funguje')->body($zprava)->success()->send();
    }

    public function uloz(): void
    {
        $data = $this->form->getState();
        $brana = (string) $data['brana'];
        $predtim = NastaveniPlateb::rezimVolba();
        $rezim = app()->isProduction() ? ($data['rezim'] ?? NastaveniPlateb::REZIM_TEST) : $predtim;

        // Ostře jen s ověřenými údaji: nové (neověřené) údaje se s ostrým režimem neuloží.
        if ($rezim === NastaveniPlateb::REZIM_OSTRY) {
            [$id] = $this->udajeZFormulare($brana, $data);
            $noveTajemstvi = filled($data[$brana === 'comgate' ? 'comgate_secret' : 'moone_client_secret'] ?? null);

            if ($noveTajemstvi || $id !== NastaveniPlateb::udajeKlienta($brana)['id']) {
                throw ValidationException::withMessages(['data.rezim' => 'Nové údaje nejdřív ověř tlačítkem Ověřit spojení, pak přepni na ostrý režim.']);
            }
        }

        NastaveniPlateb::uloz(array_intersect_key($data, array_flip(['brana', 'comgate_merchant', 'comgate_secret', 'moone_client_id', 'moone_client_secret'])));

        if ($rezim === NastaveniPlateb::REZIM_OSTRY && ($duvod = NastaveniPlateb::procNeOstre())) {
            throw ValidationException::withMessages(['data.rezim' => 'Ostrý režim nejde zapnout: '.$duvod]);
        }

        NastaveniPlateb::uloz(['rezim' => $rezim]);
        $this->vycistitTajemstvi();

        if ($rezim !== $predtim) {
            AuditLogger::record('platby.rezim', null, $rezim === NastaveniPlateb::REZIM_OSTRY
                ? 'Platby přepnuty na OSTRÝ režim ('.NastaveniPlateb::BRANY[$brana].')'
                : 'Platby přepnuty na testovací režim', ['rezim' => $predtim], ['rezim' => $rezim]);
        }

        Notification::make()->title('Uloženo')->body(NastaveniPlateb::prehled()['chyba'] ?? NastaveniPlateb::prehled()['popis'])->success()->send();
    }

    /** @return array{0: ?string, 1: ?string} */
    private function udajeZFormulare(string $brana, array $data): array
    {
        $ulozene = NastaveniPlateb::udajeKlienta($brana);

        return $brana === 'comgate'
            ? [filled($data['comgate_merchant'] ?? null) ? trim($data['comgate_merchant']) : null, filled($data['comgate_secret'] ?? null) ? trim($data['comgate_secret']) : $ulozene['tajemstvi']]
            : [filled($data['moone_client_id'] ?? null) ? trim($data['moone_client_id']) : null, filled($data['moone_client_secret'] ?? null) ? trim($data['moone_client_secret']) : $ulozene['tajemstvi']];
    }

    /** Tajemství po uložení z formuláře pryč (Livewire by ho jinak držel ve stavu stránky). */
    private function vycistitTajemstvi(): void
    {
        $this->data['comgate_secret'] = null;
        $this->data['moone_client_secret'] = null;
        NastaveniPlateb::zapomen();
    }

    private static function stavOvereni(string $brana): HtmlString
    {
        if (! isset(NastaveniPlateb::BRANY[$brana])) {
            return new HtmlString('');
        }

        $kdy = NastaveniPlateb::overenoKdy($brana);

        return new HtmlString($kdy
            ? '<p style="font-size: .875rem; color: var(--success-600);">Údaje ověřené '.e(Carbon::parse($kdy)->format('j. n. Y H:i')).'.</p>'
            : '<p style="font-size: .875rem; color: var(--warning-600);">Údaje nejsou ověřené – před ostrým režimem klikni na Ověřit spojení.</p>');
    }
}
