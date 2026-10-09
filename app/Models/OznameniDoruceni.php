<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Doručení jednomu příjemci jedním kanálem (e-mail, později push).
 * Stav: ceka → odeslano | preskoceno (proč) | chyba (proč). U e-mailu je
 * výsledek doručení z Pošty v Logy → E-maily (mail_log_id).
 */
class OznameniDoruceni extends Model
{
    public const CEKA = 'ceka';

    public const ODESLANO = 'odeslano';

    public const PRESKOCENO = 'preskoceno';

    public const CHYBA = 'chyba';

    protected $table = 'oznameni_doruceni';

    protected $fillable = ['prijemce_id', 'kanal', 'stav', 'duvod', 'mail_log_id', 'odeslano_at'];

    protected function casts(): array
    {
        return ['odeslano_at' => 'datetime'];
    }

    public function prijemce(): BelongsTo
    {
        return $this->belongsTo(OznameniPrijemce::class, 'prijemce_id');
    }

    public function mailLog(): BelongsTo
    {
        return $this->belongsTo(MailLog::class);
    }
}
