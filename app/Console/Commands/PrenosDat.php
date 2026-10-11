<?php

namespace App\Console\Commands;

use App\Support\Prenos\Casti;
use App\Support\Prenos\PoImportu;
use App\Support\Prenos\Prenos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Přenos vybraných částí dat mezi weby – spouští ho jen shim portálu
 * (`portal-deploy <projekt> data-casti | data-export-casti | data-nahled |
 * data-import-casti`), člověk na serveru ne. Výstup je jeden řádek JSON.
 *
 *   casti               co tahle aplikace umí přenést (pro okno v portálu)
 *   export <id>         všechny části do storage/framework/prenos/<id>
 *   import <id> --volby=cast:rezim,… [--nanecisto]
 *   uklid <id>          smaže adresář přenosu
 *   po-importu          po importu CELÉ databáze z jiného webu (shim data-import):
 *                       zapomene napojení zdroje (Pošta – portál propojí znovu)
 *                       a uklidí, co patří jen zdroji (PoImportu)
 */
class PrenosDat extends Command
{
    protected $signature = 'simren:prenos {akce : casti|export|import|uklid|po-importu} {id? : identifikátor přenosu (p + 32 hex)}
        {--volby= : cast:rezim,… (import)}
        {--nanecisto : import jen spočítá, nic nezmění}';

    protected $description = 'Přenos vybraných částí dat mezi weby (portál → Přenést data z testu)';

    public function handle(): int
    {
        try {
            $vysledek = match ($this->argument('akce')) {
                'casti' => ['casti' => Casti::prehled()],
                'export' => app(Prenos::class)->export(Prenos::adresar((string) $this->argument('id'))),
                'import' => app(Prenos::class)->import(
                    Prenos::adresar((string) $this->argument('id')),
                    Casti::volby((string) $this->option('volby')),
                    (bool) $this->option('nanecisto'),
                ),
                'uklid' => ['smazano' => File::deleteDirectory(Prenos::adresar((string) $this->argument('id')))],
                'po-importu' => app(PoImportu::class)->proved(),
                default => throw new \InvalidArgumentException('Neznámá akce.'),
            };
        } catch (Throwable $e) {
            $this->line(json_encode(['ok' => false, 'chyba' => $e->getMessage()], JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        $this->line(json_encode(['ok' => true] + $vysledek, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
