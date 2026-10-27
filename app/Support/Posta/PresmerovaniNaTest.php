<?php

namespace App\Support\Posta;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\RawMessage;

/**
 * Transport testovacího webu (TestovaciSchranka): každý e-mail jen na testovací
 * adresu, od testovací adresy. Kopie, skryté kopie a Reply-To pryč (odpověď
 * testera nesmí dojít skutečnému člověku). Komu by šel: předmět
 * „[TEST → …]“ a hlavičky X-Simren-Puvodni-*.
 *
 * Obaluje LoggingTransport, takže Logy → E-maily ukazují, co doopravdy odešlo.
 */
class PresmerovaniNaTest implements TransportInterface
{
    public const HLAVICKA_PRIJEMCI = 'X-Simren-Puvodni-Prijemci';

    public const HLAVICKA_ODESILATEL = 'X-Simren-Puvodni-Odesilatel';

    public const HLAVICKA_ODPOVED = 'X-Simren-Puvodni-Reply-To';

    public function __construct(private TransportInterface $inner, private string $adresa, private string $jmeno) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        // Syrovou zprávu bez hlaviček přesměrovat nejde – radši neposlat nic.
        if (! $message instanceof Message) {
            throw new TransportException(__('posta.test_schranka.syrovou_zpravu_nejde_presmerovat'));
        }

        $email = $message instanceof Email ? $message : MessageConverter::toEmail($message);
        $hlavicky = $email->getHeaders();

        $prijemci = $envelope?->getRecipients() ?: [...$email->getTo(), ...$email->getCc(), ...$email->getBcc()];
        $prijemci = array_values(array_unique(array_map(fn (Address $a) => strtolower($a->getAddress()), $prijemci)));

        $hlavicky->remove(self::HLAVICKA_PRIJEMCI);
        $hlavicky->addTextHeader(self::HLAVICKA_PRIJEMCI, implode(', ', $prijemci));
        $hlavicky->remove(self::HLAVICKA_ODESILATEL);
        $hlavicky->addTextHeader(self::HLAVICKA_ODESILATEL, implode(', ', array_map(fn (Address $a) => $a->getAddress(), $email->getFrom())));
        $hlavicky->remove(self::HLAVICKA_ODPOVED);

        if ($email->getReplyTo() !== []) {
            $hlavicky->addTextHeader(self::HLAVICKA_ODPOVED, implode(', ', array_map(fn (Address $a) => $a->getAddress(), $email->getReplyTo())));
        }

        foreach (['Cc', 'Bcc', 'Reply-To', 'Sender', 'Return-Path'] as $hlavicka) {
            $hlavicky->remove($hlavicka);
        }

        $testovaci = new Address($this->adresa, $this->jmeno);
        $email->from($testovaci)->to(new Address($this->adresa));
        $email->subject(__('posta.test_schranka.predmet', [
            'prijemci' => $prijemci === [] ? '?' : implode(', ', $prijemci),
            'predmet' => (string) $email->getSubject(),
        ]));

        return $this->inner->send($email, new Envelope($testovaci, [new Address($this->adresa)]));
    }

    public function __toString(): string
    {
        return 'test:'.$this->adresa.'+'.$this->inner;
    }
}
