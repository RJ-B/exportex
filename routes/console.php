<?php

use App\Support\FrontaUloh;
use App\Support\Oznameni\Odeslani;
use App\Support\UdrzbaDatabaze;
use App\Support\Zdravi;
use Illuminate\Support\Facades\Schedule;

// V cronu má aplikace jen schedule:run (portál ho spouští s posunem v rámci
// minuty, ať všechny aplikace serveru nestartují naráz). Všechno ostatní
// pouští plánovač – a jen když je co dělat.

// Fronta úloh: worker na pozadí jen, když ve frontě něco čeká (FrontaUloh).
// Doběhne, až je fronta prázdná (nejvýš 50 s); další minutu se pustí znovu.
// withoutOverlapping(minuty, false): druhý parametr vypíná pcntl_signal(),
// který Hestia zakazuje; zámek drží, dokud worker běží (uvolní schedule:finish).
Schedule::exec(FrontaUloh::prikaz())
    ->everyMinute()
    ->name('fronta')
    ->withoutOverlapping(5, false)
    ->runInBackground()
    ->when(fn () => FrontaUloh::maPraci());

// Oznámení: naplánovaná odeslat, zaseknutá rozeslat znovu – jen když je co (maPraci jen čte).
Schedule::call(fn () => app(Odeslani::class)->naplanovana())
    ->everyMinute()
    ->name('oznameni-naplanovana')
    ->withoutOverlapping(5, false)
    ->when(fn () => Odeslani::maPraci());

// Údržba se rozkládá: ->rozlozit() posune minutu o posun aplikace (App\Support\Rozvrh),
// ať všechny aplikace serveru nestartují stejnou minutu. Hodiny mimo zálohy
// (Server Zálohy 2:30–3:40, Hestia 5:10). Úlohy, jejichž čas vidí zákazník, se
// nerozkládají.

// Provozní logy. Platnost zámku podle doby běhu, ne na den – po pádu by se
// úloha do vypršení zámku ani nezkusila.
// Pošta (předání z odchozí fronty, obnova tokenu) se plánuje v PostaServiceProvider;
// opakování a kontrolu schránek dělá Pošta sama.
Schedule::command('logs:prune')->dailyAt('01:00')->rozlozit()->withoutOverlapping(60, false);

// Provozní tabulky, které Laravel sám nečistí (selhané úlohy, dávky, obnova hesla, prošlá cache).
Schedule::call(function (): void {
    UdrzbaDatabaze::spust();
})->dailyAt('00:00')->rozlozit()->name('udrzba-databaze')->withoutOverlapping(30, false);

// Značka pro portál, že plánovač běží (App\Support\Zdravi).
Schedule::call(fn () => Zdravi::znackaPlanovace())->everyMinute()->name('zdravi-planovac');
