<?php

namespace App\Platby;

use RuntimeException;

/** Předmět (objednávka) už má zaplacenou platbu – druhá by byla dvojí zaplacení. */
class UzZaplaceno extends RuntimeException
{
    public function __construct(public readonly Platba $platba)
    {
        parent::__construct(__('platby.uz_zaplaceno.uz_je_zaplaceno_platba', ['verejne_id' => $platba->verejne_id]));
    }
}
