<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Pojmenovaná skupina příjemců (Oznámení → Skupiny), třeba „Stálí zákazníci“. */
class OznameniSkupina extends Model
{
    protected $table = 'oznameni_skupiny';

    protected $fillable = ['nazev', 'popis'];

    public function clenove(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'oznameni_skupiny_clenove', 'skupina_id', 'user_id');
    }
}
