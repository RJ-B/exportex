<?php

namespace App\Platby;

use RuntimeException;

/**
 * Platit teď nejde: chybí přístupové údaje, ostrý režim není ověřený, simulace
 * mimo vývoj. Selhává zavřeně – nikdy se kvůli tomu nepřepne jinam.
 */
class PlatbyNedostupne extends RuntimeException {}
