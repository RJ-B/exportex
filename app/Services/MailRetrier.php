<?php

namespace App\Services;

use App\Models\MailLog;
use App\Support\Posta\Klient;
use App\Support\Posta\Webhook;
use Throwable;

/**
 * „Poslat znovu“ v Logy → E-maily: nedoručenou zprávu zařadí znovu Pošta
 * (posta.simren.cz) – se stejným obsahem, jaký tehdy neodešel (Pošta ho
 * drží 90 dní). Opakování a výsledek řeší Pošta; záznam zůstane „ve frontě
 * Pošty“, dokud nepřijde výsledek.
 */
class MailRetrier
{
    /** @return array{ok: bool, message: string} */
    public function retry(MailLog $log): array
    {
        if (! $log->isRetryable()) {
            return ['ok' => false, 'message' => 'Tenhle e-mail Pošta neodeslala – pošli ho znovu z aplikace.'];
        }

        try {
            $zprava = app(Klient::class)->znovu($log->posta_id);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Pošta zprávu znovu nezařadila: '.mb_substr($e->getMessage(), 0, 200)];
        }

        // `error` ani `failed_at` se NEmažou: záznam má zůstat čitelný jako
        // „napoprvé selhalo, doručeno až na další pokus“.
        $log->update([
            'status' => MailLog::STATUS_QUEUED,
            'attempts' => $log->attempts + 1,
            // Kdo to spustil: přihlášený = člověk, null = automatika.
            'retried_by' => auth()->id(),
        ]);
        Webhook::aktualizuj($zprava);

        return ['ok' => true, 'message' => 'Pošta zprávu zařadila znovu – výsledek uvidíš tady.'];
    }
}
