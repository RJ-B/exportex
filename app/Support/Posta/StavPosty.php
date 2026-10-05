<?php

namespace App\Support\Posta;

use App\Models\MailLog;

/**
 * Stav pošty aplikace pro portál (simren:zdravi --json → `posta`) a Přehled.
 * Portál otevře incident „Neodchází pošta“, když ok = false:
 *  - aplikace není propojená s Poštou (e-maily neodcházejí),
 *  - Pošta je nedostupná déle než posta.nedostupna_minut (zprávy čekají ve frontě),
 *  - nedoručených zpráv za 24 hodin je aspoň posta.prah_nedorucenych.
 * `zdroj = posta` říká portálu, že nejde o heslo schránky (starší šablona).
 */
class StavPosty
{
    /** @return array{nastavena: bool, ok: ?bool, zprava: ?string, kdy: string, domena: null, zdroj: string, fronta: int, nedorucene_24h: int} */
    public static function proZdravi(): array
    {
        $fronta = Odchozi::query()->count();
        $nejstarsi = Odchozi::query()->min('created_at');
        $nedorucene = MailLog::query()->failed()->whereNotNull('posta_id')->where('failed_at', '>=', now()->subDay())->count();
        $prah = max(1, (int) config('posta.prah_nedorucenych', 5));
        $nedostupna = $nejstarsi && now()->diffInMinutes(now()->parse($nejstarsi), true) >= (int) config('posta.nedostupna_minut', 30);

        [$ok, $zprava] = match (true) {
            ! Propojeni::propojeno() => [false, 'Aplikace není propojená s Poštou – e-maily neodcházejí (Administrace → Pošta → Propojit s poštou).'],
            $nedostupna => [false, 'Pošta nepřijímá zprávy – ve frontě čeká '.$fronta.' od '.now()->parse($nejstarsi)->format('j. n. H:i').' ('.Odchozi::query()->oldest()->value('chyba').').'],
            $nedorucene >= $prah => [false, 'Nedoručeno '.$nedorucene.' zpráv za 24 hodin (Logy → E-maily).'],
            default => [true, null],
        };

        return [
            'nastavena' => true,
            'ok' => $ok,
            'zprava' => $zprava,
            'kdy' => now()->toIso8601String(),
            'domena' => null,   // DNS domén řeší Pošta s portálem
            'zdroj' => 'posta',
            'fronta' => $fronta,
            'nedorucene_24h' => $nedorucene,
        ];
    }
}
