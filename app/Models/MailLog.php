<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Záznam o jednom odeslaném (nebo neodeslaném) e-mailu. Viz migrace create_mail_logs_table.
 */
class MailLog extends Model
{
    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** Předáno Poště (posta.simren.cz), výsledek přijde webhookem / dotazem na stav. */
    public const STATUS_QUEUED = 'queued';

    /** Pošta v testovacím režimu zprávu zadržela – žádný příjemce nebyl interní. Neopakuje se. */
    public const STATUS_HELD = 'held';

    protected $fillable = [
        'status', 'to_email', 'to_name', 'recipients', 'subject', 'mailable',
        'user_id', 'error', 'attempts', 'retried_by', 'sent_at', 'failed_at', 'raw_mime',
        'posta_id', 'posta_kontrola_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'posta_kontrola_at' => 'datetime',
            'recipients' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Komu to odešlo, ke čtení. U jednoho příjemce jeho adresa, u víc
     * „a@b.cz a 2 další" — celý seznam by v tabulce rozbil řádek.
     */
    public function prijemciPopis(): string
    {
        $seznam = $this->recipients ?: array_filter([$this->to_email]);
        $prvni = $seznam[0] ?? '(neznámý)';
        $dalsi = max(0, count($seznam) - 1);

        return $dalsi === 0
            ? $prvni
            : $prvni.' a '.$dalsi.' další';
    }

    /**
     * Kdo mail poslal znovu ručně. `null` = poslala ho automatika
     * (plánovač, Pošta) — v UI se to píše jako „automaticky".
     *
     * Bez cizího klíče: `users` bývá v ekosystému VIEW do sdílené identity
     * a MariaDB na VIEW klíč nepověsí (errno 150).
     */
    public function retriedBy()
    {
        return $this->belongsTo(User::class, 'retried_by');
    }

    /** Kdo odeslání způsobil, ke čtení. */
    public function odesilatelPopis(): string
    {
        return $this->retried_by ? ($this->retriedBy?->getFilamentName() ?? '#'.$this->retried_by) : 'automaticky';
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Jde poslat znovu? Jen selhané v Poště (nedoručené) – Pošta je pošle znovu
     * se stejným obsahem, dokud ho drží (90 dní). Zprávu, kterou Pošta vůbec
     * nepřijala (odmítnutá adresa), je potřeba poslat znovu z aplikace.
     */
    public function isRetryable(): bool
    {
        return $this->isFailed() && filled($this->posta_id);
    }

    /**
     * Odešlo se to až po opakování? (Napoprvé selhalo, teď je stav `sent`.)
     * `failed_at` se při úspěšném opakování schválně NEmaže — jinak by z výpisu
     * nešlo poznat, že s tímhle mailem byl problém, a ztratila by se stopa po
     * výpadku SMTP.
     */
    public function wasRetried(): bool
    {
        return $this->status === self::STATUS_SENT && $this->failed_at !== null;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->wasRetried() => 'Odesláno po chybě',
            $this->status === self::STATUS_SENT => 'Odesláno',
            $this->status === self::STATUS_FAILED => 'Selhalo',
            $this->status === self::STATUS_QUEUED => 'Ve frontě Pošty',
            $this->status === self::STATUS_HELD => 'Zadrženo (test)',
            default => 'Odesílá se',
        };
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_FAILED);
    }

    /**
     * Zůstalo viset ve stavu „odesílá se"? Znamená to, že proces umřel uprostřed
     * odesílání (fatal, timeout, OOM) — transport už se nedostal k zápisu výsledku.
     * Pro UI je to stejně podezřelé jako selhání.
     */
    public function scopeStuck(Builder $q, int $minutes = 10): Builder
    {
        return $q->where('status', self::STATUS_SENDING)
            ->where('created_at', '<', now()->subMinutes($minutes));
    }
}
