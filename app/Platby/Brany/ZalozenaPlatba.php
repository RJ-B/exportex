<?php

namespace App\Platby\Brany;

/** Odpověď brány na založení: její id platby a kam poslat zákazníka. */
final class ZalozenaPlatba
{
    public function __construct(
        public readonly string $externiId,
        public readonly string $presmerovani,
        public readonly array $data = [],
    ) {}
}
