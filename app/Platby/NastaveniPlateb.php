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
 *    PLATBY_TEST_MOONE_URL s údaji z .env (PLATBY_TEST_MOONE_*, zakládá portál), nikdy přes
 *    bránu klienta. Výchozí api-test.znpay.tech nestrhne nic; portál ale může zapsat OSTRÝ
 *    PayPoint Sim&Ren (api.znpay.tech) – pak jsou platby skutečné (peníze se vrací ručně
 *    v aplikaci Mo.one) a štítek to říká (testovaciBranaOstra()),
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

    /**
     * Metody Comgate, které se zákazníkovi nenabízí, dokud je klient v administraci
     * nezapne: QR / převod pro klienty jiných bank a Cvak (Air Bank).
     */
    public const COMGATE_VYPNUTE_VYCHOZI = ['BANK_CZ_OTHER', 'BANK_CZ_AB_CVAK'];

    /** @var array{rezim: ?Rezim, brana: ?string, popis: string, skutecna: bool, chyba: ?string}|null */
    private static ?array $prehled = null;

    // ---- Volby z administrace ----

    public static function brana(): ?string
    {
        $brana = self::cti('brana');

        return array_key_exists((string) $brana, self::BRANY) ? $brana : null;
    }

    /**
     * Brány klienta nabízené zákazníkům (může jich být víc – zákazník si vybere).
     * Bez volby jen brána zvolená v administraci (starší nastavení s jednou bránou).
     *
     * @return list<string>
     */
    public static function nabizeneBrany(): array
    {
        $ulozene = json_decode((string) self::cti('brany'), true);
        $brany = is_array($ulozene) ? $ulozene : array_filter([self::brana()]);

        return array_values(array_filter(array_keys(self::BRANY), fn (string $b) => in_array($b, $brany, true)));
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

        foreach (MetodaPlatby::cases() as $metoda) {
            if (array_key_exists($metoda->value, $data)) {
                self::zapis('predvolba.'.$metoda->value, $data[$metoda->value] ? '1' : '0');
            }
        }

        if (array_key_exists('comgate_vypnute', $data)) {
            self::zapis('comgate.vypnute', json_encode(array_values(array_unique(array_map('strval', (array) $data['comgate_vypnute'])))));
        }

        if (array_key_exists('nabizet', $data)) {
            self::zapis('brany', json_encode(array_values(array_intersect(array_keys(self::BRANY), (array) $data['nabizet']))));
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

    /**
     * Metody Comgate vypnuté v administraci (při platbě se odeberou z výběru).
     * Bez volby výchozí COMGATE_VYPNUTE_VYCHOZI.
     *
     * @return list<string>
     */
    public static function comgateVypnute(): array
    {
        $ulozene = json_decode((string) self::cti('comgate.vypnute'), true);

        return is_array($ulozene) ? array_values(array_map('strval', $ulozene)) : self::COMGATE_VYPNUTE_VYCHOZI;
    }

    /**
     * Nabízet Apple Pay / Google Pay (předvolba na platební stránce brány)?
     * Volba v administraci, výchozí zapnuto – rozhoduje pak i brána (predvolby()).
     */
    public static function predvolbaZapnuta(MetodaPlatby $metoda): bool
    {
        return self::cti('predvolba.'.$metoda->value) !== '0';
    }

    /** Jde metodu teď předvolit? Zapnutá v administraci a brána ji má. */
    public static function nabizi(MetodaPlatby $metoda, ?Brana $brana = null): bool
    {
        if (! self::predvolbaZapnuta($metoda)) {
            return false;
        }

        try {
            $brana ??= self::aktivni();
        } catch (PlatbyNedostupne) {
            return false;
        }

        return in_array($metoda, rescue(fn () => $brana->predvolby(), [], false), true);
    }

    /**
     * Čím jde teď platit – pro aplikaci (GET /api/v1/platby/metody): karta =
     * platby fungují; apple_pay / google_pay = jde je předvolit (aplikace pak
     * ukáže tlačítko na iOS, resp. Androidu).
     *
     * @return array{karta: bool, apple_pay: bool, google_pay: bool}
     */
    public static function metody(): array
    {
        try {
            $brana = self::aktivni();
        } catch (PlatbyNedostupne) {
            return ['karta' => false, 'apple_pay' => false, 'google_pay' => false];
        }

        return ['karta' => true, 'apple_pay' => self::nabizi(MetodaPlatby::ApplePay, $brana), 'google_pay' => self::nabizi(MetodaPlatby::GooglePay, $brana)];
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

    /** Proč ostrý režim nejde (null = jde) – pro bránu, jinak pro všechny nabízené. */
    public static function procNeOstre(?string $brana = null): ?string
    {
        if ($brana === null && app()->isProduction()) {
            $nabizene = self::nabizeneBrany();

            if ($nabizene === []) {
                return __('platby.nastaveni_plateb.neni_vybrana_zadna_brana_kterou_nabizet');
            }

            foreach ($nabizene as $nabizena) {
                if ($duvod = self::procNeOstre($nabizena)) {
                    return $duvod;
                }
            }

            return null;
        }

        $brana ??= self::brana();

        return match (true) {
            ! app()->isProduction() => __('platby.nastaveni_plateb.ostre_platby_jsou_jen_na_produkci'),
            $brana === null => __('platby.nastaveni_plateb.neni_vybrana_brana_klienta'),
            ! self::vyplneno($brana) => __('platby.nastaveni_plateb.chybi_pristupove_udaje_brany', ['hodnota' => self::BRANY[$brana]]),
            ! self::overeno($brana) => __('platby.nastaveni_plateb.pristupove_udaje_brany_nejsou_overene', ['hodnota' => self::BRANY[$brana]]),
            default => null,
        };
    }

    // ---- Která brána platí ----

    public static function simulaceDovolena(): bool
    {
        return app()->environment('local', 'testing') && (bool) config('platby.simulace');
    }

    /**
     * Brána pro NOVOU platbu: zvolená zákazníkem (comgate / moone), jinak první
     * nabízená. Mimo ostrý režim vždy testovací brána / simulace.
     *
     * @throws PlatbyNedostupne
     */
    public static function aktivni(?string $volba = null): Brana
    {
        if (! app()->isProduction()) {
            if (self::simulaceDovolena()) {
                return new Simulace;
            }

            return self::testovaciBrana()
                ?? throw new PlatbyNedostupne(__('platby.nastaveni_plateb.na_testovacim_webu_se_plati_testovaci'));
        }

        if (self::rezimVolba() === self::REZIM_OSTRY) {
            $brana = $volba ?? (self::nabizeneBrany()[0] ?? self::brana());

            if ($brana === null || ! in_array($brana, self::nabizeneBrany(), true)) {
                throw new PlatbyNedostupne(__('platby.nastaveni_plateb.brana_se_zakaznikum_nenabizi_nastaveni', ['hodnota' => (self::BRANY[$brana] ?? $brana ?? '–')]));
            }

            if ($duvod = self::procNeOstre($brana)) {
                throw new PlatbyNedostupne(__('platby.nastaveni_plateb.ostry_rezim_plateb_je_zapnuty_ale', ['duvod' => $duvod]));
            }

            return self::branaKlienta($brana, Rezim::Ostry);
        }

        return self::testovaciBrana()
            ?? throw new PlatbyNedostupne(__('platby.nastaveni_plateb.testovaci_rezim_plateb_v_env_chybi'));
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
                ?? throw new PlatbyNedostupne(__('platby.nastaveni_plateb.chybi_pristupove_udaje_testovaci_brany')),
            Rezim::Ostry => self::vyplneno($platba->brana)
                ? self::branaKlienta($platba->brana, Rezim::Ostry)
                : throw new PlatbyNedostupne(__('platby.nastaveni_plateb.chybi_pristupove_udaje_brany_2', ['hodnota' => (self::BRANY[$platba->brana] ?? $platba->brana)])),
        };
    }

    /** Brána klienta s uloženými (nebo zadanými) údaji. */
    public static function branaKlienta(string $brana, Rezim $rezim = Rezim::Ostry, ?array $udaje = null): Brana
    {
        $udaje ??= self::udajeKlienta($brana);

        return match ($brana) {
            'comgate' => new Comgate((string) $udaje['id'], (string) $udaje['tajemstvi'], $rezim, self::comgateVypnute()),
            'moone' => new MoOne((string) $udaje['id'], (string) $udaje['tajemstvi'], (string) config('platby.moone.url'), $rezim),
            default => throw new PlatbyNedostupne(__('platby.nastaveni_plateb.neznama_brana', ['brana' => $brana])),
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
     * @return array{rezim: ?Rezim, brana: ?string, popis: string, skutecna: bool, chyba: ?string}
     */
    public static function prehled(): array
    {
        return self::$prehled ??= (function () {
            try {
                $brana = self::aktivni();
                $skutecna = $brana instanceof MoOne && $brana->rezim() === Rezim::Testovaci && self::testovaciBranaOstra();

                return [
                    'rezim' => $brana->rezim(),
                    'brana' => $brana->nazev(),
                    'popis' => match ($brana->rezim()) {
                        Rezim::Ostry => __('platby.nastaveni_plateb.ostre_platby_pres_penize_jdou_na', ['nabizene_brany' => (count($nazvy = array_map(fn (string $b) => self::BRANY[$b], self::nabizeneBrany())) > 1 ? __('platby.nastaveni_plateb.brany', ['implode' => implode(' a ', $nazvy)]) : __('platby.nastaveni_plateb.branu', ['nazev' => $brana->nazev()]))]),
                        Rezim::Testovaci => $skutecna
                            ? __('platby.nastaveni_plateb.platby_pres_testovaci_branu_sim_ren')
                            : __('platby.nastaveni_plateb.testovaci_platby_pres_testovaci_branu'),
                        Rezim::Simulace => __('platby.nastaveni_plateb.simulace_misto_banky_stranka_s_tlacitky'),
                    },
                    'skutecna' => $skutecna,
                    'chyba' => null,
                ];
            } catch (PlatbyNedostupne $e) {
                return ['rezim' => null, 'brana' => null, 'popis' => __('platby.nastaveni_plateb.platby_ted_nefunguji'), 'skutecna' => false, 'chyba' => $e->getMessage()];
            }
        })();
    }

    /** Je testovací brána Sim&Ren ostrá (api.znpay.tech místo api-test.znpay.tech)? Pak se platí skutečnými penězi. */
    public static function testovaciBranaOstra(): bool
    {
        return strtolower((string) parse_url((string) config('platby.test.moone.url'), PHP_URL_HOST)) === 'api.znpay.tech';
    }

    /** Text štítku na webu i v administraci. */
    public static function stitekText(): string
    {
        return self::prehled()['skutecna'] ? __('platby.nastaveni_plateb.skutecna_platba_testovaci_brana') : __('platby.nastaveni_plateb.testovaci_platby');
    }

    /**
     * Která brána se má na webu a stránkách plateb ukázat (loga, text o bráně): kód brány, která se
     * SKUTEČNĚ použije pro novou platbu (comgate | moone | simulace). Na testu a v testovacím režimu je to
     * testovací brána Sim&Ren (moone), ne brána klienta. null = platby teď nejdou.
     */
    public static function kodBrany(): ?string
    {
        try {
            return self::aktivni()->kod();
        } catch (PlatbyNedostupne) {
            return null;
        }
    }

    /**
     * Co ukázat v logách plateb pro bránu `$kod`: brána (comgate | moone | null = bez loga brány, např. simulace)
     * a peněženky. Apple Pay a Google Pay jen při zapnuté předvolbě a bráně, která platbu kartou s peněženkami
     * nabízí (Comgate; Mo.one je neumí). Bez dotazu na bránu – patička se renderuje na každé stránce.
     *
     * @return array{brana: ?string, apple_pay: bool, google_pay: bool}
     */
    public static function logaPlateb(string $kod): array
    {
        $penezenky = in_array($kod, ['comgate', 'simulace'], true);

        return [
            'brana' => in_array($kod, ['comgate', 'moone'], true) ? $kod : null,
            'apple_pay' => $penezenky && self::predvolbaZapnuta(MetodaPlatby::ApplePay),
            'google_pay' => $penezenky && self::predvolbaZapnuta(MetodaPlatby::GooglePay),
        ];
    }

    /** Ukázat štítek „TESTOVACÍ PLATBY“ / „SKUTEČNÁ PLATBA – TESTOVACÍ BRÁNA“? (platí se, ale ne přes bránu klienta) */
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
