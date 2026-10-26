<?php

namespace App\Http\Controllers;

use App\Bezpecnost\DruhyKrok;
use App\Bezpecnost\ParovaniZarizeni;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Passkeye pro druhý krok přihlášení (App\Bezpecnost\DruhyKrok) – vlastní
 * trasy místo tras balíčku laravel/passkeys: aplikace nemá přihlášení bez hesla.
 *
 *  - pridat*: přihlášený přidá passkey tohoto zařízení – jen s heslem znovu
 *    (Zabezpečení, platí DruhyKrok::HESLO_MINUT),
 *  - parovani*: telefon přes QR odkaz – odkaz sám prokazuje, že párování
 *    vydal přihlášený s heslem znovu; nikoho nepřihlašuje.
 */
class ZarizeniController extends Controller
{
    public function pridatMoznosti(Request $request): JsonResponse
    {
        $user = $this->uzivatel($request);
        abort_unless(DruhyKrok::hesloNedavno(), 403, __('web.zarizeni_controller.heslo_uz_neplati_kliknete_na_tlacitko'));
        abort_unless(DruhyKrok::passkeyNaTetoAdrese(), 422, __('web.zarizeni_controller.na_teto_adrese_passkey_nejde_otevrete'));

        return response()->json(['options' => DruhyKrok::vyzvaKRegistraci($user)]);
    }

    public function pridat(Request $request): JsonResponse
    {
        $user = $this->uzivatel($request);
        abort_unless(DruhyKrok::hesloNedavno(), 403, __('web.zarizeni_controller.heslo_uz_neplati_kliknete_na_tlacitko'));

        $data = $request->validate(['name' => ['nullable', 'string', 'max:100'], 'credential' => ['required', 'array']]);

        try {
            $passkey = DruhyKrok::uloz($user, filled($data['name'] ?? null) ? $data['name'] : __('web.zarizeni_controller.zarizeni'), $data['credential']);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Přidání passkeye selhalo', ['uzivatel' => $user->email, 'duvod' => class_basename($e).': '.$e->getMessage()]);

            return response()->json(['message' => __('web.zarizeni_controller.zarizeni_se_nepodarilo_pridat_zkuste_to')], 422);
        }

        Log::info('Přidaný passkey', ['uzivatel' => $user->email, 'nazev' => $passkey->name]);

        return response()->json(['ok' => true]);
    }

    public function parovani(string $token): View
    {
        return view('zabezpeceni.parovani', ['parovani' => ParovaniZarizeni::platny($token), 'token' => $token]);
    }

    public function parovaniMoznosti(string $token): JsonResponse
    {
        $parovani = ParovaniZarizeni::platny($token);
        abort_if($parovani === null, 410, __('web.zarizeni_controller.odkaz_na_sparovani_uz_neplati'));
        abort_unless(DruhyKrok::passkeyNaTetoAdrese(), 422, __('web.zarizeni_controller.na_teto_adrese_passkey_nejde'));

        return response()->json(['options' => DruhyKrok::vyzvaKRegistraci($parovani->user, $this->klic($parovani))]);
    }

    public function sparovat(Request $request, string $token): JsonResponse
    {
        $parovani = ParovaniZarizeni::platny($token);
        abort_if($parovani === null, 410, __('web.zarizeni_controller.odkaz_na_sparovani_uz_neplati'));

        $data = $request->validate(['name' => ['nullable', 'string', 'max:100'], 'credential' => ['required', 'array']]);

        try {
            $passkey = DB::transaction(function () use ($parovani, $data) {
                // Zámek: dva telefony s týmž odkazem nesmí projít oba.
                $zamceny = ParovaniZarizeni::query()->whereKey($parovani->getKey())->lockForUpdate()->first();
                abort_if($zamceny === null || $zamceny->pouzito_v || $zamceny->zruseno_v || $zamceny->plati_do->isPast(), 410, __('web.zarizeni_controller.odkaz_na_sparovani_uz_neplati'));

                $passkey = DruhyKrok::uloz($parovani->user, filled($data['name'] ?? null) ? $data['name'] : __('web.zarizeni_controller.telefon'), $data['credential'], $this->klic($parovani));
                $zamceny->forceFill(['pouzito_v' => now()])->save();

                return $passkey;
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Spárování telefonu selhalo', ['uzivatel' => $parovani->user?->email, 'duvod' => class_basename($e).': '.$e->getMessage()]);

            return response()->json(['message' => __('web.zarizeni_controller.sparovani_se_nepovedlo_zkuste_to_znovu')], 422);
        }

        Log::info('Spárovaný telefon', ['uzivatel' => $parovani->user?->email, 'nazev' => $passkey->name]);

        return response()->json(['ok' => true]);
    }

    private function klic(ParovaniZarizeni $parovani): string
    {
        return 'druhy_krok.parovani.'.$parovani->getKey();
    }

    private function uzivatel(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
