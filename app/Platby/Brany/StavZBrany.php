<?php

namespace App\Platby\Brany;

use App\Platby\StavPlatby;

/** Autoritativní stav platby z dotazu na bránu (ne z webhooku ani z návratu). */
final class StavZBrany
{
    public function __construct(
        public readonly StavPlatby $stav,
        /** Částka podle brány v haléřích – musí sedět s naší, jinak se zaplacení nepřijme. */
        public readonly ?int $castka = null,
        public readonly ?string $metoda = null,
        public readonly ?string $poznamka = null,
        public readonly array $data = [],
    ) {}
}
