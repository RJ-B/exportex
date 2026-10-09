<?php

namespace App\Support\Oznameni;

use App\Enums\DruhOznameni;
use App\Models\Nastaveni;

/**
 * Nastavení oznámení, které jde měnit bez nasazení (superadmin, Oznámení →
 * Nastavení). Výchozí hodnoty v config/oznameni.php – konzervativní:
 * marketing vypnutý, oznámení z portálu jen správcům.
 */
class NastaveniOznameni
{
    public const MARKETING = 'oznameni.marketing';

    public const PORTAL_KONCOVYM = 'oznameni.portal_koncovym';

    public static function marketing(): bool
    {
        return self::bool(self::MARKETING, (bool) config('oznameni.marketing', false));
    }

    public static function portalKoncovym(): bool
    {
        return self::bool(self::PORTAL_KONCOVYM, (bool) config('oznameni.portal_koncovym', false));
    }

    public static function uloz(bool $marketing, bool $portalKoncovym): void
    {
        Nastaveni::nastav(self::MARKETING, $marketing ? '1' : '0');
        Nastaveni::nastav(self::PORTAL_KONCOVYM, $portalKoncovym ? '1' : '0');
    }

    public static function limit(string $klic): int
    {
        return (int) config('oznameni.limity.'.$klic);
    }

    /** Druhy, které se v aplikaci používají (marketing jen zapnutý). @return list<DruhOznameni> */
    public static function druhy(): array
    {
        return array_values(array_filter(DruhOznameni::cases(), fn (DruhOznameni $d) => $d !== DruhOznameni::Marketing || self::marketing()));
    }

    /** Co smí napsat člověk v administraci. @return list<DruhOznameni> */
    public static function druhyZAdministrace(): array
    {
        return array_values(array_filter(self::druhy(), fn (DruhOznameni $d) => $d->zAdministrace()));
    }

    private static function bool(string $klic, bool $vychozi): bool
    {
        $hodnota = rescue(fn () => Nastaveni::hodnota($klic), null, false);

        return $hodnota === null ? $vychozi : $hodnota === '1';
    }
}
