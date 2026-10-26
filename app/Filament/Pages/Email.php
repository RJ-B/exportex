<?php

namespace App\Filament\Pages;

use App\Filament\Navigace;
use App\Support\Posta\Propojeni;
use App\Support\Posta\SchrankyFirmy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nastavení → E-mail: schránky firmy a odkud web posílá k jakému účelu –
 * formuláře, účty (přihlášení, obnova hesla), doklady, objednávky, hromadné.
 * Jedna schránka pro všechno, nebo zvlášť. Data drží Pošta (SchrankyFirmy);
 * web nic neukládá, heslo jde jen při přidání schránky ze serveru do Pošty.
 *
 * Jen správce. Testovací web a web, který správce Sim&Ren ještě nepřiřadil
 * firmě (portál), jen čte – Pošta změny odmítne i sama.
 */
class Email extends Page
{
    protected string $view = 'filament.pages.email';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static ?string $slug = 'email';

    public static function getNavigationLabel(): string
    {
        return __('administrace.email.e_mail');
    }

    public function getTitle(): string|Htmlable
    {
        return __('administrace.email.e_mail');
    }

    protected static ?int $navigationSort = 805;

    /** Co o firmě řekla Pošta (GET /vlastnik), null = nepropojeno / chyba. @var array<string, mixed>|null */
    public ?array $firma = null;

    public ?string $chyba = null;

    public ?array $data = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return Navigace::nastaveni();
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
        $ucely = collect($this->firma['ucely'] ?? [])->keyBy('klic');
        $schranky = fn () => collect($this->firma['schranky'] ?? [])->filter(fn ($s) => $s['aktivni'] ?? true)
            ->mapWithKeys(fn ($s) => [$s['adresa'] => $s['adresa'].(($s['stav'] ?? null) === 'chyba' ? __('administrace.email.prihlaseni_nefunguje') : '')])->all();
        $lzeMenit = fn () => (bool) ($this->firma['smi_menit'] ?? false);

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('administrace.email.odkud_se_posila'))
                    ->description(__('administrace.email.jedna_schranka_pro_vsechno_nebo_zvlast'))
                    ->visible(fn () => $this->firma !== null)
                    ->schema([
                        Select::make('ucely.obecne')->label(__('administrace.email.pro_vsechno'))
                            ->options($schranky)->native(false)->placeholder(__('administrace.email.nenastaveno_posila_se_jako_dosud'))
                            ->disabled(fn () => ! $lzeMenit()),
                        Toggle::make('rozpis')->label(__('administrace.email.zvlast_pro_jednotlive_ucely'))->live()->disabled(fn () => ! $lzeMenit()),
                        ...$ucely->except('obecne')->map(fn ($u, $klic) => Select::make('ucely.'.$klic)->label($u['nazev'])
                            ->options($schranky)->native(false)->placeholder(__('administrace.email.jako_pro_vsechno'))
                            ->helperText($u['popis'] ?? null)
                            ->visible(fn (Get $get) => (bool) $get('rozpis'))
                            ->disabled(fn () => ! $lzeMenit()))->values()->all(),
                        Actions::make([
                            Action::make('ulozitUcely')->label(__('administrace.email.ulozit'))->icon('heroicon-o-check')
                                ->visible($lzeMenit)->action(fn () => $this->ulozUcely()),
                        ]),
                    ]),
                Section::make(__('administrace.email.pridat_schranku'))
                    ->description(__('administrace.email.adresa_a_heslo_jako_v_postovnim'))
                    ->visible($lzeMenit)
                    ->collapsed(fn () => ($this->firma['schranky'] ?? []) !== [])
                    ->columns(2)
                    ->schema([
                        Select::make('nova.poskytovatel')->label(__('administrace.email.poskytovatel'))
                            ->options(fn () => collect($this->predvolby())->pluck('nazev', 'klic')->all())
                            ->native(false)->selectablePlaceholder(false)->default('seznam')->live()
                            ->helperText(fn (Get $get) => collect($this->predvolby())->firstWhere('klic', $get('nova.poskytovatel'))['napoveda'] ?? null)
                            ->columnSpanFull(),
                        TextInput::make('nova.adresa')->label(__('administrace.email.e_mailova_adresa'))->email()->maxLength(190)->placeholder('info@vasefirma.cz'),
                        TextInput::make('nova.jmeno')->label(__('administrace.email.jmeno_odesilatele'))->maxLength(120)->placeholder(fn () => $this->firma['vlastnik']['nazev'] ?? config('app.name')),
                        TextInput::make('nova.heslo')->label(__('administrace.email.heslo_ke_schrance'))->password()->revealable()->autocomplete('new-password')->columnSpanFull(),
                        Actions::make([
                            Action::make('pridatSchranku')->label(__('administrace.email.pridat_a_overit'))->icon('heroicon-o-plus')->action(fn () => $this->pridej()),
                            Action::make('prihlasit')
                                ->label(fn (Get $get) => __('administrace.email.prihlasit_pres', ['oauth' => ($this->oauth($get('nova.poskytovatel')) === 'microsoft' ? 'Microsoft' : 'Google')]))
                                ->icon('heroicon-o-arrow-right-end-on-rectangle')->color('gray')
                                ->visible(fn (Get $get) => $this->oauth($get('nova.poskytovatel')) !== null)
                                ->action(fn () => $this->prihlas()),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    // ---------------------------------------------------------------- akce

    public function ulozUcely(): void
    {
        $ulozene = (array) ($this->firma['prirazeni']['vlastnik'] ?? []);
        $rozpis = (bool) ($this->data['rozpis'] ?? false);
        $zmeny = [];

        foreach (collect($this->firma['ucely'] ?? [])->pluck('klic') as $ucel) {
            $nova = ($rozpis || $ucel === 'obecne') ? ($this->data['ucely'][$ucel] ?? null) : null;
            $nova = filled($nova) ? (string) $nova : null;

            if ($nova !== ($ulozene[$ucel] ?? null)) {
                $zmeny[$ucel] = $nova;
            }
        }

        if ($zmeny === []) {
            Notification::make()->title(__('administrace.email.beze_zmeny'))->send();

            return;
        }

        $this->proved(fn (SchrankyFirmy $s) => $s->nastav($zmeny), __('administrace.email.ulozeno_e_maily_odchazeji_podle_noveho'));
    }

    public function pridej(): void
    {
        $nova = (array) ($this->data['nova'] ?? []);

        if (! filter_var($nova['adresa'] ?? null, FILTER_VALIDATE_EMAIL) || blank($nova['heslo'] ?? null)) {
            Notification::make()->title(__('administrace.email.vyplnte_e_mailovou_adresu_a_heslo'))->danger()->send();

            return;
        }

        $this->proved(function (SchrankyFirmy $s) use ($nova) {
            $vysledek = $s->pridej(['adresa' => strtolower(trim($nova['adresa'])), 'heslo' => $nova['heslo'], 'jmeno' => $nova['jmeno'] ?? null, 'poskytovatel' => $nova['poskytovatel'] ?? null]);
            $this->data['nova'] = ['poskytovatel' => $nova['poskytovatel'] ?? 'seznam'];

            return ($vysledek['overeni']['ok'] ?? true) ? __('administrace.email.schranka_pridana_odesilani_funguje') : __('administrace.email.schranka_pridana_ale_prihlaseni', ['hodnota' => ($vysledek['overeni']['zprava'] ?? '')]);
        });
    }

    public function prihlas(): void
    {
        $nova = (array) ($this->data['nova'] ?? []);
        $poskytovatel = $this->oauth($nova['poskytovatel'] ?? null);

        if ($poskytovatel === null || ! filter_var($nova['adresa'] ?? null, FILTER_VALIDATE_EMAIL)) {
            Notification::make()->title(__('administrace.email.vyplnte_e_mailovou_adresu'))->danger()->send();

            return;
        }

        $stav = Str::random(40);
        session()->put('posta.email.oauth', $stav);

        try {
            $this->redirect(app(SchrankyFirmy::class)->oauth($poskytovatel, strtolower(trim($nova['adresa'])), $nova['jmeno'] ?? null, static::getUrl(), $stav));
        } catch (Throwable $e) {
            Notification::make()->title(__('administrace.email.prihlaseni_nejde_zacit'))->body($e->getMessage())->danger()->send();
        }
    }

    public function overitAction(): Action
    {
        return Action::make('overit')->label(__('administrace.email.overit'))->icon('heroicon-o-signal')->color('gray')->size('sm')
            ->action(function (array $arguments) {
                try {
                    $v = app(SchrankyFirmy::class)->over((string) $arguments['adresa']);
                    Notification::make()->title($v['ok'] ? __('administrace.email.prihlaseni_funguje') : __('administrace.email.prihlaseni_nefunguje_2'))->body($v['zprava'] ?? null)->{$v['ok'] ? 'success' : 'danger'}()->send();
                } catch (Throwable $e) {
                    Notification::make()->title(__('administrace.email.overeni_se_nepovedlo'))->body($e->getMessage())->danger()->send();
                }

                $this->nacti();
            });
    }

    public function zkouskaAction(): Action
    {
        return Action::make('zkouska')->label(__('administrace.email.zkusebni_e_mail'))->icon('heroicon-o-paper-airplane')->color('gray')->size('sm')
            ->schema([TextInput::make('komu')->label(__('administrace.email.komu'))->email()->required()->default(fn () => auth()->user()?->email)])
            ->action(fn (array $arguments, array $data) => $this->proved(fn (SchrankyFirmy $s) => $s->zkouska((string) $arguments['adresa'], $data['komu'])
                ? __('administrace.email.zkusebni_e_mail_odeslan_na_zkontrolujte', ['komu' => $data['komu']]) : null));
    }

    public function odebratAction(): Action
    {
        return Action::make('odebrat')->label(__('administrace.email.odebrat'))->icon('heroicon-o-trash')->color('danger')->size('sm')->link()
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => __('administrace.email.odebrat_schranku', ['hodnota' => ($arguments['adresa'] ?? '')]))
            ->modalDescription(__('administrace.email.ucely_ktere_z_ni_posilaly_se'))
            ->action(fn (array $arguments) => $this->proved(fn (SchrankyFirmy $s) => $s->odeber((string) $arguments['adresa']) ? __('administrace.email.schranka_odebrana') : null));
    }

    // ---------------------------------------------------------------- pomocné

    private function nacti(): void
    {
        $this->chyba = null;
        $this->firma = null;

        if (! Propojeni::propojeno()) {
            $this->chyba = __('administrace.email.web_neni_propojeny_s_postovni_sluzbou');

            return;
        }

        try {
            $this->firma = app(SchrankyFirmy::class)->popis();
        } catch (Throwable $e) {
            $this->chyba = __('administrace.email.nastaveni_e_mailu_ted_nejde_nacist', ['get_message' => $e->getMessage()]);

            return;
        }

        $vlastnik = (array) ($this->firma['prirazeni']['vlastnik'] ?? []);
        $this->form->fill([
            'ucely' => collect($this->firma['ucely'] ?? [])->pluck('klic')->mapWithKeys(fn ($u) => [$u => $vlastnik[$u] ?? null])->all(),
            'rozpis' => collect($vlastnik)->except('obecne')->filter()->isNotEmpty(),
            'nova' => ['poskytovatel' => 'seznam'] + (array) ($this->data['nova'] ?? []),
        ]);
    }

    /** Návrat z přihlášení přes Google / Microsoft – jen se stavem z téhle relace. */
    private function navratZPrihlaseni(): void
    {
        $vysledek = request()->query('posta_oauth');
        $stav = session()->pull('posta.email.oauth');

        if (! is_string($vysledek) || ! is_string($stav) || ! hash_equals($stav, (string) request()->query('stav'))) {
            return;
        }

        Notification::make()
            ->title($vysledek === 'ok' ? __('administrace.email.schranka_prihlasena_odesilani_funguje') : ($vysledek === 'zruseno' ? __('administrace.email.prihlaseni_zruseno') : __('administrace.email.prihlaseni_se_nepovedlo')))
            ->body(Str::limit((string) request()->query('zprava'), 300))
            ->{$vysledek === 'ok' ? 'success' : 'warning'}()->send();
    }

    /** @param callable(SchrankyFirmy): mixed $co */
    private function proved(callable $co, ?string $hotovo = null): void
    {
        try {
            $vysledek = $co(app(SchrankyFirmy::class));
        } catch (Throwable $e) {
            Notification::make()->title(__('administrace.email.nepovedlo_se'))->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $this->nacti();
        Notification::make()->title(is_string($vysledek) ? $vysledek : ($hotovo ?? __('administrace.email.hotovo')))->success()->send();
    }

    /** @return list<array<string, mixed>> */
    private function predvolby(): array
    {
        static $predvolby = null;

        return $predvolby ??= rescue(fn () => app(SchrankyFirmy::class)->predvolby(), [['klic' => 'seznam', 'nazev' => 'Seznam Email Profi', 'oauth' => null, 'oauth_dostupne' => false]], report: false);
    }

    private function oauth(?string $poskytovatel): ?string
    {
        $p = collect($this->predvolby())->firstWhere('klic', $poskytovatel);

        return ($p['oauth_dostupne'] ?? false) ? ($p['oauth'] ?? null) : null;
    }
}
