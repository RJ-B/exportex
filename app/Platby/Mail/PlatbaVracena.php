<?php

namespace App\Platby\Mail;

use App\Platby\Platba;
use App\Support\ZakladniUdaje;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Zákazníkovi: peníze se vrací (celé nebo část). */
class PlatbaVracena extends Mailable
{
    /** @param int $castka vrácená částka v haléřích */
    public function __construct(public Platba $platba, public int $castka) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->platba->testovaci() ? '[TEST] ' : '').'Vrácení platby – '.$this->platba->popis.' – '.ZakladniUdaje::get('nazev'),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.platby.vracena');
    }
}
