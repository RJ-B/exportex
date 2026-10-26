<?php

namespace App\Support\Jazyky;

/**
 * Najde texty natvrdo – české řetězce v PHP a text v Blade (docs/jazyky.md).
 *
 * Každý nález nese, čím ho nahradit: `text` (s :parametry), `parametry`
 * (jméno => výraz v PHP) a rozsah ve zdroji (`od`, `do`). Co jde převést
 * strojově, převede `jazyky:prevod`; ostatní má `rucne` (důvod) a musí se
 * upravit ručně. `jazyky:kontrola` hlásí všechny nálezy kromě vývojářských
 * textů (`vyvojar`): logy, výjimky chyb v kódu (LogicException…), konzolové
 * příkazy a řádky s komentářem `bez překladu`.
 */
class CeskeTexty
{
    public const ZNAKY = '/[ěščřžýáíéůúťďňóĚŠČŘŽÝÁÍÉŮÚŤĎŇÓ]/u';

    /** Výjimky, jejichž text je pro vývojáře (chyba v kódu), ne pro člověka u obrazovky. */
    private const VYJIMKY_VYVOJARE = '/^(Logic|InvalidArgument|Domain|BadMethodCall|BadFunctionCall|Length|OutOfRange|OutOfBounds)Exception$/';

    /** Funkce, kde je řetězec data (hledání, porovnání), ne text k přeložení. */
    private const DATOVE_FUNKCE = ['str_contains', 'str_starts_with', 'str_ends_with', 'stripos', 'strpos', 'str_replace', 'explode', 'in_array', 'preg_match', 'preg_replace', 'preg_split', 'array_search', 'mb_stripos', 'mb_strpos', 'strtr', 'contains', 'startsWith', 'endsWith', 'where', 'orWhere', 'whereIn', 'firstWhere'];

    /**
     * Filament: vlastnost s textem → metoda, která ho přeloží (vlastnost se
     * vyhodnotí při načtení třídy, překlad musí až v požadavku).
     */
    public const VLASTNOSTI_FILAMENTU = [
        'navigationLabel' => 'public static function getNavigationLabel(): string',
        'modelLabel' => 'public static function getModelLabel(): string',
        'pluralModelLabel' => 'public static function getPluralModelLabel(): string',
        'navigationGroup' => 'public static function getNavigationGroup(): string|\UnitEnum|null',
        'title' => 'public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable',
        'clusterBreadcrumb' => 'public static function getClusterBreadcrumb(): ?string',
    ];

    /**
     * Metody, jejichž řetězec je text pro člověka i bez diakritiky („Firma“,
     * „Admin“) – Filament a pár dalších. U `make` jen třídy s popiskem v make().
     */
    private const TEXTOVE_METODY = ['label', 'title', 'body', 'heading', 'subheading', 'description', 'helperText', 'hint', 'placeholder',
        'tooltip', 'modalHeading', 'modalDescription', 'modalSubmitActionLabel', 'modalCancelActionLabel', 'emptyStateHeading',
        'emptyStateDescription', 'successNotificationTitle', 'failureNotificationTitle', 'options', 'descriptions', 'trueLabel',
        'falseLabel', 'prefix', 'suffix', 'badge', 'pluralLabel', 'modelLabel', 'navigationLabel', 'searchPrompt', 'emptyLabel'];

    private const TRIDY_S_POPISKEM = ['Section', 'Fieldset', 'Tab', 'NavigationGroup', 'NavigationItem', 'Step', 'TableColumn', 'Stat',
        // MoonShine: popisek pole a prvku je první argument make().
        'Text', 'Textarea', 'Number', 'Email', 'Password', 'PasswordRepeat', 'Select', 'Switcher', 'Checkbox', 'Date', 'DateRange',
        'Image', 'File', 'Preview', 'BelongsTo', 'BelongsToMany', 'HasMany', 'HasOne', 'Json', 'Url', 'Phone', 'Color', 'Enum',
        'Range', 'RangeSlider', 'Markdown', 'TinyMce', 'MenuItem', 'MenuGroup', 'Box', 'Heading', 'Collapse', 'ActionButton', 'Badge'];

    /** Metody, které vrací text pro člověka (return 'Aktivita', match … => 'Online'). */
    private const TEXTOVE_FUNKCE = '/^(get\w*(Label|Title|Heading|Breadcrumb|Description)|getName|popis\w*|label\w*|nazev\w*|titulek|nadpis|podskupinaObsahu)$/i';

    /** Direktivy Blade, které platí i přilepené za slovem („webu@if“) – jinak je @ součástí textu (e-mail). */
    private const DIREKTIVY = '/^(if|elseif|else|endif|unless|endunless|isset|endisset|empty|endempty|foreach|endforeach|forelse|endforelse|for|endfor|while|endwhile|switch|case|endswitch|auth|endauth|guest|endguest|php|endphp|sekce|endsekce|error|enderror|include|lang|js|json|csrf|method|class|style)$/';

    private const STOP = [',', ';', '=', '?', ':', '<', '>', '&', '|', '^', '{', '}'];

    private static ?array $stopTokeny = null;

    /** @var list<array> */
    private array $nalezy = [];

    private string $soubor = '';

    private bool $celyVyvojar = false;

    private bool $textovyMail = false;

    /** @var array<int, ?string> jméno funkce, ve které token je */
    private array $funkce = [];

    /**
     * @return list<array{soubor: string, radek: int, text: string, parametry: array<string, string>, od: int, do: int, druh: string, rucne: ?string, vyvojar: bool, vlastnost?: string}>
     */
    public static function vSouboru(string $soubor, ?string $relativni = null): array
    {
        $hledac = new self;
        $hledac->soubor = $relativni ?? $soubor;
        $zdroj = (string) file_get_contents($soubor);

        if (str_ends_with($soubor, '.blade.php')) {
            $hledac->textovyMail = (bool) preg_match('#(^|/)views/(.*/)?mail/#', $hledac->soubor);
            $hledac->blade($zdroj);
        } else {
            $hledac->celyVyvojar = str_contains($hledac->soubor, 'app/Console/')
                || (bool) preg_match('/\bextends\s+(\\\\?Illuminate\\\\Console\\\\)?Command\b/', $zdroj);
            $hledac->php($zdroj, 0, $zdroj);
        }

        usort($hledac->nalezy, fn ($a, $b) => $a['od'] <=> $b['od']);

        return $hledac->nalezy;
    }

    /** Texty k přeložení (vše kromě vývojářských). */
    public static function kPrekladu(string $soubor, ?string $relativni = null): array
    {
        return array_values(array_filter(self::vSouboru($soubor, $relativni), fn ($n) => ! $n['vyvojar']));
    }

    // ── PHP ─────────────────────────────────────────────────────────────

    /**
     * @param  string  $kod  PHP (s <?php)
     * @param  int  $posun  posun pozic v původním zdroji (Blade)
     * @param  string  $zdroj  celý původní soubor (kvůli řádkům)
     */
    private function php(string $kod, int $posun, string $zdroj, string $vBlade = ''): void
    {
        $t = $this->tokeny($kod);
        $n = count($t);
        $pary = $this->paryUvozovek($t);
        $obsah = $this->obsahy($t);
        $zpracovano = [];

        for ($i = 0; $i < $n; $i++) {
            if (isset($zpracovano[$i]) || ! $this->jeCeskyRetezec($t, $i, $pary)) {
                continue;
            }

            [$od, $do] = $this->retez($t, $i, $pary);
            for ($k = $od; $k <= $do; $k++) {
                $zpracovano[$k] = true;
            }

            $nalez = $this->nalezPhp($t, $od, $do, $pary, $obsah, $kod);
            $nalez['od'] = $t[$od][3] + $posun;
            $nalez['do'] = $t[$do][3] + strlen($t[$do][1]) + $posun;
            $nalez['radek'] = substr_count($zdroj, "\n", 0, $nalez['od']) + 1;
            $nalez['druh'] = $vBlade ?: 'php';

            if (! $nalez['vyvojar'] && $this->oznacenoBezPrekladu($zdroj, $nalez['od'])) {
                $nalez['vyvojar'] = true;
            }

            $this->nalezy[] = $nalez;
        }
    }

    /** @return list<array{0: int|string, 1: string, 2: int, 3: int}> */
    private function tokeny(string $kod): array
    {
        $vysledek = [];
        $pozice = 0;
        foreach (token_get_all($kod) as $tok) {
            $tok = is_array($tok) ? $tok : [$tok, $tok, 0];
            $vysledek[] = [$tok[0], $tok[1], $tok[2], $pozice];
            $pozice += strlen($tok[1]);
        }

        return $vysledek;
    }

    /** Páry " … " a heredoc (index začátku → konce a zpět). */
    private function paryUvozovek(array $t): array
    {
        $pary = [];
        $otevreno = null;
        foreach ($t as $i => $tok) {
            if ($tok[0] === '"' || $tok[0] === T_START_HEREDOC || $tok[0] === T_END_HEREDOC) {
                if ($otevreno === null && $tok[0] !== T_END_HEREDOC) {
                    $otevreno = $i;
                } elseif ($otevreno !== null && $tok[0] !== T_START_HEREDOC) {
                    $pary[$otevreno] = $i;
                    $pary[$i] = $otevreno;
                    $otevreno = null;
                }
            }
        }

        return $pary;
    }

    /**
     * Kontext každého tokenu: 'trida' (tělo třídy), 'funkce', 'blok', 'podpis'
     * (parametry funkce) – podle zanoření složených závorek.
     *
     * @return array<int, string>
     */
    private function obsahy(array $t): array
    {
        $zasobnik = [];
        $jmena = [];
        $cekajiciJmeno = null;
        $cekajici = null;
        $vPodpisu = false;
        $hloubkaPodpisu = 0;
        $vysledek = [];

        foreach ($t as $i => $tok) {
            $id = $tok[0];
            if (in_array($id, [T_CLASS, T_ENUM, T_TRAIT, T_INTERFACE], true) && ($this->predchozi($t, $i)[0] ?? null) !== T_DOUBLE_COLON) {
                $cekajici = 'trida';
            } elseif ($id === T_FUNCTION) {
                $cekajici = 'funkce';
                $cekajiciJmeno = ($t[$i + 2][0] ?? null) === T_STRING ? $t[$i + 2][1] : null;
                $vPodpisu = true;
                $hloubkaPodpisu = 0;
            } elseif ($vPodpisu && $id === '(') {
                $hloubkaPodpisu++;
            } elseif ($vPodpisu && $id === ')') {
                $hloubkaPodpisu--;
                if ($hloubkaPodpisu === 0) {
                    $vPodpisu = false;
                }
            } elseif ($vPodpisu && $id === ';') {
                $vPodpisu = false;
                $cekajici = null;
            }

            if ($id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $zasobnik[] = $cekajici ?? 'blok';
                $jmena[] = $cekajici === 'funkce' ? $cekajiciJmeno : (end($jmena) ?: null);
                $cekajici = null;
            } elseif ($id === '}') {
                array_pop($zasobnik);
                array_pop($jmena);
            } elseif ($id === ';' && $cekajici === 'funkce' && ! $vPodpisu) {
                $cekajici = null;
            }

            $vysledek[$i] = $vPodpisu && $hloubkaPodpisu > 0 ? 'podpis' : (end($zasobnik) ?: 'soubor');
            $this->funkce[$i] = end($jmena) ?: null;
        }

        return $vysledek;
    }

    private function jeCeskyRetezec(array $t, int $i, array $pary): bool
    {
        $tok = $t[$i];
        if ($tok[0] === T_CONSTANT_ENCAPSED_STRING) {
            return preg_match(self::ZNAKY, $tok[1]) || $this->jeTextBezDiakritiky($t, $i);
        }
        if (($tok[0] === '"' || $tok[0] === T_START_HEREDOC) && isset($pary[$i]) && $pary[$i] > $i) {
            for ($k = $i + 1; $k < $pary[$i]; $k++) {
                if ($t[$k][0] === T_ENCAPSED_AND_WHITESPACE && preg_match(self::ZNAKY, $t[$k][1])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Řetězec bez diakritiky, který je přesto text: popisek Filamentu, volba, vlastnost s textem. */
    private function jeTextBezDiakritiky(array $t, int $i): bool
    {
        $hodnota = $this->hodnotaRetezce($t[$i][1]);
        $pred = $this->predchozi($t, $i);

        // Vlastnost Filamentu s textem (i malým písmenem: $modelLabel = 'push notifikace').
        if ($pred !== null && $pred[0] === '=' && ($promenna = $this->predchozi($t, $pred[4])) && $promenna[0] === T_VARIABLE) {
            return isset(self::VLASTNOSTI_FILAMENTU[substr($promenna[1], 1)]) && preg_match('/\p{L}{2,}/u', $hodnota);
        }
        if (! preg_match('/^\p{Lu}[\p{L}\p{N} ,!?()\-–&+%]*$/u', $hodnota) || ! preg_match('/\p{L}{2,}/u', $hodnota)) {
            return false;
        }
        $funkce = $this->funkce[$i] ?? null;
        if ($funkce !== null && preg_match(self::TEXTOVE_FUNKCE, $funkce) && $pred !== null
            && in_array($pred[0], [T_RETURN, T_DOUBLE_ARROW, '?', ':', T_COALESCE], true)) {
            return true;
        }

        // Argument (i hodnota v poli argumentu) textové metody.
        $hloubka = 0;
        for ($k = $i - 1; $k >= 0; $k--) {
            $id = $t[$k][0];
            if ($this->jePrazdny($t[$k])) {
                continue;
            }
            if ($id === ')' || $id === ']') {
                $hloubka++;
            } elseif ($id === '[') {
                if ($hloubka-- === 0) {
                    $hloubka = 0;
                }
            } elseif ($id === '(') {
                if ($hloubka > 0) {
                    $hloubka--;

                    continue;
                }
                $jmeno = $this->predchozi($t, $k);
                if ($jmeno === null || $jmeno[0] !== T_STRING) {
                    return false;
                }
                if ($jmeno[1] === 'make') {
                    $trida = $this->predchozi($t, $this->predchozi($t, $jmeno[4])[4] ?? 0);

                    return $trida !== null && in_array(substr($trida[1], (int) strrpos('\\'.$trida[1], '\\')), self::TRIDY_S_POPISKEM, true);
                }

                return in_array($jmeno[1], self::TEXTOVE_METODY, true);
            } elseif ($hloubka === 0 && in_array($id, [';', '{', '}'], true)) {
                return false;
            }
        }

        return false;
    }

    private function jePrazdny(array $tok): bool
    {
        return in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

    private function predchozi(array $t, int $i): ?array
    {
        for ($k = $i - 1; $k >= 0; $k--) {
            if (! $this->jePrazdny($t[$k])) {
                return $t[$k] + [4 => $k];
            }
        }

        return null;
    }

    private function dalsi(array $t, int $i): ?array
    {
        for ($k = $i + 1, $n = count($t); $k < $n; $k++) {
            if (! $this->jePrazdny($t[$k])) {
                return $t[$k] + [4 => $k];
            }
        }

        return null;
    }

    private function jeStop(array $tok): bool
    {
        self::$stopTokeny ??= array_flip([T_DOUBLE_ARROW, T_COALESCE, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR,
            T_IS_EQUAL, T_IS_NOT_EQUAL, T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL, T_IS_GREATER_OR_EQUAL, T_SPACESHIP,
            T_RETURN, T_ECHO, T_PRINT, T_THROW, T_CONCAT_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_COALESCE_EQUAL,
            T_OPEN_TAG, T_CLOSE_TAG, T_SL, T_SR, T_YIELD, T_CASE, T_ELSE, T_INLINE_HTML, T_AS]);

        return is_string($tok[0]) ? in_array($tok[0], self::STOP, true) : isset(self::$stopTokeny[$tok[0]]);
    }

    /** Konec operandu zprava od $i (index posledního tokenu), nebo null. */
    private function operandVpravo(array $t, int $i, array $pary): ?int
    {
        $hloubka = 0;
        $posledni = null;
        $sBlokem = false;
        for ($k = $i, $n = count($t); $k < $n; $k++) {
            $tok = $t[$k];
            if ($this->jePrazdny($tok)) {
                continue;
            }
            // match (…) { … }, function () { … }: složené závorky patří k výrazu.
            if (in_array($tok[0], [T_MATCH, T_FUNCTION, T_FN], true)) {
                $sBlokem = true;
            }
            if ($tok[0] === '{' && ($sBlokem || $hloubka > 0)) {
                $hloubka++;
                $posledni = $k;

                continue;
            }
            if ($tok[0] === '}' && $hloubka > 0) {
                $hloubka--;
                $posledni = $k;

                continue;
            }
            if (($tok[0] === '"' || $tok[0] === T_START_HEREDOC) && isset($pary[$k])) {
                $k = $pary[$k];
                $posledni = $k;

                continue;
            }
            if ($hloubka === 0 && ($tok[0] === '.' || $this->jeStop($tok))) {
                break;
            }
            if (in_array($tok[0], ['(', '['], true) || $tok[0] === T_CURLY_OPEN) {
                $hloubka++;
            } elseif (in_array($tok[0], [')', ']'], true)) {
                if ($hloubka === 0) {
                    break;
                }
                $hloubka--;
            }
            $posledni = $k;
        }

        return $posledni;
    }

    /** Začátek operandu zleva od $i, nebo null. */
    private function operandVlevo(array $t, int $i, array $pary): ?int
    {
        $hloubka = 0;
        $prvni = null;
        for ($k = $i; $k >= 0; $k--) {
            $tok = $t[$k];
            if ($this->jePrazdny($tok)) {
                continue;
            }
            if (($tok[0] === '"' || $tok[0] === T_END_HEREDOC) && isset($pary[$k])) {
                $k = $pary[$k];
                $prvni = $k;

                continue;
            }
            if ($tok[0] === '}') {
                $hloubka++;
                $prvni = $k;

                continue;
            }
            if ($tok[0] === '{' && $hloubka > 0) {
                $hloubka--;
                $prvni = $k;

                continue;
            }
            if ($hloubka === 0 && ($tok[0] === '.' || $this->jeStop($tok))) {
                break;
            }
            if (in_array($tok[0], [')', ']'], true)) {
                $hloubka++;
            } elseif (in_array($tok[0], ['(', '['], true)) {
                if ($hloubka === 0) {
                    break;
                }
                $hloubka--;
            }
            $prvni = $k;
        }

        return $prvni;
    }

    /** Celý řetěz spojený tečkami kolem řetězce na $i. @return array{0: int, 1: int} */
    private function retez(array $t, int $i, array $pary): array
    {
        $od = $i;
        $do = ($t[$i][0] === '"' || $t[$i][0] === T_START_HEREDOC) ? $pary[$i] : $i;

        while (($p = $this->predchozi($t, $od)) && $p[0] === '.') {
            $zacatek = $this->operandVlevo($t, $p[4] - 1, $pary);
            if ($zacatek === null) {
                break;
            }
            $od = $zacatek;
        }
        while (($d = $this->dalsi($t, $do)) && $d[0] === '.') {
            $konec = $this->operandVpravo($t, $d[4] + 1, $pary);
            if ($konec === null) {
                break;
            }
            $do = $konec;
        }

        return [$od, $do];
    }

    private function nalezPhp(array $t, int $od, int $do, array $pary, array $obsah, string $kod): array
    {
        $nalez = ['soubor' => $this->soubor, 'text' => '', 'parametry' => [], 'rucne' => null, 'vyvojar' => $this->celyVyvojar];
        $vyrazy = [];

        // Operandy řetězu: texty a výrazy.
        $k = $od;
        while ($k <= $do) {
            if ($this->jePrazdny($t[$k]) || $t[$k][0] === '.') {
                $k++;

                continue;
            }
            $konec = (($t[$k][0] === '"' || $t[$k][0] === T_START_HEREDOC) && isset($pary[$k])) ? $pary[$k] : ($this->operandVpravo($t, $k, $pary) ?? $k);
            $konec = min($konec, $do);

            if ($konec === $k && $t[$k][0] === T_CONSTANT_ENCAPSED_STRING) {
                $nalez['text'] .= $this->hodnotaRetezce($t[$k][1]);
            } elseif ($t[$k][0] === '"') {
                $this->retezecSPromennymi($t, $k, $konec, $nalez, $vyrazy);
            } elseif ($t[$k][0] === T_START_HEREDOC) {
                $nalez['rucne'] ??= 'heredoc';
                $nalez['text'] .= $this->zdrojem($t, $k, $konec);
            } else {
                $nalez['text'] .= ':'.$this->parametr($this->zdrojem($t, $k, $konec), $nalez, $vyrazy);
            }
            $k = $konec + 1;
        }

        // Regulární výraz není text.
        if (count($nalez['parametry']) === 0 && preg_match('~^([/#\~]).*\1[imsxuU]*$~s', $nalez['text'])) {
            $nalez['vyvojar'] = true;

            return $nalez;
        }

        $pred = $this->predchozi($t, $od);
        $za = $this->dalsi($t, $do);

        // Kde stojí: tělo třídy (konstanta, vlastnost), podpis funkce, atribut.
        if ($obsah[$od] === 'trida') {
            $vlastnost = $this->vlastnostFilamentu($t, $od);
            if ($vlastnost !== null && $od === $do) {
                $nalez += $vlastnost;
            } else {
                $nalez['rucne'] ??= 'konstanta nebo vlastnost třídy';
            }
        } elseif ($obsah[$od] === 'podpis') {
            $nalez['rucne'] ??= 'výchozí hodnota parametru';
        }
        if ($this->vAtributu($t, $od)) {
            $nalez['rucne'] ??= 'atribut';
        }
        if ($pred !== null && $pred[0] === T_CASE) {
            $nalez['rucne'] ??= 'hodnota enumu';
        }
        if ($za !== null && $za[0] === T_DOUBLE_ARROW && ! ($pred !== null && $pred[0] === T_DOUBLE_ARROW)) {
            $nalez['rucne'] ??= 'klíč pole';
        }
        foreach ([$pred, $za] as $soused) {
            if ($soused !== null && in_array($soused[0], [T_IS_EQUAL, T_IS_NOT_EQUAL, T_IS_IDENTICAL, T_IS_NOT_IDENTICAL], true)) {
                $nalez['rucne'] ??= 'porovnání';
            }
        }

        // Komu je argumentem: logy a výjimky pro vývojáře, datové funkce.
        [$volani, $predVolanim, $jeNew] = $this->volani($t, $od);
        if ($volani !== null) {
            $jmeno = $volani[1];
            $zaklad = substr($jmeno, (int) strrpos('\\'.$jmeno, '\\'));
            $prikaz = $this->prikaz($t, $od, $kod);
            if ($jeNew && preg_match(self::VYJIMKY_VYVOJARE, ltrim($zaklad, '\\'))) {
                $nalez['vyvojar'] = true;
            } elseif (in_array($jmeno, ['logger', 'info', 'report'], true) && ($predVolanim === null || ! in_array($predVolanim[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR], true))) {
                $nalez['vyvojar'] = true;
            } elseif (str_contains($prikaz, 'Log::') && preg_match('/^(emergency|alert|critical|error|warning|notice|info|debug|log|channel|stack|build)$/', $jmeno)) {
                $nalez['vyvojar'] = true;
            } elseif (in_array($jmeno, self::DATOVE_FUNKCE, true)) {
                $nalez['rucne'] ??= 'data ('.$jmeno.')';
            }
        }

        return $nalez;
    }

    /** Text řetězce v PHP (bez uvozovek, s rozvinutými escape sekvencemi). */
    private function hodnotaRetezce(string $literal): string
    {
        if ($literal[0] === "'") {
            return strtr(substr($literal, 1, -1), ['\\\\' => '\\', "\\'" => "'"]);
        }

        return stripcslashes(strtr(substr($literal, 1, -1), ['\\$' => '$']));
    }

    private function retezecSPromennymi(array $t, int $od, int $do, array &$nalez, array &$vyrazy): void
    {
        $k = $od + 1;
        while ($k < $do) {
            $tok = $t[$k];
            if ($tok[0] === T_ENCAPSED_AND_WHITESPACE) {
                $nalez['text'] .= stripcslashes(strtr($tok[1], ['\\$' => '$', '\\"' => '"']));
                $k++;
            } elseif ($tok[0] === T_CURLY_OPEN || $tok[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $hloubka = 1;
                $konec = $k + 1;
                while ($konec < $do && $hloubka > 0) {
                    if ($t[$konec][0] === T_CURLY_OPEN || $t[$konec][0] === '{' || $t[$konec][0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                        $hloubka++;
                    } elseif ($t[$konec][0] === '}') {
                        $hloubka--;
                    }
                    $konec++;
                }
                $vyraz = $this->zdrojem($t, $k + 1, $konec - 2);
                if ($tok[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                    $vyraz = '$'.$vyraz;
                }
                $nalez['text'] .= ':'.$this->parametr($vyraz, $nalez, $vyrazy);
                $k = $konec;
            } elseif ($tok[0] === T_VARIABLE) {
                $konec = $k;
                if (($t[$k + 1][0] ?? null) === T_OBJECT_OPERATOR || ($t[$k + 1][0] ?? null) === T_NULLSAFE_OBJECT_OPERATOR) {
                    $konec = $k + 2;
                } elseif (($t[$k + 1][0] ?? null) === '[') {
                    $nalez['rucne'] ??= 'pole v řetězci';
                    $konec = $k;
                }
                $nalez['text'] .= ':'.$this->parametr($this->zdrojem($t, $k, $konec), $nalez, $vyrazy);
                $k = $konec + 1;
            } else {
                $nalez['text'] .= $tok[1];
                $k++;
            }
        }
    }

    private function zdrojem(array $t, int $od, int $do): string
    {
        $s = '';
        for ($k = $od; $k <= $do; $k++) {
            $s .= $t[$k][1];
        }

        return trim($s);
    }

    /** Jméno parametru pro výraz (stejný výraz = stejné jméno). */
    private function parametr(string $vyraz, array &$nalez, array &$vyrazy): string
    {
        if (isset($vyrazy[$vyraz])) {
            return $vyrazy[$vyraz];
        }

        $holy = trim((string) preg_replace('/^(\((int|string|float|bool)\)\s*)+/', '', $vyraz));
        // Funkce kolem jednoduchého výrazu (mb_strtolower($popis), e($x)) – jméno podle výrazu.
        while (preg_match('/^\\\\?[a-z_]\w*\(\s*(\$[^,()]+?)\s*(,.*)?\)$/s', $holy, $m) && ! str_starts_with($holy, 'config(')) {
            $holy = $m[1];
        }
        $jmeno = match (true) {
            (bool) preg_match('/^\$([a-zA-Z_]\w*)$/', $holy, $m) => $m[1],
            (bool) preg_match('/\[\s*[\'"](\w+)[\'"]\s*\]$/', $holy, $m) => $m[1],
            (bool) preg_match('/^\\\\?[\w\\\\]*\(\s*[\'"]([\w.]+)[\'"]/', $holy, $m) => substr($m[1], (int) strrpos('.'.$m[1], '.')),
            (bool) preg_match('/(?:->|\?->|::)\$?([a-zA-Z_]\w*)\s*(\(.*\))?\s*$/s', $holy, $m) => $m[1],
            (bool) preg_match('/^\\\\?(?:\w+\\\\)*([a-zA-Z_]\w*)\s*\(/', $holy, $m) => $m[1],
            (bool) preg_match('/^\$([a-zA-Z_]\w*)/', $holy, $m) => $m[1],
            default => 'hodnota',
        };
        $jmeno = strtoupper($jmeno) === $jmeno ? strtolower($jmeno) : strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $jmeno));
        $jmeno = trim((string) preg_replace('/[^a-z0-9_]/', '', $jmeno), '_') ?: 'hodnota';
        if (in_array($jmeno, ['this', 'e', 'number_format', 'trim', 'strval', 'intval'], true)) {
            $jmeno = 'hodnota';
        }

        $zaklad = $jmeno;
        for ($i = 2; isset($nalez['parametry'][$jmeno]); $i++) {
            $jmeno = $zaklad.$i;
        }

        $nalez['parametry'][$jmeno] = $vyraz;

        return $vyrazy[$vyraz] = $jmeno;
    }

    /** Řetězec je hodnotou vlastnosti Filamentu (navigationLabel…)? */
    private function vlastnostFilamentu(array $t, int $i): ?array
    {
        $rovnase = $this->predchozi($t, $i);
        if ($rovnase === null || $rovnase[0] !== '=') {
            return null;
        }
        $promenna = $this->predchozi($t, $rovnase[4]);
        if ($promenna === null || $promenna[0] !== T_VARIABLE || ! isset(self::VLASTNOSTI_FILAMENTU[substr($promenna[1], 1)])) {
            return null;
        }
        $za = $this->dalsi($t, $i);
        if ($za === null || $za[0] !== ';') {
            return null;
        }

        // Začátek deklarace: první modifikátor před proměnnou.
        $zacatek = $promenna[4];
        for ($k = $promenna[4] - 1; $k >= 0; $k--) {
            if ($this->jePrazdny($t[$k])) {
                continue;
            }
            if (in_array($t[$k][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, '?', '|', T_READONLY], true)
                || (is_array($t[$k]) && in_array(strtolower($t[$k][1]), ['null', 'string', 'int', 'bool'], true))) {
                $zacatek = $k;

                continue;
            }
            break;
        }

        return [
            'vlastnost' => substr($promenna[1], 1),
            'staticka' => str_contains($this->zdrojem($t, $zacatek, $promenna[4]), 'static'),
            'deklarace_od' => $t[$zacatek][3],
            'deklarace_do' => $t[$za[4]][3] + 1,
        ];
    }

    private function vAtributu(array $t, int $i): bool
    {
        $hloubka = 0;
        for ($k = $i - 1; $k >= 0; $k--) {
            $id = $t[$k][0];
            if ($id === ']') {
                $hloubka++;
            } elseif ($id === '[') {
                $hloubka--;
            } elseif ($id === T_ATTRIBUTE) {
                if ($hloubka === 0) {
                    return true;
                }
                $hloubka--;
            } elseif ($id === ';' || $id === '{' || $id === '}') {
                return false;
            }
        }

        return false;
    }

    /**
     * Funkce nebo metoda, jejímž argumentem řetěz je.
     *
     * @return array{0: ?array, 1: ?array, 2: bool} [jméno, token před jménem, je `new`]
     */
    private function volani(array $t, int $i): array
    {
        $hloubka = 0;
        for ($k = $i - 1; $k >= 0; $k--) {
            $tok = $t[$k];
            if ($this->jePrazdny($tok)) {
                continue;
            }
            if (in_array($tok[0], [')', ']'], true)) {
                $hloubka++;
            } elseif ($tok[0] === '[') {
                if ($hloubka === 0) {
                    return [null, null, false];
                }
                $hloubka--;
            } elseif ($tok[0] === '(') {
                if ($hloubka > 0) {
                    $hloubka--;

                    continue;
                }
                $jmeno = $this->predchozi($t, $k);
                if ($jmeno === null || ! in_array($jmeno[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                    return [null, null, false];
                }
                $pred = $this->predchozi($t, $jmeno[4]);

                return [$jmeno, $pred, $pred !== null && $pred[0] === T_NEW];
            } elseif ($hloubka === 0 && in_array($tok[0], [';', '{', '}'], true)) {
                return [null, null, false];
            }
        }

        return [null, null, false];
    }

    /** Zdroj příkazu, ve kterém token je (od posledního ; nebo {). */
    private function prikaz(array $t, int $i, string $kod): string
    {
        for ($k = $i; $k >= 0; $k--) {
            if (in_array($t[$k][0], [';', '{', '}'], true)) {
                break;
            }
        }

        return substr($kod, $t[max($k, 0)][3], $t[$i][3] - $t[max($k, 0)][3]);
    }

    private function oznacenoBezPrekladu(string $zdroj, int $pozice): bool
    {
        $zacatek = strrpos(substr($zdroj, 0, $pozice), "\n");
        $zacatek = $zacatek === false ? 0 : $zacatek;
        $predchozi = strrpos(substr($zdroj, 0, max($zacatek, 0)), "\n");
        $konec = strpos($zdroj, "\n", $pozice);
        $radky = substr($zdroj, $predchozi === false ? 0 : $predchozi, ($konec === false ? strlen($zdroj) : $konec) - ($predchozi === false ? 0 : $predchozi));

        return str_contains($radky, 'bez překladu');
    }

    // ── Blade ───────────────────────────────────────────────────────────

    private function blade(string $zdroj): void
    {
        $useky = $this->usekyBlade($zdroj);
        $beh = [];

        $uzavri = function () use (&$beh, $zdroj) {
            $this->behBlade($beh, $zdroj);
            $beh = [];
        };

        foreach ($useky as $u) {
            if ($u['druh'] === 'text' || ($u['druh'] === 'echo' && ! $u['surove'])) {
                // Prázdný řádek (odstavec) dělí běh textu.
                if ($u['druh'] === 'text' && preg_match('/\n[ \t]*\n/', $u['obsah'])) {
                    $casti = preg_split('/(\n[ \t]*\n)/', $u['obsah'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
                    foreach ($casti as [$cast, $pos]) {
                        if (preg_match('/^\n[ \t]*\n$/', $cast)) {
                            $uzavri();
                        } elseif ($cast !== '') {
                            $beh[] = ['druh' => 'text', 'obsah' => $cast, 'od' => $u['od'] + $pos, 'do' => $u['od'] + $pos + strlen($cast)];
                        }
                    }

                    continue;
                }
                $beh[] = $u;

                continue;
            }

            $uzavri();

            match ($u['druh']) {
                'echo' => $this->php('<?php '.$u['obsah'].';', $u['obsah_od'] - 6, $zdroj, 'blade-php'),
                'php', 'direktiva' => $this->php('<?php '.$u['obsah'].';', $u['obsah_od'] - 6, $zdroj, 'blade-php'),
                'znacka' => $this->znackaBlade($u, $zdroj),
                'skript' => $this->skriptBlade($u, $zdroj),
                default => null,
            };
        }
        $uzavri();
    }

    /** Skript: komentáře nevadí, český řetězec v JS ano (předat ho z PHP přes @js(__('…'))). Styl se nekontroluje. */
    private function skriptBlade(array $u, string $zdroj): void
    {
        if (preg_match('/^<style/i', $u['obsah'])) {
            return;
        }
        $bezKomentaru = (string) preg_replace('#/\*.*?\*/|(?<![:\\\'"])//[^\n]*|\{\{--.*?--\}\}#s', '', $u['obsah']);
        // Echo v skriptu (@js, {{ }}) zpracuje PHP.
        preg_match_all('/\{\{(.*?)\}\}|@js\((.*?)\)\s*[;,)\]]/s', $u['obsah'], $echa, PREG_OFFSET_CAPTURE);
        foreach ($echa[1] as $i => [$vyraz, $pos]) {
            if ($pos >= 0) {
                $this->php('<?php '.$vyraz.';', $u['od'] + $pos - 6, $zdroj, 'blade-php');
            }
        }
        $bezEch = (string) preg_replace('/\{\{.*?\}\}|@js\(.*?\)/s', '', $bezKomentaru);
        if (preg_match(self::ZNAKY, $bezEch)) {
            $u['obsah'] = $bezEch;
            $this->nalezy[] = $this->rucniNalez($u, $zdroj, 'text ve skriptu');
        }
    }

    private function rucniNalez(array $u, string $zdroj, string $duvod): array
    {
        preg_match(self::ZNAKY, $u['obsah'], $m, PREG_OFFSET_CAPTURE);
        $od = $u['od'] + ($m[0][1] ?? 0);

        return ['soubor' => $this->soubor, 'radek' => substr_count($zdroj, "\n", 0, $od) + 1, 'text' => trim(mb_substr(substr($u['obsah'], $m[0][1] ?? 0), 0, 80)),
            'parametry' => [], 'od' => $od, 'do' => $od, 'druh' => 'blade', 'rucne' => $duvod, 'vyvojar' => $this->oznacenoBezPrekladu($zdroj, $od)];
    }

    /** Rozdělí Blade na úseky: text, echo, php, direktiva, znacka, skript, komentar. */
    private function usekyBlade(string $s): array
    {
        $useky = [];
        $n = strlen($s);
        $i = 0;
        $text = 0;

        $pridejText = function (int $do) use (&$useky, &$text, $s) {
            if ($do > $text) {
                $useky[] = ['druh' => 'text', 'obsah' => substr($s, $text, $do - $text), 'od' => $text, 'do' => $do];
            }
        };

        while ($i < $n) {
            $c = $s[$i];
            if ($c === '{' && substr($s, $i, 4) === '{{--') {
                $konec = strpos($s, '--}}', $i);
                $konec = $konec === false ? $n : $konec + 4;
                $pridejText($i);
                $useky[] = ['druh' => 'komentar', 'obsah' => '', 'od' => $i, 'do' => $konec];
                $i = $text = $konec;
            } elseif ($c === '{' && (substr($s, $i, 2) === '{{' || substr($s, $i, 3) === '{!!') && ($i === 0 || $s[$i - 1] !== '@')) {
                $surove = substr($s, $i, 3) === '{!!';
                $zavirka = $surove ? '!!}' : '}}';
                $zacatek = $i + ($surove ? 3 : 2);
                $konec = strpos($s, $zavirka, $zacatek);
                $konec = $konec === false ? $n : $konec;
                $pridejText($i);
                $useky[] = ['druh' => 'echo', 'surove' => $surove, 'obsah' => substr($s, $zacatek, $konec - $zacatek), 'obsah_od' => $zacatek, 'od' => $i, 'do' => $konec + 3 - ($surove ? 0 : 1)];
                $i = $text = $konec + strlen($zavirka);
            } elseif ($c === '@' && preg_match('/\G@(\w+)/', $s, $m, 0, $i)
                && ($i === 0 || ! preg_match('/[\w@]/', $s[$i - 1]) || preg_match(self::DIREKTIVY, $m[1]))) {
                $pridejText($i);
                $j = $i + strlen($m[0]);
                if ($m[1] === 'php' && ! preg_match('/\G\s*\(/', $s, $_, 0, $j)) {
                    $konec = strpos($s, '@endphp', $j);
                    $konec = $konec === false ? $n : $konec;
                    $useky[] = ['druh' => 'php', 'obsah' => substr($s, $j, $konec - $j), 'obsah_od' => $j, 'od' => $i, 'do' => $konec + 7];
                    $i = $text = $konec + 7;
                } elseif ($m[1] === 'verbatim') {
                    $konec = strpos($s, '@endverbatim', $j);
                    $konec = $konec === false ? $n : $konec + 12;
                    $useky[] = ['druh' => 'skript', 'obsah' => substr($s, $j, $konec - $j), 'od' => $j, 'do' => $konec];
                    $i = $text = $konec;
                } elseif (preg_match('/\G[ \t]*\(/', $s, $mz, 0, $j)) {
                    $zavorka = $j + strlen($mz[0]) - 1;
                    $konec = $this->konecZavorky($s, $zavorka);
                    $useky[] = ['druh' => 'direktiva', 'obsah' => substr($s, $zavorka + 1, $konec - $zavorka - 1), 'obsah_od' => $zavorka + 1, 'od' => $i, 'do' => $konec + 1];
                    $i = $text = $konec + 1;
                } else {
                    $useky[] = ['druh' => 'direktiva', 'obsah' => '', 'obsah_od' => $j, 'od' => $i, 'do' => $j];
                    $i = $text = $j;
                }
            } elseif ($c === '<' && preg_match('/\G<(script|style)\b/i', $s, $m, 0, $i)) {
                $pridejText($i);
                $konec = stripos($s, '</'.$m[1], $i);
                $konec = $konec === false ? $n : strpos($s, '>', $konec) + 1;
                $useky[] = ['druh' => 'skript', 'obsah' => substr($s, $i, $konec - $i), 'od' => $i, 'do' => $konec];
                $i = $text = $konec;
            } elseif ($c === '<' && preg_match('/\G<[a-zA-Z\/!]/', $s, $_, 0, $i)) {
                $pridejText($i);
                $konec = $this->konecZnacky($s, $i);
                $useky[] = ['druh' => 'znacka', 'obsah' => substr($s, $i, $konec - $i), 'od' => $i, 'do' => $konec];
                $i = $text = $konec;
            } else {
                $i++;
            }
        }
        $pridejText($n);

        return $useky;
    }

    private function konecZavorky(string $s, int $i): int
    {
        $hloubka = 0;
        $uvozovka = null;
        for ($n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];
            if ($uvozovka !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $uvozovka) {
                    $uvozovka = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $uvozovka = $c;
            } elseif ($c === '(') {
                $hloubka++;
            } elseif ($c === ')') {
                if (--$hloubka === 0) {
                    return $i;
                }
            }
        }

        return $n - 1;
    }

    private function konecZnacky(string $s, int $i): int
    {
        if (substr($s, $i, 4) === '<!--') {
            $konec = strpos($s, '-->', $i);

            return $konec === false ? strlen($s) : $konec + 3;
        }
        $uvozovka = null;
        for ($n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];
            if (substr($s, $i, 2) === '{{') {
                $konec = strpos($s, '}}', $i);
                $i = $konec === false ? $n : $konec + 1;
            } elseif ($uvozovka === null && $c === '@' && preg_match('/\G@\w+[ \t]*\(/', $s, $m, 0, $i)) {
                $i = $this->konecZavorky($s, $i + strlen($m[0]) - 1);
            } elseif ($uvozovka !== null) {
                if ($c === $uvozovka) {
                    $uvozovka = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $uvozovka = $c;
            } elseif ($c === '>') {
                return $i + 1;
            }
        }

        return $n;
    }

    /** Text (s echy jako :parametry) mezi značkami. */
    private function behBlade(array $beh, string $zdroj): void
    {
        $sCestinou = false;
        foreach ($beh as $u) {
            if ($u['druh'] === 'text' && (preg_match(self::ZNAKY, $u['obsah']) || $this->jeTextBlade(html_entity_decode(trim($u['obsah']))))) {
                $sCestinou = true;
            }
        }
        // Echo s českým textem uvnitř věty: nejdřív se převede text ve výrazu,
        // věta až v dalším kole (jazyky:prevod opakuje, dokud je co převádět).
        $vnoreny = $sCestinou && collect($beh)->contains(fn ($u) => $u['druh'] === 'echo' && preg_match(self::ZNAKY, $u['obsah']));
        if (! $sCestinou || $vnoreny) {
            foreach ($beh as $u) {
                if ($u['druh'] === 'echo') {
                    $this->php('<?php '.$u['obsah'].';', $u['obsah_od'] - 6, $zdroj, 'blade-php');
                }
            }

            return;
        }

        // Okrajové mezery zůstávají mimo překlad.
        $prvni = $beh[0];
        $posledni = $beh[count($beh) - 1];
        $od = $prvni['od'];
        $do = $posledni['do'];
        if ($prvni['druh'] === 'text') {
            $od += strlen($prvni['obsah']) - strlen(ltrim($prvni['obsah']));
        }
        if ($posledni['druh'] === 'text') {
            $do -= strlen($posledni['obsah']) - strlen(rtrim($posledni['obsah']));
        }

        $nalez = ['soubor' => $this->soubor, 'radek' => substr_count($zdroj, "\n", 0, $od) + 1, 'text' => '', 'parametry' => [],
            'od' => $od, 'do' => $do, 'druh' => 'blade', 'rucne' => null, 'vyvojar' => $this->oznacenoBezPrekladu($zdroj, $od)];
        $vyrazy = [];
        foreach ($beh as $u) {
            if ($u['druh'] === 'text') {
                $cast = substr($zdroj, max($u['od'], $od), min($u['do'], $do) - max($u['od'], $od));
                $nalez['text'] .= $this->textovyMail ? $cast : html_entity_decode($cast, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $vyraz = trim($u['obsah']);
                if (preg_match(self::ZNAKY, $vyraz)) {
                    $nalez['rucne'] ??= 'český text ve výrazu uvnitř věty';
                }
                $nalez['text'] .= ':'.$this->parametr($vyraz, $nalez, $vyrazy);
            }
        }
        if (! $this->textovyMail) {
            $nalez['text'] = (string) preg_replace('/\s+/u', ' ', $nalez['text']);
        }
        $nalez['surove'] = $this->textovyMail;
        $this->nalezy[] = $nalez;
    }

    /** Text v Blade bez diakritiky: slovo s malými písmeny, ne technický údaj (XSRF-TOKEN, souhlas_cookies, www.comgate.eu). */
    private function jeTextBlade(string $text): bool
    {
        return preg_match('/\p{L}{2,}/u', $text) && preg_match('/\p{Ll}/u', $text)
            && ! preg_match('/^[^\s]*[_.@\/][^\s]*$/u', $text);
    }

    /** Atributy značky: text v hodnotě → {{ __() }}, výrazy (:atribut, echo) → PHP. */
    private function znackaBlade(array $u, string $zdroj): void
    {
        $znacka = $u['obsah'];
        if (! str_contains($znacka, '{{') && ! preg_match(self::ZNAKY, $znacka)) {
            return;
        }

        preg_match_all('/([:@]?[\w\-:.@]+)\s*=\s*("([^"]*)"|\'([^\']*)\')/u', $znacka, $atributy, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($atributy as $a) {
            $jmeno = $a[1][0];
            $hodnota = ($a[3][1] ?? -1) >= 0 ? $a[3][0] : ($a[4][0] ?? '');
            $hodnotaOd = $u['od'] + (($a[3][1] ?? -1) >= 0 ? $a[3][1] : $a[4][1]);

            if (str_starts_with($jmeno, ':')) {
                $this->php('<?php '.$hodnota.';', $hodnotaOd - 6, $zdroj, 'blade-php');

                continue;
            }
            // Text mimo echa; echo s českým textem se převede nejdřív (v dalším kole celá hodnota).
            $mimoEcha = (string) preg_replace('/\{\{.*?\}\}/s', '', $hodnota);
            $vnoreny = (bool) preg_match('/\{\{(?:(?!\}\}).)*'.trim(self::ZNAKY, '/u').'/su', $hodnota);
            $textovy = in_array($jmeno, ['placeholder', 'title', 'alt', 'aria-label', 'label', 'heading', 'description'], true)
                && preg_match('/^\p{Lu}/u', $mimoEcha) && preg_match('/\p{L}{2,}/u', $mimoEcha);
            if ((! preg_match(self::ZNAKY, $mimoEcha) && ! $textovy) || $vnoreny) {
                // Jen echa v hodnotě.
                preg_match_all('/\{\{(.*?)\}\}/s', $hodnota, $echa, PREG_OFFSET_CAPTURE);
                foreach ($echa[1] as [$vyraz, $pos]) {
                    $this->php('<?php '.$vyraz.';', $hodnotaOd + $pos - 6, $zdroj, 'blade-php');
                }

                continue;
            }

            $nalez = ['soubor' => $this->soubor, 'radek' => substr_count($zdroj, "\n", 0, $hodnotaOd) + 1, 'text' => '', 'parametry' => [],
                'od' => $hodnotaOd, 'do' => $hodnotaOd + strlen($hodnota), 'druh' => 'blade-atribut', 'rucne' => null, 'vyvojar' => $this->oznacenoBezPrekladu($zdroj, $hodnotaOd)];
            if (preg_match('/^(x-|@|wire:|onclick|onsubmit|style)/', $jmeno)) {
                $nalez['rucne'] = 'atribut se skriptem ('.$jmeno.')';
            }
            $vyrazy = [];
            foreach (preg_split('/(\{\{.*?\}\})/s', $hodnota, -1, PREG_SPLIT_DELIM_CAPTURE) as $cast) {
                if (str_starts_with($cast, '{{')) {
                    $nalez['text'] .= ':'.$this->parametr(trim(substr($cast, 2, -2)), $nalez, $vyrazy);
                } else {
                    $nalez['text'] .= html_entity_decode($cast, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
            $this->nalezy[] = $nalez;
        }

        if (preg_match(self::ZNAKY, (string) preg_replace('/([:@]?[\w\-:.@]+)\s*=\s*("[^"]*"|\'[^\']*\')/u', '', $znacka))) {
            $this->nalezy[] = $this->rucniNalez($u, $zdroj, 'text ve značce');
        }
    }
}
