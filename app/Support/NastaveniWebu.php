<?php

namespace App\Support;

use App\Models\Nastaveni;

/**
 * Nastavení webu mimo Základní údaje: logo, sociální sítě, patička, SEO
 * a měření, kam chodí zprávy z formuláře. Upravuje se v Obsahu webu.
 */
class NastaveniWebu
{
    public const POLE = [
        'logo', 'tagline',
        'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok',
        'paticka_poznamka',
        'formular_prijemce',
        'meta_popis', 'ga4_id', 'seznam_id', 'google_ads_id', 'sklik_id', 'cookie_lista',
    ];

    /**
     * Měřicí nástroje, které se na webu opravdu spustí (po souhlasu): zapnuté
     * měření (přepínač), zapnutá cookie lišta a vyplněné ID.
     *
     * @return array{analytics: array<string, string>, marketing: array<string, string>} nástroj => ID
     */
    public static function nastroje(): array
    {
        $w = self::nacti();
        $nic = ['analytics' => [], 'marketing' => []];

        if (! SekceWebu::zapnuta('mereni') || ($w['cookie_lista'] ?? '1') === '0') {
            return $nic;
        }

        return [
            'analytics' => array_filter(['ga4' => $w['ga4_id'], 'seznam' => $w['seznam_id']]),
            'marketing' => array_filter(['google_ads' => $w['google_ads_id'], 'sklik' => $w['sklik_id']]),
        ];
    }

    /** Měří web vůbec něco? Bez toho cookie lišta není potřeba. */
    public static function meri(): bool
    {
        $n = self::nastroje();

        return $n['analytics'] !== [] || $n['marketing'] !== [];
    }

    /** @return array<string, ?string> */
    public static function nacti(): array
    {
        $hodnoty = [];

        foreach (self::POLE as $pole) {
            $hodnoty[$pole] = rescue(fn () => self::cti($pole), null, false);
        }

        return $hodnoty;
    }

    public static function get(string $pole): ?string
    {
        return self::nacti()[$pole] ?? null;
    }

    public static function uloz(array $data): void
    {
        foreach (self::POLE as $pole) {
            if (array_key_exists($pole, $data)) {
                $hodnota = is_bool($data[$pole]) ? ($data[$pole] ? '1' : '0') : trim((string) $data[$pole]);
                self::zapis($pole, $hodnota === '' ? null : $hodnota);
            }
        }
    }

    /** Kam chodí zprávy z formuláře: nastavený příjemce, jinak kontaktní e-mail. */
    public static function prijemceFormulare(): ?string
    {
        return self::get('formular_prijemce') ?: ZakladniUdaje::get('email');
    }

    // ---- Úložiště. Projekt s vlastním modelem nastavení mění jen tyhle dvě metody. ----

    private static function cti(string $pole): ?string
    {
        return Nastaveni::hodnota('web.'.$pole);
    }

    private static function zapis(string $pole, ?string $hodnota): void
    {
        Nastaveni::nastav('web.'.$pole, $hodnota);
    }
}
