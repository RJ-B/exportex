<?php

namespace App\Mail;

use App\Models\MailLog;
use App\Models\User;
use App\Support\Posta\PostaTransport;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Throwable;

/**
 * Obal nad skutečným Symfony transportem, který zaznamená každý odeslaný e-mail.
 *
 * Proč dekorátor a ne listener: Laravel dispatchuje `MessageSending`/`MessageSent`,
 * ale při selhání transportu už žádnou událost nevyhodí — a Symfony `FailedMessageEvent`
 * se nedispatchne, protože Laravel transportu žádný event dispatcher nepředává.
 * Odchycení kolem `send()` je jediné místo, které vidí úspěch i selhání, a to
 * pro všechny maily bez ohledu na to, kde vznikly.
 *
 * Log nikdy nesmí ovlivnit odesílání: chyby při zápisu se polykají, výjimka
 * z transportu se propaguje dál (volající na ní staví).
 */
class LoggingTransport implements TransportInterface
{
    /**
     * Vypne zápis do logu pro jedno odeslání.
     *
     * Pro odeslání, které se do logu psát nemá (zkouška spojení apod.).
     */
    public static bool $suppress = false;

    public function __construct(private TransportInterface $inner) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (self::$suppress) {
            return $this->inner->send($message, $envelope);
        }

        $log = $this->startLog($message, $envelope);

        try {
            $sent = $this->inner->send($message, $envelope);

            // Přes Poštu: předáno, výsledek (odesláno / nedoručeno) přijde později
            // webhookem nebo dotazem na stav – záznam nese id zprávy v Poště.
            $this->finish($log, fn (MailLog $l) => $this->inner instanceof PostaTransport && $sent
                ? PostaTransport::poOdeslani($l, $sent)
                : $l->update(['status' => MailLog::STATUS_SENT, 'sent_at' => now()]));

            return $sent;
        } catch (Throwable $e) {
            // Pošta zprávu odmítla (neplatná adresa…) – opakování nepomůže, obsah se
            // nedrží (osobní údaje); poslat znovu jde jen z aplikace.
            $this->finish($log, fn (MailLog $l) => $l->update([
                'status' => MailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]));

            throw $e;
        }
    }

    public function __toString(): string
    {
        return (string) $this->inner;
    }

    private function startLog(RawMessage $message, ?Envelope $envelope): ?MailLog
    {
        try {
            $prijemci = $this->prijemci($message, $envelope);
            $to = $prijemci[0] ?? ['email' => null, 'name' => null];

            return MailLog::create([
                'status' => MailLog::STATUS_SENDING,
                // `to_email` drží prvního — váže se na něj `user_id` a řadí se
                // podle něj přehled. Celý seznam je v `recipients`.
                'to_email' => $to['email'] ?? '(neznámý)',
                'to_name' => $to['name'] ?? null,
                'recipients' => array_values(array_filter(array_column($prijemci, 'email'))),
                'subject' => $message instanceof Email ? $message->getSubject() : null,
                'user_id' => $to['email'] ? $this->resolveUserId($to['email']) : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('MailLog: zápis se nezdařil', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function finish(?MailLog $log, callable $update): void
    {
        if (! $log) {
            return;
        }

        try {
            $update($log);
        } catch (Throwable $e) {
            Log::warning('MailLog: uzavření záznamu se nezdařilo', ['error' => $e->getMessage()]);
        }
    }

    /**
     * VŠICHNI příjemci, ne jen první.
     *
     * Bere se obálka, ne hlavičky zprávy: v obálce jsou i skryté kopie, a
     * právě ty by v logu chyběly nejvíc — u dokládání „komu to odešlo" je
     * skrytý příjemce ten, na kterého se člověk ptá.
     *
     * @return list<array{email: ?string, name: ?string}>
     */
    private function prijemci(RawMessage $message, ?Envelope $envelope): array
    {
        $addresses = [];

        if ($envelope) {
            $addresses = $envelope->getRecipients();
        } elseif ($message instanceof Email) {
            $addresses = array_merge($message->getTo(), $message->getCc(), $message->getBcc());
        }

        return array_map(fn ($a) => [
            'email' => $a->getAddress(),
            'name' => method_exists($a, 'getName') ? ($a->getName() ?: null) : null,
        ], array_values($addresses));
    }

    private function resolveUserId(string $email): ?int
    {
        try {
            return User::query()->where('email', $email)->value('id');
        } catch (Throwable) {
            return null;
        }
    }
}
