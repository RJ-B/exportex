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

    protected $fillable = [
        'status', 'to_email', 'to_name', 'recipients', 'subject', 'mailable',
        'user_id', 'error', 'attempts', 'retried_by', 'sent_at', 'failed_at', 'raw_mime',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
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
     * (`mail:retry-failed`) — v UI se to píše jako „automaticky".
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

    /** Jde poslat znovu? Jen selhané, u kterých máme uložený syrový mail. */
    public function isRetryable(): bool
    {
        return $this->isFailed() && ! empty($this->raw_mime);
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
