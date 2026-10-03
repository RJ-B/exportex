<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Zpráva z kontaktního formuláře na webu (Exportex: poptávka s firmou a jazykem webu). */
class Zprava extends Model
{
    protected $table = 'zpravy';

    protected $fillable = ['jmeno', 'prijmeni', 'firma', 'email', 'telefon', 'zprava', 'jazyk', 'ip_adresa'];

    protected function casts(): array
    {
        return ['precteno_at' => 'datetime'];
    }

    public function scopeNeprectene(Builder $dotaz): Builder
    {
        return $dotaz->whereNull('precteno_at');
    }

    /** Jazyk webu, ve kterém návštěvník psal – pro člověka. */
    public function popisJazyka(): ?string
    {
        return ['cs' => 'čeština', 'en' => 'angličtina'][$this->jazyk] ?? null;
    }

    public function celeJmeno(): string
    {
        return trim($this->jmeno.' '.$this->prijmeni);
    }
}
