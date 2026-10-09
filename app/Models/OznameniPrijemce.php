<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/**
 * Komu oznámení přišlo. Jeden řádek na člověka (i když ho cílení zasáhlo
 * víckrát – rolí, skupinou i výběrem). Nese stav v centru: přečteno,
 * prokliknuto, archivováno – stejný pro web, administraci i mobil.
 */
class OznameniPrijemce extends Model
{
    protected $table = 'oznameni_prijemci';

    protected $fillable = ['oznameni_id', 'user_id', 'v_centru'];

    protected function casts(): array
    {
        return [
            'v_centru' => 'boolean',
            'precteno_at' => 'datetime',
            'prokliknuto_at' => 'datetime',
            'archivovano_at' => 'datetime',
        ];
    }

    public function oznameni(): BelongsTo
    {
        return $this->belongsTo(Oznameni::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doruceni(): HasMany
    {
        return $this->hasMany(OznameniDoruceni::class, 'prijemce_id');
    }

    /** Co uživatel vidí ve svém centru (odeslané, ne archivované). */
    public function scopeVCentru(Builder $query, User|int $user): Builder
    {
        return $query
            ->where('user_id', $user instanceof User ? $user->getKey() : $user)
            ->where('v_centru', true)
            ->whereHas('oznameni', fn (Builder $query) => $query->where('stav', 'odeslano'));
    }

    public function scopeNeprectene(Builder $query): Builder
    {
        return $query->whereNull('precteno_at')->whereNull('archivovano_at');
    }

    public function oznacPrectene(): void
    {
        if (! $this->precteno_at) {
            $this->forceFill(['precteno_at' => now()])->save();
        }
    }

    public function oznacProkliknute(): void
    {
        $this->forceFill([
            'precteno_at' => $this->precteno_at ?? now(),
            'prokliknuto_at' => $this->prokliknuto_at ?? now(),
        ])->save();
    }

    /**
     * Odkaz na oznámení přes aplikaci (podepsaný – funguje i z e-mailu bez
     * přihlášení): zapíše přečteno a proklik a pošle dál. Jen na naši doménu,
     * žádné měření třetí stranou.
     */
    public function odkazProkliku(): ?string
    {
        if (blank($this->oznameni?->odkaz)) {
            return null;
        }

        return URL::signedRoute('oznameni.proklik', ['prijemce' => $this->getKey()]);
    }
}
