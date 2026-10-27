<?php

namespace App\Support\Prenos;

use App\Support\Posta\TestovaciSchranka;
use InvalidArgumentException;

/**
 * Části dat, které jde přenést z jednoho webu do druhého (test → produkce)
 * tlačítkem v portálu. Seznam je v config/prenos.php – každý projekt si ho
 * doplní o své tabulky (obsah webu, katalog…).
 *
 * Část = jedna nebo víc tabulek; řádky se mezi weby párují podle
 * přirozeného klíče (`klic` – slug, e-mail, klíč nastavení…), ne podle id,
 * které se na každém webu liší. Vazby (`vazby` – sloupec => tabulka) se
 * při přenosu přečíslují. Fotky a soubory, na které řádky odkazují, jdou
 * s nimi (Soubory).
 *
 * Nikdy se nepřenáší: provozní tabulky (fronta, sezení, logy, migrace)
 * a v nastavení klíče Pošty a platební brány – jsou šifrované klíčem webu,
 * na jiném webu by nešly přečíst, a patří k prostředí, ne k obsahu.
 */
class Casti
{
    public const REZIMY = ['doplnit', 'aktualizovat', 'nahradit'];

    /** Tabulky, které žádná část obsahovat nesmí. */
    public const NIKDY_TABULKY = [
        'migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens', 'audit_logs', 'mail_logs', 'error_logs', 'posta_odchozi',
        // Passkey patří doméně webu – na jiném webu nefunguje.
        'passkeys', 'parovani_zarizeni',
        // Tokeny API a zařízení pro push: testovací přihlášení nesmí platit v produkci
        // a push nesmí jít na telefony z druhého prostředí. Platby jsou peníze jednoho webu.
        'personal_access_tokens', 'zarizeni', 'push_odeslani', 'platby', 'platby_udalosti',
    ];

    /**
     * Sloupce, které se nepřenáší nikdy: Authenticator je zašifrovaný klíčem
     * aplikace (na jiném webu nejde přečíst a přihlášení by spadlo). Druhý krok
     * si člověk na produkci zapne znovu v Zabezpečení.
     */
    public const NIKDY_SLOUPCE = [
        'users' => ['app_authentication_secret', 'app_authentication_recovery_codes'],
    ];

    /** Řádek bez sloupců NIKDY_SLOUPCE. */
    public static function bezTajnych(string $tabulka, array $radek): array
    {
        return array_diff_key($radek, array_flip(self::NIKDY_SLOUPCE[$tabulka] ?? []));
    }

    /** Klíče nastavení, které se nepřenáší nikdy (LIKE vzory). */
    public const NIKDY_NASTAVENI = ['posta.%', 'platby.%', 'fakturace.%', 'web.stav'];

    /**
     * @return array<string, array{nazev: string, popis: string, vychozi: bool, citlive: bool,
     *     rezimy: list<string>, tabulky: array<string, array{klic: list<list<string>>, pk: ?string,
     *     vazby: array<string, string>, jen: array<string, list<string>>, krome: array<string, list<string>>}>}>
     */
    public static function vse(): array
    {
        $casti = [];

        foreach ((array) config('prenos.casti', []) as $klic => $c) {
            if (! preg_match('/^[a-z0-9_]{1,40}$/', (string) $klic)) {
                throw new InvalidArgumentException("Část přenosu „{$klic}“ má neplatný klíč.");
            }

            $tabulky = [];

            foreach ((array) ($c['tabulky'] ?? []) as $tabulka => $t) {
                if (in_array($tabulka, self::NIKDY_TABULKY, true)) {
                    throw new InvalidArgumentException("Tabulka {$tabulka} se nepřenáší (část {$klic}).");
                }

                // klic = sloupce, nebo víc možností za sebou ([['puvodni_id'], ['nazev']]):
                // řádek se páruje první, ve které má zdroj všechny hodnoty vyplněné.
                $sloupce = array_values((array) ($t['klic'] ?? []));
                $klice = $sloupce !== [] && is_array($sloupce[0]) ? array_map(fn ($k) => array_values((array) $k), $sloupce) : [$sloupce];

                $tabulky[$tabulka] = [
                    'klic' => $klice,
                    'pk' => array_key_exists('pk', $t) ? $t['pk'] : 'id',
                    'vazby' => (array) ($t['vazby'] ?? []),
                    'jen' => (array) ($t['jen'] ?? []),
                    'krome' => (array) ($t['krome'] ?? []),
                ];

                if (in_array([], $klice, true)) {
                    throw new InvalidArgumentException("Tabulka {$tabulka} (část {$klic}) nemá klíč pro párování.");
                }
            }

            $casti[$klic] = [
                'nazev' => (string) ($c['nazev'] ?? $klic),
                'popis' => (string) ($c['popis'] ?? ''),
                'vychozi' => (bool) ($c['vychozi'] ?? true),
                'citlive' => (bool) ($c['citlive'] ?? false),
                'rezimy' => array_values(array_intersect(self::REZIMY, (array) ($c['rezimy'] ?? self::REZIMY))),
                'tabulky' => $tabulky,
            ];
        }

        return $casti;
    }

    /** Popis částí pro portál (bez tabulek a filtrů). */
    public static function prehled(): array
    {
        return collect(self::vse())->map(fn (array $c, string $klic) => [
            'klic' => $klic,
            'nazev' => $c['nazev'],
            'popis' => $c['popis'],
            'vychozi' => $c['vychozi'],
            'citlive' => $c['citlive'],
            'rezimy' => $c['rezimy'],
        ])->values()->all();
    }

    /**
     * Volby z portálu: „cast:rezim,cast:rezim“ → [cast => rezim].
     *
     * @return array<string, string>
     */
    public static function volby(string $text): array
    {
        $casti = self::vse();
        $volby = [];

        foreach (array_filter(explode(',', $text)) as $kus) {
            [$cast, $rezim] = array_pad(explode(':', $kus, 2), 2, '');

            if (! isset($casti[$cast])) {
                throw new InvalidArgumentException("Část „{$cast}“ tahle aplikace nezná.");
            }

            if (! in_array($rezim, $casti[$cast]['rezimy'], true)) {
                throw new InvalidArgumentException("Část „{$casti[$cast]['nazev']}“ nejde přenést režimem „{$rezim}“.");
            }

            $volby[$cast] = $rezim;
        }

        if ($volby === []) {
            throw new InvalidArgumentException('Není vybraná žádná část.');
        }

        return $volby;
    }

    /** Patří řádek do části? (filtry `jen` / `krome` a nikdy nepřenášená nastavení) */
    public static function patri(string $tabulka, array $definice, array $radek): bool
    {
        if ($tabulka === 'nastaveni' && self::odpovida((string) ($radek['klic'] ?? ''), self::NIKDY_NASTAVENI)) {
            return false;
        }

        // Testovací schránka (test@simren.cz) do produkce nikdy – ani jako kontaktní e-mail.
        if ($tabulka === 'nastaveni' && TestovaciSchranka::jeTestovaci(is_string($radek['hodnota'] ?? null) ? $radek['hodnota'] : null)) {
            return false;
        }

        foreach ($definice['jen'] as $sloupec => $vzory) {
            if (! self::odpovida((string) ($radek[$sloupec] ?? ''), $vzory)) {
                return false;
            }
        }

        foreach ($definice['krome'] as $sloupec => $vzory) {
            if (self::odpovida((string) ($radek[$sloupec] ?? ''), $vzory)) {
                return false;
            }
        }

        return true;
    }

    /** Vzory jako v LIKE: % = cokoli. */
    public static function odpovida(string $hodnota, array $vzory): bool
    {
        foreach ($vzory as $vzor) {
            $regex = '/^'.str_replace('%', '.*', preg_quote((string) $vzor, '/')).'$/s';

            if (preg_match($regex, $hodnota)) {
                return true;
            }
        }

        return false;
    }
}
