<?php

namespace App\Console\Commands;

use App\Support\Jazyky\Jazyky;
use App\Support\Jazyky\Kontrola;
use App\Support\Jazyky\Prevod;
use Illuminate\Console\Command;

/**
 * Převede texty natvrdo na klíče a zapíše je do lang/<výchozí jazyk>/ (docs/jazyky.md).
 *   php artisan jazyky:prevod                     celý projekt (app, resources/views, routes/web.php)
 *   php artisan jazyky:prevod app/Filament/Pages  jen část
 *   php artisan jazyky:prevod --nanecisto         jen vypíše, co by převedl
 * Co nejde strojově (konstanty tříd, klíče polí, porovnání…), vypíše – to se upraví ručně.
 * Výsledek vždy projít v diffu a pustit testy.
 */
class JazykyPrevod extends Command
{
    protected $signature = 'jazyky:prevod {cesty?* : soubory nebo složky} {--nanecisto : nic nezapisovat}';

    protected $description = 'Převede texty natvrdo na klíče překladů';

    public function handle(): int
    {
        $prevod = new Prevod(base_path(), Jazyky::vychozi());
        $cesty = $this->argument('cesty');
        $soubory = array_filter(Kontrola::projekt()->soubory(), fn ($s) => $cesty === [] || collect($cesty)->contains(fn ($c) => str_starts_with($s, rtrim($c, '/'))));

        // Víc kol: text vnořený ve výrazu (parametr věty, větev podmínky) se převede
        // v prvním kole, věta kolem něj v dalším.
        $celkem = 0;
        for ($kolo = 1; $kolo <= 4; $kolo++) {
            $rucne = [];
            $vKole = 0;
            foreach ($soubory as $soubor) {
                $vysledek = $prevod->soubor($soubor, ! $this->option('nanecisto'));
                $vKole += $vysledek['prevedeno'];
                array_push($rucne, ...$vysledek['rucne']);
                if ($vysledek['prevedeno'] > 0) {
                    $this->line(sprintf('  %-70s %d', $soubor, $vysledek['prevedeno']));
                }
            }
            $celkem += $vKole;
            if ($vKole === 0 || $this->option('nanecisto')) {
                break;
            }
        }

        if (! $this->option('nanecisto')) {
            foreach ($prevod->zapisPreklady() as $zapsany) {
                $this->line('  zapsáno '.$zapsany);
            }
        }

        foreach ($rucne as $n) {
            $this->line(sprintf('  ručně %s:%d  %s  [%s]', $n['soubor'], $n['radek'], mb_strimwidth($n['text'], 0, 80, '…'), $n['rucne']));
        }
        $this->info(($this->option('nanecisto') ? 'Převedlo by se ' : 'Převedeno ').$celkem.' textů, ručně zbývá '.count($rucne).'.');

        return self::SUCCESS;
    }
}
