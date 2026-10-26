<?php

namespace App\Platby\Filament\Stranky;

use App\Filament\Navigace;
use App\Platby\Brany\Comgate;
use App\Platby\MetodaPlatby;
use App\Platby\NastaveniPlateb;
use App\Platby\PlatbyNedostupne;
use App\Platby\Rezim;
use App\Services\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
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

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return Navigace::nastaveni();
    }

    public static function getNavigationLabel(): string
    {
        return __('platby.platebni_brana.platebni_brana');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platby.platebni_brana.platebni_brana');
    }

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
            'nabizet' => NastaveniPlateb::nabizeneBrany() ?: [NastaveniPlateb::brana() ?? 'comgate'],
            'rezim' => NastaveniPlateb::rezimVolba(),
            'comgate_merchant' => NastaveniPlateb::udajeKlienta('comgate')['id'],
            'moone_client_id' => NastaveniPlateb::udajeKlienta('moone')['id'],
            'comgate_metody' => array_values(array_diff(array_keys(self::metodyComgate() ?? []), NastaveniPlateb::comgateVypnute())),
            'apple_pay' => NastaveniPlateb::predvolbaZapnuta(MetodaPlatby::ApplePay),
            'google_pay' => NastaveniPlateb::predvolbaZapnuta(MetodaPlatby::GooglePay),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $tajne = fn (string $brana) => fn () => NastaveniPlateb::tajemstviUlozeno($brana) ? __('platby.platebni_brana.ulozeno_vypln_jen_pro_zmenu') : null;

        return $schema
            ->statePath('data')
            ->components([
                Html::make(fn () => view('filament.platby.prehled', ['prehled' => NastaveniPlateb::prehled()])),
                Section::make(__('platby.platebni_brana.brana_klienta'))
                    ->description(__('platby.platebni_brana.pres_ni_jdou_ostre_platby_na'))
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('nabizet')->label(__('platby.platebni_brana.nabizet_zakaznikum'))->required()
                            ->options(NastaveniPlateb::BRANY)
                            ->helperText(__('platby.platebni_brana.zapnute_brany_si_zakaznik_vybere_pri'))
                            ->columns(2)
                            ->columnSpanFull(),
                        ToggleButtons::make('brana')->label(__('platby.platebni_brana.udaje_brany'))->inline()->live()->required()
                            ->options(NastaveniPlateb::BRANY)
                            ->columnSpanFull(),
                        TextInput::make('comgate_merchant')->label(__('platby.platebni_brana.identifikator_obchodu_merchant'))->maxLength(40)
                            ->visible(fn ($get) => $get('brana') === 'comgate')
                            ->helperText(__('platby.platebni_brana.klientsky_portal_comgate_integrace')),
                        TextInput::make('comgate_secret')->label(__('platby.platebni_brana.heslo_obchodu_secret'))->password()->maxLength(200)
                            ->placeholder($tajne('comgate'))
                            ->visible(fn ($get) => $get('brana') === 'comgate')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null),
                        Html::make(fn () => new HtmlString('<p style="font-size: .875rem; color: inherit; opacity: .8;">'.__('platby.platebni_brana.v_portalu_comgate_nastav_url_pro', ['hodnota' => e(route('platby.webhook', 'comgate'))]).'</p>'))
                            ->visible(fn ($get) => $get('brana') === 'comgate')
                            ->columnSpanFull(),
                        TextInput::make('moone_client_id')->label(__('platby.platebni_brana.client_id'))->maxLength(64)
                            ->visible(fn ($get) => $get('brana') === 'moone')
                            ->helperText(__('platby.platebni_brana.aplikace_mo_one_paypoint_typu_platebni')),
                        TextInput::make('moone_client_secret')->label(__('platby.platebni_brana.client_secret'))->password()->maxLength(200)
                            ->placeholder($tajne('moone'))
                            ->visible(fn ($get) => $get('brana') === 'moone')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null),
                        Html::make(fn () => new HtmlString('<p style="font-size: .875rem; color: inherit; opacity: .8;">'.__('platby.platebni_brana.vysledek_platby_posila_mo_one_samo', ['hodnota' => e(route('platby.webhook', 'moone'))]).'</p>'))
                            ->visible(fn ($get) => $get('brana') === 'moone')
                            ->columnSpanFull(),
                        Html::make(fn ($get) => self::stavOvereni((string) $get('brana')))->columnSpanFull(),
                    ]),
                Section::make(__('platby.platebni_brana.zpusoby_platby_u_comgate'))
                    ->description(__('platby.platebni_brana.co_comgate_zakaznikovi_nabidne_seznam_je'))
                    ->visible(fn ($get) => $get('brana') === 'comgate')
                    ->schema([
                        CheckboxList::make('comgate_metody')->label(__('platby.platebni_brana.nabizet'))
                            ->options(fn () => collect(self::metodyComgate() ?? [])
                                ->map(fn (array $m) => filled($m['skupina']) && $m['skupina'] !== $m['nazev'] ? $m['skupina'].' – '.$m['nazev'] : $m['nazev'])
                                ->all())
                            ->columns(2)
                            ->bulkToggleable()
                            ->visible(fn () => self::metodyComgate() !== null)
                            ->rule(fn () => fn (string $attribute, $value, \Closure $fail) => blank($value) ? $fail(__('platby.platebni_brana.nech_zapnutou_aspon_jednu_metodu_treba')) : null),
                        Html::make(fn () => new HtmlString('<p style="font-size: .875rem; opacity: .8;">'.__('platby.platebni_brana.seznam_metod_se_ukaze_po_overeni', ['comgate_vypnute' => e(implode(', ', NastaveniPlateb::comgateVypnute()) ?: __('platby.platebni_brana.nic_navic'))]).'</p>'))
                            ->visible(fn () => self::metodyComgate() === null),
                    ]),
                Section::make(__('platby.platebni_brana.apple_pay_a_google_pay'))
                    ->description(__('platby.platebni_brana.aplikace_k_platbe_kartou_nabidne_i'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('apple_pay')->label(__('platby.platebni_brana.nabizet_apple_pay'))
                            ->helperText(fn () => self::stavPredvolby(MetodaPlatby::ApplePay)),
                        Toggle::make('google_pay')->label(__('platby.platebni_brana.nabizet_google_pay'))
                            ->helperText(fn () => self::stavPredvolby(MetodaPlatby::GooglePay)),
                    ]),
                Section::make(__('platby.platebni_brana.rezim'))
                    ->schema([
                        ToggleButtons::make('rezim')->label(__('platby.platebni_brana.platby'))->inline()->required()
                            ->options([NastaveniPlateb::REZIM_TEST => __('platby.platebni_brana.testovaci'), NastaveniPlateb::REZIM_OSTRY => __('platby.platebni_brana.ostry')])
                            ->colors([NastaveniPlateb::REZIM_TEST => 'warning', NastaveniPlateb::REZIM_OSTRY => 'success'])
                            ->disabled(fn () => ! app()->isProduction())
                            ->helperText(fn () => app()->isProduction()
                                ? __('platby.platebni_brana.testovaci_testovaci_brana_sim_ren_na')
                                : __('platby.platebni_brana.testovaci_web_plati_vzdy_testovaci')),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('overit')
                ->label(__('platby.platebni_brana.overit_spojeni'))
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
            Notification::make()->title(__('platby.platebni_brana.vypln_pristupove_udaje_brany', ['hodnota' => NastaveniPlateb::BRANY[$brana]]))->warning()->send();

            return;
        }

        try {
            $zprava = NastaveniPlateb::branaKlienta($brana, Rezim::Ostry, ['id' => $id, 'tajemstvi' => $tajemstvi])->overSpojeni();
        } catch (Throwable $e) {
            Notification::make()->title(__('platby.platebni_brana.spojeni_s_branou_nefunguje'))->body($e->getMessage())->danger()->send();

            return;
        }

        // Povedlo se – údaje uložit (bez změny režimu) a označit jako ověřené.
        NastaveniPlateb::uloz(array_intersect_key($data, array_flip(['brana', 'comgate_merchant', 'comgate_secret', 'moone_client_id', 'moone_client_secret'])));
        NastaveniPlateb::oznacOvereno($brana);
        $this->vycistitTajemstvi();

        // Po prvním ověření se ukáže seznam metod – předvyplnit podle uložené volby.
        $this->data['comgate_metody'] = array_values(array_diff(array_keys(self::metodyComgate() ?? []), NastaveniPlateb::comgateVypnute()));

        AuditLogger::record('platby.overeno', null, __('platby.platebni_brana.platebni_brana_spojeni_overeno', ['hodnota' => NastaveniPlateb::BRANY[$brana]]));
        Notification::make()->title(__('platby.platebni_brana.spojeni_funguje'))->body($zprava)->success()->send();
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
                throw ValidationException::withMessages(['data.rezim' => __('platby.platebni_brana.nove_udaje_nejdriv_over_tlacitkem_overit')]);
            }

            // Každá nabízená brána musí být ověřená – jinak by zákazník vybral nefunkční.
            foreach ((array) ($data['nabizet'] ?? []) as $nabizena) {
                if (app()->isProduction() && ($duvod = NastaveniPlateb::procNeOstre($nabizena))) {
                    throw ValidationException::withMessages(['data.nabizet' => __('platby.platebni_brana.brana_nejde_nabizet_ostre', ['hodnota' => NastaveniPlateb::BRANY[$nabizena], 'duvod' => $duvod])]);
                }
            }
        }

        NastaveniPlateb::uloz(array_intersect_key($data, array_flip(['brana', 'nabizet', 'comgate_merchant', 'comgate_secret', 'moone_client_id', 'moone_client_secret', 'apple_pay', 'google_pay'])));

        if (array_key_exists('comgate_metody', $data) && ($metody = self::metodyComgate()) !== null) {
            // Vypnuté = nabízené Comgatem a odškrtnuté; dřív vypnuté, které Comgate teď nehlásí, zůstanou vypnuté.
            $nehlasene = array_diff(NastaveniPlateb::comgateVypnute(), array_keys($metody));
            NastaveniPlateb::uloz(['comgate_vypnute' => [...$nehlasene, ...array_diff(array_keys($metody), (array) $data['comgate_metody'])]]);
        }

        if ($rezim === NastaveniPlateb::REZIM_OSTRY && ($duvod = NastaveniPlateb::procNeOstre())) {
            throw ValidationException::withMessages(['data.rezim' => __('platby.platebni_brana.ostry_rezim_nejde_zapnout', ['duvod' => $duvod])]);
        }

        NastaveniPlateb::uloz(['rezim' => $rezim]);
        $this->vycistitTajemstvi();

        if ($rezim !== $predtim) {
            AuditLogger::record('platby.rezim', null, $rezim === NastaveniPlateb::REZIM_OSTRY
                ? __('platby.platebni_brana.platby_prepnuty_na_ostry_rezim', ['nabizene_brany' => implode(', ', array_map(fn (string $b) => NastaveniPlateb::BRANY[$b], NastaveniPlateb::nabizeneBrany()))])
                : __('platby.platebni_brana.platby_prepnuty_na_testovaci_rezim'), ['rezim' => $predtim], ['rezim' => $rezim]);
        }

        Notification::make()->title(__('platby.platebni_brana.ulozeno'))->body(NastaveniPlateb::prehled()['chyba'] ?? NastaveniPlateb::prehled()['popis'])->success()->send();
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

    /** Co k metodě hlásí brána, přes kterou se teď platí. */
    private static function stavPredvolby(MetodaPlatby $metoda): string
    {
        try {
            $brana = NastaveniPlateb::aktivni();
        } catch (PlatbyNedostupne) {
            return __('platby.platebni_brana.platby_ted_nefunguji_metoda_se_nenabizi');
        }

        return in_array($metoda, rescue(fn () => $brana->predvolby(), [], false), true)
            ? __('platby.platebni_brana.ma', ['nazev' => $brana->nazev(), 'popis' => $metoda->popis()])
            : __('platby.platebni_brana.ted_nehlasi_u_comgate_patri_k', ['nazev' => $brana->nazev(), 'popis' => $metoda->popis()]);
    }

    /**
     * Metody, které má obchod klienta povolené v Comgate (uložené ověřené údaje).
     * Null = údaje chybí / nejsou ověřené / Comgate neodpovídá.
     *
     * @return array<string, array{nazev: string, skupina: string}>|null
     */
    private static function metodyComgate(): ?array
    {
        if (! NastaveniPlateb::overeno('comgate')) {
            return null;
        }

        $brana = NastaveniPlateb::branaKlienta('comgate');

        return $brana instanceof Comgate ? $brana->metody() : null;
    }

    private static function stavOvereni(string $brana): HtmlString
    {
        if (! isset(NastaveniPlateb::BRANY[$brana])) {
            return new HtmlString('');
        }

        $kdy = NastaveniPlateb::overenoKdy($brana);

        return new HtmlString($kdy
            ? '<p style="font-size: .875rem; color: var(--success-600);">'.__('platby.platebni_brana.udaje_overene', ['parse' => e(Carbon::parse($kdy)->format(__('formaty.datum_cas')))]).'</p>'
            : '<p style="font-size: .875rem; color: var(--warning-600);">'.__('platby.platebni_brana.udaje_nejsou_overene_pred_ostrym_rezimem').'</p>');
    }
}
