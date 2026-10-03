<?php

namespace App\Console\Commands;

use App\Support\Posta;
use Illuminate\Console\Command;

/** Ověří přihlášení k nastavené schránce (Administrace → Pošta); výsledek čte /zdravi. */
class KontrolaPosty extends Command
{
    protected $signature = 'posta:kontrola';

    protected $description = 'Ověří přihlášení ke schránce, ze které aplikace posílá poštu';

    public function handle(): int
    {
        $vysledek = Posta::zkontroluj();

        if (! $vysledek['nastavena']) {
            $this->line('Schránka není nastavená – platí .env.');

            return self::SUCCESS;
        }

        $vysledek['ok'] ? $this->info($vysledek['zprava']) : $this->error($vysledek['zprava']);

        return $vysledek['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
