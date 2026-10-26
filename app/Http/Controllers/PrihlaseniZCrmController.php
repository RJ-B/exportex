<?php

namespace App\Http\Controllers;

use App\Bezpecnost\DruhyKrok;
use App\Support\PrihlaseniZCrm;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Jednorázové přihlášení z CRM (Můj účet správce u účtu, který už heslo má –
 * App\Support\PrihlaseniZCrm): heslo se nemění, správce jde rovnou do
 * administrace. Podepsaný odkaz (middleware signed), platí 5 minut a jen
 * jednou. Kdo má zapnutý druhý krok, jde na přihlášení – odkaz ho neobejde.
 */
class PrihlaseniZCrmController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $user = PrihlaseniZCrm::prevezmi($token);
        $admin = Filament::getPanel('admin');

        if (! $user || ! $user->jeSuperadmin()) {
            Notification::make()->title(__('web.prihlaseni_z_crm_controller.odkaz_uz_neplati'))->body(__('web.prihlaseni_z_crm_controller.v_crm_klikni_znovu_na_muj'))->danger()->send();

            return redirect()->to($admin->getLoginUrl());
        }

        if (DruhyKrok::zapnuty($user)) {
            Notification::make()->title(__('web.prihlaseni_z_crm_controller.prihlaste_se'))->body(__('web.prihlaseni_z_crm_controller.mate_zapnuty_druhy_krok_prihlaste_se'))->info()->send();

            return redirect()->to($admin->getLoginUrl());
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->to($admin->getUrl());
    }
}
