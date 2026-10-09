<?php

namespace App\Http\Controllers;

use App\Enums\DruhOznameni;
use App\Enums\KanalOznameni;
use App\Models\OznameniPrijemce;
use App\Models\User;
use App\Support\Oznameni\Centrum;
use App\Support\Oznameni\NastaveniOznameni;
use App\Support\Oznameni\Predvolby;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Centrum oznámení pro přihlášeného (web i zvoneček v administraci – stejné
 * trasy, session). Mobil bude číst totéž přes API (docs/oznameni.md, krok 3);
 * přečtení se zapisuje k témuž příjemci, takže se srovná všude.
 *
 * Odhlášení z e-mailu a proklik jsou podepsané odkazy – fungují bez přihlášení.
 */
class OznameniController extends Controller
{
    /** Stránka Oznámení: seznam, archiv a předvolby. */
    public function stranka(Request $request): View
    {
        $zobrazit = in_array($request->query('zobrazit'), ['neprectene', 'archiv'], true) ? $request->query('zobrazit') : '';
        $user = $request->user();

        return view('layouts.verejna', [
            'nadpis' => 'Oznámení',
            'stitek' => 'Váš účet',
            'obsah' => 'oznameni.stranka',
            'zobrazit' => $zobrazit,
            'neprectenych' => Centrum::neprectenych($user),
            'polozky' => OznameniPrijemce::query()->vCentru($user)
                ->when($zobrazit === 'neprectene', fn ($query) => $query->neprectene())
                ->when($zobrazit === 'archiv', fn ($query) => $query->whereNotNull('archivovano_at'))
                ->with('oznameni')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'matice' => Predvolby::matice($user),
            'druhy' => NastaveniOznameni::druhy(),
            'kanaly' => KanalOznameni::predvolby(),
        ]);
    }

    /** Zvoneček: posledních pár a počet nepřečtených (JSON, čas ISO 8601 s posunem). */
    public function centrum(Request $request): JsonResponse
    {
        return response()->json(Centrum::json($request->user()));
    }

    public function precteno(Request $request, OznameniPrijemce $prijemce): JsonResponse|RedirectResponse
    {
        $this->jeho($request, $prijemce)->oznacPrectene();

        return $request->expectsJson() ? response()->json(Centrum::json($request->user())) : back();
    }

    public function prectenoVse(Request $request): JsonResponse|RedirectResponse
    {
        OznameniPrijemce::query()->vCentru($request->user())->neprectene()->update(['precteno_at' => now()]);

        return $request->expectsJson() ? response()->json(Centrum::json($request->user())) : back();
    }

    /** Do archivu a zpátky (archivované zmizí ze zvonečku, zůstane na stránce v Archivu). */
    public function archiv(Request $request, OznameniPrijemce $prijemce): JsonResponse|RedirectResponse
    {
        $prijemce = $this->jeho($request, $prijemce);
        $prijemce->forceFill([
            'archivovano_at' => $prijemce->archivovano_at ? null : now(),
            'precteno_at' => $prijemce->precteno_at ?? now(),
        ])->save();

        return $request->expectsJson() ? response()->json(Centrum::json($request->user())) : back();
    }

    public function predvolby(Request $request): RedirectResponse
    {
        Predvolby::uloz($request->user(), (array) $request->input('predvolby', []), 'predvolby', $request);

        return redirect(route('oznameni.stranka').'#predvolby')->with('predvolby_ulozeny', true);
    }

    /** Odkaz z centra nebo e-mailu (podepsaný): zapíše přečteno a proklik, pošle dál. */
    public function proklik(OznameniPrijemce $prijemce): RedirectResponse
    {
        $prijemce->oznacProkliknute();
        $odkaz = (string) $prijemce->oznameni->odkaz;

        return redirect()->away(str_starts_with($odkaz, '/') ? url($odkaz) : $odkaz);
    }

    /** Odhlášení odkazem z e-mailu – stránka s jedním tlačítkem (skener odkazů v poště nic neodhlásí). */
    public function odhlaseni(Request $request, User $user, string $druh): View
    {
        $druh = $this->druhSeSouhlasem($druh);

        return view('layouts.verejna', [
            'nadpis' => 'Odhlášení z odběru',
            'stitek' => 'Oznámení',
            'obsah' => 'oznameni.odhlaseni',
            'druh' => $druh,
            'email' => $user->email,
            'hotovo' => ! Predvolby::chce($user, $druh, KanalOznameni::Email),
        ]);
    }

    /**
     * Odhlášení: tlačítko na stránce, nebo jedno kliknutí z poštovního programu
     * (RFC 8058: POST s List-Unsubscribe=One-Click, bez cookies a CSRF).
     */
    public function odhlasit(Request $request, User $user, string $druh): View|Response
    {
        $druh = $this->druhSeSouhlasem($druh);
        $jednoKliknuti = $request->input('List-Unsubscribe') === 'One-Click';

        Predvolby::odhlas($user, $druh, $jednoKliknuti ? 'jedno-kliknuti' : 'odhlaseni', $request);

        if ($jednoKliknuti) {
            return response('', 200);
        }

        return view('layouts.verejna', [
            'nadpis' => 'Odhlášení z odběru',
            'stitek' => 'Oznámení',
            'obsah' => 'oznameni.odhlaseni',
            'druh' => $druh,
            'email' => $user->email,
            'hotovo' => true,
        ]);
    }

    private function jeho(Request $request, OznameniPrijemce $prijemce): OznameniPrijemce
    {
        abort_unless($prijemce->user_id === $request->user()->getKey(), 404);

        return $prijemce;
    }

    private function druhSeSouhlasem(string $druh): DruhOznameni
    {
        $druh = DruhOznameni::tryFrom($druh);
        abort_unless($druh?->vyzadujeSouhlas(), 404);

        return $druh;
    }
}
