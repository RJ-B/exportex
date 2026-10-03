<?php

namespace App\Console\Commands;

use App\Support\Zdravi as Kontrola;
use Illuminate\Console\Command;

/**
 * Diagnostika pro portál (`portal-deploy <projekt> diagnostika`).
 * Bez --json vypíše totéž čitelně pro člověka na serveru.
 */
class Zdravi extends Command
{
    protected $signature = 'simren:zdravi {--json : Výstup pro portál}';

    protected $description = 'Zdraví aplikace – databáze, cache, storage, plánovač, fronta, chyby v logu';

    public function handle(): int
    {
        $d = Kontrola::diagnostika();

        if ($this->option('json')) {
            $this->line(json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $d['ok'] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($d['tvrde'] as $co => $k) {
            $this->line(sprintf('%-9s %s  %s', $co, $k['ok'] ? 'OK   ' : 'CHYBA', $k['zprava']));
        }

        $this->line('plánovač  '.($d['planovac']['naposledy'] ?? 'nikdy'));
        $this->line('fronta    čeká '.($d['fronta']['cekajici'] ?? '–').', selhalo '.($d['fronta']['selhane'] ?? '–'));
        $this->line('chyby     '.$d['chyby']['za_hodinu'].' za hodinu'.($d['chyby']['posledni'] ? ' – '.$d['chyby']['posledni']['zprava'] : ''));
        $this->line('disk      volno '.($d['disk']['volne_procent'] ?? '–').' %');

        return $d['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
