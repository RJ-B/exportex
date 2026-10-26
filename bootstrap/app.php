<?php

use App\Http\Middleware\BezpecnostniHlavicky;
use App\Http\Middleware\KanonickaDomena;
use App\Http\Middleware\NastavJazyk;
use App\Http\Middleware\SekceWebuMiddleware;
use App\Services\ErrorLogger;
use App\Support\PodvrzenyLivewire;
use App\Support\Zdravi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Mimo skupinu web: bez session a cookies, aby spadlá databáze
        // skončila 503 z kontroly, ne 500 z middleware.
        then: fn () => Route::get('/zdravi', [Zdravi::class, 'odpoved']),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Kontrola zdraví odpovídá i v údržbě (artisan down): portál podle ní pozná,
        // že nová verze funguje, a nasadit jde i během údržby. Bez výjimky vrací
        // /zdravi v údržbě 503 a nasazení se vrátí na předchozí verzi.
        $middleware->preventRequestsDuringMaintenance(except: ['zdravi']);
        // Veřejné routy vypínatelné sekce webu: ->middleware('sekce:sluzby').
        $middleware->alias(['sekce' => SekceWebuMiddleware::class]);
        // Odhlášení z oznámení jedním kliknutím z poštovního programu (RFC 8058):
        // POST bez cookies – pravost dává podpis odkazu, ne CSRF.
        $middleware->validateCsrfTokens(except: ['oznameni/odhlasit/*']);
        // Nepřihlášený na stránce, která přihlášení chce (Oznámení): přihlášení
        // projektu, když ho web má, jinak přihlášení do administrace.
        $middleware->redirectGuestsTo(fn () => Route::has('login') ? route('login') : '/admin/login');
        // Jedna doména (www. a další domény webu trvale na APP_URL) a bezpečnostní
        // hlavičky na celém webu (administrace je má v AdminPanelProvider).
        $middleware->web(append: [
            KanonickaDomena::class,
            BezpecnostniHlavicky::class,
            // Jazyk požadavku (uživatel → prohlížeč → výchozí), docs/jazyky.md.
            NastavJazyk::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Chyby i do Logy → Chyby v administraci (agregované, s odškrtnutím).
        // laravel.log zůstává – z něj je čte portál (`simren:zdravi`).
        //
        // HttpException je ve vestavěném `$internalDontReport` a zahodí se ještě
        // před callbackem – bez stopIgnoring by se `abort(500)` nikdy nezapsal.
        $exceptions->stopIgnoring(HttpException::class);

        // Podvržený požadavek na Livewire ze skenerů (zamčené vlastnosti, nesmyslná
        // data) Livewire odmítne – to není chyba aplikace: 400 bez zápisu (PodvrzenyLivewire).
        $exceptions->map(fn (Throwable $e) => PodvrzenyLivewire::mapuj($e));

        $exceptions->report(function (Throwable $e) {
            app(ErrorLogger::class)->report($e);

            // 404, 403, 419… jsou běžný provoz: po stopIgnoring by jinak
            // zaplavily laravel.log. false = dál je nezpracovávat.
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return false;
            }
        });
    })->create();
