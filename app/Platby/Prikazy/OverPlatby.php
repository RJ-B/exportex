<?php

namespace App\Platby\Prikazy;

use App\Platby\Platby;
use Illuminate\Console\Command;

/** Pojistka za webhook: zeptá se brány na čekající platby (plánovač to dělá sám, jen když nějaké jsou). */
class OverPlatby extends Command
{
    protected $signature = 'platby:over';

    protected $description = 'Ověří u brány stav čekajících plateb';

    public function handle(Platby $platby): int
    {
        $this->info('Ověřeno plateb: '.$platby->overCekajici());

        return self::SUCCESS;
    }
}
