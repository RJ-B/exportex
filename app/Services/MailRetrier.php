<?php

namespace App\Services;

use App\Mail\LoggingTransport;
use App\Models\MailLog;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Znovuodeslání selhaného mailu ze syrového MIME.
 *
 * Posílá se přesně ta zpráva, která tehdy neodešla — ne nově vyrenderovaná.
 * Kdyby se mezitím změnila data nebo šablona, příjemce by dostal něco jiného,
 * než co mu tehdy mělo přijít.
 */
class MailRetrier
{
    /** @return array{ok: bool, message: string} */
    public function retry(MailLog $log): array
    {
        if (! $log->isRetryable()) {
            return ['ok' => false, 'message' => 'Tenhle e-mail znovu odeslat nelze — chybí uložený obsah.'];
        }

        try {
            // Log se pro tohle odeslání vypne — aktualizujeme původní záznam níž,
            // jinak by transport založil druhý (a prázdný, viz LoggingTransport::$suppress).
            LoggingTransport::$suppress = true;

            // Posílá se napřímo transportem, ne přes Mail::to()->send() — máme hotový
            // MIME včetně hlaviček, takže není co skládat.
            //
            // Obálka se MUSÍ předat explicitně: RawMessage nemá hlavičky, ze kterých
            // by si ji Symfony odvodilo, a bez ní vyhodí „Cannot send a RawMessage
            // instance without an explicit Envelope". Odesílatel se bere z aktuální
            // konfigurace (SMTP účet smí posílat jen sám za sebe), příjemce ze záznamu.
            //
            // POZOR: obálka nese JEN `to_email`. Dnes to sedí — žádný mail v aplikaci
            // nepoužívá cc/bcc a notifikace se posílají každému příjemci zvlášť, takže
            // každý má vlastní záznam. Kdyby někdy přibylo `->cc()`/`->bcc()` nebo mail
            // s víc adresáty, opakované odeslání by je potichu zahodilo a došlo by jen
            // prvnímu — pak je potřeba ukládat všechny příjemce, ne jen prvního.
            Mail::getSymfonyTransport()->send(
                new RawMessage($log->raw_mime),
                new Envelope(
                    new Address(config('mail.from.address')),
                    array_map(fn (string $a) => new Address($a), $log->recipients ?: [$log->to_email]),
                ),
            );

            // `error` ani `failed_at` se NEmažou: záznam má zůstat čitelný jako
            // „napoprvé selhalo, doručeno až na druhý pokus“. Kdybychom je vynulovali,
            // vypadal by řádek jako bezproblémové odeslání a stopa po výpadku SMTP
            // by z přehledu zmizela. Tlačítko „Odeslat znovu“ zmizí samo — stav je
            // `sent`, takže `isRetryable()` je false (navíc mažeme `raw_mime`).
            $log->update([
                'status' => MailLog::STATUS_SENT,
                'sent_at' => now(),
                'attempts' => $log->attempts + 1,
                // Kdo odeslání spustil. Přihlášený účet = klikl na to člověk;
                // `null` (plánovač běží bez přihlášení) = poslala automatika.
                // V přehledu se pak pozná „odesláno po chybě, ručně, Rosťa"
                // od „odesláno po chybě, automaticky".
                'retried_by' => auth()->id(),
                'raw_mime' => null, // po úspěchu tělo nedržíme (osobní údaje)
            ]);

            return ['ok' => true, 'message' => 'E-mail byl odeslán na '.$log->prijemciPopis().'.'];
        } catch (\Throwable $e) {
            $log->update([
                'attempts' => $log->attempts + 1,
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'failed_at' => now(),
            ]);

            return ['ok' => false, 'message' => 'Odeslání znovu selhalo: '.mb_substr($e->getMessage(), 0, 200)];
        } finally {
            // MUSÍ být ve finally: kdyby odeslání spadlo, zůstalo by suppress zapnuté
            // a v tomhle procesu by se přestaly logovat úplně všechny další maily.
            LoggingTransport::$suppress = false;
        }
    }
}
