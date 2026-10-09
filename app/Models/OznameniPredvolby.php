<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Předvolby uživatele: druh × kanál (co tu není, platí výchozí z DruhOznameni). */
class OznameniPredvolby extends Model
{
    protected $table = 'oznameni_predvolby';

    protected $fillable = ['user_id', 'kanaly'];

    protected function casts(): array
    {
        return ['kanaly' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
