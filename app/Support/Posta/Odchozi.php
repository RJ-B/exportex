<?php

namespace App\Support\Posta;

use App\Models\MailLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Odchozí fronta: zpráva, kterou Pošta zrovna nepřijala (nedostupná, token).
 * posta:fronta ji zkouší znovu se stejným Idempotency-Key (Pošta ji tak
 * nepošle dvakrát) a po úspěchu řádek smaže. Obsah (i přílohy v base64)
 * je tu jen do předání.
 */
class Odchozi extends Model
{
    protected $table = 'posta_odchozi';

    protected $fillable = ['idempotency_klic', 'zprava', 'mail_log_id', 'pokusu', 'dalsi_pokus_at', 'chyba'];

    protected function casts(): array
    {
        return [
            'zprava' => 'encrypted:array',
            'dalsi_pokus_at' => 'datetime',
        ];
    }

    public function mailLog(): BelongsTo
    {
        return $this->belongsTo(MailLog::class);
    }
}
