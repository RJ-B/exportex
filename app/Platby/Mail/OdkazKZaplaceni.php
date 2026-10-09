<?php

namespace App\Platby\Mail;

use App\Platby\Platba;
use App\Support\ZakladniUdaje;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Odkaz k zaplacení (Platby → Nová platba v administraci). */
class OdkazKZaplaceni extends Mailable
{
    public function __construct(public Platba $platba) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->platba->testovaci() ? '[TEST] ' : '').'Platba: '.$this->platba->popis.' – '.ZakladniUdaje::get('nazev'),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.platby.odkaz');
    }
}
