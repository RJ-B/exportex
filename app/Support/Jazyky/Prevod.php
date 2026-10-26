<?php

namespace App\Support\Jazyky;

use Illuminate\Support\Str;

/**
 * Převod textů natvrdo na klíče (`php artisan jazyky:prevod`, docs/jazyky.md).
 *
 * Nálezy z CeskeTexty, které jdou převést strojově, nahradí `__('oblast.skupina.klic')`
 * (s parametry) a texty zapíše do lang/<výchozí jazyk>/<oblast>.php. Oblast je
 * podle cesty souboru (OBLASTI), skupina podle jména souboru, klíč z prvních
 * slov textu. Stejný text ve stejné skupině = stejný klíč; už přeložené klíče
 * se znovu použijí, takže převod jde pustit opakovaně (např. po merge šablony).
 */
class Prevod
{
    /** Cesta (začátek, regulární výraz) → oblast = soubor lang/<jazyk>/<oblast>.php. První shoda vyhrává. */
    public const OBLASTI = [
        '#^app/Filament/Sprava/|^resources/views/filament/sprava/#' => 'sprava',
        '#^app/Filament/Resources/Platforma/#' => 'platforma',
        '#^app/Platby/|^resources/views/(filament/)?platby/|^resources/views/mail/platby/#' => 'platby',
        '#^app/Saas/|^resources/views/saas/#' => 'saas',
        '#^app/Support/Posta/|^app/Filament/Pages/Posta\.php|^resources/views/filament/pages/posta#' => 'posta',
        '#^app/Support/Seo/|^app/Filament/Pages/Seo|^resources/views/seo/#' => 'seo',
        '#^app/Mail/|^resources/views/mail/#' => 'maily',
        '#^app/Models/|^app/Enums/#' => 'modely',
        '#^resources/views/pravni/|^app/Support/OchranaUdaju|^app/Filament/Pages/OchranaOsobnich#' => 'pravni',
        '#^app/Filament/|^resources/views/filament/|^app/Bezpecnost/#' => 'administrace',
        '#^app/Http/|^resources/views/#' => 'web',
    ];

    public const OBECNA = 'obecne';

    /** @var array<string, array<string, array<string, string>>> oblast → skupina → klíč → text */
    private array $texty = [];

    /** @var array<string, true> oblasti, které se změnily */
    private array $zmenene = [];

    public function __construct(private string $koren, private string $jazyk) {}

    /**
     * Převede soubor. Vrátí počet převedených a nepřevedené (ruční) nálezy.
     *
     * @return array{prevedeno: int, rucne: list<array>}
     */
    public function soubor(string $relativni, bool $zapsat = true): array
    {
        $cesta = $this->koren.'/'.$relativni;
        $zdroj = (string) file_get_contents($cesta);
        $nalezy = CeskeTexty::kPrekladu($cesta, $relativni);
        $rucne = [];
        $nahrady = [];
        $konec = -1;

        foreach ($nalezy as $n) {
            if ($n['rucne'] !== null) {
                $rucne[] = $n;

                continue;
            }
            $od = $n['deklarace_od'] ?? $n['od'];
            $do = $n['deklarace_do'] ?? $n['do'];
            if ($od < $konec) {
                $n['rucne'] = 'překrývá se s jiným textem';
                $rucne[] = $n;

                continue;
            }
            $nahrady[] = [$od, $do, $this->kod($n, $relativni, $zdroj)];
            $konec = $do;
        }

        foreach (array_reverse($nahrady) as [$od, $do, $kod]) {
            $zdroj = substr($zdroj, 0, $od).$kod.substr($zdroj, $do);
        }
        if ($zapsat && $nahrady !== []) {
            file_put_contents($cesta, $zdroj);
        }

        return ['prevedeno' => count($nahrady), 'rucne' => $rucne];
    }

    /** Zapíše lang/<jazyk>/<oblast>.php pro změněné oblasti. @return list<string> zapsané soubory */
    public function zapisPreklady(): array
    {
        $zapsane = [];
        foreach (array_keys($this->zmenene) as $oblast) {
            $soubor = $this->koren.'/lang/'.$this->jazyk.'/'.$oblast.'.php';
            @mkdir(dirname($soubor), 0775, true);
            file_put_contents($soubor, self::phpPole($this->texty[$oblast]));
            $zapsane[] = 'lang/'.$this->jazyk.'/'.$oblast.'.php';
        }

        return $zapsane;
    }

    public static function oblast(string $relativni): string
    {
        foreach (self::OBLASTI as $vzor => $oblast) {
            if (preg_match($vzor, $relativni)) {
                return $oblast;
            }
        }

        return self::OBECNA;
    }

    public static function skupina(string $relativni): string
    {
        $jmeno = (string) preg_replace('/(\.blade)?\.php$/', '', basename($relativni));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::snake(ltrim($jmeno, '_'))), '_') ?: 'texty';
    }

    /** Klíč z prvních slov textu (bez parametrů a diakritiky). */
    public static function slug(string $text): string
    {
        $text = (string) preg_replace('/:[a-z_][a-z0-9_]*/', ' ', strip_tags($text));
        $slova = preg_split('/[^a-z0-9]+/', strtolower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY);
        $klic = '';
        foreach (array_slice($slova, 0, 6) as $slovo) {
            if ($klic !== '' && strlen($klic.'_'.$slovo) > 40) {
                break;
            }
            $klic .= ($klic === '' ? '' : '_').$slovo;
        }
        $klic = $klic === '' ? 'text' : $klic;

        return preg_match('/^\d/', $klic) ? 't'.$klic : $klic;
    }

    /** Klíč pro text (stejný text ve skupině = stejný klíč). */
    public function klic(string $oblast, string $skupina, string $text): string
    {
        $this->nactiOblast($oblast);
        $skupinaTextu = $this->texty[$oblast][$skupina] ?? [];

        if (($existujici = array_search($text, $skupinaTextu, true)) !== false) {
            return $oblast.'.'.$skupina.'.'.$existujici;
        }

        $zaklad = self::slug($text);
        $klic = $zaklad;
        for ($i = 2; isset($skupinaTextu[$klic]); $i++) {
            $klic = $zaklad.'_'.$i;
        }

        $this->texty[$oblast][$skupina][$klic] = $text;
        $this->zmenene[$oblast] = true;

        return $oblast.'.'.$skupina.'.'.$klic;
    }

    private function nactiOblast(string $oblast): void
    {
        if (isset($this->texty[$oblast])) {
            return;
        }
        $soubor = $this->koren.'/lang/'.$this->jazyk.'/'.$oblast.'.php';
        $this->texty[$oblast] = is_file($soubor) ? (array) require $soubor : [];
    }

    private function kod(array $n, string $relativni, string $zdroj): string
    {
        // HTML na okrajích (<p style…>, </p>) zůstává v kódu, překládá se jen text.
        [$pred, $za] = ['', ''];
        if (in_array($n['druh'], ['php', 'blade-php'], true) && preg_match('/^((?:\s*<[^>]*>)*)(.*?)((?:<[^>]*>\s*)*)$/s', $n['text'], $m)
            && ! collect(array_keys($n['parametry']))->contains(fn ($p) => str_contains($m[1].$m[3], ':'.$p)) && preg_match('/\p{L}/u', $m[2])) {
            [$pred, $n['text'], $za] = [$m[1], $m[2], $m[3]];
        }

        $klic = $this->klic(self::oblast($relativni), self::skupina($relativni), $n['text']);
        $volani = "__('".$klic."'".($n['parametry'] === [] ? '' : ', ['.implode(', ', array_map(
            fn ($jmeno, $vyraz) => "'".$jmeno."' => ".$vyraz,
            array_keys($n['parametry']),
            $n['parametry'],
        )).']').')';

        if (isset($n['vlastnost'])) {
            $radek = substr($zdroj, (int) strrpos(substr($zdroj, 0, $n['deklarace_od']), "\n") + 1, 64);
            $odsazeni = (string) preg_replace('/\S.*$/s', '', $radek);

            // MoonShine má getTitle() jen jako string (Filament string|Htmlable).
            $podpis = $n['vlastnost'] === 'title' && str_contains($zdroj, 'MoonShine\\')
                ? 'public function getTitle(): string'
                : CeskeTexty::VLASTNOSTI_FILAMENTU[$n['vlastnost']];

            return $podpis."\n".$odsazeni."{\n".$odsazeni.'    return '.$volani.";\n".$odsazeni.'}';
        }

        return match ($n['druh']) {
            'blade' => ($n['surove'] ?? false) ? '{!! '.$volani.' !!}' : '{{ '.$volani.' }}',
            'blade-atribut' => '{{ '.$volani.' }}',
            default => ($pred === '' ? '' : self::retezec($pred).'.').$volani.($za === '' ? '' : '.'.self::retezec($za)),
        };
    }

    /** Pole jako čitelný PHP soubor s jednoduchými uvozovkami. */
    public static function phpPole(array $pole): string
    {
        $zapis = function (array $pole, int $uroven) use (&$zapis): string {
            $odsazeni = str_repeat('    ', $uroven);
            $radky = [];
            foreach ($pole as $klic => $hodnota) {
                $k = "'".addcslashes((string) $klic, "'\\")."'";
                $radky[] = is_array($hodnota)
                    ? $odsazeni.$k." => [\n".$zapis($hodnota, $uroven + 1).$odsazeni.'],'
                    : $odsazeni.$k.' => '.self::retezec((string) $hodnota).',';
            }

            return $radky === [] ? '' : implode("\n", $radky)."\n";
        };

        return "<?php\n\nreturn [\n".$zapis($pole, 1)."];\n";
    }

    private static function retezec(string $text): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $text)."'";
    }
}
