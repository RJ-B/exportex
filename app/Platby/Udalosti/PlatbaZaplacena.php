<?php

namespace App\Platby\Udalosti;

use App\Platby\Platba;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Platba je zaplacená – přijde jednou za platbu (zámek a přechod stavu), ať
 * dorazí webhook, návrat i plánovač naráz. Posluchač projektu (objednávka
 * zaplacena, vystavit doklad…) má být rychlý; pomalé věci do fronty.
 *
 * $platba->testovaci() = zaplaceno přes testovací bránu nebo simulaci –
 * nic se nestrhlo, zboží neodesílat.
 */
class PlatbaZaplacena
{
    use Dispatchable;

    public function __construct(public readonly Platba $platba) {}
}
