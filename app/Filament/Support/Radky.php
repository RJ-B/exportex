<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/**
 * Víceřádkový popisek pod hodnotou buňky.
 *
 * Provozní logy mají několik sloupců, kde pod hlavní hodnotou stojí další
 * patra — u Aktivity role a IP, u E-mailů kdy/kdo/kolikátý pokus, u Chyb kdy
 * a kdo je zavřel. Kanón chce každé patro na SVÉM řádku. Filament umí jen
 * jeden `description()`, takže sražení oddělovačem („role · IP") je svůdné —
 * jenže se ty údaje slijí v jeden řetězec a přestanou se číst.
 */
final class Radky
{
    /** Poskládá patra pod sebe; prázdné hodnoty vynechá. Vrací null, když nezbude nic. */
    public static function pod(?string ...$radky): ?HtmlString
    {
        $radky = array_values(array_filter($radky, fn (?string $r) => filled($r)));

        if ($radky === []) {
            return null;
        }

        return new HtmlString(implode('<br>', array_map(e(...), $radky)));
    }
}
