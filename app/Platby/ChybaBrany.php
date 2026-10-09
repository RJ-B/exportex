<?php

namespace App\Platby;

use RuntimeException;

/** Brána odmítla požadavek nebo neodpověděla. Zpráva je pro člověka (administrace, Chyby). */
class ChybaBrany extends RuntimeException {}
