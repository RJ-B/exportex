<?php

use App\Http\Middleware\BezpecnostniHlavicky;
use App\Http\Middleware\KanonickaDomena;
use App\Http\Middleware\SekceWebuMiddleware;
use App\Services\ErrorLogger;
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
        // Veřejné routy vypínatelné sekce webu: ->middleware('sekce:sluzby').
        $middleware->alias(['sekce' => SekceWebuMiddleware::class]);
        // www.exportex.cz trvale na exportex.cz a bezpečnostní hlavičky na celém
        // webu (administrace je má v AdminPanelProvider).
        // Odhlášení z oznámení jedním kliknutím z poštovního programu (RFC 8058):
        // POST bez cookies – pravost dává podpis odkazu, ne CSRF.
        $middleware->validateCsrfTokens(except: ['oznameni/odhlasit/*']);
        // Nepřihlášený na stránce, která přihlášení chce (Oznámení): přihlášení
        // projektu, když ho web má, jinak přihlášení do administrace.
        $middleware->redirectGuestsTo(fn () => Route::has('login') ? route('login') : '/admin/login');
        $middleware->web(append: [
            KanonickaDomena::class,
            BezpecnostniHlavicky::class,
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

        $exceptions->report(function (Throwable $e) {
            app(ErrorLogger::class)->report($e);

            // 404, 403, 419… jsou běžný provoz: po stopIgnoring by jinak
            // zaplavily laravel.log. false = dál je nezpracovávat.
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return false;
            }
        });
    })->create();
