<?php

namespace App\Support\Posta;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;

/**
 * Účel zprávy pro Poštu (formuláře, účty, doklady…): podle něj Pošta vybere
 * schránku firmy (víc schránek nastavuje Sim&Ren v portálu / Poště). Mailable nebo notifikace dostane účel
 * z config/posta.php (`ucely`: třída → účel), obnova hesla a ověření e-mailu
 * vždy „ucty“. Do Pošty jde jako pole `ucel` (Zprava), ne jako hlavička.
 *
 * Vlastní: `->withSymfonyMessage(fn ($m) => UcelZpravy::nastav($m, 'objednavky'))`.
 */
class UcelZpravy
{
    public const HLAVICKA = 'X-Posta-Ucel';

    /** Účely, které Pošta zná (číselník v Poště; neznámý by odmítla). */
    public const UCELY = ['obecne', 'formulare', 'ucty', 'doklady', 'objednavky', 'hromadne'];

    public static function nastav(Email $zprava, string $ucel): void
    {
        if (in_array($ucel, self::UCELY, true) && ! $zprava->getHeaders()->has(self::HLAVICKA)) {
            $zprava->getHeaders()->addTextHeader(self::HLAVICKA, $ucel);
        }
    }

    /** Účel ze zprávy (null = bez účelu – Pošta pošle jako dřív). */
    public static function zeZpravy(Email $zprava): ?string
    {
        $ucel = $zprava->getHeaders()->get(self::HLAVICKA)?->getBodyAsString();

        return in_array($ucel, self::UCELY, true) ? $ucel : null;
    }

    /** MessageSending: účel podle třídy mailable / notifikace (PostaServiceProvider). */
    public function handle(MessageSending $udalost): void
    {
        $trida = (string) ($udalost->data['__laravel_mailable'] ?? $udalost->data['__laravel_notification'] ?? '');

        if ($trida === '' || ! $udalost->message instanceof Email) {
            return;
        }

        // config('posta.ucely.Trida') nejde – tečka by byla cesta; mapa se čte celá.
        $ucel = ((array) config('posta.ucely'))[$trida]
            ?? (preg_match('/ResetPassword|VerifyEmail|Pozvank|Invit/i', class_basename($trida)) ? 'ucty' : null);

        if (is_string($ucel)) {
            self::nastav($udalost->message, $ucel);
        }
    }
}
