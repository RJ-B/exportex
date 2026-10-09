<?php

namespace App\Platby;

use App\Models\Nastaveni;
use App\Platby\Brany\Brana;
use App\Platby\Brany\Comgate;
use App\Platby\Brany\MoOne;
use App\Platby\Brany\Simulace;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Která brána platí a v jakém režimu (docs/platby.md → Test × produkce).
 *
 *  - lokálně a v testech: simulace (PLATBY_SIMULACE=true, výchozí), jinak testovací brána,
 *  - testovací web (APP_ENV=staging): VŽDY testovací brána Sim&Ren – Mo.one na
 *    api-test.znpay.tech s údaji z .env (PLATBY_TEST_MOONE_*, zakládá portál), nikdy ostře,
 *  - produkce: Testovací režim = totéž; Ostrý režim = brána klienta (Comgate nebo
 *    Mo.one, údaje z administrace šifrovaně) – jen s vyplněnými a ověřenými údaji
 *    (Ověřit spojení; změna údajů ověření zruší).
 *
 * Když zvolený režim nejde (ostrý bez ověření, chybí testovací údaje), platby
 * se ZASTAVÍ (PlatbyNedostupne) – nikdy se tiše nepřepne jinam: ostrý web nesmí
 * „prodávat“ přes testovací bránu a test nesmí platit ostře.
 */
class NastaveniPlateb
{
    public const BRANY = ['comgate' => 'Comgate', 'moone' => 'Mo.one'];

    public const REZIM_TEST = 'test';

    public const REZIM_OSTRY = 'ostry';

    /** @var array{rezim: ?Rezim, brana: ?string, popis: string, chyba: ?string}|null */
    private static ?array $prehled = null;

    // ---- Volby z administrace ----

    public static function brana(): ?string
    {
        $brana = self::cti('brana');

        return array_key_exists((string) $brana, self::BRANY) ? $brana : null;
    }

    public static function rezimVolba(): string
    {
        return self::cti('rezim') === self::REZIM_OSTRY ? self::REZIM_OSTRY : self::REZIM_TEST;
    }

    /**
     * Údaje brány klienta (dešifrované – jen pro volání brány, nikdy do formuláře).
     *
     * @return array{id: ?string, tajemstvi: ?string}
     */
    public static function udajeKlienta(string $brana): array
    {
        return match ($brana) {
            'comgate' => ['id' => self::cti('comgate.merchant'), 'tajemstvi' => self::tajne('comgate.secret')],
            'moone' => ['id' => self::cti('moone.client_id'), 'tajemstvi' => self::tajne('moone.client_secret')],
            default => ['id' => null, 'tajemstvi' => null],
        };
    }

    public static function vyplneno(string $brana): bool
    {
        $udaje = self::udajeKlienta($brana);

        return filled($udaje['id']) && filled($udaje['tajemstvi']);
    }

    /** Uložené tajemství je (do formuláře se nevrací, jen „uloženo“). */
    public static function tajemstviUlozeno(string $brana): bool
    {
        return filled(self::udajeKlienta($brana)['tajemstvi']);
    }

    /**
     * Uloží volby a údaje. Tajemství jen vyplněné (prázdné pole = beze změny),
     * šifrovaně klíčem aplikace. Změna údajů zruší ověření (jiný otisk).
     */
    public static function uloz(array $data): void
    {
        if (array_key_exists('brana', $data)) {
            self::zapis('brana', array_key_exists((string) $data['brana'], self::BRANY) ? $data['brana'] : null);
        }

        if (array_key_exists('rezim', $data)) {
            self::zapis('rezim', $data['rezim'] === self::REZIM_OSTRY ? self::REZIM_OSTRY : self::REZIM_TEST);
        }

        foreach (['comgate_merchant' => 'comgate.merchant', 'moone_client_id' => 'moone.client_id'] as $pole => $klic) {
            if (array_key_exists($pole, $data)) {
                self::zapis($klic, filled($data[$pole]) ? trim((string) $data[$pole]) : null);
            }
        }

        foreach (['comgate_secret' => 'comgate.secret', 'moone_client_secret' => 'moone.client_secret'] as $pole => $klic) {
            if (filled($data[$pole] ?? null)) {
                self::zapis($klic, Crypt::encryptString(trim((string) $data[$pole])));
            }
        }

        self::$prehled = null;
    }

    /** Otisk aktuálních údajů brány – ověření platí jen pro ně. */
    public static function otisk(string $brana): ?string
    {
        $udaje = self::udajeKlienta($brana);

        if (blank($udaje['id']) || blank($udaje['tajemstvi'])) {
            return null;
        }

        return hash_hmac('sha256', $brana.'|'.$udaje['id'].'|'.$udaje['tajemstvi'], (string) config('app.key'));
    }

    public static function overeno(string $brana): bool
    {
        $otisk = self::otisk($brana);
        $overeno = (array) json_decode((string) self::cti('overeno'), true);

        return $otisk !== null && hash_equals((string) ($overeno[$brana]['otisk'] ?? ''), $otisk);
    }

    public static function overenoKdy(string $brana): ?string
    {
        return self::overeno($brana) ? (((array) json_decode((string) self::cti('overeno'), true))[$brana]['kdy'] ?? null) : null;
    }

    public static function oznacOvereno(string $brana): void
    {
        $overeno = (array) json_decode((string) self::cti('overeno'), true);
        $overeno[$brana] = ['otisk' => self::otisk($brana), 'kdy' => now()->toIso8601String()];
        self::zapis('overeno', json_encode($overeno));
        self::$prehled = null;
    }

    /** Proč ostrý režim nejde (null = jde). */
    public static function procNeOstre(): ?string
    {
        $brana = self::brana();

        return match (true) {
            ! app()->isProduction() => 'ostré platby jsou jen na produkci (testovací web platí vždy testovací bránou).',
            $brana === null => 'není vybraná brána klienta.',
            ! self::vyplneno($brana) => 'chybí přístupové údaje brány '.self::BRANY[$brana].'.',
            ! self::overeno($brana) => 'přístupové údaje brány '.self::BRANY[$brana].' nejsou ověřené (Ověřit spojení).',
            default => null,
        };
    }

    // ---- Která brána platí ----

    public static function simulaceDovolena(): bool
    {
        return app()->environment('local', 'testing') && (bool) config('platby.simulace');
    }

    /**
     * Brána pro NOVOU platbu.
     *
     * @throws PlatbyNedostupne
     */
    public static function aktivni(): Brana
    {
        if (! app()->isProduction()) {
            if (self::simulaceDovolena()) {
                return new Simulace;
            }

            return self::testovaciBrana()
                ?? throw new PlatbyNedostupne('Na testovacím webu se platí testovací bránou Sim&Ren (Mo.one), ale v .env chybí její přístupové údaje (PLATBY_TEST_MOONE_CLIENT_ID a PLATBY_TEST_MOONE_CLIENT_SECRET).');
        }

        if (self::rezimVolba() === self::REZIM_OSTRY) {
            if ($duvod = self::procNeOstre()) {
                throw new PlatbyNedostupne('Ostrý režim plateb je zapnutý, ale '.$duvod.' Platby stojí – na testovací bránu se nepřepíná.');
            }

            return self::branaKlienta((string) self::brana(), Rezim::Ostry);
        }

        return self::testovaciBrana()
            ?? throw new PlatbyNedostupne('Testovací režim plateb: v .env chybí přístupové údaje testovací brány Sim&Ren (PLATBY_TEST_MOONE_*). Pro ostré platby přepni v Nastavení → Platební brána na bránu klienta.');
    }

    /**
     * Brána, přes kterou platba vznikla (stejná brána i režim) – pro ověření
     * stavu, zrušení a vrácení. Přepnutí režimu rozpracované platby nepřehodí.
     *
     * @throws PlatbyNedostupne
     */
    public static function proPlatbu(Platba $platba): Brana
    {
        return match ($platba->rezim) {
            Rezim::Simulace => new Simulace,
            Rezim::Testovaci => self::testovaciBrana()
                ?? throw new PlatbyNedostupne('Chybí přístupové údaje testovací brány Sim&Ren (PLATBY_TEST_MOONE_*).'),
            Rezim::Ostry => self::vyplneno($platba->brana)
                ? self::branaKlienta($platba->brana, Rezim::Ostry)
                : throw new PlatbyNedostupne('Chybí přístupové údaje brány '.(self::BRANY[$platba->brana] ?? $platba->brana).'.'),
        };
    }

    /** Brána klienta s uloženými (nebo zadanými) údaji. */
    public static function branaKlienta(string $brana, Rezim $rezim = Rezim::Ostry, ?array $udaje = null): Brana
    {
        $udaje ??= self::udajeKlienta($brana);

        return match ($brana) {
            'comgate' => new Comgate((string) $udaje['id'], (string) $udaje['tajemstvi'], $rezim),
            'moone' => new MoOne((string) $udaje['id'], (string) $udaje['tajemstvi'], (string) config('platby.moone.url'), $rezim),
            default => throw new PlatbyNedostupne('Neznámá brána „'.$brana.'“.'),
        };
    }

    /** Testovací brána Sim&Ren: Mo.one test s údaji z .env (nikdy z administrace ani z repa). */
    public static function testovaciBrana(): ?MoOne
    {
        $id = config('platby.test.moone.client_id');
        $tajemstvi = config('platby.test.moone.client_secret');

        return filled($id) && filled($tajemstvi)
            ? new MoOne((string) $id, (string) $tajemstvi, (string) config('platby.test.moone.url'), Rezim::Testovaci)
            : null;
    }

    /**
     * Jak teď platby běží – pro administraci a štítek „TESTOVACÍ PLATBY“.
     *
     * @return array{rezim: ?Rezim, brana: ?string, popis: string, chyba: ?string}
     */
    public static function prehled(): array
    {
        return self::$prehled ??= (function () {
            try {
                $brana = self::aktivni();

                return [
                    'rezim' => $brana->rezim(),
                    'brana' => $brana->nazev(),
                    'popis' => match ($brana->rezim()) {
                        Rezim::Ostry => 'Ostré platby přes bránu '.$brana->nazev().' – peníze jdou na účet klienta.',
                        Rezim::Testovaci => 'Testovací platby přes testovací bránu Sim&Ren (Mo.one test) – nic se nestrhne.',
                        Rezim::Simulace => 'Simulace – místo banky stránka s tlačítky Zaplatit / Zamítnout (jen lokálně).',
                    },
                    'chyba' => null,
                ];
            } catch (PlatbyNedostupne $e) {
                return ['rezim' => null, 'brana' => null, 'popis' => 'Platby teď nefungují.', 'chyba' => $e->getMessage()];
            }
        })();
    }

    /** Ukázat štítek „TESTOVACÍ PLATBY“? (platí se, ale ne ostře) */
    public static function stitek(): bool
    {
        return in_array(self::prehled()['rezim'], [Rezim::Testovaci, Rezim::Simulace], true);
    }

    public static function zapomen(): void
    {
        self::$prehled = null;
    }

    /**
     * Je adresa dosažitelná z internetu? Mo.one webhook na localhost,
     * *.test / *.localhost ani privátní IP nepošle.
     */
    public static function verejnaAdresa(string $adresa): bool
    {
        $host = strtolower((string) parse_url($adresa, PHP_URL_HOST));

        if ($host === '' || $host === 'localhost' || preg_match('/\.(test|localhost|local|internal|invalid|example)$/', $host)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return str_contains($host, '.');
    }

    // ---- Úložiště ----

    private static function cti(string $klic): ?string
    {
        return rescue(fn () => Nastaveni::hodnota('platby.'.$klic), null, false);
    }

    private static function tajne(string $klic): ?string
    {
        $hodnota = self::cti($klic);

        if (blank($hodnota)) {
            return null;
        }

        try {
            return Crypt::decryptString($hodnota);
        } catch (DecryptException) {
            // Jiný APP_KEY (přenos z jiného prostředí) – údaj se musí zadat znovu.
            return null;
        }
    }

    private static function zapis(string $klic, ?string $hodnota): void
    {
        Nastaveni::nastav('platby.'.$klic, $hodnota);
    }
}
