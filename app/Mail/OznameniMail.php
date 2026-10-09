<?php

namespace App\Mail;

use App\Models\OznameniPrijemce;
use App\Support\ZakladniUdaje;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Oznámení e-mailem (přes Poštu). Odkaz vede přes aplikaci (proklik se zapíše
 * k příjemci, žádné měřicí pixely ani cizí služby). Novinky a nabídky mají
 * odhlášení jedním kliknutím – odkaz v patičce i hlavička List-Unsubscribe-Post
 * (Gmail, Apple Mail a další ukážou vlastní tlačítko Odhlásit).
 */
class OznameniMail extends Mailable
{
    public function __construct(public OznameniPrijemce $prijemce, public bool $zkouska = false) {}

    public function envelope(): Envelope
    {
        $oznameni = $this->prijemce->oznameni;

        return new Envelope(
            subject: ($this->zkouska ? '[Zkouška] ' : '').$oznameni->titulek,
            tags: ['oznameni', 'oznameni-'.$oznameni->druh->value],
            metadata: array_filter(['oznameni' => $oznameni->uuid]),
        );
    }

    public function headers(): Headers
    {
        $odhlaseni = $this->odkazOdhlaseni();

        return new Headers(text: $odhlaseni && ! $this->zkouska ? [
            'List-Unsubscribe' => '<'.$odhlaseni.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ] : []);
    }

    public function content(): Content
    {
        $oznameni = $this->prijemce->oznameni;

        return new Content(
            html: 'mail.oznameni',
            text: 'mail.oznameni-text',
            with: [
                'oznameni' => $oznameni,
                'nazevWebu' => ZakladniUdaje::get('nazev') ?: config('app.name'),
                'odkaz' => $this->prijemce->exists ? $this->prijemce->odkazProkliku() : $this->celyOdkaz($oznameni->odkaz),
                'odhlaseni' => $this->odkazOdhlaseni(),
                'predvolby' => Route::has('oznameni.stranka') ? route('oznameni.stranka').'#predvolby' : null,
                'zkouska' => $this->zkouska,
            ],
        );
    }

    /** Odhlášení jen u druhů se souhlasem (novinky, nabídky); provozní a servisní se neodhlašují. */
    private function odkazOdhlaseni(): ?string
    {
        $oznameni = $this->prijemce->oznameni;
        $user = $this->prijemce->user;

        if (! $oznameni->druh->vyzadujeSouhlas() || ! $user?->exists) {
            return null;
        }

        return URL::signedRoute('oznameni.odhlasit', ['user' => $user->getKey(), 'druh' => $oznameni->druh->value]);
    }

    private function celyOdkaz(?string $odkaz): ?string
    {
        return blank($odkaz) ? null : (str_starts_with($odkaz, '/') ? url($odkaz) : $odkaz);
    }
}
