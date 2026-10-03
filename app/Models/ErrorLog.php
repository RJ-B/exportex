<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Agregovaný záznam chyby. Viz migrace create_error_logs_table.
 */
class ErrorLog extends Model
{
    protected $fillable = [
        'fingerprint', 'level', 'exception', 'message', 'file', 'line', 'trace',
        'url', 'method', 'user_id', 'ip', 'occurrences', 'first_seen_at', 'last_seen_at',
        'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Kdo chybu odbavil. Bere se přihlášený účet v okamžiku kliknutí — „kdo to
     * zavřel" je u chyby první otázka, když se vrátí.
     *
     * Bez cizího klíče, `users` bývá na produkci VIEW (errno 150).
     */
    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function shortException(): string
    {
        return class_basename($this->exception);
    }

    /** Zkrácená cesta k souboru – plná je od `/home/…/web/…` a v tabulce k ničemu. */
    public function shortFile(): ?string
    {
        if (! $this->file) {
            return null;
        }

        return str_replace(base_path().'/', '', $this->file);
    }

    public function scopeUnresolved(Builder $q): Builder
    {
        return $q->whereNull('resolved_at');
    }
}
