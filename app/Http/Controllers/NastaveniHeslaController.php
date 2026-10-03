<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
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
            'password.confirmed' => 'Hesla se neshodují.',
            'password.min' => 'Heslo musí mít aspoň :min znaků.',
        ]);

        $prihlasit = null;

        $stav = Password::broker()->reset($data, function (User $user, string $heslo) use (&$prihlasit) {
            $user->forceFill(['password' => $heslo, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
            $prihlasit = $user;
        });

        if ($stav !== Password::PASSWORD_RESET || ! $prihlasit) {
            return back()->withErrors(['password' => 'Odkaz už neplatí. V CRM klikni znovu na Můj účet správce.']);
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
