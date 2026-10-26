<?php

namespace App\Support;

use App\Models\Nastaveni;

/**
 * Co návštěvník čte na stránce „Připravujeme“ a „Údržba“ (Správa aplikace → Stav webu).
 * Prázdné = výchozí text.
 */
class TextyStavuWebu
{
    /** @return array<string, string> */
    public static function vychozi(): array
    {
        return [
            'pripravujeme_nadpis' => __('obecne.texty_stavu_webu.pripravujeme'),
            'pripravujeme_text' => __('obecne.texty_stavu_webu.novy_web_se_prave_dokoncuje_brzy'),
            'udrzba_nadpis' => __('obecne.texty_stavu_webu.web_se_prave_upravuje'),
            'udrzba_text' => __('obecne.texty_stavu_webu.probiha_kratka_udrzba_zkuste_to_prosim'),
        ];
    }

    /** @return array<string, string> */
    public static function nacti(): array
    {
        return collect(self::vychozi())
            ->map(fn (string $vychozi, string $klic) => rescue(fn () => self::cti($klic), null, false) ?: $vychozi)
            ->all();
    }

    /** Uložené hodnoty bez výchozích – do formuláře (prázdné pole ukáže výchozí jako nápovědu). */
    public static function ulozene(): array
    {
        return collect(self::vychozi())->map(fn ($v, string $klic) => self::cti($klic))->all();
    }

    public static function uloz(array $data): void
    {
        foreach (array_keys(self::vychozi()) as $klic) {
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
