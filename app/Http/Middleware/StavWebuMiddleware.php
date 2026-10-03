<?php

namespace App\Http\Middleware;

use App\Enums\StavWebu;
use App\Support\ZakladniUdaje;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Veřejný web podle stavu (App\Enums\StavWebu): celý web, „Připravujeme“,
 * nebo „Web se právě upravuje“. Přihlášený vidí web vždy celý.
 *
 * Patří jen na veřejné routy (routes/web.php) – administrace, /zdravi a /up
 * mají vlastní a běží v každém stavu.
 */
class StavWebuMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $stav = StavWebu::aktualni();

        if ($stav === StavWebu::Online || auth()->check()) {
            return $next($request);
        }

        $udaje = ZakladniUdaje::nacti();

        if ($stav === StavWebu::Udrzba) {
            // 503 + Retry-After: vyhledávač pochopí, že jde o přechodný stav.
            return response()->view('stav-webu.udrzba', ['udaje' => $udaje], 503)->header('Retry-After', '600');
        }

        return response()->view('stav-webu.pripravujeme', ['udaje' => $udaje]);
    }
}
