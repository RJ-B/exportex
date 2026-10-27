<?php

namespace App\Mail;

use App\Support\Posta\TestovaciSchranka;
use Illuminate\Mail\MailManager;

/**
 * MailManager, který každý postavený transport obalí do LoggingTransportu.
 *
 * Dědí se schválně místo ručního sestavování transportu: Laravel si dál řeší
 * scheme, timeout, local_domain i přihlašovací údaje sám, my jen obalíme výsledek.
 * Platí to pro všechny drivery — i pro `log` v lokálu, takže se log mailů dá
 * vyzkoušet bez skutečného odesílání.
 *
 * Na testu navrch přesměrování na test@simren.cz (TestovaciSchranka) – log pak
 * ukazuje, co doopravdy odešlo.
 */
class LoggingMailManager extends MailManager
{
    public function createSymfonyTransport(array $config)
    {
        return TestovaciSchranka::obal(new LoggingTransport(parent::createSymfonyTransport($config)));
    }
}
