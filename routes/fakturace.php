<?php

use App\Fakturace\Http\FakturaceWebhook;
use Illuminate\Support\Facades\Route;

/*
 * Doplněk Fakturace (docs/fakturace.md) – načítá FakturaceServiceProvider jen se
 * zapnutým doplňkem. Webhook je mimo skupinu web (fakturace nemá session ani
 * CSRF token) a mimo Stav webu – pravost dává podpis V2.
 */
Route::post('/webhooky/fakturace', FakturaceWebhook::class)
    ->middleware('throttle:120,1')
    ->name('fakturace.webhook');
