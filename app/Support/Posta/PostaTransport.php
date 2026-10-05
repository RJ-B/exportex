<?php

namespace App\Support\Posta;

use App\Models\MailLog;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Mail transport `posta`: Mail::to()->send() beze změny v aplikaci, zprávu
 * ale neposílá SMTP, předá ji API Pošty (posta.simren.cz).
 *
 *  - Pošta přijala → SentMessage nese její id zprávy (Message-ID = „posta:<id>“),
 *    LoggingTransport ho zapíše k záznamu v Logy → E-maily a ten čeká na výsledek
 *    (webhook Pošty, záložně posta:fronta se zeptá),
 *  - Pošta nedostupná → zpráva do odchozí fronty (posta_odchozi), aplikace jede
 *    dál; posta:fronta ji předá se stejným Idempotency-Key, jakmile Pošta odpoví,
 *  - Pošta odmítla (neplatná adresa…) → TransportException jako u SMTP.
 */
class PostaTransport extends AbstractTransport
{
    public const PREDPONA_ID = 'posta:';

    public const PREDPONA_FRONTA = 'posta-fronta:';

    public function __construct(private Klient $klient)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $puvodni = $message->getOriginalMessage();

        if (! $puvodni instanceof Message) {
            throw new TransportException('Syrovou zprávu (RawMessage) přes Poštu poslat nejde – pošli ji znovu z aplikace.');
        }

        $email = $puvodni instanceof Email ? $puvodni : MessageConverter::toEmail($puvodni);
        $zprava = Zprava::zEmailu($email, $message->getEnvelope());
        $klic = (string) Str::uuid();

        try {
            $vysledek = $this->klient->posli($zprava, $klic);
            $message->setMessageId(self::PREDPONA_ID.$vysledek['id']);
        } catch (PostaOdmitla $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        } catch (PostaNedostupna $e) {
            $odchozi = Odchozi::create([
                'idempotency_klic' => $klic,
                'zprava' => $zprava,
                'pokusu' => 1,
                'dalsi_pokus_at' => now()->addMinutes((int) (config('posta.fronta.opakovani_minut')[0] ?? 1)),
                'chyba' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            $message->setMessageId(self::PREDPONA_FRONTA.$odchozi->id);
        }
    }

    /**
     * Po odeslání (LoggingTransport): k záznamu v logu e-mailů id zprávy v Poště,
     * nebo vazba na odchozí frontu. Stav „ve frontě Pošty“ – výsledek přijde později.
     */
    public static function poOdeslani(MailLog $log, SentMessage $odeslano): void
    {
        $id = $odeslano->getMessageId();

        if (str_starts_with($id, self::PREDPONA_ID)) {
            $log->update(['status' => MailLog::STATUS_QUEUED, 'posta_id' => substr($id, strlen(self::PREDPONA_ID))]);
        } elseif (str_starts_with($id, self::PREDPONA_FRONTA)) {
            Odchozi::query()->whereKey((int) substr($id, strlen(self::PREDPONA_FRONTA)))->update(['mail_log_id' => $log->id]);
            $log->update(['status' => MailLog::STATUS_QUEUED, 'error' => 'Čeká na Poštu (nedostupná) – předá se automaticky.']);
        }
    }

    public function __toString(): string
    {
        return 'posta://'.(parse_url(Propojeni::url(), PHP_URL_HOST) ?: 'posta');
    }
}
