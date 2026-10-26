<?php

namespace App\Http\Middleware;

use App\Support\Jazyky\Jazyky;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jazyk požadavku (App\Support\Jazyky\Jazyky::urci) – po UrciFirmu a po startu
 * session, aby byl znát přihlášený uživatel i firma. Skupina web i oba panely.
 */
class NastavJazyk
{
    public function handle(Request $request, Closure $next): Response
    {
        Jazyky::nastav(Jazyky::urci($request));

        return $next($request);
    }
}
