<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Záznam o udělení nebo odvolání souhlasu s novinkami / nabídkami (GDPR –
 * kdo, kdy, s jakým zněním, odkud). Jen se přidává, nic se nepřepisuje;
 * platí poslední záznam (a ten je i v oznameni_predvolby).
 */
class OznameniSouhlas extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'oznameni_souhlasy';

    protected $fillable = ['user_id', 'druh', 'kanal', 'udelen', 'zdroj', 'text', 'ip_adresa', 'prohlizec'];

    protected function casts(): array
    {
        return ['udelen' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
