<?php

namespace App\Filament\Auth;

use App\Bezpecnost\DruhyKrok;
use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Contracts\HasBeforeChallengeHook;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Passkey jako druhý krok přihlášení do administrace (Filament MFA).
 *
 * V panelu je PRVNÍ, takže kdo má passkey i Authenticator, dostane nejdřív
 * passkey a kód jen jako zálohu (výběr nad výzvou, VyzvaPasskey ho sama
 * přepne, když passkey nejde). Na adrese mimo doménu z APP_URL se passkey
 * nenabízí vůbec. Správa je v Zabezpečení (App\Filament\Pages\Zabezpeceni),
 * ne v profilu.
 */
class PasskeyDruhyKrok implements HasBeforeChallengeHook, MultiFactorAuthenticationProvider
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'passkey';
    }

    public function getLoginFormLabel(): string
    {
        return __('administrace.passkey_druhy_krok.passkey_touch_id_face_id_windows');
    }

    public function isEnabled(Authenticatable $user): bool
    {
        return $user instanceof User && DruhyKrok::maPasskey($user) && DruhyKrok::passkeyNaTetoAdrese();
    }

    /** Výzva vzniká, až když se passkey opravdu nabídne (i po přepnutí zpět z kódu). */
    public function beforeChallenge(Authenticatable $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        // Poškozený záznam passkeye nesmí zavřít přihlášení – výzva se pak
        // neukáže a zbývá kód z Authenticatoru.
        try {
            DruhyKrok::vyzva($user);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getManagementSchemaComponents(): array
    {
        return [];
    }

    public function getChallengeFormComponents(Authenticatable $user): array
    {
        /** @var User $user */
        return [
            VyzvaPasskey::make('credential')
                ->hiddenLabel()
                ->uzivatel($user)
                ->required()
                ->validationMessages(['required' => __('administrace.passkey_druhy_krok.potvrdte_prihlaseni_passkeyem_tlacitko')])
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    if (DruhyKrok::overPrihlaseni($user, (string) $value)) {
                        return;
                    }

                    // Výzva platí jen jednou – další pokus dostane novou.
                    rescue(fn () => DruhyKrok::vyzva($user));

                    $fail(DruhyKrok::maAuthenticator($user)
                        ? __('administrace.passkey_druhy_krok.passkey_se_nepodarilo_overit_zkuste_to')
                        : __('administrace.passkey_druhy_krok.passkey_se_nepodarilo_overit_zkuste_to_2'));
                }),
        ];
    }
}
