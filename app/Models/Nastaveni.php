<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Nastavení aplikace klíč–hodnota (retence logů a další provozní volby).
 *
 * Co se mění podle provozu, patří sem, ne do kódu ani do .env: změní se
 * bez nasazení a zapíše se do Aktivity, kdo to změnil.
 *
 * Výchozí hodnoty projektu (údaje firmy, předvyplněná pošta, texty) patří do
 * config/nastaveni.php, NIKDY do migrace ani seederu: čerstvá produkce by
 * „měla data“ a portál by odmítl přenos dat z testu.
 */
class Nastaveni extends Model
{
    /** Klíč mezipaměti (s příznakem, jestli řádek je – NULL se jinak neukládá). */
    private const CACHE = 'nastaveni.v2.';

    protected $table = 'nastaveni';

    protected $primaryKey = 'klic';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['klic', 'hodnota'];

    /**
     * Uložená hodnota; když řádek není, výchozí hodnota projektu z config/nastaveni.php,
     * a teprve pak $vychozi z kódu. Řádek s NULL (správce pole vymazal) výchozí
     * hodnotu projektu nevrací – vymazané má zůstat vymazané.
     */
    public static function hodnota(string $klic, mixed $vychozi = null): mixed
    {
        $ulozeno = Cache::rememberForever(self::CACHE.$klic, function () use ($klic) {
            $radek = static::query()->whereKey($klic)->first(['hodnota']);

            return ['je' => $radek !== null, 'hodnota' => $radek?->hodnota];
        });

        if (! is_array($ulozeno) || ! ($ulozeno['je'] ?? false)) {
            return self::vychoziProjektu($klic) ?? $vychozi;
        }

        return $ulozeno['hodnota'] ?? $vychozi;
    }

    public static function nastav(string $klic, mixed $hodnota): void
    {
        static::query()->updateOrCreate(['klic' => $klic], ['hodnota' => $hodnota]);
        Cache::forget(self::CACHE.$klic);
    }

    public static function smaz(string $klic): void
    {
        static::query()->whereKey($klic)->delete();
        Cache::forget(self::CACHE.$klic);
    }

    /** Výchozí hodnota projektu (config/nastaveni.php) – v databázi nic není. */
    public static function vychoziProjektu(string $klic): mixed
    {
        return ((array) config('nastaveni.vychozi', []))[$klic] ?? null;
    }
}
