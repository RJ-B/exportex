<?php

use App\Support\Zdravi;
use Illuminate\Support\Facades\Schedule;

// Frontu (queue:work) spouští vlastní řádek v cronu, který zakládá portál –
// ne plánovač: podproces plánovače by běžel bez `-d disable_functions=`
// a queue:work na Hestii spadne na zakázaném pcntl_signal().

// Provozní logy. withoutOverlapping(minuty, false): druhý parametr vypíná
// pcntl_signal(), který Hestia zakazuje; platnost zámku podle doby běhu,
// ne na den – po pádu by se úloha do vypršení zámku ani nezkusila.
// Pošta (posta:fronta, posta:obnov-token) se plánuje v PostaServiceProvider;
// opakování a kontrolu schránek dělá Pošta sama.
Schedule::command('logs:prune')->dailyAt('03:30')->withoutOverlapping(60, false);

// Značka pro portál, že plánovač běží (App\Support\Zdravi).
Schedule::call(fn () => Zdravi::znackaPlanovace())->everyMinute()->name('zdravi-planovac');
