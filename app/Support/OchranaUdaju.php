<?php

namespace App\Support;

use App\Models\Nastaveni;

/**
 * Ochrana osobních údajů (Obsah webu → Ochrana osobních údajů).
 *
 * Text je vzor podle simren.cz a skládá se sám: správce ze Základních údajů,
 * části podle toho, co web dělá (formulář, měření, smlouvy). Tady jsou jen
 * údaje, které se liší web od webu a doplní je správce.
 */
class OchranaUdaju
{
    public const VYCHOZI = [
        'ucinnost_od' => null,
        'poverenec' => null,
        'formular_udaje' => 'jméno a příjmení, e-mailová adresa, telefonní číslo, pokud jej uvedete, a obsah zprávy',
        'formular_doba' => '3 roky od poslední komunikace',
        'smlouvy' => '0',
        'smlouvy_doba' => 'po dobu trvání smluvního vztahu a zpravidla ještě 3 roky po jeho skončení',
        'dalsi_prijemci' => null,
        'doplnek' => null,
    ];

    /** @return array<string, ?string> */
    public static function nacti(): array
    {
        return collect(self::VYCHOZI)
            ->map(fn ($vychozi, string $klic) => rescue(fn () => self::cti($klic), null, false) ?? $vychozi)
            ->all();
    }

    public static function uloz(array $data): void
    {
        foreach (array_keys(self::VYCHOZI) as $klic) {
            if (array_key_exists($klic, $data)) {
                $hodnota = is_bool($data[$klic]) ? ($data[$klic] ? '1' : '0') : trim((string) $data[$klic]);
                self::zapis($klic, $hodnota === '' ? null : $hodnota);
            }
        }
    }

    /**
     * Co ve vzoru chybí – správce to musí doplnit, jinak text není úplný.
     *
     * @return list<string>
     */
    public static function chybejici(): array
    {
        $u = ZakladniUdaje::nacti();
        $chybi = [];

        foreach (['firma' => 'provozovatel (firma / jméno)', 'adresa' => 'sídlo', 'ico' => 'IČO', 'email' => 'kontaktní e-mail'] as $pole => $nazev) {
            if (blank($u[$pole] ?? null)) {
                $chybi[] = $nazev.' – Hlavička a patička';
            }
        }

        if (blank(self::nacti()['ucinnost_od'])) {
            $chybi[] = 'datum účinnosti – tady';
        }

        return $chybi;
    }

    /** @return list<string> */
    public static function dalsiPrijemci(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) self::nacti()['dalsi_prijemci']))));
    }

    // ---- Úložiště. Projekt s vlastním modelem nastavení mění jen tyhle dvě metody. ----

    private static function cti(string $klic): ?string
    {
        return Nastaveni::hodnota('gdpr.'.$klic);
    }

    private static function zapis(string $klic, ?string $hodnota): void
    {
        Nastaveni::nastav('gdpr.'.$klic, $hodnota);
    }
}
