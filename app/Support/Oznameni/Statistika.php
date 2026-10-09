<?php

namespace App\Support\Oznameni;

use App\Enums\KanalOznameni;
use App\Models\MailLog;
use App\Models\Oznameni;
use App\Models\OznameniDoruceni;
use App\Models\OznameniPrijemce;
use Illuminate\Database\Eloquent\Builder;

/**
 * Čísla jednoho oznámení: komu přišlo, kolik si ho přečetlo a prokliklo,
 * jak dopadly e-maily. Jen z vlastních dat aplikace (proklik přes vlastní
 * odkaz, doručení z Pošty) – žádné měřicí pixely ani sledování napříč weby.
 * Superadmini (vývojáři) se nepočítají.
 */
class Statistika
{
    /** @return array<string, int> */
    public static function pro(Oznameni $oznameni): array
    {
        $prijemci = fn (): Builder => OznameniPrijemce::query()
            ->where('oznameni_id', $oznameni->getKey())
            ->whereHas('user', fn (Builder $query) => $query->where('role', '!=', 'superadmin'));

        $cisla = [
            'prijemcu' => $prijemci()->count(),
            'v_centru' => $prijemci()->where('v_centru', true)->count(),
            'precteno' => $prijemci()->whereNotNull('precteno_at')->count(),
            'prokliknuto' => $prijemci()->whereNotNull('prokliknuto_at')->count(),
        ];

        if ($oznameni->maKanal(KanalOznameni::Email)) {
            $emaily = fn (): Builder => OznameniDoruceni::query()
                ->where('kanal', KanalOznameni::Email->value)
                ->whereIn('prijemce_id', $prijemci()->select('id'));

            $cisla += [
                'email_ceka' => $emaily()->where('stav', OznameniDoruceni::CEKA)->count(),
                'email_predano' => $emaily()->where('stav', OznameniDoruceni::ODESLANO)->count(),
                'email_doruceno' => $emaily()->where('stav', OznameniDoruceni::ODESLANO)
                    ->whereHas('mailLog', fn (Builder $query) => $query->where('status', MailLog::STATUS_SENT))->count(),
                'email_nedoruceno' => $emaily()->where(fn (Builder $query) => $query
                    ->where('stav', OznameniDoruceni::CHYBA)
                    ->orWhereHas('mailLog', fn (Builder $query) => $query->where('status', MailLog::STATUS_FAILED)))->count(),
                'email_bez_souhlasu' => $emaily()->where('stav', OznameniDoruceni::PRESKOCENO)->where('duvod', 'Bez souhlasu.')->count(),
                'email_preskoceno' => $emaily()->where('stav', OznameniDoruceni::PRESKOCENO)->where('duvod', '!=', 'Bez souhlasu.')->count(),
            ];
        }

        return $cisla;
    }
}
