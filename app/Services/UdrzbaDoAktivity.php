<?php

namespace App\Services;

use Illuminate\Foundation\Events\MaintenanceModeDisabled;
use Illuminate\Foundation\Events\MaintenanceModeEnabled;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Údržba (`artisan down` / `artisan up`) patří do Aktivity jako událost, ne do Chyb.
 *
 * Shim web na chvíli zavře při nasazení a převodu dat a návštěvníci mezitím
 * dostávají 503. To je záměr – ErrorLogger ho proto přeskočí –, ale v logu
 * musí být vidět, kdy web stál: „Údržba zapnuta“ / „Údržba vypnuta“.
 * Kdo = systém (příkaz na serveru, bez přihlášeného účtu), čas serveru.
 *
 * Registruje se v AppServiceProvider::boot(). Schválně ne v app/Listeners:
 * tam by ho Laravel našel i sám a zápis by byl dvakrát.
 */
class UdrzbaDoAktivity
{
    public static function poslouchej(): void
    {
        Event::listen(MaintenanceModeEnabled::class, fn () => AuditLogger::record(
            event: 'udrzba.zapnuta',
            summary: 'Údržba zapnuta',
            new: self::nastaveni(),
        ));

        Event::listen(MaintenanceModeDisabled::class, fn () => AuditLogger::record(
            event: 'udrzba.vypnuta',
            summary: 'Údržba vypnuta',
            new: ['prikaz' => 'artisan up'],
        ));
    }

    /**
     * S čím se web zavřel (retry, refresh, status, přesměrování).
     *
     * Tajný klíč pro průchod údržbou (`--secret`) se NEZAPISUJE – kdo ho zná,
     * projde zavřeným webem. Šablona je celé HTML stránky, do logu nepatří.
     *
     * @return array<string, mixed>
     */
    private static function nastaveni(): array
    {
        try {
            $data = (array) app()->maintenanceMode()->data();
        } catch (Throwable) {
            $data = [];
        }

        return ['prikaz' => 'artisan down', ...array_filter(
            array_intersect_key($data, array_flip(['retry', 'refresh', 'status', 'redirect'])),
            fn ($hodnota) => $hodnota !== null && $hodnota !== '',
        )];
    }
}
