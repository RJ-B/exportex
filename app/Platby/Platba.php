<?php

namespace App\Platby;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Number;

/**
 * Platba přes platební bránu (doplněk Platby, docs/platby.md).
 *
 * Částky v haléřích (celé číslo) – žádné desetinné číslo s plovoucí čárkou.
 * Stav, externí id a vrácená částka mimo $fillable: mění je jen služba
 * Platby (zámek, historie, události).
 *
 * @property StavPlatby $stav
 * @property Rezim $rezim
 */
class Platba extends Model
{
    protected $table = 'platby';

    protected $fillable = ['popis', 'reference', 'email', 'jmeno', 'prijmeni', 'navrat_url'];

    protected function casts(): array
    {
        return [
            'stav' => StavPlatby::class,
            'rezim' => Rezim::class,
            'castka' => 'integer',
            'vraceno' => 'integer',
            'zaplaceno_v' => 'datetime',
            'overeno_v' => 'datetime',
        ];
    }

    /** Veřejné adresy (návrat, zaplatit) jdou přes neuhodnutelné verejne_id, ne přes id. */
    public function getRouteKeyName(): string
    {
        return 'verejne_id';
    }

    /** Co se platí (objednávka, rezervace…) – nepovinné. */
    public function predmet(): MorphTo
    {
        return $this->morphTo();
    }

    public function udalosti(): HasMany
    {
        return $this->hasMany(PlatbaUdalost::class)->orderBy('id');
    }

    public function testovaci(): bool
    {
        return $this->rezim->testovaci();
    }

    public function nazevBrany(): string
    {
        return $this->brana === 'simulace' ? 'Simulace' : (NastaveniPlateb::BRANY[$this->brana] ?? $this->brana);
    }

    public function castkaKc(): string
    {
        return self::kc($this->castka, $this->mena);
    }

    public function zbyvaVratit(): int
    {
        return $this->stav->zaplaceno() ? max(0, $this->castka - $this->vraceno) : 0;
    }

    public function celeJmeno(): string
    {
        return trim($this->jmeno.' '.$this->prijmeni);
    }

    /** Odkaz, kterým zákazník zaplatí (přesměruje na bránu). */
    public function odkazKZaplaceni(): string
    {
        return route('platby.zaplatit', $this);
    }

    public static function kc(int $halere, string $mena = 'CZK'): string
    {
        return (string) Number::currency($halere / 100, in: $mena, locale: 'cs');
    }
}
