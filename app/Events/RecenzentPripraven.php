<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Účet pro kontrolu obchodu je připravený (App\Support\UcetRecenzenta) –
 * po každém spuštění `simren:recenzent`, nový i existující účet.
 *
 * Háček pro konkrétní aplikaci: posluchač v app/Listeners (Laravel ho najde
 * sám podle typu parametru) doplní data, na kterých jde aplikace vyzkoušet –
 * idempotentně, protože se volá při každém kliknutí na „Můj účet správce“.
 * Výjimka z posluchače účet nezaloží (vše běží v jedné transakci) a portál
 * ukáže její zprávu.
 */
final class RecenzentPripraven
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly bool $novy,
    ) {}
}
