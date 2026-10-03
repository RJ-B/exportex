<?php

namespace App\Mail;

use App\Models\Zprava;
use App\Support\ZakladniUdaje;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Upozornění na novou poptávku z formuláře – odpověď jde rovnou odesílateli.
 * Exportex: v předmětu i firma, v textu jazyk webu (anglická poptávka = odpovědět anglicky).
 */
class NovaZprava extends Mailable
{
    public function __construct(public Zprava $zprava) {}

    public function envelope(): Envelope
    {
        $od = collect([$this->zprava->celeJmeno(), $this->zprava->firma])->filter()->join(', ');

        return new Envelope(
            replyTo: [new Address($this->zprava->email, $this->zprava->celeJmeno())],
            subject: 'Nová poptávka z webu '.ZakladniUdaje::get('nazev').': '.$od.($this->zprava->jazyk === 'en' ? ' [EN]' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.nova-zprava');
    }
}
