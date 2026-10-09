<?php

namespace App\Platby;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historie platby: každá změna stavu i každý dotaz, webhook a návrat, který
 * nic nezměnil. Jen se přidává, nikdy se nemění ani nemaže.
 */
class PlatbaUdalost extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'platby_udalosti';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stav_z' => StavPlatby::class,
            'stav_na' => StavPlatby::class,
            'data' => 'array',
        ];
    }

    public function platba(): BelongsTo
    {
        return $this->belongsTo(Platba::class);
    }

    public function uzivatel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function popisZdroje(): string
    {
        return match ($this->zdroj) {
            'zalozeni' => 'Založení',
            'navrat' => 'Návrat zákazníka',
            'webhook' => 'Oznámení brány',
            'overeni' => 'Ověření stavu',
            'planovac' => 'Kontrola plánovačem',
            'administrace' => 'Administrace',
            'simulace' => 'Simulace',
            default => (string) $this->zdroj,
        };
    }
}
