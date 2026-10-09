<?php

namespace App\Platby;

/**
 * Jak platby běží. Ostře jen na produkci s ověřenými údaji brány klienta;
 * testovací brána Sim&Ren (Mo.one test) nikdy nestrhne peníze; simulace
 * jen lokálně a v automatických testech.
 */
enum Rezim: string
{
    case Ostry = 'ostry';
    case Testovaci = 'test';
    case Simulace = 'simulace';

    public function popis(): string
    {
        return match ($this) {
            self::Ostry => 'Ostrý',
            self::Testovaci => 'Testovací',
            self::Simulace => 'Simulace',
        };
    }

    public function testovaci(): bool
    {
        return $this !== self::Ostry;
    }
}
