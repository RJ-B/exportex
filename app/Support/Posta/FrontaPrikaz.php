<?php

namespace App\Support\Posta;

use Illuminate\Console\Command;

/**
 * posta:fronta – ruční spuštění toho, co plánovač dělá sám uvnitř
 * schedule:run (OdchoziFronta, jen když je co dělat): předá Poště zprávy
 * z odchozí fronty a zjistí výsledek zpráv, ke kterým nepřišel webhook.
 */
class FrontaPrikaz extends Command
{
    protected $signature = 'posta:fronta {--limit=100}';

    protected $description = 'Předá Poště zprávy z odchozí fronty a zjistí výsledek zpráv bez webhooku';

    public function handle(OdchoziFronta $fronta): int
    {
        if (! Propojeni::propojeno()) {
            $this->line('Aplikace není propojená s Poštou.');

            return self::SUCCESS;
        }

        $vysledek = $fronta->zpracuj((int) $this->option('limit'));
        $this->line("Předáno z fronty: {$vysledek['predano']}, zjištěn stav: {$vysledek['zjisteno']}.");

        return self::SUCCESS;
    }
}
