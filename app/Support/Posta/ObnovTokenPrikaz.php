<?php

namespace App\Support\Posta;

use Illuminate\Console\Command;

/** posta:obnov-token (denně): token Pošty se obnoví 30 dní před vypršením. */
class ObnovTokenPrikaz extends Command
{
    protected $signature = 'posta:obnov-token {--vzdy : Obnovit hned}';

    protected $description = 'Obnoví token Pošty, když brzy vyprší';

    public function handle(Propojeni $propojeni): int
    {
        $this->line($propojeni->obnovToken((bool) $this->option('vzdy')) ? 'Token obnoven.' : 'Obnova není potřeba.');

        return self::SUCCESS;
    }
}
