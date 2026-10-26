<?php

namespace App\Platby;

/**
 * Stav platby. Mění ho jen Platby::prejdi() (zámek řádku, záznam do historie,
 * události) – nikdy přímý zápis do sloupce.
 *
 * Zrušenou nebo zamítnutou platbu brána ještě může potvrdit jako zaplacenou
 * (zákazník nechal bránu otevřenou a zaplatil později) – peníze odešly, proto
 * se to přijme a zapíše do Chyb, ať o tom ví člověk.
 */
enum StavPlatby: string
{
    /** Založená u nás, brána ještě neodpověděla. */
    case Zalozena = 'zalozena';

    /** Brána platbu přijala, zákazník platí (nebo odešel od brány). */
    case Ceka = 'ceka';

    case Zaplacena = 'zaplacena';

    /** Brána platbu odmítla (karta, banka). */
    case Zamitnuta = 'zamitnuta';

    /** Zrušená – zákazníkem, vypršením nebo z administrace. */
    case Zrusena = 'zrusena';

    case CastecneVracena = 'castecne_vracena';

    case Vracena = 'vracena';

    /** Bránu se nepodařilo založit (výpadek, špatné údaje) – v Chybách. */
    case Chyba = 'chyba';

    public function popis(): string
    {
        return match ($this) {
            self::Zalozena => __('platby.stav_platby.zalozena'),
            self::Ceka => __('platby.stav_platby.ceka_na_zaplaceni'),
            self::Zaplacena => __('platby.stav_platby.zaplacena'),
            self::Zamitnuta => __('platby.stav_platby.zamitnuta'),
            self::Zrusena => __('platby.stav_platby.zrusena'),
            self::CastecneVracena => __('platby.stav_platby.castecne_vracena'),
            self::Vracena => __('platby.stav_platby.vracena'),
            self::Chyba => __('platby.stav_platby.chyba_brany'),
        };
    }

    /** Barva štítku ve Filamentu. */
    public function barva(): string
    {
        return match ($this) {
            self::Zaplacena => 'success',
            self::Zalozena, self::Ceka => 'warning',
            self::Zamitnuta, self::Chyba => 'danger',
            self::CastecneVracena, self::Vracena => 'info',
            self::Zrusena => 'gray',
        };
    }

    /** Peníze přišly (i když se část nebo všechno pak vrátilo). */
    public function zaplaceno(): bool
    {
        return in_array($this, [self::Zaplacena, self::CastecneVracena, self::Vracena], true);
    }

    /** Ještě se může změnit bez zásahu člověka (ověřuje se dotazem na bránu). */
    public function otevrena(): bool
    {
        return in_array($this, [self::Zalozena, self::Ceka], true);
    }

    public function muzePrejitNa(self $novy): bool
    {
        if ($novy === $this) {
            return false;
        }

        return in_array($novy, match ($this) {
            self::Zalozena => [self::Ceka, self::Zaplacena, self::Zamitnuta, self::Zrusena, self::Chyba],
            self::Ceka => [self::Zaplacena, self::Zamitnuta, self::Zrusena],
            // Pozdě zaplacená platba – brána je autoritativní.
            self::Zamitnuta, self::Zrusena => [self::Zaplacena],
            self::Zaplacena => [self::CastecneVracena, self::Vracena],
            self::CastecneVracena => [self::Vracena],
            self::Vracena, self::Chyba => [],
        }, true);
    }

    /** @return array<string, string> hodnota => popis (filtry) */
    public static function moznosti(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->popis()])->all();
    }
}
