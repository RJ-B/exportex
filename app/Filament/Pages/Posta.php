<?php

namespace App\Filament\Pages;

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
        return self::sekceVypnuta() ? null : 'Pošta neodchází – aplikace není propojená s Poštou';
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
        return 'Kontakt';
    }

    /** Stránka s formulářem na webu – když ji projekt má (routa „kontakt“). */
    public static function odkazSekce(): ?string
    {
        return Route::has(self::routaKontaktu()) ? route(self::routaKontaktu(), absolute: false) : null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return static::webovy() ? static::skupinaObsahu() : 'Nastavení';
    }

    public static function getNavigationLabel(): string
    {
        return static::webovy() ? 'Kontakt a formulář' : 'Pošta';
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
                Section::make('Formulář')
                    ->description('Kam chodí zprávy, které návštěvníci pošlou z formuláře na webu.')
                    ->visible(fn () => static::webovy())
                    ->schema([
                        TextInput::make('formular_prijemce')->label('Zprávy z formuláře posílat na')->email()
                            ->placeholder(fn () => ZakladniUdaje::get('email') ?: 'info@firma.cz')
                            ->helperText('Prázdné = na kontaktní e-mail z Hlavičky a patičky.'),
                    ]),
                Section::make('Odesílatel')
                    ->description('Adresy, ze kterých aplikace smí posílat, přiděluje správce Pošty. Jinou adresu Pošta nahradí výchozí.')
                    ->visible(fn () => Propojeni::propojeno())
                    ->schema([
                        Select::make('od')
                            ->label('Výchozí odesílatel')
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

        Notification::make()->title('Uloženo')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([$this->prepinacSekce()]),
            Action::make('propojit')
                ->label(fn () => Propojeni::propojeno() ? 'Propojit znovu' : 'Propojit s poštou')
                ->icon('heroicon-o-link')
                ->color(fn () => Propojeni::propojeno() ? 'gray' : 'primary')
                ->modalHeading('Propojit s poštou')
                ->modalDescription('V Poště správce přidělí adresy, ze kterých aplikace smí posílat, a povolí. Klíče si aplikace vyzvedne sama a uloží šifrovaně.')
                ->modalSubmitActionLabel('Pokračovat do Pošty')
                ->schema([
                    TextInput::make('url')->label('Adresa Pošty')->url()->required()->default(fn () => Propojeni::url()),
                    // Stará schránka aplikace (nastavení nebo .env) – Pošta ji může převzít i s heslem.
                    Toggle::make('prevzit')
                        ->label(fn () => 'Převzít stávající schránku ('.(StaraSchranka::popis()['adresa'] ?? '').')')
                        ->helperText('Aplikace dosud posílá ze své schránky. Když převzetí v Poště správce povolí, pošle jí aplikace údaje i heslo přímo ze serveru; po zkušebním e-mailu se stará schránka z aplikace smaže.')
                        ->default(true)
                        ->visible(fn () => StaraSchranka::popis() !== null),
                ])
                ->action(function (array $data) {
                    try {
                        $this->redirect(app(Propojeni::class)->zacni($data['url'], (bool) ($data['prevzit'] ?? false)));
                    } catch (Throwable $e) {
                        Notification::make()->title('Propojení nejde začít')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('rucne')
                ->label('Klíč ručně')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->modalHeading('Napojit ručně klíčem z Pošty')
                ->modalDescription('Záloha k tlačítku Propojit s poštou: v Poště Aplikace → Přidat ručně ukáže API klíč a tajemství webhooků jednou.')
                ->schema([
                    TextInput::make('url')->label('Adresa Pošty')->url()->required()->default(fn () => Propojeni::url()),
                    TextInput::make('token')->label('API klíč')->password()->revealable()->required()->autocomplete('off'),
                    TextInput::make('tajemstvi')->label('Tajemství webhooků')->password()->revealable()->autocomplete('off')
                        ->helperText('Bez něj aplikace výsledek zpráv zjišťuje dotazem (každých pár minut).'),
                ])
                ->action(function (array $data) {
                    try {
                        $aplikace = app(Propojeni::class)->rucne($data['url'], $data['token'], $data['tajemstvi'] ?? null);
                        Notification::make()->title('Napojeno na Poštu')->body('Aplikace „'.($aplikace['nazev'] ?? '?').'“.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Napojení nevyšlo')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('overit')
                ->label('Ověřit')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn () => Propojeni::propojeno())
                ->action(function (): void {
                    $vysledek = app(Propojeni::class)->otestuj();

                    Notification::make()
                        ->title($vysledek['ok'] ? 'Pošta odpovídá' : 'Pošta neodpovídá')
                        ->body($vysledek['zprava'])
                        ->{$vysledek['ok'] ? 'success' : 'danger'}()
                        ->persistent(! $vysledek['ok'])
                        ->send();
                }),
            Action::make('zkusebni')
                ->label('Poslat zkušební e-mail')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => Propojeni::propojeno())
                ->modalDescription(fn () => 'Pošle se na '.auth()->user()->email.' přes Poštu.')
                ->action(function (): void {
                    try {
                        $odeslano = Mail::raw('Zkušební e-mail z '.config('app.name').' – odesílání přes Poštu funguje.', fn ($m) => $m
                            ->to(auth()->user()->email)
                            ->subject(config('app.name').': zkouška odesílání'));

                        // Po převzetí staré schránky: až Pošta tuhle zprávu potvrdí, stará schránka se smaže.
                        $id = (string) $odeslano?->getMessageId();
                        if (str_starts_with($id, PostaTransport::PREDPONA_ID)) {
                            StaraSchranka::zkouska(substr($id, strlen(PostaTransport::PREDPONA_ID)));
                        }

                        Notification::make()->title('Předáno Poště')->body('Výsledek uvidíš v Logy → E-maily. Zkontroluj schránku '.auth()->user()->email.' – i spam.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Neodešlo')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('odpojit')
                ->label('Odpojit')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => Propojeni::propojeno())
                ->modalDescription('Pošta klíče hned zneplatní. Aplikace pak neodešle nic, dokud se znovu nepropojí.')
                ->action(function (): void {
                    app(Propojeni::class)->odpoj();
                    Notification::make()->title('Odpojeno od Pošty')->success()->send();
                }),
        ];
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
