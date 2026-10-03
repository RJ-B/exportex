<?php

namespace App\Http\Middleware;

use App\Support\SekceWebu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Veřejné routy sekce webu: `->middleware('sekce:sluzby')`. Vypnutá sekce
 * (Obsah webu → přepínač) vrátí návštěvníkovi 404; přihlášený ji vidí dál,
 * ať si ji může připravit, než ji zapne.
 */
class SekceWebuMiddleware
{
    public function handle(Request $request, Closure $next, string $klic): Response
    {
        abort_unless(SekceWebu::zapnuta($klic) || auth()->check(), 404);

        return $next($request);
    }
}
