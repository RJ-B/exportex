<?php

namespace App\Support\Oznameni;

use RuntimeException;

/** Oznámení nejde odeslat (chybí titulek, nikdo ho nedostane, překročený limit…). */
class OznameniNejdeOdeslat extends RuntimeException
{
    /** @param  list<string>  $chyby */
    public function __construct(public readonly array $chyby)
    {
        parent::__construct(implode(' ', $chyby));
    }
}
