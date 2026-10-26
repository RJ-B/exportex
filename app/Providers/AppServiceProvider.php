<?php

namespace App\Providers;

use App\Mail\LoggingMailManager;
use App\Observers\AuditableObserver;
use App\Services\UdrzbaDoAktivity;
use App\Support\CasAplikace;
use App\Support\Posta\Propojeni as Posta;
use App\Support\Rozvrh;
use App\Support\SekceWebu;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Signals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Passkey je jen druhý krok po hesle (App\Bezpecnost\DruhyKrok) – trasy
        // balíčku (přihlášení bez hesla) se neregistrují; vlastní v routes/web.php.
        // V register(): balíček trasy zakládá ve svém boot(), který běží dřív.
        Passkeys::ignoreRoutes();

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

    /**
     * Odkazy (i v e-mailech z fronty) přes https, když web na https běží –
     * za proxy Hestie by jinak vedly na http.
     */
    public static function https(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Žádné prvky, které kreslí prohlížeč (Safari a Chrome je mají každý jinak):
     * Select, filtry tabulky (i TernaryFilter, je to potomek) a výběr data a času
     * kreslí Filament sám. Co Filament jako <select> vykreslí natvrdo
     * (stránkování, řazení a seskupení tabulky, měsíc v kalendáři), převleče
     * public/js/vyber.js (+ public/css/vyber.css, vložené v AdminPanelProvider).
     */
    public static function bezNativnichPrvku(): void
    {
        // Povinné pole nejde vyprázdnit křížkem – jako u nativní roletky.
        Select::configureUsing(fn (Select $pole) => $pole
            ->native(false)
            ->selectablePlaceholder(fn (Select $component): bool => ! $component->isRequired()));
        SelectFilter::configureUsing(fn (SelectFilter $filtr) => $filtr->native(false));
        DatePicker::configureUsing(fn (DatePicker $pole) => $pole->native(false)->displayFormat(__('formaty.datum')));
        DateTimePicker::configureUsing(fn (DateTimePicker $pole) => $pole->native(false)->displayFormat(__('formaty.datum_cas')));
        TimePicker::configureUsing(fn (TimePicker $pole) => $pole->native(false));
    }

    public function boot(): void
    {
        // ->rozlozit() u plánovaných úloh: posun aplikace v hodině (portál, jinak z adresy webu).
        Rozvrh::zapni();

        // Pravidlo času: čas zvenku (API, webhooky, shim) se ukládá v pásmu aplikace, ne v UTC.
        CasAplikace::zapni();

        self::https();

        // Veřejné formuláře: 3 odeslání za minutu a 20 za den z jedné IP. Při
        // překročení vrátí stránku s chybou formuláře (ne holou 429).
        RateLimiter::for('formular', function (Request $request) {
            // Formulář na webu posílá JSON (assets/js/main.js) – dostane 429, ne přesměrování.
            $prilisMnoho = fn () => $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'throttle'], 429)
                : back()->withInput()->withErrors(['formular' => __('obecne.app_service_provider.odeslali_jste_moc_zprav_za_sebou')]);

            return [
                Limit::perMinute(3)->by('formular-min:'.$request->ip())->response($prilisMnoho),
                Limit::perDay(20)->by('formular-den:'.$request->ip())->response($prilisMnoho),
            ];
        });

        self::bezNativnichPrvku();

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
