<?php

namespace App\Support\Oznameni;

use App\Enums\ZavaznostOznameni;
use App\Models\Oznameni;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Pruh přes celý web a administraci (odstávky, výpadky). Pruh pro všechny
 * vidí i nepřihlášený návštěvník (mezipaměť na minutu – na každé stránce
 * webu by to jinak byl dotaz), cílený jen přihlášený příjemce.
 * Vážné nahoře, pak upozornění, informace, vyřešené.
 */
class Pruh
{
    private const CACHE = 'oznameni.pruhy.vsem.v1';

    /** @return Collection<int, Oznameni> */
    public static function pro(?User $user): Collection
    {
        // V mezipaměti jen hodnoty sloupců, ne objekty – Laravel modely z cache
        // nerozbalí (cache.serializable_classes), vrátil by __PHP_Incomplete_Class.
        $radky = (array) rescue(fn () => Cache::remember(self::CACHE, 60, fn () => Oznameni::query()->pruhPlati()
            ->where('cileni->komu', 'vsichni')->get()->map(fn (Oznameni $o) => $o->getAttributes())->all()), [], false);
        $vsem = Oznameni::hydrate(array_values(array_filter($radky, fn ($r) => is_array($r) && isset($r['id']))));

        // Mezipaměť drží i pruh, kterému mezitím skončila platnost – ten pryč.
        $vsem = $vsem->filter(fn (Oznameni $o) => (! $o->pruh_od || $o->pruh_od->lte(now())) && (! $o->pruh_do || $o->pruh_do->isFuture()));

        $cilene = $user ? rescue(fn () => Oznameni::query()->pruhPlati()
            ->where('cileni->komu', '!=', 'vsichni')
            ->whereHas('prijemci', fn ($query) => $query->where('user_id', $user->getKey()))
            ->get(), collect(), false) : collect();

        $poradi = array_flip(array_map(fn ($z) => $z->value, [ZavaznostOznameni::Kriticke, ZavaznostOznameni::Varovani, ZavaznostOznameni::Info, ZavaznostOznameni::Vyreseno]));

        return $vsem->concat($cilene)
            ->unique('id')
            ->sortBy(fn (Oznameni $o) => [$poradi[$o->zavaznost->value] ?? 9, -$o->id])
            ->values();
    }

    public static function zapomen(): void
    {
        rescue(fn () => Cache::forget(self::CACHE), null, false);
    }
}
