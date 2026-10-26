<?php

namespace App\Platby;

/**
 * Předvolená platební metoda (úroveň 1 Apple Pay / Google Pay, docs/platby.md):
 * platba se pořád platí na platební stránce brány, jen rovnou otevře zvolenou
 * peněženku místo výběru metod. Bez předvolby (null) vybírá zákazník na bráně.
 *
 * Nativní tlačítko v aplikaci (platební token z telefonu) to není – to by
 * musela brána přijmout přes API (docs/platby.md → Nativní Apple Pay a Google Pay).
 */
enum MetodaPlatby: string
{
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';

    public function popis(): string
    {
        return match ($this) {
            self::ApplePay => __('platby.metoda_platby.apple_pay'),
            self::GooglePay => __('platby.metoda_platby.google_pay'),
        };
    }

    /** Kód metody v Comgate (`method` při založení platby, výpis `method.json`). */
    public function kodComgate(): string
    {
        return match ($this) {
            self::ApplePay => 'APPLEPAY_REDIRECT',
            self::GooglePay => 'GOOGLEPAY_REDIRECT',
        };
    }

    /** Z požadavku aplikace (`apple_pay`, `google_pay`); cokoli jiného = bez předvolby. */
    public static function zVolby(mixed $volba): ?self
    {
        return is_string($volba) ? self::tryFrom($volba) : null;
    }
}
