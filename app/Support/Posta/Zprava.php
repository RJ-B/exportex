<?php

namespace App\Support\Posta;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Převod e-mailu z Laravelu (Symfony Email) na zprávu pro API Pošty.
 *
 *  - příjemci vždy jako pole {adresa, jmeno} – nikdy víc adres v jednom řetězci,
 *  - skrytá kopie = Bcc + kdo je jen v obálce,
 *  - odesílatel: jen přidělená adresa; jiný From (výchozí z .env, adresa
 *    z kódu) se nahradí výchozím odesílatelem z Pošty, jméno zůstane,
 *  - hlavičky jen ty, které Pošta pustí (List-Unsubscribe…), štítek
 *    z ->tag() a metadata z ->metadata() Laravelu.
 */
class Zprava
{
    public const HLAVICKY = [
        'List-Unsubscribe', 'List-Unsubscribe-Post', 'List-Id', 'Precedence', 'Auto-Submitted',
        'X-Auto-Response-Suppress', 'In-Reply-To', 'References', 'X-Priority', 'Importance', 'X-Entity-Ref-ID',
    ];

    /** @return array<string, mixed> */
    public static function zEmailu(Email $email, ?Envelope $obalka = null): array
    {
        $komu = self::adresy($email->getTo());
        $kopie = self::adresy($email->getCc());
        $skryta = self::adresy($email->getBcc());

        // Kdo je jen v obálce (Symfony ho do hlaviček nedá) – skrytá kopie.
        if ($obalka) {
            $znami = array_map(fn ($a) => strtolower($a['adresa']), [...$komu, ...$kopie, ...$skryta]);
            foreach ($obalka->getRecipients() as $prijemce) {
                if (! in_array(strtolower($prijemce->getAddress()), $znami, true)) {
                    $skryta[] = ['adresa' => $prijemce->getAddress(), 'jmeno' => $prijemce->getName() ?: null];
                }
            }
        }

        $od = $email->getFrom()[0] ?? null;
        $povolene = array_map(fn ($a) => strtolower($a['adresa']), Propojeni::adresy());
        $odAdresa = $od && in_array(strtolower($od->getAddress()), $povolene, true) ? $od->getAddress() : (Propojeni::odesilatel()['adresa'] ?? $od?->getAddress());

        $hlavicky = [];
        $stitek = null;
        $metadata = [];

        foreach ($email->getHeaders()->all() as $hlavicka) {
            if ($hlavicka instanceof TagHeader) {
                $stitek ??= mb_substr($hlavicka->getValue(), 0, 64);
            } elseif ($hlavicka instanceof MetadataHeader) {
                $metadata[$hlavicka->getKey()] = $hlavicka->getValue();
            } elseif (in_array(strtolower($hlavicka->getName()), array_map('strtolower', self::HLAVICKY), true)) {
                $hlavicky[$hlavicka->getName()] = $hlavicka->getBodyAsString();
            }
        }

        $prilohy = array_map(fn (DataPart $p) => array_filter([
            'nazev' => $p->getFilename() ?: 'priloha',
            'typ' => $p->getContentType(),
            'obsah' => base64_encode($p->getBody()),
            'cid' => $p->getDisposition() === 'inline' && $p->hasContentId() ? $p->getContentId() : null,
        ], fn ($v) => $v !== null), $email->getAttachments());

        return array_filter([
            'od' => ['adresa' => $odAdresa, 'jmeno' => $od?->getName() ?: null],
            'komu' => $komu,
            'kopie' => $kopie,
            'skryta_kopie' => $skryta,
            'odpovedet_na' => self::adresy($email->getReplyTo()),
            'predmet' => (string) ($email->getSubject() ?: '(bez předmětu)'),
            'html' => self::telo($email->getHtmlBody()),
            'text' => self::telo($email->getTextBody()),
            'prilohy' => $prilohy,
            'hlavicky' => $hlavicky,
            'stitek' => $stitek,
            'metadata' => $metadata,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** @param  list<Address>  $adresy @return list<array{adresa: string, jmeno: ?string}> */
    private static function adresy(array $adresy): array
    {
        return array_map(fn (Address $a) => ['adresa' => $a->getAddress(), 'jmeno' => $a->getName() ?: null], $adresy);
    }

    private static function telo(mixed $telo): ?string
    {
        if (is_resource($telo)) {
            $telo = stream_get_contents($telo);
        }

        return is_string($telo) && $telo !== '' ? $telo : null;
    }
}
