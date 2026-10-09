<?php

namespace App\Platby;

use RuntimeException;

/** Předmět (objednávka) už má zaplacenou platbu – druhá by byla dvojí zaplacení. */
class UzZaplaceno extends RuntimeException
{
    public function __construct(public readonly Platba $platba)
    {
        parent::__construct('Už je zaplaceno (platba '.$platba->verejne_id.').');
    }
}
