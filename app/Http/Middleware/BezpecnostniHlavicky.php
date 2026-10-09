<?php

namespace App\Http\Middleware;

use App\Support\NastaveniWebu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bezpečnostní hlavičky pro web i administraci (šablona Sim&Ren).
 *
 * Veřejný web exportex.cz má navíc přísnou CSP (dřív v <meta> v HTML, protože
 * GitHub Pages hlavičky neuměl – frame-ancestors teď konečně platí): skripty,
 * písma i fotky jen z vlastní adresy, žádný inline skript (data webu jsou
 * v <script type="application/json">, ten se nespouští). Až když se v Obsahu
 * webu → SEO a měření zapne měření, přidá se cookie lišta šablony (inline
 * skript) a domény měřicích nástrojů – spustí se až po souhlasu.
 *
 * Administrace a stránky šablony (nastavení hesla) CSP nemají – Filament
 * a Livewire mají vložené skripty, styly a písma a přísná politika by je rozbila.
 */
class BezpecnostniHlavicky
{
    /** Měření z Obsahu webu → SEO a měření (GA4, Google Ads, Seznam, Sklik). */
    private const MERENI = 'https://www.googletagmanager.com https://*.google-analytics.com https://*.analytics.google.com '
        .'https://www.googleadservices.com https://googleads.g.doubleclick.net https://*.doubleclick.net https://www.google.com '
        .'https://c.seznam.cz https://*.seznam.cz';

    public function handle(Request $request, Closure $next): Response
    {
        $odpoved = $next($request);
        $h = $odpoved->headers;
        $administrace = $request->is('admin', 'admin/*', 'livewire*', 'filament/*', 'nastaveni-hesla*');

        // Cizí web nás nesmí vložit do rámu (clickjacking). Veřejný web do rámu
        // nesmí vůbec, administrace jen sama do sebe (Filament).
        $h->set('X-Frame-Options', $administrace ? 'SAMEORIGIN' : 'DENY', false);
        $h->set('X-Content-Type-Options', 'nosniff', false);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()', false);

        if (! $administrace) {
            $mereni = rescue(fn () => NastaveniWebu::meri(), false, false);

            $h->set('Content-Security-Policy', implode('; ', array_filter([
                "default-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                $mereni ? "script-src 'self' 'unsafe-inline' ".self::MERENI : "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "font-src 'self'",
                $mereni ? "img-src 'self' data: https:" : "img-src 'self' data:",
                $mereni ? "connect-src 'self' ".self::MERENI : "connect-src 'self'",
                $mereni ? 'frame-src https://*.doubleclick.net https://www.googletagmanager.com' : null,
                "manifest-src 'self'",
                "object-src 'none'",
                "frame-ancestors 'none'",
                $request->isSecure() ? 'upgrade-insecure-requests' : null,
            ])), false);
        }

        // Testovací web (*.test.simren.cz) do vyhledávačů nepatří – klientský web by
        // tam byl dvakrát. Administrace a nastavení hesla nikde.
        if (! app()->isProduction() || $request->is('admin', 'admin/*', 'nastaveni-hesla*')) {
            $h->set('X-Robots-Tag', 'noindex, nofollow', false);
        }

        // HSTS jen na produkci přes HTTPS – lokálně by prohlížeč zablokoval http.
        if ($request->isSecure() && app()->isProduction()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $odpoved;
    }
}
