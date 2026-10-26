<?php

namespace App\Filament\Pages;

use App\Bezpecnost\DruhyKrok;
use App\Bezpecnost\ParovaniZarizeni;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passkeys\Passkeys;

/**
 * Zabezpečení účtu (uživatelské menu vpravo nahoře): druhý krok přihlášení.
 *
 *  - passkey tohoto zařízení (Touch ID, Face ID, Windows Hello) a telefon
 *    spárovaný QR kódem – přidání a odebrání chce heslo znovu (platí
 *    DruhyKrok::HESLO_MINUT): kdo by převzal otevřenou relaci, nesmí si
 *    přidat vlastní zařízení,
 *  - kód z Authenticatoru s obnovovacími kódy (Filament AppAuthentication –
 *    nastavení chce heslo i první kód, vypnutí kód).
 *
 * Výzva při přihlášení nabídne nejdřív passkey a když nejde, kód.
 */
class Zabezpeceni extends Page
{
    protected string $view = 'filament.pages.zabezpeceni';

    public function getTitle(): string|Htmlable
    {
        return __('administrace.zabezpeceni.zabezpeceni');
    }

    protected static ?string $slug = 'zabezpeceni';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    // V uživatelském menu vpravo nahoře, ne v levém menu.
    protected static bool $shouldRegisterNavigation = false;

    public ?string $parovaniQr = null;

    public ?string $parovaniOdkaz = null;

    /** Authenticator a obnovovací kódy – správa z Filamentu (AppAuthentication). */
    public function authenticator(Schema $schema): Schema
    {
        $app = Filament::getMultiFactorAuthenticationProviders()['app'] ?? null;

        return $schema->components($app ? [Group::make($app->getManagementSchemaComponents())] : []);
    }

    /** Heslo do okna akce – jen když není čerstvě ověřené. */
    private static function poleHesla(): TextInput
    {
        return TextInput::make('heslo')
            ->label(__('administrace.zabezpeceni.heslo'))
            ->password()
            ->revealable()
            ->autocomplete('current-password')
            ->helperText(__('administrace.zabezpeceni.zmeny_zabezpeceni_chteji_heslo_znovu', ['heslo_minut' => DruhyKrok::HESLO_MINUT]))
            ->required()
            ->visible(fn () => ! DruhyKrok::hesloNedavno());
    }

    /** Ověří heslo z okna akce; při chybě okno nechá otevřené. */
    private function overHeslo(array $data, Action $action): void
    {
        if (DruhyKrok::hesloNedavno()) {
            return;
        }

        $ja = Auth::user();
        $klic = 'zabezpeceni-heslo:'.$ja->getKey();

        if (RateLimiter::tooManyAttempts($klic, 5)) {
            Notification::make()->title(__('administrace.zabezpeceni.moc_pokusu_zkuste_to_za_minutu'))->danger()->send();
            $action->halt();
        }

        RateLimiter::hit($klic, 60);

        if (! Hash::check((string) ($data['heslo'] ?? ''), (string) $ja->getAuthPassword())) {
            Notification::make()->title(__('administrace.zabezpeceni.heslo_nesedi'))->danger()->send();
            $action->halt();
        }

        RateLimiter::clear($klic);
        DruhyKrok::oznacHeslo();
    }

    /** Passkey tohoto zařízení – název a heslo v okně, pak výzva prohlížeče. */
    public function pridatZarizeniAction(): Action
    {
        return Action::make('pridatZarizeni')
            ->label(__('administrace.zabezpeceni.pridat_toto_zarizeni'))
            ->icon(Heroicon::OutlinedFingerPrint)
            ->modalHeading(__('administrace.zabezpeceni.pridat_toto_zarizeni'))
            ->modalDescription(__('administrace.zabezpeceni.po_potvrzeni_se_ozve_touch_id'))
            ->modalSubmitActionLabel(__('administrace.zabezpeceni.pokracovat'))
            ->modalWidth('md')
            ->schema([
                TextInput::make('nazev')
                    ->label(__('administrace.zabezpeceni.nazev_zarizeni'))
                    ->default(__('administrace.zabezpeceni.pocitac'))
                    ->required()
                    ->maxLength(60),
                self::poleHesla(),
            ])
            ->action(function (array $data, Action $action): void {
                $this->overHeslo($data, $action);

                // Ceremonii odbaví prohlížeč (public/js/passkeys.js) – posluchač ve stránce.
                $this->dispatch('simren-pridat-passkey', nazev: trim((string) $data['nazev']) ?: __('administrace.zabezpeceni.zarizeni'));
            });
    }

    public function sparovatTelefonAction(): Action
    {
        return Action::make('sparovatTelefon')
            ->label(__('administrace.zabezpeceni.sparovat_telefon_qr_kod'))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->modalHeading(__('administrace.zabezpeceni.sparovat_telefon'))
            ->modalDescription(__('administrace.zabezpeceni.ukaze_se_qr_kod_naskenujete_ho'))
            ->modalSubmitActionLabel(__('administrace.zabezpeceni.ukazat_qr_kod'))
            ->modalWidth('md')
            ->schema([self::poleHesla()])
            ->action(function (array $data, Action $action): void {
                $this->overHeslo($data, $action);

                $this->parovaniOdkaz = route('zarizeni.parovani', ParovaniZarizeni::vydej(Auth::user()));
                // chillerlan/php-qrcode má Filament s sebou (QR kód Authenticatoru).
                $this->parovaniQr = (new QRCode(new QROptions([
                    'outputType' => QROutputInterface::MARKUP_SVG,
                    'outputBase64' => false,
                    'svgAddXmlHeader' => false,
                ])))->render($this->parovaniOdkaz);
            });
    }

    public function odebratAction(): Action
    {
        return Action::make('odebrat')
            ->label(__('administrace.zabezpeceni.odebrat'))
            ->link()
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => __('administrace.zabezpeceni.odebrat_zarizeni', ['user' => (Auth::user()->passkeys()->find($arguments['id'] ?? 0)?->name ?? '')]))
            ->modalDescription(__('administrace.zabezpeceni.timhle_zarizenim_se_pak_nepujde'))
            ->modalSubmitActionLabel(__('administrace.zabezpeceni.odebrat'))
            ->schema([self::poleHesla()])
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->overHeslo($data, $action);

                $passkey = Auth::user()->passkeys()->find((int) ($arguments['id'] ?? 0));

                if ($passkey) {
                    $passkey->delete();
                    Log::info('Odebraný passkey', ['uzivatel' => Auth::user()->email, 'nazev' => $passkey->name]);
                    Notification::make()->title(__('administrace.zabezpeceni.zarizeni_odebrano'))->body($passkey->name)->success()->send();
                }
            });
    }

    /** Během párování se stránka ptá, jestli telefon už odkaz použil. */
    public function obnovit(): void
    {
        if ($this->parovaniOdkaz && ! ParovaniZarizeni::query()->platne()->where('user_id', Auth::id())->exists()) {
            $this->parovaniOdkaz = $this->parovaniQr = null;
            Notification::make()->title(__('administrace.zabezpeceni.telefon_je_sparovany'))->success()->send();
        }
    }

    public function zrusitParovani(): void
    {
        ParovaniZarizeni::query()->platne()->where('user_id', Auth::id())->update(['zruseno_v' => now()]);
        $this->parovaniOdkaz = $this->parovaniQr = null;
    }

    protected function getViewData(): array
    {
        $ja = Auth::user();

        return [
            'zarizeni' => $ja->passkeys()->orderByDesc('created_at')->get(),
            'maAuthenticator' => DruhyKrok::maAuthenticator($ja),
            'passkeyTady' => DruhyKrok::passkeyNaTetoAdrese(),
            'domena' => Passkeys::relyingPartyId(),
        ];
    }
}
