<?php

namespace App\Console\Commands;

use App\Support\Jazyky\Jazyky;
use App\Support\Jazyky\Kontrola;
use Illuminate\Console\Command;

/**
 * Najde texty natvrdo a chybějící překlady (docs/jazyky.md).
 *   php artisan jazyky:kontrola            selže na textu natvrdo a klíči, který chybí ve výchozím jazyce
 *   php artisan jazyky:kontrola --prisne   selže i na chybějícím překladu v dalších zapnutých jazycích
 *   php artisan jazyky:kontrola --jen-hlasit  vypíše, ale neselže (projekt, který převádí postupně)
 */
class JazykyKontrola extends Command
{
    protected $signature = 'jazyky:kontrola {--prisne : selhat i na chybějícím překladu} {--jen-hlasit : jen vypsat} {--vse : vypsat všechny chybějící klíče}';

    protected $description = 'Texty natvrdo a chybějící překlady';

    public function handle(): int
    {
        $kontrola = Kontrola::projekt();
        $chyba = false;

        $natvrdo = $kontrola->natvrdo();
        foreach ($natvrdo as $n) {
            $this->line(sprintf('  %s:%d  %s%s', $n['soubor'], $n['radek'], mb_strimwidth(str_replace("\n", ' ', $n['text']), 0, 90, '…'), $n['rucne'] ? '  ['.$n['rucne'].']' : ''));
        }
        $this->line(count($natvrdo) === 0 ? '<info>Texty natvrdo: žádné.</info>' : '<error>Texty natvrdo: '.count($natvrdo).'</error> (převod: php artisan jazyky:prevod)');
        $chyba = $chyba || count($natvrdo) > 0;

        foreach (array_keys(Jazyky::zapnute()) as $jazyk) {
            $chybi = $kontrola->chybejiciKlice($jazyk);
            $preklad = $jazyk === Jazyky::vychozi() ? ['chybi' => [], 'parametry' => []] : $kontrola->preklad($jazyk);
            $vypis = $this->option('vse') ? $chybi : array_slice($chybi, 0, 20, true);
            foreach ($vypis as $klic => $kde) {
                $this->line("  [$jazyk] $klic  ($kde)");
            }
            foreach ($preklad['parametry'] as $klic) {
                $this->line("  [$jazyk] $klic  (jiné :parametry než ".Jazyky::vychozi().')');
            }

            $vadne = count($chybi) + count($preklad['parametry']);
            $zavazne = $jazyk === Jazyky::vychozi() || $this->option('prisne');
            $this->line(sprintf('%s: chybí %d klíčů z kódu, %d textů výchozího jazyka, jiné parametry %d',
                $jazyk, count($chybi), count($preklad['chybi']), count($preklad['parametry'])));
            $chyba = $chyba || ($zavazne && ($vadne > 0 || count($preklad['chybi']) > 0)) || count($preklad['parametry']) > 0;
        }

        return $chyba && ! $this->option('jen-hlasit') ? self::FAILURE : self::SUCCESS;
    }
}
