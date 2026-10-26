<?php

namespace App\Fakturace\Filament\Stranky;

use App\Fakturace\Fakturace as KlientFakturace;
use App\Fakturace\FakturaceChyba;
use App\Filament\Navigace;
use App\Services\AuditLogger;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Nastavení → Fakturace (doplněk Fakturace): stav napojení na fakturaci
 * (napojeno / testovací režim / čeká) a Bankovní účet pro doklady – správce
 * ho zadá tady a uloží se ve fakturaci (API firma:udaje), nemusí tam chodit.
 * IBAN a SWIFT dopočítá fakturace z čísla účtu. Testovací napojení účet jen
 * ukáže (mění se z ostrého webu, z CRM nebo ve fakturaci).
 */
class Fakturace extends Page
{
    protected string $view = 'filament.pages.formular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return Navigace::nastaveni();
    }

    public static function getNavigationLabel(): string
    {
        return __('obecne.fakturace.fakturace');
    }

    public function getTitle(): string|Htmlable
    {
        return __('obecne.fakturace.fakturace');
    }

    protected static ?string $slug = 'fakturace';

    protected static ?int $navigationSort = 21;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['cislo_uctu' => self::klient()->udajeFirmy()['firma']['bankovni_ucet']['cislo'] ?? null]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('obecne.fakturace.napojeni_na_fakturaci'))
                    ->schema([Html::make(fn () => self::stavNapojeni())]),
                Section::make(__('obecne.fakturace.bankovni_ucet_pro_doklady'))
                    ->description(__('obecne.fakturace.tiskne_se_na_kazdy_doklad_a'))
                    ->visible(fn () => self::klient()->napojeno())
                    ->schema([
                        Html::make(fn () => self::ucet()),
                        TextInput::make('cislo_uctu')
                            ->label(__('obecne.fakturace.cislo_uctu'))
                            ->placeholder('19-2000145399/0800')
                            ->helperText(__('obecne.fakturace.predcisli_cislo_kod_banky_zahranicni'))
                            ->maxLength(191)
                            ->required()
                            ->disabled(fn () => ! self::klient()->udajeFirmy()['lze_menit']),
                    ]),
            ]);
    }

    public function uloz(): void
    {
        $klient = self::klient();

        if (! $klient->udajeFirmy()['lze_menit']) {
            Notification::make()->title(__('obecne.fakturace.ucet_tady_zmenit_nejde'))
                ->body($klient->testovaci() ? __('obecne.fakturace.testovaci_web_udaje_firmy_jen_ukazuje') : ($klient->udajeFirmy()['chyba'] ?? null))
                ->warning()->send();

            return;
        }

        $cislo = trim((string) ($this->form->getState()['cislo_uctu'] ?? ''));
        $pred = $klient->udajeFirmy()['firma']['bankovni_ucet']['cislo'] ?? null;

        try {
            $firma = $klient->ulozUdajeFirmy(['cislo_uctu' => $cislo]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.cislo_uctu' => collect($e->errors())->flatten()->implode(' ')]);
        } catch (FakturaceChyba $e) {
            Notification::make()->title(__('obecne.fakturace.ucet_se_nepodarilo_ulozit'))->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $ucet = (array) ($firma['bankovni_ucet'] ?? []);
        AuditLogger::record('fakturace.ucet', null, __('obecne.fakturace.bankovni_ucet_pro_doklady_ve_fakturaci', ['hodnota' => ($ucet['cislo'] ?? $cislo)]), ['cislo' => $pred], ['cislo' => $ucet['cislo'] ?? $cislo]);
        Notification::make()->title(__('obecne.fakturace.ucet_ulozen_ve_fakturaci'))->body(__('obecne.fakturace.iban', ['hodnota' => ($ucet['iban_citelny'] ?? '–'), 'hodnota2' => (filled($ucet['swift'] ?? null) ? ' · SWIFT '.$ucet['swift'] : '')]))->success()->send();
    }

    private static function klient(): KlientFakturace
    {
        return app(KlientFakturace::class);
    }

    private static function stavNapojeni(): HtmlString
    {
        $stav = self::klient()->stav();
        $barva = match ($stav['stav']) {
            KlientFakturace::STAV_NAPOJENO => 'var(--success-600)',
            KlientFakturace::STAV_TEST => 'var(--warning-600)',
            default => 'var(--gray-500)',
        };

        return new HtmlString('<p><strong style="color: '.$barva.';">'.e($stav['nazev']).'</strong> – '.e($stav['popis']).'</p>');
    }

    private static function ucet(): HtmlString
    {
        $v = self::klient()->udajeFirmy();

        if ($v['firma'] === null) {
            return new HtmlString('<p style="opacity: .8;">'.e($v['chyba'] ?? __('obecne.fakturace.udaje_z_fakturace_nejdou_nacist')).'</p>');
        }

        $ucet = (array) ($v['firma']['bankovni_ucet'] ?? []);

        if (blank($ucet['cislo'] ?? null) && blank($ucet['iban'] ?? null)) {
            return new HtmlString('<p style="color: var(--warning-600);"><strong>'.__('obecne.fakturace.ucet_zatim_neni_vyplneny_doklady_bez').'</p>');
        }

        return new HtmlString('<p>Ve fakturaci: <strong>'.e($ucet['cislo'] ?? $ucet['iban_citelny'] ?? '').'</strong>'
            .(filled($ucet['banka'] ?? null) ? ' · '.e($ucet['banka']) : '')
            .'<br>IBAN '.e($ucet['iban_citelny'] ?? '–').(filled($ucet['swift'] ?? null) ? ' · SWIFT '.e($ucet['swift']) : '')
            .(! $v['lze_menit'] ? '<br><span style="opacity: .8;">'.__('obecne.fakturace.testovaci_web_udaje_jen_ukazuje_meni').'</span>' : '')
            .'</p>');
    }
}
