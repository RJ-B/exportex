<?php

namespace App\Support\Posta;

use RuntimeException;

/** Pošta teď zprávu nepřijala (spojení, 5xx, 429, token) – jde do odchozí fronty. */
class PostaNedostupna extends RuntimeException {}
