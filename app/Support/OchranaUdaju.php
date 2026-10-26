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
    /** @return array<string, ?string> */
    public static function vychozi(): array
    {
        return [
            'ucinnost_od' => null,
            'poverenec' => null,
            'formular_udaje' => __('pravni.ochrana_udaju.jmeno_a_prijmeni_e_mailova_adresa'),
            'formular_doba' => __('pravni.ochrana_udaju.t3_roky_od_posledni_komunikace'),
            'smlouvy' => '0',
            'smlouvy_doba' => __('pravni.ochrana_udaju.po_dobu_trvani_smluvniho_vztahu_a'),
            'dalsi_prijemci' => null,
            'doplnek' => null,
        ];
    }

    /** @return array<string, ?string> */
    public static function nacti(): array
    {
        return collect(self::vychozi())
            ->map(fn ($vychozi, string $klic) => rescue(fn () => self::cti($klic), null, false) ?? $vychozi)
            ->all();
    }

    public static function uloz(array $data): void
    {
        foreach (array_keys(self::vychozi()) as $klic) {
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

        foreach (['firma' => __('pravni.ochrana_udaju.provozovatel_firma_jmeno'), 'adresa' => __('pravni.ochrana_udaju.sidlo'), 'ico' => __('pravni.ochrana_udaju.ico'), 'email' => __('pravni.ochrana_udaju.kontaktni_e_mail')] as $pole => $nazev) {
            if (blank($u[$pole] ?? null)) {
                $chybi[] = __('pravni.ochrana_udaju.hlavicka_a_paticka', ['nazev' => $nazev]);
            }
        }

        if (blank(self::nacti()['ucinnost_od'])) {
            $chybi[] = __('pravni.ochrana_udaju.datum_ucinnosti_tady');
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
