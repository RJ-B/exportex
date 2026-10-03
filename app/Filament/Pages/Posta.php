<?php

namespace App\Filament\Pages;

use App\Support\Posta as NastaveniPosty;
use App\Filament\Support\CastObsahuWebu;
use App\Support\NastaveniWebu;
use App\Support\PostaDns;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Schránka, ze které aplikace posílá, a návod na DNS domény pro klienta
 * (aby pošta chodila a nepadala do spamu).
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

    /** Výsledek poslední kontroly DNS (null = zatím nekontrolováno). */
    public ?array $dns = null;

    /** Prefix MX z Email Profi, zjištěný kontrolou – doplní se do návodu. */
    public ?string $prefixMx = null;

    /**
     * Web s kontaktní stránkou (routa „kontakt“): Pošta je Obsah webu → Kontakt
     * a formulář. Web bez ní (maily posílá třeba rezervace) i aplikace bez webu
     * ji mají v Nastavení.
     */
    public static function webovy(): bool
    {
        return static::obsahWebu() !== null && \Illuminate\Support\Facades\Route::has(self::routaKontaktu());
    }

    /** Nenastavená schránka = nic neodchází. Štítek, dokud ji někdo nevyplní. */
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
        return self::sekceVypnuta() ? null : 'Pošta neodchází – není nastavená schránka';
    }

    /**
     * Varovat jen když opravdu nic neodchází: bez schránky v aplikaci a se
     * serverem na log/array. Web, který posílá podle .env serveru, maily posílá.
     */
    private static function nicNeodchazi(): bool
    {
        return ! NastaveniPosty::kompletni() && in_array(config('mail.default'), ['log', 'array'], true);
    }

    private static function sekceVypnuta(): bool
    {
        return static::webovy() && ! \App\Support\SekceWebu::zapnuta('formular');
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
        return \Illuminate\Support\Facades\Route::has(self::routaKontaktu()) ? route(self::routaKontaktu(), absolute: false) : null;
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
        $posta = collect(NastaveniPosty::nacti())->except('heslo_ulozeno')->all();

        // Heslo se do formuláře nikdy nevrací – jen prázdné pole na změnu.
        $this->form->fill($posta
            + ['heslo' => null, 'formular_prijemce' => NastaveniWebu::get('formular_prijemce'),
                'poskytovatel' => self::poskytovatelPodle($posta['host'] ?? null, $posta['port'] ?? null, $posta['sifrovani'] ?? null)]);
    }

    /** Předvolba, které odpovídá uložený server (jinak „jiný“ – server a port ručně). */
    private static function poskytovatelPodle(?string $host, ?string $port, ?string $sifrovani): string
    {
        foreach (NastaveniPosty::POSKYTOVATELE as $klic => $p) {
            if ($p['host'] === $host && $p['port'] === (string) $port && $p['sifrovani'] === $sifrovani) {
                return $klic;
            }
        }

        return 'jiny';
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
                            ->placeholder(fn () => \App\Support\ZakladniUdaje::get('email') ?: 'info@firma.cz')
                            ->helperText('Prázdné = na kontaktní e-mail z Hlavičky a patičky.'),
                    ]),
                Section::make('Schránka pro odesílání')
                    ->description('Odtud aplikace posílá všechno – formuláře, upozornění i obnovu hesla. Schránka je u poskytovatele pošty (Seznam Email Profi, Forpsi…), na našem serveru pošta není.')
                    ->columns(2)
                    ->schema([
                        // Předvolba jen vyplní server, port a šifrování – ukládají se ta pole.
                        Select::make('poskytovatel')
                            ->label('Poskytovatel pošty')
                            ->options(collect(NastaveniPosty::POSKYTOVATELE)->map(fn ($p) => $p['nazev'])->all() + ['jiny' => 'Jiný (server a port ručně)'])
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, $set): void {
                                if ($p = NastaveniPosty::POSKYTOVATELE[$state] ?? null) {
                                    $set('host', $p['host']);
                                    $set('port', $p['port']);
                                    $set('sifrovani', $p['sifrovani']);
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('uzivatel')
                            ->label('Schránka')
                            ->email()
                            ->required()
                            ->live(onBlur: true)   // návod na DNS se přepočítá pro doménu schránky
                            ->placeholder('info@firma.cz')
                            ->helperText('Odesílatelem bude tahle adresa – server pošty jinou obvykle nepustí.'),
                        TextInput::make('heslo')
                            ->label('Heslo')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->regex(NastaveniPosty::HESLO_ASCII)
                            ->validationMessages(['regex' => 'Heslo nesmí obsahovat diakritiku – při odesílání ho server pošty nepřijme. Změň ho ve schránce.'])
                            ->required(fn () => ! NastaveniPosty::nacti()['heslo_ulozeno'])
                            ->placeholder(fn () => NastaveniPosty::nacti()['heslo_ulozeno'] ? '•••••••• uložené' : '')
                            ->helperText(fn () => NastaveniPosty::nacti()['heslo_ulozeno']
                                ? 'Uložené. Vyplň jen, když ho chceš změnit.'
                                : 'Heslo ke schránce, bez diakritiky.'),
                        TextInput::make('jmeno')
                            ->label('Jméno odesílatele')
                            ->placeholder(config('app.name')),
                        Select::make('sifrovani')
                            ->label('Šifrování')
                            ->options(NastaveniPosty::SIFROVANI)
                            ->selectablePlaceholder(false),
                        TextInput::make('host')->label('Server pro odesílání')->required()->live(onBlur: true)
                            ->helperText('Např. smtp.seznam.cz, smtp.forpsi.com.'),
                        TextInput::make('port')->label('Port')->integer()->required(),
                    ]),
            ]);
    }

    public function uloz(): void
    {
        $data = $this->form->getState();

        // Nejdřív se přihlásit – nefunkční heslo by se jinak tiše uložilo
        // a maily by přestaly odcházet.
        $test = NastaveniPosty::otestuj($data);

        if (! $test['ok']) {
            throw ValidationException::withMessages([
                filled($data['heslo'] ?? null) ? 'data.heslo' : 'data.uzivatel' => $test['zprava'],
            ]);
        }

        NastaveniPosty::uloz($data);

        if (static::webovy()) {
            NastaveniWebu::uloz(['formular_prijemce' => $data['formular_prijemce'] ?? null]);
        }
        // Heslo po uložení z formuláře pryč, ať nezůstává v prohlížeči.
        $this->data['heslo'] = null;
        $this->dns = null;

        Notification::make()->title('Uloženo')->body(NastaveniPosty::popisStavu())->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([$this->prepinacSekce()]),
            Action::make('vyzkouset')
                ->label('Vyzkoušet přihlášení')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function (): void {
                    $vysledek = NastaveniPosty::otestuj($this->data ?? []);

                    Notification::make()
                        ->title($vysledek['ok'] ? 'Přihlášení funguje' : 'Přihlášení nefunguje')
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
                ->modalDescription(fn () => 'Pošle se na '.auth()->user()->email.' přes uloženou schránku.')
                ->action(function (): void {
                    try {
                        Mail::raw('Zkušební e-mail z '.config('app.name').' – odesílání funguje.', fn ($m) => $m
                            ->to(auth()->user()->email)
                            ->subject(config('app.name').': zkouška odesílání'));

                        Notification::make()->title('Odesláno')->body('Zkontroluj schránku '.auth()->user()->email.' – a jestli nedorazil do spamu.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Neodešlo')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }

    public function zkontrolujDns(): void
    {
        $domena = $this->domena();

        $poskytovatel = $this->poskytovatelDns();

        // DNS se kontroluje jen u poskytovatele, jehož záznamy známe (Seznam, Forpsi).
        $this->dns = $domena && $poskytovatel ? PostaDns::over($domena, $poskytovatel) : null;
        $this->prefixMx = $domena && $poskytovatel === 'seznam' ? PostaDns::prefixMx($domena) : null;
    }

    /** Poskytovatel podle serveru z formuláře (jinak uloženého) – podle něj návod a kontrola DNS. */
    public function poskytovatelDns(): ?string
    {
        return PostaDns::poskytovatel($this->data['host'] ?? NastaveniPosty::nacti()['host']);
    }

    /** Doména schránky – z formuláře, jinak z uložené. */
    public function domena(): ?string
    {
        $schranka = $this->data['uzivatel'] ?? NastaveniPosty::nacti()['uzivatel'];

        return str_contains((string) $schranka, '@') ? Str::lower(Str::afterLast($schranka, '@')) : null;
    }

    protected function getViewData(): array
    {
        $domena = $this->domena();

        $poskytovatel = $this->poskytovatelDns();

        return [
            'stav' => NastaveniPosty::popisStavu(),
            'domena' => $domena,
            'poskytovatel' => $poskytovatel,
            'nazevPoskytovatele' => $poskytovatel ? PostaDns::POSKYTOVATELE[$poskytovatel]['nazev'] : null,
            'server' => $this->data['host'] ?? NastaveniPosty::nacti()['host'],
            'navod' => $domena && $poskytovatel ? PostaDns::navod($domena, $this->data['uzivatel'] ?? null, $this->prefixMx, $poskytovatel) : [],
            'bezplatna' => $poskytovatel === 'seznam' && NastaveniPosty::bezplatna($this->data['uzivatel'] ?? null),
        ];
    }
}
