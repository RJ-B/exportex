<?php

namespace App\Platby;

use Illuminate\Support\Str;

/**
 * Stav plateb pro portál – blok `platby` v `simren:zdravi --json` (docs/platby.md).
 * Portál podle něj ukáže „Platby: ostré, v pořádku“ / „testovací režim“ /
 * „chybí klíče brány“ a u produkce otevře upozornění; CRM ho dostane ve
 * stavu produkce zakázky.
 *
 * ŽÁDNÉ TAJNOSTI: údaje brány jen jako ano/ne (`klice`, `overeno`), chyby
 * brány zkrácené a bez hodnot klíčů (bezTajemstvi). Doplněk vypnutý =
 * jen `zapnuto: false` – web platby nepoužívá a portál nic nehlásí.
 */
final class StavPlateb
{
    public const VERZE = 1;

    /**
     * @return array{verze: int, zapnuto: bool, brana?: ?string, klice?: bool, overeno?: bool, rezim_volba?: string, rezim?: ?string, funguje?: bool, duvod?: ?string, posledni_platba?: ?array{cas: string, rezim: ?string}, posledni_chyba?: ?array{cas: string, zprava: string}}
     */
    public static function proZdravi(): array
    {
        if (! PlatbyServiceProvider::zapnuto()) {
            return ['verze' => self::VERZE, 'zapnuto' => false];
        }

        NastaveniPlateb::zapomen();
        $brana = NastaveniPlateb::brana();
        $prehled = NastaveniPlateb::prehled();

        return [
            'verze' => self::VERZE,
            'zapnuto' => true,
            // Brána klienta zvolená v administraci: comgate | moone | null (žádná).
            'brana' => $brana,
            // Jen ano/ne – hodnoty klíčů nikdy.
            'klice' => $brana !== null && NastaveniPlateb::vyplneno($brana),
            'overeno' => $brana !== null && NastaveniPlateb::overeno($brana),
            // Co je zvolené (test / ostry) a jak platby opravdu běží (ostry / test /
            // simulace; null = platby stojí – důvod v `duvod`).
            'rezim_volba' => NastaveniPlateb::rezimVolba(),
            'rezim' => $prehled['rezim']?->value,
            'funguje' => $prehled['rezim'] !== null,
            'duvod' => self::bezTajemstvi($prehled['chyba']),
            'posledni_platba' => rescue(function () {
                $platba = Platba::query()->whereNotNull('zaplaceno_v')->latest('zaplaceno_v')->first(['zaplaceno_v', 'rezim']);

                return $platba ? ['cas' => $platba->zaplaceno_v->toIso8601String(), 'rezim' => $platba->rezim?->value] : null;
            }, null, false),
            'posledni_chyba' => rescue(function () {
                $platba = Platba::query()->whereNotNull('chyba')->where('chyba', '!=', '')->latest('updated_at')->first(['chyba', 'updated_at']);

                return $platba ? ['cas' => $platba->updated_at->toIso8601String(), 'zprava' => (string) self::bezTajemstvi($platba->chyba)] : null;
            }, null, false),
        ];
    }

    /**
     * Zpráva pro portál: zkrácená a bez hodnot klíčů (kdyby je brána nebo
     * HTTP klient vrátil v textu chyby).
     */
    public static function bezTajemstvi(?string $zprava): ?string
    {
        if (blank($zprava)) {
            return null;
        }

        $tajne = collect(array_keys(NastaveniPlateb::BRANY))
            ->flatMap(fn (string $brana) => array_values(NastaveniPlateb::udajeKlienta($brana)))
            ->push(config('platby.test.moone.client_id'), config('platby.test.moone.client_secret'))
            ->filter(fn ($hodnota) => is_string($hodnota) && mb_strlen($hodnota) >= 4)
            ->unique()
            ->all();

        return Str::limit(trim(str_replace($tajne, '***', (string) $zprava)), 200);
    }

    /** Jedna věta pro člověka na serveru (`simren:zdravi` bez --json). */
    public static function popis(array $p): string
    {
        if (! ($p['zapnuto'] ?? false)) {
            return __('platby.stav_plateb.doplnek_vypnuty');
        }

        $brany = NastaveniPlateb::BRANY;

        return collect([
            match ($p['rezim'] ?? null) {
                'ostry' => __('platby.stav_plateb.ostre', ['hodnota' => ($brany[$p['brana']] ?? $p['brana'])]),
                'test' => __('platby.stav_plateb.testovaci_rezim'),
                'simulace' => 'simulace',
                default => __('platby.stav_plateb.stoji', ['hodnota' => ($p['duvod'] ?? __('platby.stav_plateb.neznamy_duvod'))]),
            },
            ($p['brana'] ?? null) === null ? __('platby.stav_plateb.brana_klienta_nevybrana') : (($p['klice'] ?? false) ? null : __('platby.stav_plateb.chybi_klice_brany')),
            ($p['posledni_platba']['cas'] ?? null) ? __('platby.stav_plateb.posledni_platba', ['cas' => $p['posledni_platba']['cas']]) : null,
            ($p['posledni_chyba']['zprava'] ?? null) ? __('platby.stav_plateb.posledni_chyba', ['cas' => $p['posledni_chyba']['cas'], 'zprava' => $p['posledni_chyba']['zprava']]) : null,
        ])->filter()->implode(', ');
    }
}
