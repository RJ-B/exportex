<?php

namespace App\Support\Oznameni;

use App\Enums\KanalOznameni;
use App\Models\Oznameni;
use App\Models\OznameniSkupina;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Komu oznámení jde. Cílení (JSON v oznameni.cileni):
 *
 *   {"komu": "vsichni"}
 *   {"komu": "vybrani", "role": ["klient"], "skupiny": [3], "uzivatele": [12, 15]}
 *
 * Výběry se sčítají (sjednocení) a každý člověk dostane oznámení jednou.
 * Superadmini (vývojáři Sim&Ren) nejsou ve „všech“ ani v rolích – do
 * hromadných zpráv klienta nepatří a nepočítají se do statistik; dostanou
 * ho jen, když je někdo vybere jmenovitě.
 */
class Cileni
{
    /** Role, které jde v cílení vybrat. */
    public const ROLE = ['admin' => 'Správci (admin)', 'klient' => 'Klienti'];

    public static function dotaz(array $cileni): Builder
    {
        $dotaz = User::query();

        if (($cileni['komu'] ?? null) === 'vsichni') {
            return $dotaz->where('role', '!=', 'superadmin');
        }

        $role = array_values(array_intersect((array) ($cileni['role'] ?? []), array_keys(self::ROLE)));
        $skupiny = array_map('intval', (array) ($cileni['skupiny'] ?? []));
        $uzivatele = array_map('intval', (array) ($cileni['uzivatele'] ?? []));

        if ($role === [] && $skupiny === [] && $uzivatele === []) {
            return $dotaz->whereRaw('1 = 0');
        }

        return $dotaz->where(function (Builder $query) use ($role, $skupiny, $uzivatele) {
            if ($role !== []) {
                $query->orWhereIn('role', $role);
            }
            if ($skupiny !== []) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('role', '!=', 'superadmin')
                    ->whereHas('oznameniSkupiny', fn (Builder $query) => $query->whereIn('oznameni_skupiny.id', $skupiny)));
            }
            if ($uzivatele !== []) {
                $query->orWhereIn('id', $uzivatele);
            }
        });
    }

    public static function prazdne(array $cileni): bool
    {
        return ($cileni['komu'] ?? null) !== 'vsichni'
            && blank($cileni['role'] ?? null) && blank($cileni['skupiny'] ?? null) && blank($cileni['uzivatele'] ?? null);
    }

    /**
     * Kolik lidí co dostane – pro potvrzení před odesláním.
     *
     * @return array{prijemcu: int, centrum: int, email: int, email_bez_souhlasu: int, email_vypnuto: int}
     */
    public static function pocty(Oznameni $oznameni): array
    {
        $pocty = ['prijemcu' => 0, 'centrum' => 0, 'email' => 0, 'email_bez_souhlasu' => 0, 'email_vypnuto' => 0];
        $druh = $oznameni->druh;

        self::dotaz((array) $oznameni->cileni)->with('oznameniPredvolby')->chunkById(500, function ($uzivatele) use (&$pocty, $oznameni, $druh) {
            foreach ($uzivatele as $user) {
                $pocty['prijemcu']++;

                if ($oznameni->maKanal(KanalOznameni::Centrum) && Predvolby::chce($user, $druh, KanalOznameni::Centrum)) {
                    $pocty['centrum']++;
                }

                if ($oznameni->maKanal(KanalOznameni::Email)) {
                    if (Predvolby::chce($user, $druh, KanalOznameni::Email)) {
                        $pocty['email']++;
                    } elseif ($druh->vyzadujeSouhlas()) {
                        $pocty['email_bez_souhlasu']++;
                    } else {
                        $pocty['email_vypnuto']++;
                    }
                }
            }
        });

        return $pocty;
    }

    /** Krátký popis pro tabulku: „Všem“, „Klienti · Stálí zákazníci · 3 vybraní“. */
    public static function popis(array $cileni): string
    {
        if (($cileni['komu'] ?? null) === 'vsichni') {
            return 'Všem';
        }

        $casti = array_map(fn ($r) => self::ROLE[$r] ?? $r, (array) ($cileni['role'] ?? []));

        $skupiny = (array) ($cileni['skupiny'] ?? []);
        if ($skupiny !== []) {
            array_push($casti, ...OznameniSkupina::query()->whereIn('id', $skupiny)->orderBy('nazev')->pluck('nazev')->all());
        }

        $vybrani = count((array) ($cileni['uzivatele'] ?? []));
        if ($vybrani > 0) {
            $casti[] = $vybrani === 1 ? '1 vybraný' : ($vybrani < 5 ? $vybrani.' vybraní' : $vybrani.' vybraných');
        }

        return $casti === [] ? 'Nikomu' : implode(' · ', $casti);
    }
}
