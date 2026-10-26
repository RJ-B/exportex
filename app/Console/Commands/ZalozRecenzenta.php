<?php

namespace App\Console\Commands;

use App\Support\UcetRecenzenta;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Throwable;

/**
 * Účet pro kontrolu obchodu (App Store, Google Play) – viz App\Support\UcetRecenzenta.
 *
 * Portál ho spouští přes shim (`portal-deploy <recept> recenzent`) s JSON
 * {email, heslo, jmeno, prijmeni} na standardním vstupu: heslo tak nikdy není
 * v argumentech procesu ani v logu. Výstup je jeden řádek JSON bez hesla.
 */
class ZalozRecenzenta extends Command
{
    protected $signature = 'simren:recenzent {--json : Výstup pro portál}';

    protected $description = 'Založí nebo srovná účet pro kontrolu obchodu (údaje jako JSON na standardním vstupu)';

    public function handle(): int
    {
        try {
            $proud = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
            $data = json_decode((string) stream_get_contents($proud ?? STDIN, 16384), true);
            $vysledek = UcetRecenzenta::zajisti(is_array($data) ? $data : []);
        } catch (Throwable $e) {
            $this->line(json_encode(['ok' => false, 'chyba' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $this->line($this->option('json')
            ? json_encode($vysledek, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : 'Účet pro kontrolu '.$vysledek['email'].($vysledek['created'] ? ' založen.' : ($vysledek['heslo_zmeneno'] ? ' – heslo přenastaveno.' : ' je v pořádku.')));

        return self::SUCCESS;
    }
}
