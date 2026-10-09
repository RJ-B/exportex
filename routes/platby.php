<?php

use App\Platby\Http\PlatbyController;
use Illuminate\Support\Facades\Route;

/*
 * Doplněk Platby (docs/platby.md) – načítá PlatbyServiceProvider jen se zapnutým
 * doplňkem. Mimo Stav webu: zaplacenou platbu je potřeba dokončit i v Údržbě.
 * Webhook je mimo skupinu web (brána nemá session ani CSRF token) – pravost
 * dává dotaz na stav platby u brány.
 */
Route::post('/platby/webhook/{brana}', [PlatbyController::class, 'webhook'])
    ->whereIn('brana', ['comgate', 'moone'])
    ->middleware('throttle:120,1')
    ->name('platby.webhook');

Route::middleware('web')->prefix('platby')->name('platby.')->controller(PlatbyController::class)->group(function () {
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/simulace/{platba}', 'simulace')->whereUuid('platba')->name('simulace');
        Route::post('/simulace/{platba}', 'simulaceOdeslat')->whereUuid('platba')->name('simulace.odeslat');
        Route::get('/{platba}/zaplatit', 'zaplatit')->whereUuid('platba')->name('zaplatit');
        Route::get('/{platba}/navrat', 'navrat')->whereUuid('platba')->name('navrat');
        Route::post('/{platba}/znovu', 'znovu')->whereUuid('platba')->middleware('throttle:10,1')->name('znovu');
        Route::get('/{platba}', 'vysledek')->whereUuid('platba')->name('vysledek');
    });
});
