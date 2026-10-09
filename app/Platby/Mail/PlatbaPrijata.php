<?php

namespace App\Platby\Mail;

use App\Platby\Platba;
use App\Support\ZakladniUdaje;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Potvrzení zákazníkovi, že platba přišla (přes Poštu jako každý mail aplikace). */
class PlatbaPrijata extends Mailable
{
    public function __construct(public Platba $platba) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->platba->testovaci() ? '[TEST] ' : '').'Platba přijata – '.$this->platba->popis.' – '.ZakladniUdaje::get('nazev'),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.platby.prijata');
    }
}
