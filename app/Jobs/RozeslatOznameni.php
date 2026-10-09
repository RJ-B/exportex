<?php

namespace App\Jobs;

use App\Models\Oznameni;
use App\Support\Oznameni\Odeslani;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Velké oznámení: příjemci a doručení ve frontě (Odeslani::rozeslat je opakovatelné). */
class RozeslatOznameni implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $oznameniId) {}

    public function handle(Odeslani $odeslani): void
    {
        if ($oznameni = Oznameni::query()->find($this->oznameniId)) {
            $odeslani->rozeslat($oznameni);
        }
    }
}
