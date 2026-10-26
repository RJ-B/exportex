<?php

namespace App\Support;

use App\Models\Nastaveni;

/**
 * Texty a seznamy sekcí webu Exportex (Obsah webu → Úvod, Sortiment, Jak to
 * funguje, Trasa, Doklady a clo, O nás, Ukázky zakázek, Kontakt – texty
 * a Hlavička a patička). Výchozí obsah je ten, se kterým web vznikl (dřív
 * index.html a assets/js/data.js) – dokud ho správce nezmění, web vypadá
 * přesně jako dřív.
 *
 * Web je dvojjazyčný (CZ / EN, přepínač v hlavičce): každý text má českou
 * podobu v poli `<pole>` a anglickou v `<pole>_en`; u seznamů nese obě každá
 * položka. Každá sekce je v nastavení jeden klíč `obsah.<sekce>` (JSON).
 * Neuložené pole se bere z výchozího obsahu (config/obsah.php), takže nové pole ve výchozím obsahu se na
 * webu objeví i u sekce, kterou už někdo upravil.
 */
class ObsahWebu
{
    /**
     * Výchozí obsah sekcí (config/obsah.php – data, ne texty rozhraní).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function vychozi(): array
    {
        return (array) config('obsah.vychozi', []);
    }

    /** @return array<string, mixed> */
    public static function sekce(string $sekce): array
    {
        $vychozi = self::vychozi()[$sekce] ?? [];
        $ulozene = json_decode((string) rescue(fn () => Nastaveni::hodnota('obsah.'.$sekce), null, false), true);

        return is_array($ulozene) ? array_replace($vychozi, $ulozene) : $vychozi;
    }

    /** Uloží jen pole, která sekce zná. */
    public static function uloz(string $sekce, array $data): void
    {
        $pole = array_intersect_key($data, self::vychozi()[$sekce] ?? []);

        Nastaveni::nastav('obsah.'.$sekce, json_encode($pole, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Sekce úvodní stránky: klíč v Obsahu webu (SekceWebu) => kotva na stránce
     * a sekce obsahu, ze které se bere štítek do menu. Pořadí a zapnutí určuje
     * Obsah webu → Hlavička a patička → Sekce na webu.
     */
    public const SEKCE = [
        'sortiment' => ['kotva' => 'sortiment', 'obsah' => 'sortiment'],
        'jak' => ['kotva' => 'jak', 'obsah' => 'jak'],
        'trasa' => ['kotva' => 'trasa', 'obsah' => 'trasa'],
        'doklady' => ['kotva' => 'doklady', 'obsah' => 'doklady'],
        'onas' => ['kotva' => 'onas', 'obsah' => 'onas'],
        'reference' => ['kotva' => 'reference', 'obsah' => 'reference'],
        'formular' => ['kotva' => 'kontakt', 'obsah' => 'kontakt'],
    ];

    /**
     * Zapnuté sekce v pořadí s číslem (01, 02…), kotvou a štítkem v obou
     * jazycích – pro sekce úvodní stránky, menu, mobilní menu i patičku.
     *
     * @return list<array{klic: string, kotva: string, cislo: string, cs: string, en: string, vMenu: bool}>
     */
    public static function sekceNaWebu(): array
    {
        $vMenu = array_column(SekceWebu::menu(), 'klic');
        $sekce = [];

        foreach (SekceWebu::naWebu() as $klic) {
            if (! isset(self::SEKCE[$klic])) {
                continue;
            }

            $obsah = self::sekce(self::SEKCE[$klic]['obsah']);
            $sekce[] = [
                'klic' => $klic,
                'kotva' => self::SEKCE[$klic]['kotva'],
                'cislo' => str_pad((string) (count($sekce) + 1), 2, '0', STR_PAD_LEFT),
                'cs' => (string) $obsah['stitek'],
                'en' => (string) ($obsah['stitek_en'] ?: $obsah['stitek']),
                'vMenu' => in_array($klic, $vMenu, true),
            ];
        }

        return $sekce;
    }

    /**
     * Adresa fotky: původní fotky leží v public/assets/img (cesta začíná „/“),
     * nahrané v administraci na disku public (storage).
     */
    public static function obrazek(?string $cesta): string
    {
        $cesta = (string) $cesta;

        return str_starts_with($cesta, '/') || str_starts_with($cesta, 'assets/')
            ? '/'.ltrim($cesta, '/')
            : '/storage/'.$cesta;
    }

    /**
     * Data pro skript webu (dřív assets/js/data.js): sortiment, kroky, trasa,
     * doklady, ukázky zakázek a popisky formuláře v obou jazycích. Na stránce
     * jako <script type="application/json"> – nespouští se, CSP zůstává přísná.
     *
     * @return array{cs: array, en: array}
     */
    public static function proSkript(): array
    {
        $sortiment = self::sekce('sortiment');
        $jak = self::sekce('jak');
        $trasa = self::sekce('trasa');
        $doklady = self::sekce('doklady');
        $reference = self::sekce('reference');
        $kontakt = self::sekce('kontakt');
        $udaje = ZakladniUdaje::nacti();
        $kategorie = array_values((array) $sortiment['kategorie']);
        $data = [];

        foreach (['cs', 'en'] as $jazyk) {
            $p = fn (array $polozka, string $pole) => (string) ($jazyk === 'en' ? ($polozka[$pole.'_en'] ?? '') : ($polozka[$pole] ?? ''));
            $kategoriePodleNazvu = collect($kategorie)->mapWithKeys(fn ($k) => [(string) ($k['nazev'] ?? '') => $p($k, 'nazev')]);
            // Popisky ovládání webu (filtr, detail, formulář) – texty rozhraní v lang/<jazyk>/web.php.
            $popisky = (array) trans('web.popisky_webu', [], $jazyk);

            $data[$jazyk] = [
                'filters' => [$popisky['vse'], ...array_map(fn ($k) => $p($k, 'nazev'), $kategorie)],
                'detail' => $popisky['detail'],
                'cta' => $popisky['cta'],
                'termsNote' => $p($sortiment, 'podminky_poznamka'),
                'close' => $popisky['close'],
                'products' => array_map(fn ($produkt) => [
                    'tag' => $p($produkt, 'stitek'),
                    'cat' => $kategoriePodleNazvu[(string) ($produkt['kategorie'] ?? '')] ?? '',
                    'img' => self::obrazek($produkt['foto'] ?? ''),
                    'title' => $p($produkt, 'nazev'),
                    'desc' => $p($produkt, 'popis'),
                    'points' => array_values(array_filter(array_map(fn ($bod) => (string) ($bod[$jazyk] ?? ''), array_values((array) ($produkt['body'] ?? []))))),
                    'specs' => array_map(fn ($par) => ['k' => $p($par, 'nazev'), 'v' => $p($par, 'hodnota')], array_values((array) ($produkt['parametry'] ?? []))),
                    'terms' => $p($produkt, 'podminky'),
                ], array_values((array) $sortiment['produkty'])),
                'steps' => collect(array_values((array) $jak['kroky']))->map(fn ($krok, $i) => [
                    'n' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    't' => $p($krok, 'nazev'),
                    'd' => $p($krok, 'popis'),
                ])->all(),
                'mapFrom' => ['city' => $p($trasa, 'odkud'), 'sub' => $p($trasa, 'odkud_popis')],
                'mapTo' => ['city' => $p($trasa, 'kam'), 'sub' => $p($trasa, 'kam_popis')],
                'route' => ['bow' => 78],
                'compliance' => array_map(fn ($d) => ['k' => (string) ($d['stitek'] ?? ''), 't' => $p($d, 'nazev'), 'd' => $p($d, 'popis')], array_values((array) $doklady['polozky'])),
                'cases' => array_map(fn ($r) => ['badge' => (string) ($r['stitek'] ?? ''), 't' => $p($r, 'nazev'), 'd' => $p($r, 'popis')], array_values((array) $reference['polozky'])),
                'fields' => $popisky['fields'],
                'err' => $popisky['err'],
                'form' => $popisky['form'] + [
                    'note' => $p($kontakt, 'poznamka'),
                    'done' => $p($kontakt, 'odeslano'),
                ],
            ];
        }

        $data['kontakt'] = [
            'email' => (string) $udaje['email'],
            'telefon' => (string) $udaje['telefon'],
            'ochrana' => route('ochrana-udaju', absolute: false),
        ];

        return $data;
    }

}
