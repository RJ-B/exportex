<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sekce webu se zapnula, vypnula nebo přeřadila (Obsah webu). Projekt s vlastní
 * správou menu webu na ni reaguje (simren.cz srovná MenuItem).
 */
class SekceWebuZmeneny
{
    use Dispatchable;
}
