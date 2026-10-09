<?php

namespace App\Providers;

use App\Mail\LoggingMailManager;
use App\Observers\AuditableObserver;
use App\Services\UdrzbaDoAktivity;
use App\Support\Posta\Propojeni as Posta;
use App\Support\SekceWebu;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Signals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Každý odchozí mail projde LoggingTransportem a zapíše se do Logy → E-maily.
        // Obaluje se `mail.manager`, ne jednotlivá volání – jinak by log minul
        // maily posílané mimo Mailable (Mail::raw). Musí to být extend():
        // MailServiceProvider je deferred a prostou vazbu by přepsal.
        //
        // Pošta (posta.simren.cz): propojená aplikace posílá přes transport `posta`
        // (App\Support\Posta, PostaServiceProvider) – nastaví se tady, při prvním
        // odeslání, ne při každém požadavku. Bez propojení platí .env.
        $this->app->extend('mail.manager', function ($manager, $app) {
            Posta::pouzij();

            return new LoggingMailManager($app);
        });
    }

    public function boot(): void
    {
        // Veřejné formuláře: 3 odeslání za minutu a 20 za den z jedné IP. Při
        // překročení vrátí stránku s chybou formuláře (ne holou 429).
        RateLimiter::for('formular', function (Request $request) {
            // Formulář na webu posílá JSON (assets/js/main.js) – dostane 429, ne přesměrování.
            $prilisMnoho = fn () => $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'throttle'], 429)
                : back()->withInput()->withErrors(['formular' => 'Odeslali jste moc zpráv za sebou. Zkuste to prosím později.']);

            return [
                Limit::perMinute(3)->by('formular-min:'.$request->ip())->response($prilisMnoho),
                Limit::perDay(20)->by('formular-den:'.$request->ip())->response($prilisMnoho),
            ];
        });

        // Za reverzní proxy Hestie končí odkazy na http, když se schéma nevynutí.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // @sekce('sluzby') … @endsekce – část webu jen u zapnuté sekce (Obsah webu → přepínač).
        Blade::if('sekce', fn (string $klic) => SekceWebu::zapnuta($klic));

        // Aktivita: kdo změnil účty a nastavení (sledovaná pole v observeru).
        foreach (array_keys(AuditableObserver::WATCHED) as $model) {
            $model::observe(AuditableObserver::class);
        }

        // Údržba (artisan down / up) do Aktivity jako „Údržba zapnuta / vypnuta“, ne do Chyb.
        UdrzbaDoAktivity::poslouchej();

        // HestiaCP má v CLI zakázané pcntl_* funkce, ale rozšíření je načtené.
        // Laravel se ptá jen extension_loaded('pcntl'), takže příkazy se
        // signály (queue:work, schedule:work) by spadly na „Call to undefined
        // function pcntl_signal()“. Až při CommandStarting – ArtisanServiceProvider
        // je deferred a vlastní resolver by nastavil až po bootu.
        Event::listen(CommandStarting::class, fn () => Signals::resolveAvailabilityUsing(fn () => $this->app->runningInConsole()
            && ! $this->app->runningUnitTests()
            && extension_loaded('pcntl')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_async_signals')));
    }
}
