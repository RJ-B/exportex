<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Uživatel. Role: superadmin (vývojáři – provozní věci), admin (majitel
 * aplikace), klient. Do administrace smí superadmin a admin.
 */
#[Fillable(['name', 'jmeno', 'prijmeni', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE = ['superadmin', 'admin', 'klient'];

    /** Popisky rolí pro člověka (sloupec `role` drží klíč). */
    public const ROLE_POPISKY = [
        'superadmin' => 'Superadmin',
        'admin' => 'Admin',
        'klient' => 'Klient',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->jeSpravce();
    }

    public function getFilamentName(): string
    {
        return trim($this->jmeno.' '.$this->prijmeni) ?: (string) ($this->name ?: $this->email);
    }

    public function jeSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /** Admin (majitel) nebo superadmin – kdo spravuje aplikaci. */
    public function jeSpravce(): bool
    {
        return in_array($this->role, ['superadmin', 'admin'], true);
    }

    /** Oznámení, která uživatel dostal (centrum – přečteno, prokliknuto, archiv). */
    public function oznameni(): HasMany
    {
        return $this->hasMany(OznameniPrijemce::class);
    }

    /** Co chce dostávat (druh × kanál) – App\Support\Oznameni\Predvolby. */
    public function oznameniPredvolby(): HasOne
    {
        return $this->hasOne(OznameniPredvolby::class);
    }

    /** Skupiny příjemců oznámení, do kterých patří. */
    public function oznameniSkupiny(): BelongsToMany
    {
        return $this->belongsToMany(OznameniSkupina::class, 'oznameni_skupiny_clenove', 'user_id', 'skupina_id');
    }
}
