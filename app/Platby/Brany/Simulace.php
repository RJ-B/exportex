<?php

namespace App\Platby\Brany;

use App\Platby\Platba;
use App\Platby\PlatbyNedostupne;
use App\Platby\Rezim;
use App\Platby\StavPlatby;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Falešná brána pro lokální vývoj a automatické testy: místo přesměrování do
 * banky stránka s tlačítky Zaplatit / Zamítnout / Zrušit. Průchod je stejný
 * jako u skutečné brány – i tady se stav po návratu zjišťuje dotazem (stav()).
 *
 * Mimo `local` a `testing` neexistuje: konstruktor vyhodí výjimku a stránka
 * simulace vrátí 404 – na serveru nejde nic „zaplatit“ tlačítkem.
 */
class Simulace implements Brana
{
    public function __construct()
    {
        if (! app()->environment('local', 'testing')) {
            throw new PlatbyNedostupne('Simulace plateb je jen pro lokální vývoj a testy.');
        }
    }

    public function kod(): string
    {
        return 'simulace';
    }

    public function nazev(): string
    {
        return 'Simulace';
    }

    public function rezim(): Rezim
    {
        return Rezim::Simulace;
    }

    public function zaloz(Platba $platba): ZalozenaPlatba
    {
        $id = 'SIM-'.Str::upper(Str::random(10));
        self::uloz($id, StavPlatby::Ceka, 0);

        return new ZalozenaPlatba($id, route('platby.simulace', $platba), ['simulace' => true]);
    }

    public function stav(Platba $platba): StavZBrany
    {
        $stav = self::nacti((string) $platba->externi_id);

        return new StavZBrany(
            stav: StavPlatby::tryFrom($stav['stav'] ?? '') ?? StavPlatby::Ceka,
            castka: $platba->castka,
            metoda: ($stav['stav'] ?? null) === StavPlatby::Zaplacena->value ? 'SIMULACE' : null,
            data: ['simulace' => $stav],
        );
    }

    public function umiZrusit(): bool
    {
        return true;
    }

    public function zrus(Platba $platba): void
    {
        self::uloz((string) $platba->externi_id, StavPlatby::Zrusena, 0);
    }

    public function umiVratit(): bool
    {
        return true;
    }

    public function vrat(Platba $platba, int $castka): void
    {
        $stav = self::nacti((string) $platba->externi_id);
        self::uloz((string) $platba->externi_id, StavPlatby::Zaplacena, (int) ($stav['vraceno'] ?? 0) + $castka);
    }

    public function overSpojeni(): string
    {
        return 'Simulace – nic se neověřuje.';
    }

    public function webhookPravy(Request $request): bool
    {
        return true;
    }

    public static function zWebhooku(Request $request): array
    {
        return ['externi_id' => null, 'verejne_id' => null, 'stav' => null];
    }

    /** Tlačítko na stránce simulace (zákazník „zaplatil“ / banka zamítla). */
    public static function nastav(Platba $platba, StavPlatby $stav): void
    {
        self::uloz((string) $platba->externi_id, $stav, (int) (self::nacti((string) $platba->externi_id)['vraceno'] ?? 0));
    }

    private static function uloz(string $id, StavPlatby $stav, int $vraceno): void
    {
        Cache::put('platby:simulace:'.$id, ['stav' => $stav->value, 'vraceno' => $vraceno], now()->addDays(7));
    }

    private static function nacti(string $id): array
    {
        return (array) Cache::get('platby:simulace:'.$id, []);
    }
}
