<?php

namespace App\Support;

use App\Models\Nastaveni;

/**
 * Co návštěvník čte na stránce „Připravujeme“ a „Údržba“ (Provoz → Stav webu).
 * Prázdné = výchozí text.
 */
class TextyStavuWebu
{
    public const VYCHOZI = [
        'pripravujeme_nadpis' => 'Připravujeme',
        'pripravujeme_text' => 'Nový web se právě dokončuje. Brzy tu bude – do té doby nás najdete na kontaktech níže.',
        'udrzba_nadpis' => 'Web se právě upravuje',
        'udrzba_text' => 'Probíhá krátká údržba. Zkuste to prosím za pár minut znovu.',
    ];

    /** @return array<string, string> */
    public static function nacti(): array
    {
        return collect(self::VYCHOZI)
            ->map(fn (string $vychozi, string $klic) => rescue(fn () => self::cti($klic), null, false) ?: $vychozi)
            ->all();
    }

    /** Uložené hodnoty bez výchozích – do formuláře (prázdné pole ukáže výchozí jako nápovědu). */
    public static function ulozene(): array
    {
        return collect(self::VYCHOZI)->map(fn ($v, string $klic) => self::cti($klic))->all();
    }

    public static function uloz(array $data): void
    {
        foreach (array_keys(self::VYCHOZI) as $klic) {
            if (array_key_exists($klic, $data)) {
                $hodnota = trim((string) $data[$klic]);
                self::zapis($klic, $hodnota === '' ? null : $hodnota);
            }
        }
    }

    // ---- Úložiště. Projekt s vlastním modelem nastavení mění jen tyhle dvě metody. ----

    private static function cti(string $klic): ?string
    {
        return Nastaveni::hodnota('web.text.'.$klic);
    }

    private static function zapis(string $klic, ?string $hodnota): void
    {
        Nastaveni::nastav('web.text.'.$klic, $hodnota);
    }
}
