<?php

namespace App\Support\Posta;

use RuntimeException;

/** Pošta zprávu odmítla (4xx) – je špatně, opakování nepomůže. */
class PostaOdmitla extends RuntimeException {}
