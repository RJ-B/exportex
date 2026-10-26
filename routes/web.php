<?php

use App\Http\Controllers\KontaktController;
use App\Http\Controllers\MapaWebuController;
use App\Http\Controllers\NastaveniHeslaController;
use App\Http\Controllers\OznameniController;
use App\Http\Controllers\PrihlaseniZCrmController;
use App\Http\Controllers\ZarizeniController;
use App\Http\Middleware\StavWebuMiddleware;
use Illuminate\Support\Facades\Route;

// Veřejný web. Stav webu (Správa aplikace → Stav webu) tu může ukázat „Připravujeme“
// nebo „Údržbu“ – nové veřejné routy patří DO této skupiny.
Route::middleware(StavWebuMiddleware::class)->group(function () {
    // Jednostránkový web: sekce se skládají podle Obsahu webu (pořadí, zapnuté).
    Route::view('/', 'domu')->name('domu');

    // Poptávkový formulář – sekce „Kontakt“ na úvodní stránce (jde vypnout
    // v Obsahu webu). Proti botům skryté pole + časová past (OchranaFormulare)
    // a limit odeslání z jedné IP.
    Route::middleware('sekce:formular')->group(function () {
        Route::get('/kontakt', [KontaktController::class, 'zobrazit'])->name('kontakt');
        Route::post('/kontakt', [KontaktController::class, 'odeslat'])->middleware('throttle:formular')->name('kontakt.odeslat');
    });
});

// Nastavení hesla z CRM (Můj účet správce → simren:spravce). V každém stavu
// webu – správce se musí dostat dovnitř i během Údržby.
Route::get('/nastaveni-hesla/{token}', [NastaveniHeslaController::class, 'formular'])->name('nastaveni-hesla');
Route::post('/nastaveni-hesla', [NastaveniHeslaController::class, 'ulozit'])->middleware('throttle:10,1')->name('nastaveni-hesla.ulozit');
// Účet s heslem: jednorázové přihlášení z CRM rovnou do administrace (PrihlaseniZCrm).
Route::get('/prihlaseni-spravce/{token}', PrihlaseniZCrmController::class)->middleware(['signed', 'throttle:10,1'])->name('prihlaseni-z-crm');

// Právní stránky – přístupné v každém stavu webu (i v Údržbě a Připravujeme).
Route::view('/ochrana-osobnich-udaju', 'ochrana-osobnich-udaju')->name('ochrana-udaju');
Route::view('/zasady-cookies', 'zasady-cookies')->name('cookies');

// Adresy statického webu (do 10/2026 na GitHub Pages) – staré odkazy a záložky
// trvale na nové.
Route::permanentRedirect('/index.html', '/');
Route::permanentRedirect('/soukromi.html', '/ochrana-osobnich-udaju');
Route::permanentRedirect('/cookies.html', '/zasady-cookies');

// Pro vyhledávače (App\Support\MapaWebu): mimo produkci a mimo stav Online
// robots.txt nic nepovolí a sitemap.xml není. Statický public/robots.txt nepatří –
// server by ho vrátil dřív, než se zeptá aplikace.
Route::get('/robots.txt', [MapaWebuController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [MapaWebuController::class, 'sitemap'])->name('sitemap');

// Oznámení (docs/oznameni.md): centrum přihlášeného – stránka a zvoneček na webu
// i v administraci. V každém stavu webu (odstávku je potřeba oznámit i v Údržbě).
Route::middleware('auth')->prefix('oznameni')->name('oznameni.')->controller(OznameniController::class)->group(function () {
    Route::get('/', 'stranka')->name('stranka');
    Route::get('/centrum', 'centrum')->name('centrum');
    Route::post('/precteno-vse', 'prectenoVse')->name('precteno-vse');
    Route::post('/predvolby', 'predvolby')->name('predvolby');
    Route::post('/{prijemce}/precteno', 'precteno')->whereNumber('prijemce')->name('precteno');
    Route::post('/{prijemce}/archiv', 'archiv')->whereNumber('prijemce')->name('archiv');
});

// Podepsané odkazy z e-mailu a centra – bez přihlášení. Odhlášení přijímá i POST
// z poštovního programu (List-Unsubscribe-Post), proto je mimo CSRF (bootstrap/app.php).
Route::middleware(['signed', 'throttle:30,1'])->controller(OznameniController::class)->group(function () {
    Route::get('/oznameni/proklik/{prijemce}', 'proklik')->whereNumber('prijemce')->name('oznameni.proklik');
    Route::get('/oznameni/odhlasit/{user}/{druh}', 'odhlaseni')->whereNumber('user')->name('oznameni.odhlasit');
    Route::post('/oznameni/odhlasit/{user}/{druh}', 'odhlasit')->whereNumber('user')->name('oznameni.odhlasit.potvrdit');
});

// Druhý krok přihlášení (App\Bezpecnost\DruhyKrok): přidání passkeye tohoto zařízení
// (Zabezpečení, jen s heslem znovu) a spárování telefonu QR odkazem. V každém stavu webu.
Route::controller(ZarizeniController::class)->group(function () {
    Route::middleware(['auth', 'throttle:20,1'])->group(function () {
        Route::get('/ucet/passkey/moznosti', 'pridatMoznosti')->name('zarizeni.pridat.moznosti');
        Route::post('/ucet/passkey', 'pridat')->name('zarizeni.pridat');
    });

    // Telefon odkaz otevírá bez přihlášení – odkaz sám je důkaz, nikoho nepřihlásí.
    Route::middleware('throttle:20,10')->group(function () {
        Route::get('/ucet/parovani/{token}', 'parovani')->name('zarizeni.parovani');
        Route::get('/ucet/parovani/{token}/moznosti', 'parovaniMoznosti')->name('zarizeni.parovani.moznosti');
        Route::post('/ucet/parovani/{token}', 'sparovat')->name('zarizeni.sparovat');
    });
});
