<?php

namespace App\Models;

use App\Support\Jazyky\Jazyky;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

/**
 * Uživatel. Role: superadmin (vývojáři – provozní věci), admin (majitel
 * aplikace), klient. Do administrace smí superadmin a admin.
 *
 * Druhý krok přihlášení (App\Bezpecnost\DruhyKrok): passkeye (laravel/passkeys)
 * a kód z Authenticatoru s obnovovacími kódy (Filament AppAuthentication,
 * šifrované klíčem aplikace) – zapíná si je každý sám v Zabezpečení.
 */
#[Fillable(['name', 'jmeno', 'prijmeni', 'email', 'password', 'jazyk'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasLocalePreference, HasName, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable, PasskeyAuthenticatable;

    public const ROLE = ['superadmin', 'admin', 'klient'];

    /** Popisky rolí pro člověka (sloupec `role` drží klíč). */
    /** @return array<string, string> */
    public static function popiskyRoli(): array
    {
        return [
            'superadmin' => __('modely.user.superadmin'),
            'admin' => __('modely.user.admin'),
            'klient' => __('modely.user.klient'),
        ];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'recenzent' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Jazyk e-mailů uživateli: jeho volba, jinak jazyk požadavku (docs/jazyky.md). */
    public function preferredLocale(): ?string
    {
        return Jazyky::je($this->jazyk) ? $this->jazyk : null;
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

    /**
     * Bez superadminů (vývojáři Sim&Ren testují naostro) a účtu pro kontrolu
     * obchodu (`recenzent`, App\Support\UcetRecenzenta) – do KAŽDÉ statistiky
     * a přehledu počtů (uživatelé, prodeje, návštěvnost, tržby), jako subjekt
     * i jako aktér. U záznamů s vazbou na uživatele `whereDoesntHave(vazba,
     * fn ($q) => User::mimoStatistiky($q))`, ať záznamy bez uživatele
     * (NULL – host, samoobsluha) v číslech zůstanou.
     */
    public function scopeBezSuperadminu(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('role')->orWhere('role', '!=', 'superadmin'))
            ->where(fn (Builder $q) => $q->whereNull('recenzent')->orWhere('recenzent', false));
    }

    /** Podmínka „uživatel, který do statistik nepatří“ (superadmin, účet pro kontrolu) – pro whereDoesntHave. */
    public static function mimoStatistiky(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('role', 'superadmin')->orWhere('recenzent', true));
    }

    /** Účet pro kontrolu obchodu (App Store, Google Play) – App\Support\UcetRecenzenta. */
    public function jeRecenzent(): bool
    {
        return (bool) $this->recenzent;
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

    /** Jak se účet jmenuje v Touch ID / Face ID / správci hesel. */
    public function getPasskeyDisplayName(): string
    {
        return $this->getFilamentName();
    }
}
