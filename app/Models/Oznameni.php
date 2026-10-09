<?php

namespace App\Models;

use App\Enums\DruhOznameni;
use App\Enums\KanalOznameni;
use App\Enums\StavOznameni;
use App\Enums\ZavaznostOznameni;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Oznámení – jedna zpráva, která k lidem dojde kanály (centrum, pruh, e-mail…).
 *
 * Odesílá ho jen App\Support\Oznameni\Odeslani (administrace, plánovač,
 * Oznam z kódu); stav se ručně nemění. Odeslané se už neupravuje – lidé ho
 * mají v centru a v e-mailu. Pruh jde u odeslaného jen ukončit (pruh_do).
 */
class Oznameni extends Model
{
    protected $table = 'oznameni';

    protected $fillable = [
        'zdroj', 'zdroj_klic', 'druh', 'zavaznost', 'titulek', 'text', 'odkaz', 'odkaz_text',
        'kanaly', 'cileni', 'naplanovano_na', 'pruh_od', 'pruh_do', 'udalost_od', 'udalost_do',
    ];

    protected $attributes = [
        'zdroj' => 'administrace',
        'zavaznost' => 'info',
        'stav' => 'koncept',
    ];

    protected function casts(): array
    {
        return [
            'druh' => DruhOznameni::class,
            'zavaznost' => ZavaznostOznameni::class,
            'stav' => StavOznameni::class,
            'kanaly' => 'array',
            'cileni' => 'array',
            'naplanovano_na' => 'datetime',
            'odeslano_at' => 'datetime',
            'pruh_od' => 'datetime',
            'pruh_do' => 'datetime',
            'udalost_od' => 'datetime',
            'udalost_do' => 'datetime',
            'pocet_prijemcu' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $oznameni) {
            $oznameni->uuid ??= (string) Str::uuid();
            $oznameni->vytvoril_id ??= auth()->id();
        });
    }

    public function prijemci(): HasMany
    {
        return $this->hasMany(OznameniPrijemce::class);
    }

    public function vytvoril(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vytvoril_id');
    }

    public function odeslal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'odeslal_id');
    }

    public function maKanal(KanalOznameni $kanal): bool
    {
        return in_array($kanal->value, (array) $this->kanaly, true);
    }

    /** @return list<KanalOznameni> */
    public function kanalyEnum(): array
    {
        return array_values(array_filter(array_map(fn ($k) => KanalOznameni::tryFrom((string) $k), (array) $this->kanaly)));
    }

    public function jeProVsechny(): bool
    {
        return ($this->cileni['komu'] ?? null) === 'vsichni';
    }

    /** Text (Markdown) jako HTML – bez vlastního HTML a bez nebezpečných odkazů. */
    public function textHtml(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->text, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));
    }

    /** Text bez formátování (e-mail v textové podobě, náhled v centru). */
    public function textProsty(): string
    {
        return trim(html_entity_decode(strip_tags((string) $this->textHtml()), ENT_QUOTES | ENT_HTML5));
    }

    /** Pruhy, které právě platí (bez ohledu na cílení – to řeší App\Support\Oznameni\Pruh). */
    public function scopePruhPlati(Builder $query): Builder
    {
        return $query
            ->where('stav', StavOznameni::Odeslano->value)
            ->whereJsonContains('kanaly', KanalOznameni::Pruh->value)
            ->where(fn (Builder $query) => $query->whereNull('pruh_od')->orWhere('pruh_od', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('pruh_do')->orWhere('pruh_do', '>', now()));
    }
}
