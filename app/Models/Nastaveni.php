<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Nastavení aplikace klíč–hodnota (retence logů a další provozní volby).
 *
 * Co se mění podle provozu, patří sem, ne do kódu ani do .env: změní se
 * bez nasazení a zapíše se do Aktivity, kdo to změnil.
 */
class Nastaveni extends Model
{
    protected $table = 'nastaveni';

    protected $primaryKey = 'klic';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['klic', 'hodnota'];

    public static function hodnota(string $klic, mixed $vychozi = null): mixed
    {
        $hodnota = Cache::rememberForever('nastaveni.'.$klic, fn () => static::query()->whereKey($klic)->value('hodnota'));

        return $hodnota ?? $vychozi;
    }

    public static function nastav(string $klic, mixed $hodnota): void
    {
        static::query()->updateOrCreate(['klic' => $klic], ['hodnota' => $hodnota]);
        Cache::forget('nastaveni.'.$klic);
    }
}
