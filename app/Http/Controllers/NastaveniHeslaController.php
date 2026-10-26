<?php

namespace App\Http\Controllers;

use App\Bezpecnost\DruhyKrok;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PravidlaHesla;
use Illuminate\View\View;

/**
 * Nastavení hesla z jednorázového odkazu (CRM → Můj účet správce →
 * simren:spravce). Stejná stránka ve všech aplikacích Sim&Ren – člověk na ni
 * přijde jen z CRM, proto vypadá jako CRM, ne jako aplikace.
 *
 * Po uložení hesla rovnou přihlásí a pošle do aplikace (config
 * sablona.po_nastaveni_hesla, výchozí administrace). Odkaz platí jen jednou:
 * token se po použití smaže (Password broker).
 *
 * Kdo má zapnutý druhý krok (passkey, Authenticator), se rovnou nepřihlásí –
 * odkaz z e-mailu by jinak druhý krok obešel. Jde na přihlášení.
 */
class NastaveniHeslaController extends Controller
{
    public function formular(Request $request, string $token): View
    {
        $email = (string) $request->query('email', '');
        $user = User::where('email', $email)->first();

        // Neplatný nebo použitý odkaz se pozná hned, ne až po vyplnění hesla.
        $platny = $user && Password::broker()->tokenExists($user, $token);

        return view('heslo.nastaveni', [
            'token' => $token,
            'email' => $email,
            'jmeno' => $user?->getFilamentName(),
            'platny' => $platny,
        ]);
    }

    public function ulozit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PravidlaHesla::min(10)],
        ], [
            'password.confirmed' => __('web.nastaveni_hesla_controller.hesla_se_neshoduji'),
            'password.min' => __('web.nastaveni_hesla_controller.heslo_musi_mit_aspon_znaku'),
        ]);

        $prihlasit = null;

        $stav = Password::broker()->reset($data, function (User $user, string $heslo) use (&$prihlasit) {
            $user->forceFill(['password' => $heslo, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
            $prihlasit = $user;
        });

        if ($stav !== Password::PASSWORD_RESET || ! $prihlasit) {
            return back()->withErrors(['password' => __('web.nastaveni_hesla_controller.odkaz_uz_neplati_v_crm_klikni')]);
        }

        if (DruhyKrok::zapnuty($prihlasit)) {
            Notification::make()->title(__('web.nastaveni_hesla_controller.heslo_je_nastavene'))->body(__('web.nastaveni_hesla_controller.prihlaste_se_a_potvrdte_druhym_krokem'))->success()->send();

            return redirect()->to(Filament::getPanel('admin')->getLoginUrl());
        }

        Auth::login($prihlasit, remember: true);
        $request->session()->regenerate();

        return redirect()->to(self::kamPotom());
    }

    /** Kam po nastavení hesla: aplikace, nebo administrace. */
    public static function kamPotom(): string
    {
        return (string) (config('sablona.po_nastaveni_hesla') ?: Filament::getPanel('admin')->getUrl());
    }
}
