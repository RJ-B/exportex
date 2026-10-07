<?php

namespace App\Console\Commands;

use App\Support\ChybyAplikace;
use Illuminate\Console\Command;

/**
 * Chyby aplikace pro portál (`portal-deploy <projekt> chyby | chyba-vyresit | chyba-vratit`).
 *
 *   simren:chyby --json [--od=<unix čas>]      nevyřešené a změněné od času
 *   simren:chyby --vyresit=<otisk> --json      kdo a poznámka jako JSON na stdin
 *   simren:chyby --vratit=<otisk> --json
 *
 * Bez --json vypíše nevyřešené čitelně pro člověka na serveru.
 */
class Chyby extends Command
{
    protected $signature = 'simren:chyby
        {--json : Výstup pro portál}
        {--od= : Jen změněné od unixového času (a všechny nevyřešené)}
        {--vyresit= : Otisk chyby, kterou označit jako vyřešenou (kdo a poznámka na stdin)}
        {--vratit= : Otisk chyby, kterou vrátit mezi nevyřešené}';

    protected $description = 'Chyby aplikace podle otisku pro portál Sim&Ren – výpis, vyřešení, vrácení';

    public function handle(): int
    {
        if (filled($otisk = $this->option('vyresit'))) {
            $vstup = json_decode((string) stream_get_contents(STDIN, 8192), true);
            $vysledek = ChybyAplikace::vyresit((string) $otisk, $vstup['kdo'] ?? null, $vstup['poznamka'] ?? null);

            return $this->vystup($vysledek);
        }

        if (filled($otisk = $this->option('vratit'))) {
            return $this->vystup(ChybyAplikace::vratit((string) $otisk));
        }

        $od = $this->option('od');
        $vypis = ChybyAplikace::vypis(is_numeric($od) ? (int) $od : null);

        if ($this->option('json')) {
            return $this->vystup($vypis);
        }

        $nevyresene = collect($vypis['chyby'])->whereNull('vyreseno');

        if ($nevyresene->isEmpty()) {
            $this->info('Žádná nevyřešená chyba.');
        }

        foreach ($nevyresene as $c) {
            $this->line(sprintf('%5d×  %s  %s:%s  %s', $c['pocet'], class_basename((string) $c['vyjimka']), $c['soubor'], $c['radek'], mb_substr((string) $c['zprava'], 0, 100)));
        }

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $data */
    protected function vystup(array $data): int
    {
        $this->line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));

        return ($data['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
