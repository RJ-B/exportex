<?php

namespace App\Providers;

use App\Support\Posta\FrontaPrikaz;
use App\Support\Posta\Klient;
use App\Support\Posta\NavratZPosty;
use App\Support\Posta\ObnovTokenPrikaz;
use App\Support\Posta\PostaTransport;
use App\Support\Posta\PrikazZPortalu;
use App\Support\Posta\Webhook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Pošta (posta.simren.cz) – ovladač pro aplikaci: mail transport `posta`,
 * webhook o výsledku zpráv, návrat z propojení a plánované úlohy.
 * Soběstačný, ať jde do starší aplikace vložit (docs/prevod-na-postu.md).
 */
class PostaServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        // Transport se registruje na správce pošty, až vznikne (MailServiceProvider je deferred).
        $this->callAfterResolving('mail.manager', function ($manager) {
            $manager->extend('posta', fn () => new PostaTransport($this->app->make(Klient::class)));
        });
    }

    public function boot(): void
    {
        // Webhook bez session a CSRF – pravost dává podpis V2.
        Route::post('/posta/webhook', Webhook::class)->middleware('throttle:240,1')->name('posta.webhook');
        Route::get('/posta/propojeni/navrat', NavratZPosty::class)->middleware('web')->name('posta.propojeni.navrat');

        if ($this->app->runningInConsole()) {
            $this->commands([FrontaPrikaz::class, ObnovTokenPrikaz::class, PrikazZPortalu::class]);
        }

        // withoutOverlapping(minuty, false): druhý parametr vypíná pcntl_signal(),
        // který Hestia zakazuje (viz routes/console.php).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('posta:fronta')->everyMinute()->withoutOverlapping(5, false);
            $schedule->command('posta:obnov-token')->dailyAt('04:20')->withoutOverlapping(10, false);
        });
    }
}
