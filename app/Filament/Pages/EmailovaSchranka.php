<?php

namespace App\Filament\Pages;

use App\Filament\Navigace;
use App\Support\CasAplikace;
use App\Support\Posta\Poskytovatele;
use App\Support\Posta\SchrankaWebu;
use App\Support\ZakladniUdaje;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nastavení → E-mailová schránka: ze které schránky web posílá (SchrankaWebu).
 * Poskytovatel s krátkým návodem a předvyplněným serverem, adresa, jméno,
 * heslo, zkušební e-mail, stav DNS domény a Vrátit na systémovou schránku.
 * Schránka může být jakákoli – nemusí být na doméně webu.
 *
 * Jen správce. Heslo jde jen ze serveru do Pošty, web ho neukládá a do
 * formuláře se nevrací. Kde poslat co z jaké schránky (formuláře, doklady…),
 * je dál na stránce E-mail (Email) – odkaz dole.
 */
class EmailovaSchranka extends Page
{
    protected string $view = 'filament.pages.emailova-schranka';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $slug = 'emailova-schranka';

    protected static ?int $navigationSort = 804;

    public ?array $data = [];

    /** Stav z Pošty (GET /schranka – bez hesla). @var array<string, mixed>|null */
    public ?array $stav = null;

    /** @var array<string, mixed>|null DNS z Pošty (načte se jednou za zobrazení stránky). */
    public ?array $dnsData = null;

    public ?string $chyba = null;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return Navigace::nastaveni();
    }

    public static function getNavigationLabel(): string
    {
        return __('pruvodce.emailova_schranka.emailova_schranka');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pruvodce.emailova_schranka.emailova_schranka');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    public function mount(): void
    {
        $this->nacti();
        $this->navratZPrihlaseni();
    }

    public function form(Schema $schema): Schema
    {
        $predvolba = fn (Get $get) => Poskytovatele::predvolba($get('poskytovatel'), $this->predvolbyPosty()) ?? [];
        // Server se zadává jen u jiného poskytovatele – u ostatních ho doplní služba pro odesílání sama.
        $servery = fn (Get $get) => $get('poskytovatel') === 'jiny';

        return $schema->statePath('data')->components([
            Section::make(__('pruvodce.emailova_schranka.odkud_web_posila'))
                ->description(__('pruvodce.emailova_schranka.potvrzeni_formulare_a_dalsi_e_maily'))
                ->afterHeader([
                    Action::make('zkusebni')->label(__('pruvodce.emailova_schranka.poslat_zkusebni_e_mail'))->icon('heroicon-o-paper-airplane')->color('gray')->size('sm')
                        ->visible(fn () => $this->schranka() !== null)
                        ->schema([TextInput::make('komu')->label(__('pruvodce.emailova_schranka.komu'))->email()->required()->default(fn () => auth()->user()?->email)])
                        ->action(fn (array $data) => $this->zkouska($data['komu'])),
                    Action::make('vratit')->label(__('pruvodce.emailova_schranka.vratit_na_systemovou'))->icon('heroicon-o-arrow-uturn-left')->color('danger')->size('sm')->link()
                        ->visible(fn () => $this->schranka() !== null && $this->smiMenit() && ($this->schranka()['smi_menit'] ?? true))
                        ->requiresConfirmation()
                        ->modalHeading(__('pruvodce.emailova_schranka.vratit_na_systemovou'))
                        ->modalDescription(__('pruvodce.emailova_schranka.e_maily_pak_pujdou_ze_systemove'))
                        ->action(fn () => $this->vrat()),
                ])
                ->schema([Placeholder::make('stav')->hiddenLabel()->content(fn () => $this->popisStavu())]),
            Section::make(fn () => $this->schranka() ? __('pruvodce.emailova_schranka.zmenit_schranku') : __('pruvodce.emailova_schranka.nastavit_schranku'))
                ->visible(fn () => $this->stav !== null && $this->smiMenit())
                ->columns(2)
                ->schema([
                    Select::make('poskytovatel')->label(__('pruvodce.emailova_schranka.poskytovatel'))
                        ->options(Poskytovatele::moznosti())->native(false)->selectablePlaceholder(false)->live()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $p = Poskytovatele::predvolba($state) ?? [];
                            foreach (['smtp_host', 'smtp_port', 'smtp_sifrovani'] as $pole) {
                                $set($pole, $p[$pole] ?? null);
                            }
                        })
                        ->columnSpanFull(),
                    Placeholder::make('navod')->hiddenLabel()->columnSpanFull()
                        ->content(fn (Get $get) => $this->navod($predvolba($get))),
                    TextInput::make('adresa')->label(__('pruvodce.emailova_schranka.e_mailova_adresa'))->email()->required()->maxLength(190)->placeholder('info@vasefirma.cz'),
                    TextInput::make('jmeno')->label(__('pruvodce.emailova_schranka.jmeno_odesilatele'))->maxLength(120)->placeholder(fn () => ZakladniUdaje::get('nazev')),
                    TextInput::make('heslo')->label(__('pruvodce.emailova_schranka.heslo_ke_schrance'))->password()->revealable()->autocomplete('new-password')
                        ->placeholder(fn () => $this->schranka() ? __('pruvodce.emailova_schranka.ulozene') : null)
                        ->helperText(fn () => $this->schranka() ? __('pruvodce.emailova_schranka.vyplnte_jen_pri_zmene') : __('pruvodce.emailova_schranka.heslo_se_ulozi_zasifrovane'))
                        ->columnSpanFull(),
                    Section::make(__('pruvodce.emailova_schranka.server_pro_odesilani'))->compact()->columns(6)->columnSpanFull()
                        ->visible($servery)
                        ->schema([
                            TextInput::make('smtp_host')->label(__('pruvodce.emailova_schranka.server_smtp'))->placeholder('smtp.vasefirma.cz')->columnSpan(3),
                            TextInput::make('smtp_port')->label(__('pruvodce.emailova_schranka.port'))->integer()->columnSpan(1),
                            Select::make('smtp_sifrovani')->label(__('pruvodce.emailova_schranka.sifrovani'))->options(Poskytovatele::sifrovani())->native(false)->columnSpan(2),
                            TextInput::make('uzivatel')->label(__('pruvodce.emailova_schranka.prihlasovaci_jmeno'))->placeholder(__('pruvodce.emailova_schranka.prazdne_e_mailova_adresa'))->columnSpanFull(),
                        ]),
                    Actions::make([
                        Action::make('ulozit')->label(__('pruvodce.emailova_schranka.ulozit_a_overit'))->icon('heroicon-o-check')->action(fn () => $this->uloz()),
                        Action::make('prihlasit')
                            ->label(fn (Get $get) => $predvolba($get)['oauth'] === 'microsoft' ? __('pruvodce.emailova_schranka.prihlasit_pres_microsoft') : __('pruvodce.emailova_schranka.prihlasit_pres_google'))
                            ->icon('heroicon-o-arrow-right-end-on-rectangle')->color('gray')
                            ->visible(fn (Get $get) => (bool) ($predvolba($get)['oauth_dostupne'] ?? false))
                            ->action(fn () => $this->prihlas()),
                    ])->columnSpanFull(),
                ]),
            Section::make(__('pruvodce.emailova_schranka.dns_domeny'))
                ->description(__('pruvodce.emailova_schranka.aby_e_maily_nepadaly_do_spamu'))
                ->visible(fn () => $this->schranka() !== null)
                ->afterHeader([
                    Action::make('dnsZnovu')->label(__('pruvodce.emailova_schranka.zkontrolovat_znovu'))->icon('heroicon-o-arrow-path')->color('gray')->size('sm')
                        ->visible(fn () => ! $this->bezplatna())
                        ->action(fn () => $this->dnsZnovu()),
                ])
                ->schema([Placeholder::make('dns')->hiddenLabel()->content(fn () => $this->dns())]),
        ]);
    }

    // ---------------------------------------------------------------- akce

    public function uloz(): void
    {
        abort_unless($this->smiMenit(), 403);
        $data = $this->zFormulare();

        if ($data === null) {
            return;
        }

        try {
            $vysledek = app(SchrankaWebu::class)->uloz($data);
        } catch (Throwable $e) {
            // Pošta nejdřív zkusí přihlášení – nepovedené nic neuloží a důvod je v hlášce.
            Notification::make()->title(__('pruvodce.emailova_schranka.schranka_se_neulozila'))->body(SchrankaWebu::chyba($e))->danger()->persistent()->send();

            return;
        }

        $this->nacti();
        Notification::make()
            ->title(__('pruvodce.emailova_schranka.ulozeno_web_posila_z', ['adresa' => $vysledek['schranka']['adresa'] ?? $data['adresa']]))
            ->body($vysledek['overeni']['zprava'] ?? null)
            ->success()->send();
    }

    public function prihlas(): void
    {
        abort_unless($this->smiMenit(), 403);
        $data = (array) $this->data;
        $p = Poskytovatele::predvolba($data['poskytovatel'] ?? null, $this->predvolbyPosty()) ?? [];

        if (! ($p['oauth_dostupne'] ?? false) || ! filter_var($data['adresa'] ?? null, FILTER_VALIDATE_EMAIL)) {
            $this->addError('data.adresa', __('pruvodce.emailova_schranka.vyplnte_adresu'));

            return;
        }

        $stav = Str::random(40);
        session()->put('posta.schranka.oauth', $stav);

        try {
            $this->redirect(app(SchrankaWebu::class)->oauth($p['oauth'], $data['adresa'], $data['jmeno'] ?? null, static::getUrl(), $stav));
        } catch (Throwable $e) {
            Notification::make()->title(__('pruvodce.emailova_schranka.prihlaseni_nejde_zacit'))->body(SchrankaWebu::chyba($e))->danger()->send();
        }
    }

    public function zkouska(string $komu): void
    {
        try {
            $zprava = app(SchrankaWebu::class)->zkouska($komu);
        } catch (Throwable $e) {
            Notification::make()->title(__('pruvodce.emailova_schranka.zkusebni_e_mail_neodesel'))->body(SchrankaWebu::chyba($e))->danger()->persistent()->send();

            return;
        }

        $neodesel = ($zprava['stav'] ?? null) === 'nedoruceno';
        Notification::make()
            ->title($neodesel ? __('pruvodce.emailova_schranka.zkusebni_e_mail_neodesel') : __('pruvodce.emailova_schranka.zkusebni_e_mail_odeslan', ['komu' => $komu]))
            ->body($zprava['chyba'] ?? __('pruvodce.emailova_schranka.zkontrolujte_i_spam'))
            ->{$neodesel ? 'danger' : 'success'}()->send();
    }

    public function vrat(): void
    {
        abort_unless($this->smiMenit(), 403);

        try {
            app(SchrankaWebu::class)->vratNaSystemovou();
        } catch (Throwable $e) {
            Notification::make()->title(__('pruvodce.emailova_schranka.nepovedlo_se'))->body(SchrankaWebu::chyba($e))->danger()->send();

            return;
        }

        $this->nacti();
        Notification::make()->title(__('pruvodce.emailova_schranka.web_posila_ze_systemove'))->success()->send();
    }

    public function dnsZnovu(): void
    {
        try {
            $this->dnsData = app(SchrankaWebu::class)->dns(zkontrolovat: true);
        } catch (Throwable $e) {
            Notification::make()->title(__('pruvodce.emailova_schranka.dns_nejde_zkontrolovat'))->body(SchrankaWebu::chyba($e))->danger()->send();
        }
    }

    // ---------------------------------------------------------------- pomocné

    /** Návrat z přihlášení přes Google / Microsoft – jen se stavem z téhle relace; výsledek se načte z Pošty. */
    private function navratZPrihlaseni(): void
    {
        $vysledek = request()->query('posta_oauth');
        $stav = session()->pull('posta.schranka.oauth');

        if (! is_string($vysledek) || ! is_string($stav) || ! hash_equals($stav, (string) request()->query('stav'))) {
            return;
        }

        $this->nacti();
        $ok = $vysledek === 'ok' && $this->schranka() !== null;
        Notification::make()
            ->title($ok ? __('pruvodce.emailova_schranka.ulozeno_web_posila_z', ['adresa' => $this->schranka()['adresa']]) : ($vysledek === 'zruseno' ? __('pruvodce.emailova_schranka.prihlaseni_zruseno') : __('pruvodce.emailova_schranka.prihlaseni_se_nepovedlo')))
            ->body(Str::limit((string) request()->query('zprava'), 300))
            ->{$ok ? 'success' : 'warning'}()->send();
    }

    /** @return list<array<string, mixed>> */
    private function predvolbyPosty(): array
    {
        static $predvolby = null;

        return $predvolby ??= app(SchrankaWebu::class)->predvolby();
    }

    private function nacti(): void
    {
        $this->chyba = null;
        $this->stav = null;
        $this->dnsData = null;

        if (! SchrankaWebu::zapnuto()) {
            $this->chyba = __('pruvodce.emailova_schranka.web_zatim_nema_odesilani');
        } else {
            try {
                $this->stav = app(SchrankaWebu::class)->stav(znovu: true);
            } catch (Throwable $e) {
                $this->chyba = __('pruvodce.emailova_schranka.ted_nejde_nacist').' '.SchrankaWebu::chyba($e);
            }
        }

        $s = $this->schranka();
        $poskytovatel = $s['poskytovatel'] ?? null;
        $poskytovatel = Poskytovatele::predvolba($poskytovatel) ? $poskytovatel : array_key_first(Poskytovatele::moznosti());
        $p = Poskytovatele::predvolba($poskytovatel) ?? [];

        $this->form->fill([
            'poskytovatel' => $poskytovatel,
            'adresa' => $s['adresa'] ?? null,
            'jmeno' => $s['jmeno'] ?? ZakladniUdaje::get('nazev'),
            'heslo' => null,
            'smtp_host' => $s['smtp_host'] ?? $p['smtp_host'] ?? null,
            'smtp_port' => $s['smtp_port'] ?? $p['smtp_port'] ?? null,
            'smtp_sifrovani' => $s['smtp_sifrovani'] ?? $p['smtp_sifrovani'] ?? null,
            'uzivatel' => $s['uzivatel'] ?? null,
        ]);
    }

    private function schranka(): ?array
    {
        return $this->stav['schranka'] ?? null;
    }

    private function smiMenit(): bool
    {
        return (bool) ($this->stav['smi_menit'] ?? false);
    }

    private function bezplatna(): bool
    {
        return (bool) ($this->dnsData()['bezplatna'] ?? false);
    }

    /** @return array<string, mixed>|null ověřená data, null = chyby u polí */
    private function zFormulare(): ?array
    {
        $data = (array) $this->data;
        $this->resetErrorBag();
        $nova = strtolower(trim((string) ($data['adresa'] ?? ''))) !== ($this->schranka()['adresa'] ?? null);

        $validator = Validator::make($data, [
            'poskytovatel' => ['required', 'in:'.implode(',', array_keys(Poskytovatele::moznosti()))],
            'adresa' => ['required', 'email', 'max:190'],
            'jmeno' => ['nullable', 'string', 'max:120'],
            'heslo' => [$nova ? 'required' : 'nullable', 'string', 'max:500'],
            'smtp_host' => [($data['poskytovatel'] ?? null) === 'jiny' ? 'required' : 'nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9.-]+$/'],
            // Jen poštovní porty – jiné služba pro odesílání stejně odmítne.
            'smtp_port' => ['nullable', 'integer', 'in:25,465,587,2525'],
            'smtp_sifrovani' => ['nullable', 'in:'.implode(',', array_keys(Poskytovatele::sifrovani()))],
            'uzivatel' => ['nullable', 'string', 'max:190'],
        ], ['smtp_port.in' => __('pruvodce.emailova_schranka.port_jen_postovni')], [
            'adresa' => __('pruvodce.emailova_schranka.e_mailova_adresa'),
            'heslo' => __('pruvodce.emailova_schranka.heslo_ke_schrance'),
            'smtp_host' => __('pruvodce.emailova_schranka.server_smtp'),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $pole => $zpravy) {
                $this->addError('data.'.$pole, $zpravy[0]);
            }

            return null;
        }

        $servery = $data['poskytovatel'] === 'jiny'
            ? ['smtp_host' => $data['smtp_host'] ?? null, 'smtp_port' => $data['smtp_port'] ?? null, 'smtp_sifrovani' => $data['smtp_sifrovani'] ?? null, 'uzivatel' => $data['uzivatel'] ?? null]
            : [];

        return ['adresa' => strtolower(trim((string) $data['adresa'])), 'jmeno' => $data['jmeno'] ?? null, 'poskytovatel' => $data['poskytovatel'], 'heslo' => $data['heslo'] ?? null] + $servery;
    }

    /** Návod poskytovatele a předvyplněný server. */
    private function navod(array $p): HtmlString
    {
        $server = filled($p['smtp_host'] ?? null)
            ? '<br><span style="color:var(--gray-500)">'.e(__('pruvodce.emailova_schranka.server', ['server' => $p['smtp_host'], 'port' => $p['smtp_port'] ?? '', 'sifrovani' => Poskytovatele::sifrovani()[$p['smtp_sifrovani'] ?? ''] ?? ''])).'</span>'
            : '';

        return new HtmlString('<div style="border-radius:.5rem;background:var(--gray-50);padding:.75rem 1rem;font-size:.875rem">'.e($p['navod'] ?? '').$server.'</div>');
    }

    private function popisStavu(): HtmlString
    {
        if ($this->chyba !== null) {
            return new HtmlString('<span style="color:var(--danger-600)">'.e($this->chyba).'</span>');
        }

        $s = $this->schranka();
        $radky = [];

        if ($s === null) {
            $radky[] = filled($this->stav['odesila'] ?? null)
                ? __('pruvodce.emailova_schranka.ted_posila_systemova_adresa', ['adresa' => '<b>'.e($this->stav['odesila']).'</b>'])
                : e(__('pruvodce.emailova_schranka.ted_posila_systemova_adresa_bez'));
            $radky[] = e(__('pruvodce.emailova_schranka.nastavte_vlastni'));
        } else {
            $barva = match ($s['stav'] ?? null) {
                'ok' => 'var(--success-600)',
                'chyba' => 'var(--danger-600)',
                default => 'var(--gray-500)',
            };
            $kdo = filled($s['jmeno'] ?? null) ? $s['jmeno'].' <'.$s['adresa'].'>' : $s['adresa'];
            $overeno = filled($s['overeno'] ?? null) ? ' · '.e(__('pruvodce.emailova_schranka.overeno', ['kdy' => CasAplikace::zVenku($s['overeno'])?->format(__('formaty.datum_cas'))])) : '';
            $radky[] = '<b>'.e($kdo).'</b> – <span style="color:'.$barva.'">'.e($s['stav_popis'] ?? $s['stav'] ?? '').'</span>'.$overeno;

            if (($s['stav'] ?? null) === 'chyba' && filled($s['stav_zprava'] ?? null)) {
                $radky[] = '<span style="color:var(--danger-600)">'.e($s['stav_zprava']).'</span> '.e(__('pruvodce.emailova_schranka.opravte_heslo'));
            }
        }

        if (! $this->smiMenit() && filled($this->stav['proc_ne'] ?? null)) {
            $radky[] = '<span style="color:var(--warning-600)">'.e($this->stav['proc_ne']).'</span>';
        }

        return new HtmlString(implode('<br>', $radky));
    }

    /** @return array<string, mixed>|null */
    private function dnsData(): ?array
    {
        if ($this->dnsData === null && $this->schranka() !== null) {
            $this->dnsData = rescue(fn () => app(SchrankaWebu::class)->dns(), [], report: false);
        }

        return $this->dnsData;
    }

    private function dns(): HtmlString
    {
        return new HtmlString(view('filament.pruvodce.dns', ['dns' => $this->dnsData() ?? [], 'adresa' => $this->schranka()['adresa'] ?? ''])->render());
    }
}
