<?php

namespace App\Support\Posta;

use App\Models\MailLog;

/**
 * Stav pošty aplikace pro portál (simren:zdravi --json → `posta`) a Přehled.
 * Portál otevře incident „Neodchází pošta“, když ok = false:
 *  - aplikace není propojená s Poštou (e-maily neodcházejí) – na produkci s mailerem
 *    `log`/`array` to hláška řekne výslovně (e-maily se jen zapisují do logu),
 *  - Pošta je nedostupná déle než posta.nedostupna_minut (zprávy čekají ve frontě),
 *  - nedoručených zpráv za 24 hodin je aspoň posta.prah_nedorucenych.
 * `zdroj = posta` říká portálu, že nejde o heslo schránky (starší šablona).
 */
class StavPosty
{
    /** @return array{nastavena: bool, ok: ?bool, zprava: ?string, kdy: string, domena: null, zdroj: string, mailer: string, fronta: int, nedorucene_24h: int} */
    public static function proZdravi(): array
    {
        $fronta = Odchozi::query()->count();
        $nejstarsi = Odchozi::query()->min('created_at');
        $nedorucene = MailLog::query()->failed()->whereNotNull('posta_id')->where('failed_at', '>=', now()->subDay())->count();
        $prah = max(1, (int) config('posta.prah_nedorucenych', 5));
        $nedostupna = $nejstarsi && now()->diffInMinutes(now()->parse($nejstarsi), true) >= (int) config('posta.nedostupna_minut', 30);

        $mailer = self::mailer();

        [$ok, $zprava] = match (true) {
            ! Propojeni::propojeno() && self::jenDoLogu() => [false, __('posta.stav_posty.na_produkci_se_e_maily_jen', ['mailer' => $mailer])],
            ! Propojeni::propojeno() => [false, __('posta.stav_posty.aplikace_neni_propojena_s_postou_e')],
            $nedostupna => [false, __('posta.stav_posty.posta_neprijima_zpravy_ve_fronte_ceka', ['fronta' => $fronta, 'parse' => now()->parse($nejstarsi)->format(__('formaty.den_mesic_cas')), 'query' => Odchozi::query()->oldest()->value('chyba')])],
            $nedorucene >= $prah => [false, __('posta.stav_posty.nedoruceno_zprav_za_24_hodin_logy', ['nedorucene' => $nedorucene])],
            default => [true, null],
        };

        return [
            'nastavena' => true,
            'ok' => $ok,
            'zprava' => $zprava,
            'kdy' => now()->toIso8601String(),
            'domena' => null,   // DNS domén řeší Pošta s portálem
            'zdroj' => 'posta',
            'mailer' => $mailer,
            'fronta' => $fronta,
            'nedorucene_24h' => $nedorucene,
        ];
    }

    /** Mailer, kterým e-maily skutečně jdou: po propojení vždy Pošta, jinak z .env. */
    public static function mailer(): string
    {
        return Propojeni::propojeno() ? 'posta' : (string) config('mail.default');
    }

    /**
     * Produkce, která e-maily jen zapisuje (mailer log / array) – tváří se, že
     * odeslala, a nikomu nic nedojde. Lokálně a na testu je to v pořádku.
     */
    public static function jenDoLogu(): bool
    {
        $mailer = self::mailer();

        return app()->isProduction()
            && in_array((string) config('mail.mailers.'.$mailer.'.transport', $mailer), ['log', 'array'], true);
    }
}
