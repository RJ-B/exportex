<?php

namespace App\Support\Jazyky;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/**
 * Kontrola vícejazyčnosti (`php artisan jazyky:kontrola`, JazykyTest):
 * texty natvrdo, klíče, které v kódu jsou a v překladu chybí, a co chybí
 * v dalších jazycích (i nesedící :parametry).
 */
class Kontrola
{
    /** Kde se hledá (vůči kořeni projektu). */
    public const CESTY = ['app', 'resources/views', 'routes/web.php', 'routes/api.php', 'bootstrap/app.php'];

    /** Statické skripty: texty jen přes T('skupina.klic') z lang/<jazyk>/js.php (resources/views/jazyky/texty-js.blade.php). */
    public const SKRIPTY = 'public/js';

    /** Co se nekontroluje: sám nástroj (jeho texty jsou pro vývojáře). */
    public const VYNECHAT = ['app/Support/Jazyky/'];

    public function __construct(private string $koren) {}

    public static function projekt(): self
    {
        return new self(base_path());
    }

    /** @return list<string> relativní cesty souborů */
    public function soubory(): array
    {
        $soubory = [];
        foreach (self::CESTY as $cesta) {
            $plna = $this->koren.'/'.$cesta;
            if (is_file($plna)) {
                $soubory[] = $cesta;
            } elseif (is_dir($plna)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($plna, \FilesystemIterator::SKIP_DOTS)) as $f) {
                    $relativni = substr($f->getPathname(), strlen($this->koren) + 1);
                    if (str_ends_with($f->getFilename(), '.php') && ! collect(self::VYNECHAT)->contains(fn ($v) => str_starts_with($relativni, $v))) {
                        $soubory[] = substr($f->getPathname(), strlen($this->koren) + 1);
                    }
                }
            }
        }
        sort($soubory);

        return $soubory;
    }

    /** Texty natvrdo (bez vývojářských). @return list<array> */
    public function natvrdo(): array
    {
        $nalezy = [];
        foreach ($this->soubory() as $soubor) {
            array_push($nalezy, ...CeskeTexty::kPrekladu($this->koren.'/'.$soubor, $soubor));
        }
        foreach ($this->skripty() as $soubor) {
            $zdroj = (string) file_get_contents($this->koren.'/'.$soubor);
            preg_match_all("#/\\*.*?\\*/|//[^\\n]*|(['\"`])((?:(?!\\1)[^\\\\\\n]|\\\\.)*)\\1#su", $zdroj, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($m as $shoda) {
                if (isset($shoda[2]) && preg_match(CeskeTexty::ZNAKY, $shoda[2][0])) {
                    $nalezy[] = ['soubor' => $soubor, 'radek' => substr_count($zdroj, "\n", 0, $shoda[0][1]) + 1, 'text' => $shoda[2][0],
                        'parametry' => [], 'od' => $shoda[0][1], 'do' => $shoda[0][1], 'druh' => 'js', 'rucne' => "text v JS – T('…') a lang/*/js.php", 'vyvojar' => false];
                }
            }
        }

        return $nalezy;
    }

    /** @return list<string> statické skripty (public/js/*.js, bez podsložek – Filament a knihovny) */
    public function skripty(): array
    {
        return array_map(fn ($f) => substr($f, strlen($this->koren) + 1), glob($this->koren.'/'.self::SKRIPTY.'/*.js') ?: []);
    }

    /** Klíče z kódu, jejichž soubor je v lang/<výchozí>/ (oblast.skupina.klic). @return array<string, string> klíč → první výskyt */
    public function pouziteKlice(): array
    {
        $oblasti = array_map(fn ($f) => basename($f, '.php'), glob($this->koren.'/lang/'.Jazyky::vychozi().'/*.php') ?: []);
        $klice = [];
        foreach ($this->soubory() as $soubor) {
            $zdroj = (string) file_get_contents($this->koren.'/'.$soubor);
            preg_match_all('/(?:\b__|\btrans|\btrans_choice|@lang|Lang::get|Lang::choice)\(\s*([\'"])([a-z0-9_]+\.[a-z0-9_.]+)\1/', $zdroj, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[2] as [$klic, $pozice]) {
                if (in_array(strtok($klic, '.'), $oblasti, true)) {
                    $klice[$klic] ??= $soubor.':'.(substr_count($zdroj, "\n", 0, $pozice) + 1);
                }
            }
        }

        foreach ($this->skripty() as $soubor) {
            $zdroj = (string) file_get_contents($this->koren.'/'.$soubor);
            preg_match_all("/\\bT\\(\\s*'([a-z0-9_]+\\.[a-z0-9_.]+)'/", $zdroj, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[1] as [$klic, $pozice]) {
                $klice['js.'.$klic] ??= $soubor.':'.(substr_count($zdroj, "\n", 0, $pozice) + 1);
            }
        }

        return $klice;
    }

    /** Klíče použité v kódu, které v jazyce chybí. @return array<string, string> */
    public function chybejiciKlice(string $jazyk): array
    {
        return array_filter($this->pouziteKlice(), fn ($kde, $klic) => ! Lang::hasForLocale($klic, $jazyk), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Co má výchozí jazyk a jiný jazyk ne (nebo s jinými :parametry).
     *
     * @return array{chybi: list<string>, parametry: list<string>, navic: list<string>}
     */
    public function preklad(string $jazyk): array
    {
        $vychozi = $this->texty(Jazyky::vychozi());
        $jiny = $this->texty($jazyk);
        $parametry = [];
        foreach (array_intersect_key($vychozi, $jiny) as $klic => $text) {
            if ($this->parametry($text) !== $this->parametry($jiny[$klic])) {
                $parametry[] = $klic;
            }
        }

        return [
            'chybi' => array_keys(array_diff_key($vychozi, $jiny)),
            'parametry' => $parametry,
            'navic' => array_keys(array_diff_key($jiny, $vychozi)),
        ];
    }

    /** Texty jazyka z lang/<jazyk>/*.php (jen soubory, které má i výchozí jazyk – Laravel validation… nechává). @return array<string, string> */
    public function texty(string $jazyk): array
    {
        $texty = [];
        foreach (glob($this->koren.'/lang/'.Jazyky::vychozi().'/*.php') ?: [] as $vzor) {
            $soubor = $this->koren.'/lang/'.$jazyk.'/'.basename($vzor);
            if (is_file($soubor) && ! in_array(basename($vzor, '.php'), ['validation', 'auth', 'passwords', 'pagination'], true)) {
                foreach (Arr::dot((array) require $soubor) as $klic => $text) {
                    if (is_string($text)) {
                        $texty[basename($vzor, '.php').'.'.$klic] = $text;
                    }
                }
            }
        }

        return $texty;
    }

    /** @return list<string> */
    private function parametry(string $text): array
    {
        preg_match_all('/:([a-z_][a-z0-9_]*)/', $text, $m);
        $p = array_values(array_unique($m[1]));
        sort($p);

        return $p;
    }
}
