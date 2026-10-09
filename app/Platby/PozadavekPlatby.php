<?php

namespace App\Platby;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Co se má zaplatit – vstup pro Platby::zaloz().
 *
 *     $platba = app(Platby::class)->zaloz(new PozadavekPlatby(
 *         castka: Platby::halere($objednavka->celkem),   // Kč → haléře
 *         popis: 'Objednávka '.$objednavka->cislo,
 *         email: $objednavka->email,
 *         jmeno: $objednavka->jmeno, prijmeni: $objednavka->prijmeni,
 *         predmet: $objednavka,                          // klíč idempotence = objednavka:15
 *         reference: $objednavka->cislo,
 *         navratUrl: route('objednavka', $objednavka),   // kam po platbě (?platba=<id>)
 *     ));
 *     return redirect()->away($platba->presmerovani_url);
 */
final class PozadavekPlatby
{
    public function __construct(
        public readonly int $castka,
        public readonly string $popis,
        public readonly string $email,
        public readonly ?string $jmeno = null,
        public readonly ?string $prijmeni = null,
        public readonly ?Model $predmet = null,
        public readonly ?string $reference = null,
        public readonly ?string $klic = null,
        public readonly ?string $navratUrl = null,
        public readonly string $mena = 'CZK',
    ) {
        if ($castka < 100) {
            throw new InvalidArgumentException('Nejmenší platba je 1 Kč.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Platba potřebuje platný e-mail zákazníka.');
        }
    }

    /**
     * Klíč idempotence: na jeden klíč nejvýš jedna rozpracovaná a žádná druhá
     * zaplacená platba. Výchozí = předmět (typ:id); bez předmětu žádný.
     */
    public function klic(): ?string
    {
        return $this->klic ?? ($this->predmet ? $this->predmet->getMorphClass().':'.$this->predmet->getKey() : null);
    }
}
