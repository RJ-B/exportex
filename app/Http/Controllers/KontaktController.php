<?php

namespace App\Http\Controllers;

use App\Http\Requests\OdeslaniZpravy;
use App\Mail\NovaZprava;
use App\Models\Zprava;
use App\Support\NastaveniWebu;
use App\Support\OchranaFormulare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Poptávkový formulář (sekce Kontakt na úvodní stránce, Obsah webu → Kontakt
 * a formulář). Mechanismus šablony Sim&Ren: zpráva se uloží (Zprávy z webu)
 * a upozornění odejde příjemci až po odpovědi návštěvníkovi – pomalé SMTP ho
 * nezdrží a selhání ho nepřipraví o zprávu (Logy → E-maily ho zkusí znovu).
 * Dřív šla poptávka přes formsubmit.co; teď nikam mimo náš server.
 *
 * Ochrana: skryté pole a podepsaná časová past (OchranaFormulare), limit
 * `throttle:formular` na routě a validace (OdeslaniZpravy). Botovi se tváří
 * jako odesláno. Žádná captcha – nezdržuje zákazníka a nevolá cizí server.
 *
 * Formulář na webu posílá JSON (assets/js/main.js); obyčejné odeslání
 * (bez JavaScriptu, testy) se vrátí na sekci Kontakt.
 */
class KontaktController extends Controller
{
    /** Stránka /kontakt (odkaz sekce v administraci) = sekce Kontakt na úvodní stránce. */
    public function zobrazit(): RedirectResponse
    {
        return redirect()->to(url('/').'#kontakt');
    }

    public function odeslat(OdeslaniZpravy $request): JsonResponse|RedirectResponse
    {
        if (OchranaFormulare::jeBot($request)) {
            // Tváří se jako odesláno – bot se nemá dozvědět, co ho prozradilo.
            return $this->odeslano($request);
        }

        $zprava = Zprava::create($request->validated() + ['ip_adresa' => $request->ip()]);

        if ($prijemce = NastaveniWebu::prijemceFormulare()) {
            dispatch(fn () => Mail::to($prijemce)->send(new NovaZprava($zprava)))->afterResponse();
        }

        return $this->odeslano($request);
    }

    private function odeslano(Request $request): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->to(url('/').'#kontakt')->with('odeslano', true);
    }
}
