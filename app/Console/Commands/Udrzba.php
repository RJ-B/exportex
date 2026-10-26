<?php

namespace App\Console\Commands;

use App\Support\UdrzbaWebu;
use Illuminate\Console\Command;
use Throwable;

/**
 * Stav webu „Údržba“ / „Připravujeme“ pro portál (`portal-deploy <projekt> udrzba
 * stav-webu-…` a `<projekt> stav-webu`). artisan down / up je jiná údržba – ta
 * zůstává `artisan down` a `artisan up`.
 *
 * `--vstup`: JSON na standardním vstupu {text, konec} – text pro návštěvníky
 * a předpokládaný konec údržby (ISO 8601) z CRM. Přes argumenty nejde: shim
 * dělí příkaz na slova a text s mezerami by rozbil.
 */
class Udrzba extends Command
{
    protected $signature = 'simren:udrzba {akce=stav : stav, zapnout, vypnout, pripravujeme nebo spustit} {--json : Výstup pro portál} {--vstup : Text a konec údržby jako JSON na standardním vstupu}';

    protected $description = 'Stav webu „Údržba“ / „Připravujeme“ – zjistit, zapnout, vypnout, spustit (pro portál Sim&Ren)';

    public function handle(): int
    {
        try {
            $vstup = null;

            if ($this->option('vstup')) {
                $data = json_decode((string) stream_get_contents(STDIN, 16384), true);
                $vstup = is_array($data) ? array_intersect_key($data, ['text' => true, 'konec' => true]) : [];
            }

            $vysledek = UdrzbaWebu::proved((string) $this->argument('akce'), $vstup);
        } catch (Throwable $e) {
            if ($this->option('json')) {
                $this->line(json_encode(['ok' => false, 'chyba' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error($e->getMessage());
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($vysledek, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Stav webu: '.($vysledek['stav_webu']['nazev'] ?? '–').($vysledek['zmeneno'] ? ' (změněno)' : ' (beze změny)'));

        return self::SUCCESS;
    }
}
