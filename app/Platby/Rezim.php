<?php

namespace App\Platby;

/**
 * Jak platby běží. Ostře jen na produkci s ověřenými údaji brány klienta;
 * testovací brána Sim&Ren (Mo.one) je výchozně testovací (api-test, nic se nestrhne),
 * ale portál ji může nasměrovat na ostrý PayPoint Sim&Ren (api.znpay.tech) – pak jsou
 * platby skutečné (viz NastaveniPlateb::testovaciBranaOstra()); simulace jen lokálně
 * a v automatických testech.
 */
enum Rezim: string
{
    case Ostry = 'ostry';
    case Testovaci = 'test';
    case Simulace = 'simulace';

    public function popis(): string
    {
        return match ($this) {
            self::Ostry => __('platby.rezim.ostry'),
            self::Testovaci => __('platby.rezim.testovaci'),
            self::Simulace => __('platby.rezim.simulace'),
        };
    }

    public function testovaci(): bool
    {
        return $this !== self::Ostry;
    }
}
