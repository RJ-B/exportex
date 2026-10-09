<?php

namespace App\Platby\Udalosti;

use App\Platby\Platba;
use App\Platby\StavPlatby;
use Illuminate\Foundation\Events\Dispatchable;

/** Platba změnila stav (po commitu). Projekt podle ní mění stav objednávky. */
class PlatbaZmenilaStav
{
    use Dispatchable;

    public function __construct(public readonly Platba $platba, public readonly ?StavPlatby $puvodni) {}
}
