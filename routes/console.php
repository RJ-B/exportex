<?php

use App\Support\FrontaUloh;
use App\Support\Zdravi;
use Illuminate\Support\Facades\Schedule;

// Fronta úloh: worker na pozadí jen, když ve frontě něco čeká (FrontaUloh) –
// v cronu má aplikace jen schedule:run (portál ho posouvá v rámci minuty).
// Doběhne, až je fronta prázdná (nejvýš 50 s); další minutu se pustí znovu.
// withoutOverlapping(minuty, false): druhý parametr vypíná pcntl_signal(),
// který Hestia zakazuje; zámek drží, dokud worker běží (uvolní schedule:finish).
Schedule::exec(FrontaUloh::prikaz())
    ->everyMinute()
    ->name('fronta')
    ->withoutOverlapping(5, false)
    ->runInBackground()
    ->when(fn () => FrontaUloh::maPraci());

// Provozní logy. withoutOverlapping(minuty, false): druhý parametr vypíná
// pcntl_signal(), který Hestia zakazuje; platnost zámku podle doby běhu,
// ne na den – po pádu by se úloha do vypršení zámku ani nezkusila.
// Pošta (posta:fronta, posta:obnov-token) se plánuje v PostaServiceProvider;
// opakování a kontrolu schránek dělá Pošta sama.
Schedule::command('logs:prune')->dailyAt('03:30')->withoutOverlapping(60, false);

// Značka pro portál, že plánovač běží (App\Support\Zdravi).
Schedule::call(fn () => Zdravi::znackaPlanovace())->everyMinute()->name('zdravi-planovac');
