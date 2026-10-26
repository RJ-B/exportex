<?php

namespace App\Filament\Pages;

use App\Filament\Navigace;
use App\Filament\Support\CastObsahuWebu;
use App\Support\NastaveniWebu;
use App\Support\Posta\Odchozi;
use App\Support\Posta\PostaTransport;
use App\Support\Posta\Propojeni;
use App\Support\Posta\StaraSchranka;
use App\Support\Posta\StavPosty;
use App\Support\SekceWebu;
use App\Support\ZakladniUdaje;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Pošta aplikace: propojení s Poštou (posta.simren.cz), přes kterou aplikace
 * posílá všechno – formuláře, upozornění, obnovu hesla. Schránky, DNS domény
 * (SPF, DKIM, DMARC) i opakování řeší Pošta; tady je jen „Propojit s poštou“
 * a výchozí odesílatel z adres, které aplikaci přidělil správce Pošty.
 *
 * U webu je to Obsah webu → Kontakt a formulář (každý web má formulář) a
 * navíc kam chodí zprávy z formuláře. U aplikace bez webu (sablona.obsah_webu
 * = null) Nastavení → Pošta.
 */
class Posta extends Page
{
    use CastObsahuWebu {
        CastObsahuWebu::getNavigationGroup as protected skupinaObsahu;
    }

    protected string $view = 'filament.pages.posta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $slug = 'posta';

    /** V Obsahu webu před SEO a měřením; v Nastavení před Uživateli. */
    protected static ?int $navigationSort = 800;

    public ?array $data = [];

    /**
     * Web s kontaktní stránkou (routa „kontakt“): Pošta je Obsah webu → Kontakt
     * a formulář. Web bez ní (maily posílá třeba rezervace) i aplikace bez webu
     * ji mají v Nastavení.
     */
    public static function webovy(): bool
    {
        return static::obsahWebu() !== null && Route::has(self::routaKontaktu());
    }

    /** Nepropojená aplikace = nic neodchází. Štítek, dokud ji někdo nepropojí. */
    public static function getNavigationBadge(): ?string
    {
        return self::sekceVypnuta() ? 'vypnuto' : (self::nicNeodchazi() ? '!' : null);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::sekceVypnuta() ? 'gray' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return self::sekceVypnuta() ? null : __('posta.posta.posta_neodchazi_aplikace_neni_propojena');
    }

    /** Varovat, když aplikace není propojená s Poštou (a .env nic neposílá). */
    private static function nicNeodchazi(): bool
    {
        return ! Propojeni::propojeno() && in_array(config('mail.default'), ['log', 'array'], true);
    }

    private static function sekceVypnuta(): bool
    {
        return static::webovy() && ! SekceWebu::zapnuta('formular');
    }

    private static function routaKontaktu(): string
    {
        return (string) config('sablona.routa_kontaktu', 'kontakt');
    }

    public static function klicSekce(): ?string
    {
        return static::webovy() ? 'formular' : null;
    }

    public static function nazevSekce(): string
    {
        return __('posta.posta.kontakt');
    }

    /** Stránka s formulářem na webu – když ji projekt má (routa „kontakt“). */
    public static function odkazSekce(): ?string
    {
        return Route::has(self::routaKontaktu()) ? route(self::routaKontaktu(), absolute: false) : null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return static::webovy() ? static::skupinaObsahu() : Navigace::nastaveni();
    }

    public static function getNavigationLabel(): string
    {
        return static::webovy() ? __('posta.posta.kontakt_a_formular') : __('posta.posta.posta');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    public function mount(): void
    {
        // Přidělené adresy a kopie do Odeslaných se mění i v portálu – načíst čerstvé (chyba nevadí, platí uložené).
        if (Propojeni::propojeno()) {
            app(Propojeni::class)->otestuj();
        }

        $this->form->fill([
            'od' => Propojeni::odesilatel()['adresa'] ?? null,
            'formular_prijemce' => NastaveniWebu::get('formular_prijemce'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('posta.posta.formular'))
                    ->description(__('posta.posta.kam_chodi_zpravy_ktere_navstevnici'))
                    ->visible(fn () => static::webovy())
                    ->schema([
                        TextInput::make('formular_prijemce')->label(__('posta.posta.zpravy_z_formulare_posilat_na'))->email()
                            ->placeholder(fn () => ZakladniUdaje::get('email') ?: 'info@firma.cz')
                            ->helperText(__('posta.posta.prazdne_na_kontaktni_e_mail_z')),
                    ]),
                Section::make(__('posta.posta.odesilatel'))
                    ->description(__('posta.posta.adresy_ze_kterych_aplikace_smi_posilat'))
                    ->visible(fn () => Propojeni::propojeno())
                    ->schema([
                        Select::make('od')
                            ->label(__('posta.posta.vychozi_odesilatel'))
                            ->options(fn () => collect(Propojeni::adresy())->mapWithKeys(fn ($a) => [$a['adresa'] => $a['jmeno'] ? $a['jmeno'].' <'.$a['adresa'].'>' : $a['adresa']])->all())
                            ->native(false)
                            ->selectablePlaceholder(false),
                    ]),
            ]);
    }

    public function uloz(): void
    {
        $data = $this->form->getState();

        if (Propojeni::propojeno() && filled($data['od'] ?? null)) {
            Propojeni::nastavOdesilatele($data['od']);
        }

        if (static::webovy()) {
            NastaveniWebu::uloz(['formular_prijemce' => $data['formular_prijemce'] ?? null]);
        }

        Notification::make()->title(__('posta.posta.ulozeno'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([$this->prepinacSekce()]),
            Action::make('propojit')
                ->label(fn () => Propojeni::propojeno() ? __('posta.posta.propojit_znovu') : __('posta.posta.propojit_s_postou'))
                ->icon('heroicon-o-link')
                ->color(fn () => Propojeni::propojeno() ? 'gray' : 'primary')
                ->modalHeading(__('posta.posta.propojit_s_postou'))
                ->modalDescription(__('posta.posta.v_poste_spravce_prideli_adresy_ze'))
                ->modalSubmitActionLabel(__('posta.posta.pokracovat_do_posty'))
                ->schema([
                    TextInput::make('url')->label(__('posta.posta.adresa_posty'))->url()->required()->default(fn () => Propojeni::url()),
                    // Stará schránka aplikace (nastavení nebo .env) – Pošta ji může převzít i s heslem.
                    Toggle::make('prevzit')
                        ->label(fn () => __('posta.posta.prevzit_stavajici_schranku', ['popis' => (StaraSchranka::popis()['adresa'] ?? '')]))
                        ->helperText(__('posta.posta.aplikace_dosud_posila_ze_sve_schranky'))
                        ->default(true)
                        ->visible(fn () => StaraSchranka::popis() !== null),
                ])
                ->action(function (array $data) {
                    try {
                        $this->redirect(app(Propojeni::class)->zacni($data['url'], (bool) ($data['prevzit'] ?? false)));
                    } catch (Throwable $e) {
                        Notification::make()->title(__('posta.posta.propojeni_nejde_zacit'))->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('rucne')
                ->label(__('posta.posta.klic_rucne'))
                ->icon('heroicon-o-key')
                ->color('gray')
                ->modalHeading(__('posta.posta.napojit_rucne_klicem_z_posty'))
                ->modalDescription(__('posta.posta.zaloha_k_tlacitku_propojit_s_postou'))
                ->schema([
                    TextInput::make('url')->label(__('posta.posta.adresa_posty'))->url()->required()->default(fn () => Propojeni::url()),
                    TextInput::make('token')->label(__('posta.posta.api_klic'))->password()->revealable()->required()->autocomplete('off'),
                    TextInput::make('tajemstvi')->label(__('posta.posta.tajemstvi_webhooku'))->password()->revealable()->autocomplete('off')
                        ->helperText(__('posta.posta.bez_nej_aplikace_vysledek_zprav_zjistuje')),
                ])
                ->action(function (array $data) {
                    try {
                        $aplikace = app(Propojeni::class)->rucne($data['url'], $data['token'], $data['tajemstvi'] ?? null);
                        Notification::make()->title(__('posta.posta.napojeno_na_postu'))->body('Aplikace „'.($aplikace['nazev'] ?? '?').'“.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title(__('posta.posta.napojeni_nevyslo'))->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('overit')
                ->label(__('posta.posta.overit'))
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn () => Propojeni::propojeno())
                ->action(function (): void {
                    $vysledek = app(Propojeni::class)->otestuj();

                    Notification::make()
                        ->title($vysledek['ok'] ? __('posta.posta.posta_odpovida') : __('posta.posta.posta_neodpovida'))
                        ->body($vysledek['zprava'])
                        ->{$vysledek['ok'] ? 'success' : 'danger'}()
                        ->persistent(! $vysledek['ok'])
                        ->send();
                }),
            Action::make('zkusebni')
                ->label(__('posta.posta.poslat_zkusebni_e_mail'))
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => Propojeni::propojeno())
                ->modalDescription(fn () => __('posta.posta.posle_se_na_pres_postu', ['email' => auth()->user()->email]))
                ->action(function (): void {
                    try {
                        $odeslano = Mail::raw(__('posta.posta.zkusebni_e_mail_z_odesilani_pres', ['name' => config('app.name')]), fn ($m) => $m
                            ->to(auth()->user()->email)
                            ->subject(__('posta.posta.zkouska_odesilani', ['name' => config('app.name')])));

                        // Po převzetí staré schránky: až Pošta tuhle zprávu potvrdí, stará schránka se smaže.
                        $id = (string) $odeslano?->getMessageId();
                        if (str_starts_with($id, PostaTransport::PREDPONA_ID)) {
                            StaraSchranka::zkouska(substr($id, strlen(PostaTransport::PREDPONA_ID)));
                        }

                        Notification::make()->title(__('posta.posta.predano_poste'))->body(__('posta.posta.vysledek_uvidis_v_logy_e_maily', ['email' => auth()->user()->email]))->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title(__('posta.posta.neodeslo'))->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('odpojit')
                ->label(__('posta.posta.odpojit'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => Propojeni::propojeno())
                ->modalDescription(__('posta.posta.posta_klice_hned_zneplatni_aplikace_pak'))
                ->action(function (): void {
                    app(Propojeni::class)->odpoj();
                    Notification::make()->title(__('posta.posta.odpojeno_od_posty'))->success()->send();
                }),
        ];
    }

    /**
     * Kopie do Odeslaných u přidělené adresy – klient vidí, co aplikace poslala,
     * ve své poště. Servery i heslo zná Pošta, tady je jen přepínač.
     */
    public function kopieAction(): Action
    {
        return Action::make('kopie')
            ->label(fn (array $arguments) => ($arguments['zapnout'] ?? false) ? __('posta.posta.ukladat_do_odeslanych') : __('posta.posta.neukladat'))
            ->icon(fn (array $arguments) => ($arguments['zapnout'] ?? false) ? 'heroicon-o-inbox-arrow-down' : 'heroicon-o-x-mark')
            ->color(fn (array $arguments) => ($arguments['zapnout'] ?? false) ? 'primary' : 'gray')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => (($arguments['zapnout'] ?? false) ? __('posta.posta.ukladat_kopie_do_odeslanych') : __('posta.posta.neukladat_kopie_do_odeslanych')).($arguments['adresa'] ?? ''))
            ->modalDescription(fn (array $arguments) => ($arguments['zapnout'] ?? false)
                ? __('posta.posta.kazdy_e_mail_ktery_aplikace_z')
                : __('posta.posta.e_maily_z_aplikace_se_dal'))
            ->action(function (array $arguments): void {
                $vysledek = app(Propojeni::class)->nastavKopii((string) $arguments['adresa'], (bool) ($arguments['zapnout'] ?? false));

                Notification::make()
                    ->title($vysledek['ok'] ? (($arguments['zapnout'] ?? false) ? __('posta.posta.kopie_se_ukladaji_do_odeslanych') : __('posta.posta.kopie_do_odeslanych_vypnute')) : __('posta.posta.nepovedlo_se'))
                    ->body($vysledek['zprava'] ?: null)
                    ->{$vysledek['ok'] ? 'success' : 'danger'}()
                    ->persistent(! $vysledek['ok'])
                    ->send();
            });
    }

    protected function getViewData(): array
    {
        return [
            'propojeni' => Propojeni::popis(),
            'stav' => StavPosty::proZdravi(),
            'fronta' => Odchozi::query()->count(),
            'prevzeti' => Propojeni::prevzeti(),
            'stara' => StaraSchranka::popis(),
        ];
    }
}
