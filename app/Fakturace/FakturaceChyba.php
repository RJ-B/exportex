<?php

namespace App\Fakturace;

use RuntimeException;

/** Fakturace odmítla požadavek nebo neodpovídá – zpráva je pro člověka. */
class FakturaceChyba extends RuntimeException {}
