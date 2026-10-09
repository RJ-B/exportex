<?php

namespace App\Jobs;

use App\Models\OznameniDoruceni;
use App\Support\Oznameni\Odeslani;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Dávka e-mailů jednoho oznámení (nejvýš limit za minutu). Jen „čeká“ – opakování nepošle dvakrát. */
class PoslatEmailyOznameni implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    /** @param  list<int>  $doruceniIds */
    public function __construct(public array $doruceniIds) {}

    public function handle(Odeslani $odeslani): void
    {
        OznameniDoruceni::query()->whereIn('id', $this->doruceniIds)->where('stav', OznameniDoruceni::CEKA)
            ->each(fn (OznameniDoruceni $doruceni) => $odeslani->poslatEmail($doruceni));
    }
}
