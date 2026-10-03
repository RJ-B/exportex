<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alternativní domény (www.exportex.cz) přesměruje trvale na kanonickou
 * exportex.cz (config web.alternativni_domeny). Drží SEO sílu na jedné doméně
 * a brání duplicitnímu obsahu — dřív to dělal GitHub Pages sám.
 */
class KanonickaDomena
{
    public function handle(Request $request, Closure $next): Response
    {
        $domena = $request->getHost();

        if (in_array($domena, config('web.alternativni_domeny', []), true)) {
            $cil = rtrim(config('web.url'), '/').'/'.ltrim($request->getRequestUri(), '/');

            return redirect()->away($cil, 301);
        }

        return $next($request);
    }
}
