<?php

namespace App\Bezpecnost;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Jednorázový odkaz na spárování telefonu (QR kód v Zabezpečení).
 *
 * V databázi je jen otisk tokenu – odkaz existuje jen na obrazovce toho,
 * kdo ho vydal (s heslem znovu). Odkaz nikoho nepřihlásí: umí jen připojit
 * passkey telefonu k účtu, který ho vydal. Platí krátce a jen jednou.
 */
class ParovaniZarizeni extends Model
{
    public const PLATNOST_MINUT = 15;

    protected $table = 'parovani_zarizeni';

    protected $fillable = ['user_id', 'token_otisk', 'plati_do', 'pouzito_v', 'zruseno_v'];

    protected function casts(): array
    {
        return [
            'plati_do' => 'datetime',
            'pouzito_v' => 'datetime',
            'zruseno_v' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function otisk(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Vydá nový odkaz; starší nepoužité téhož účtu zruší. Vrací token (neukládá se). */
    public static function vydej(User $user): string
    {
        static::query()->platne()->where('user_id', $user->getKey())->update(['zruseno_v' => now()]);

        $token = Str::random(48);

        static::create([
            'user_id' => $user->getKey(),
            'token_otisk' => static::otisk($token),
            'plati_do' => now()->addMinutes(self::PLATNOST_MINUT),
        ]);

        return $token;
    }

    public static function platny(string $token): ?self
    {
        return static::query()->platne()->where('token_otisk', static::otisk($token))->with('user')->first();
    }

    public function scopePlatne(Builder $query): void
    {
        $query->whereNull('pouzito_v')->whereNull('zruseno_v')->where('plati_do', '>', now());
    }
}
