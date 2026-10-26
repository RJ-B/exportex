<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web běží na jedné doméně – té z APP_URL. `www.` (Hestia ho zakládá ke každé
 * doméně jako alias) a další domény webu (`sablona.dalsi_domeny`) přesměruje
 * trvale na ni: jinak je web ve vyhledávačích dvakrát a přihlášení platí jen
 * na jedné z adres. Lokálně a v testech nic nedělá.
 */
class KanonickaDomena
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $kanonicka = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $host = strtolower($request->getHost());

        if ($kanonicka === '' || $host === $kanonicka) {
            return $next($request);
        }

        $presmerovat = $host === 'www.'.$kanonicka
            || $kanonicka === 'www.'.$host
            || in_array($host, array_map('strtolower', (array) config('sablona.dalsi_domeny', [])), true);

        if (! $presmerovat) {
            return $next($request);
        }

        return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
    }
}
