<?php

namespace App\Support\Oznameni;

use App\Models\OznameniPrijemce;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Centrum oznámení jednoho uživatele – data pro zvoneček (web i administrace)
 * a stejný tvar pro mobilní API (krok 3). Čas ven jen ISO 8601 s posunem.
 */
class Centrum
{
    public static function neprectenych(User $user): int
    {
        return (int) rescue(fn () => OznameniPrijemce::query()->vCentru($user)->neprectene()->count(), 0, false);
    }

    /** @return array{neprectenych: int, polozky: list<array<string, mixed>>} */
    public static function json(User $user): array
    {
        $polozky = OznameniPrijemce::query()->vCentru($user)->whereNull('archivovano_at')
            ->with('oznameni')->latest('id')->limit((int) config('oznameni.zvonecek_pocet', 8))->get();

        return [
            'neprectenych' => self::neprectenych($user),
            'polozky' => $polozky->map(fn (OznameniPrijemce $p) => self::polozka($p))->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function polozka(OznameniPrijemce $p): array
    {
        $o = $p->oznameni;

        return [
            'id' => $p->getKey(),
            'oznameni' => $o->uuid,
            'druh' => $o->druh->value,
            'druh_nazev' => $o->druh->nazev(),
            'zavaznost' => $o->zavaznost->value,
            'titulek' => $o->titulek,
            'text' => Str::limit($o->textProsty(), 160),
            'odkaz' => $p->odkazProkliku(),
            'odkaz_text' => $o->odkaz_text,
            'odeslano' => $o->odeslano_at?->toIso8601String(),
            'odeslano_popis' => $o->odeslano_at?->locale('cs')->diffForHumans(),
            'precteno' => $p->precteno_at?->toIso8601String(),
            'archivovano' => $p->archivovano_at?->toIso8601String(),
            'url_precteno' => route('oznameni.precteno', $p),
            'url_archiv' => route('oznameni.archiv', $p),
        ];
    }
}
