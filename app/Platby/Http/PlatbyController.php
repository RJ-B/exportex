<?php

namespace App\Platby\Http;

use App\Platby\Brany\Simulace;
use App\Platby\ChybaBrany;
use App\Platby\NastaveniPlateb;
use App\Platby\Platba;
use App\Platby\Platby;
use App\Platby\PlatbyNedostupne;
use App\Platby\Rezim;
use App\Platby\StavPlatby;
use App\Platby\UzZaplaceno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Throwable;

/**
 * Veřejná část plateb: zaplatit (přesměrování na bránu), návrat od brány,
 * výsledek, webhook a simulace. Platba se v adrese pozná podle verejne_id
 * (UUID) – číslo objednávky by šlo uhodnout. Stránky ukazují jen stav,
 * popis a částku, žádné osobní údaje.
 *
 * Výsledek chodí dvěma cestami, které si nevěří: návrat zákazníka a webhook
 * brány. Obě jen spustí Platby::overStav() (dotaz na bránu) – stejné volání
 * vícekrát nic nezdvojí.
 */
class PlatbyController
{
    public function __construct(private readonly Platby $platby) {}

    /** Odkaz k zaplacení: na bránu, nebo na výsledek, když už není co platit. */
    public function zaplatit(Platba $platba): RedirectResponse
    {
        if ($platba->stav === StavPlatby::Ceka && filled($platba->presmerovani_url)) {
            return redirect()->away($platba->presmerovani_url);
        }

        return redirect()->route('platby.vysledek', $platba);
    }

    /** Návrat od brány (Mo.one přidá ?status=&transactionId=, Comgate nic) – stav se zjistí dotazem. */
    public function navrat(Request $request, Platba $platba): RedirectResponse
    {
        if ($platba->stav->otevrena()) {
            rescue(fn () => $this->platby->overStav($platba, 'navrat', array_filter([
                'status' => $request->query('status'),
                'transactionId' => $request->query('transactionId'),
            ])), report: false);
        }

        $platba->refresh();

        if (filled($platba->navrat_url) && $this->naTentoWeb($platba->navrat_url)) {
            $oddelovac = str_contains($platba->navrat_url, '?') ? '&' : '?';

            return redirect()->to($platba->navrat_url.$oddelovac.'platba='.$platba->verejne_id);
        }

        return redirect()->route('platby.vysledek', $platba);
    }

    public function vysledek(Platba $platba): View
    {
        // Čekající: dotaz nejvýš jednou za 5 s (stránka se sama obnovuje).
        if ($platba->stav->otevrena() && (! $platba->overeno_v || $platba->overeno_v->lt(now()->subSeconds(5)))) {
            rescue(fn () => $this->platby->overStav($platba, 'navrat'), report: false);
            $platba->refresh();
        }

        return view('platby.vysledek', ['platba' => $platba]);
    }

    /** Zkusit znovu po zamítnuté / zrušené platbě. */
    public function znovu(Platba $platba): RedirectResponse
    {
        try {
            $nova = $this->platby->znovu($platba);
        } catch (UzZaplaceno $e) {
            return redirect()->route('platby.vysledek', $e->platba);
        } catch (PlatbyNedostupne|ChybaBrany $e) {
            return redirect()->route('platby.vysledek', $platba)->with('chyba', 'Platbu teď nejde založit. Zkuste to prosím za chvíli.');
        } catch (Throwable) {
            return redirect()->route('platby.vysledek', $platba);
        }

        return redirect()->route('platby.zaplatit', $nova);
    }

    /**
     * Oznámení brány (Comgate: form, Mo.one: JSON bez podpisu). Jen podnět –
     * stav se ověří dotazem. 503 = ověřit nešlo, brána to zkusí znovu.
     */
    public function webhook(Request $request, string $brana): Response
    {
        $platba = $this->platby->najdiZWebhooku($brana, $request);

        if (! $platba) {
            return response('Neznámá platba', 404);
        }

        try {
            if (! NastaveniPlateb::proPlatbu($platba)->webhookPravy($request)) {
                return response('Neplatné tajemství', 403);
            }

            $this->platby->overStav($platba, 'webhook', Arr::except($request->all(), ['secret', 'merchant']));
        } catch (ChybaBrany|PlatbyNedostupne) {
            return response('Stav platby teď nejde ověřit', 503);
        }

        // Comgate čeká odpověď ve tvaru code=0&message=OK.
        return $brana === 'comgate'
            ? response('code=0&message=OK', 200, ['Content-Type' => 'text/plain'])
            : response('OK', 200);
    }

    /** Simulace brány – jen lokálně a v testech (jinak 404). */
    public function simulace(Platba $platba): View
    {
        abort_unless($platba->rezim === Rezim::Simulace && NastaveniPlateb::simulaceDovolena(), 404);

        return view('platby.simulace', ['platba' => $platba]);
    }

    public function simulaceOdeslat(Request $request, Platba $platba): RedirectResponse
    {
        abort_unless($platba->rezim === Rezim::Simulace && NastaveniPlateb::simulaceDovolena(), 404);

        $stav = match ($request->input('vysledek')) {
            'zaplatit' => StavPlatby::Zaplacena,
            'zamitnout' => StavPlatby::Zamitnuta,
            default => StavPlatby::Zrusena,
        };

        if ($platba->stav->otevrena()) {
            Simulace::nastav($platba, $stav);
        }

        return redirect()->route('platby.navrat', $platba);
    }

    /** Návratová adresa jen na tento web – jinak by šlo přesměrovat kamkoli (open redirect). */
    private function naTentoWeb(string $adresa): bool
    {
        if (str_starts_with($adresa, '/') && ! str_starts_with($adresa, '//')) {
            return true;
        }

        $host = parse_url($adresa, PHP_URL_HOST);

        return $host !== null && in_array(strtolower($host), [strtolower(request()->getHost()), strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))], true);
    }
}
