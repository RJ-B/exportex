<?php

namespace App\Support;

use Closure;

/**
 * DNS domény schránky, aby pošta chodila a nepadala do spamu – podle
 * poskytovatele schránky (poskytovatel(): Seznam Email Profi, Forpsi; u jiného
 * se návod ani kontrola nedělají, DNS zná jen on).
 *
 * Seznam Email Profi:
 * Návod pro klienta (co zapsat u registrátora) a kontrola, jestli je to
 * zapsané. Hodnoty podle Seznamu:
 *  - MX s prefixem, který doména dostane v Email Profi (nejde uhodnout),
 *  - SPF s `include:spf.seznam.cz` (jeden záznam – dva SPF = žádný),
 *  - DKIM: tři CNAME `szn1..3._domainkey` → `szn1..3._domainkey.seznam.cz`
 *    (v administraci Email Profi to není, ukazuje jen MX); bez nich Seznam
 *    podepisuje svou doménou a DMARC stojí jen na SPF,
 *  - DMARC s `p=quarantine`.
 *
 * U DKIM se kontroluje, že se přes CNAME opravdu načte klíč (`v=DKIM1`),
 * ne jen že CNAME existuje. Seznam si nový záznam načte až po čase (klidně
 * hodinu) – kontrola vidí veřejný stav DNS, ne ten u Seznamu.
 */
class PostaDns
{
    public const DKIM_SELEKTORY = ['szn1', 'szn2', 'szn3'];

    /**
     * Poskytovatelé, pro které je návod a kontrola DNS: klíč => SMTP server
     * a jeho domény. Forpsi (např. exportex.cz): MX mxavas.forpsi.com, SPF
     * include:_spf.forpsi.com; DKIM zapíná a selektor volí Forpsi sám – ten
     * se tu proto nekontroluje.
     */
    public const POSKYTOVATELE = [
        'seznam' => ['nazev' => 'Seznam Email Profi', 'domena' => 'seznam.cz'],
        'forpsi' => ['nazev' => 'Forpsi', 'domena' => 'forpsi.com'],
    ];

    /** Poskytovatel podle SMTP serveru ('seznam', 'forpsi'), null = jiný – DNS se tu neřeší. */
    public static function poskytovatel(?string $host): ?string
    {
        $host = strtolower(rtrim(trim((string) $host), '.'));

        foreach (self::POSKYTOVATELE as $klic => $p) {
            if ($host === $p['domena'] || str_ends_with($host, '.'.$p['domena'])) {
                return $klic;
            }
        }

        return null;
    }

    /** Náhrada za dns_get_record v testech: fn (string $host, int $typ): array */
    public static ?Closure $resolver = null;

    /**
     * Co má klient zapsat u registrátora.
     *
     * @return list<array{typ: string, nazev: string, hodnota: string, poznamka: string}>
     */
    public static function navod(string $domena, ?string $schranka = null, ?string $prefix = null, string $poskytovatel = 'seznam'): array
    {
        if ($poskytovatel === 'forpsi') {
            return [
                ['typ' => 'MX', 'nazev' => '@', 'hodnota' => 'mxavas.forpsi.com (priorita 10)',
                    'poznamka' => 'Forpsi ho u domény s poštou nastaví sám. Nemazat – jinak pošta přestane chodit.'],
                ['typ' => 'TXT', 'nazev' => '@', 'hodnota' => 'v=spf1 include:_spf.forpsi.com -all',
                    'poznamka' => 'Jen jeden SPF záznam. Bez mechanismu „a“ – A domény míří na náš server, a ten poštu neposílá.'],
                ['typ' => 'DKIM', 'nazev' => '(selektor od Forpsi)._domainkey', 'hodnota' => 'klíč vygeneruje Forpsi',
                    'poznamka' => 'Zapíná se v administraci Forpsi u e-mailu domény; selektor i klíč volí Forpsi.'],
                ['typ' => 'TXT', 'nazev' => '_dmarc', 'hodnota' => 'v=DMARC1; p=quarantine; rua=mailto:'.($schranka ?: 'info@'.$domena),
                    'poznamka' => 'Co udělat s poštou, která se za doménu jen vydává.'],
            ];
        }

        $prefix = $prefix ?: '<prefix>';

        $radky = [
            ['typ' => 'MX', 'nazev' => '@', 'hodnota' => $prefix.'.mx2.emailprofi.seznam.cz (priorita 10)',
                'poznamka' => 'Prefix přidělí Email Profi (detail domény → DNS záznamy). Starý MX nejdřív smazat.'],
            ['typ' => 'MX', 'nazev' => '@', 'hodnota' => $prefix.'.mx1.emailprofi.seznam.cz (priorita 20)',
                'poznamka' => 'Ano, mx1 má nižší prioritu než mx2 – není to překlep.'],
            ['typ' => 'TXT', 'nazev' => '@', 'hodnota' => 'v=spf1 include:spf.seznam.cz -all',
                'poznamka' => 'Jen jeden SPF záznam. Když už nějaký je, doplň do něj include:spf.seznam.cz.'],
        ];

        foreach (self::DKIM_SELEKTORY as $selektor) {
            $radky[] = ['typ' => 'CNAME', 'nazev' => $selektor.'._domainkey', 'hodnota' => $selektor.'._domainkey.seznam.cz',
                'poznamka' => 'Podpis DKIM vlastní doménou. V Email Profi se nic nezapíná.'];
        }

        $radky[] = ['typ' => 'TXT', 'nazev' => '_dmarc', 'hodnota' => 'v=DMARC1; p=quarantine; rua=mailto:'.($schranka ?: 'info@'.$domena),
            'poznamka' => 'Co udělat s poštou, která se za doménu jen vydává.'];

        return $radky;
    }

    /** Prefix MX, který doména dostala v Email Profi (když už MX u Seznamu má). */
    public static function prefixMx(string $domena): ?string
    {
        foreach (self::dotaz($domena, DNS_MX) as $r) {
            if (preg_match('/^([a-z0-9]+)\.mx[12]\.emailprofi\.seznam\.cz\.?$/i', $r['target'] ?? '', $m)) {
                return strtolower($m[1]);
            }
        }

        return null;
    }

    /**
     * Zkontroluje DNS domény.
     *
     * @return list<array{zaznam: string, stav: 'ok'|'varovani'|'chyba', zprava: string}>
     */
    public static function over(string $domena, string $poskytovatel = 'seznam'): array
    {
        if ($poskytovatel === 'forpsi') {
            return [...self::overForpsi($domena), self::overDmarc($domena)];
        }

        $vysledky = [];

        // MX
        $mx = array_map(fn ($r) => strtolower(rtrim($r['target'] ?? '', '.')), self::dotaz($domena, DNS_MX));
        $seznamMx = array_filter($mx, fn ($h) => str_ends_with($h, '.seznam.cz'));
        $vysledky[] = match (true) {
            $mx === [] => ['zaznam' => 'MX', 'stav' => 'chyba', 'zprava' => 'Doména nemá MX – pošta pro ni nechodí nikam.'],
            count($seznamMx) === count($mx) => ['zaznam' => 'MX', 'stav' => 'ok', 'zprava' => 'Pošta chodí do Seznamu ('.implode(', ', $mx).').'],
            $seznamMx !== [] => ['zaznam' => 'MX', 'stav' => 'chyba', 'zprava' => 'Vedle Seznamu je i jiný MX – pošta se tříští: '.implode(', ', $mx).'.'],
            default => ['zaznam' => 'MX', 'stav' => 'varovani', 'zprava' => 'Pošta pro doménu chodí jinam než do Seznamu: '.implode(', ', $mx).'.'],
        };

        // SPF
        $spf = array_values(array_filter(self::texty($domena), fn ($t) => str_starts_with(strtolower($t), 'v=spf1')));
        $vysledky[] = match (true) {
            $spf === [] => ['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => 'Chybí – příjemci nemají jak ověřit, že Seznam smí za doménu posílat.'],
            count($spf) > 1 => ['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => 'Víc SPF záznamů najednou – neplatí ani jeden. Slouč je do jednoho.'],
            ! str_contains(strtolower($spf[0]), 'include:spf.seznam.cz') => ['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => 'Chybí include:spf.seznam.cz ('.$spf[0].').'],
            str_contains($spf[0], '+all') => ['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => '+all povoluje posílat za doménu komukoli ('.$spf[0].').'],
            str_contains($spf[0], '-all') || str_contains($spf[0], '~all') => ['zaznam' => 'SPF', 'stav' => 'ok', 'zprava' => $spf[0]],
            default => ['zaznam' => 'SPF', 'stav' => 'varovani', 'zprava' => 'Bez -all na konci nic nezakazuje ('.$spf[0].').'],
        };

        // DKIM – přes CNAME se musí načíst klíč.
        foreach (self::DKIM_SELEKTORY as $selektor) {
            $klic = array_filter(self::texty($selektor.'._domainkey.'.$domena), fn ($t) => str_contains($t, 'v=DKIM1'));
            $vysledky[] = $klic !== []
                ? ['zaznam' => 'DKIM '.$selektor, 'stav' => 'ok', 'zprava' => 'Klíč se načte.']
                : ['zaznam' => 'DKIM '.$selektor, 'stav' => 'chyba', 'zprava' => 'Klíč se nenačte – chybí CNAME '.$selektor.'._domainkey → '.$selektor.'._domainkey.seznam.cz.'];
        }

        $vysledky[] = self::overDmarc($domena);

        return $vysledky;
    }

    /** @return array{zaznam: string, stav: 'ok'|'varovani'|'chyba', zprava: string} */
    private static function overDmarc(string $domena): array
    {
        $dmarc = array_values(array_filter(self::texty('_dmarc.'.$domena), fn ($t) => str_starts_with(strtolower($t), 'v=dmarc1')));
        $politika = $dmarc ? (preg_match('/\bp\s*=\s*(\w+)/i', $dmarc[0], $m) ? strtolower($m[1]) : null) : null;

        return match (true) {
            $dmarc === [] => ['zaznam' => 'DMARC', 'stav' => 'chyba', 'zprava' => 'Chybí – Gmail a Seznam pak poštu z domény snáz pošlou do spamu.'],
            $politika === 'none' => ['zaznam' => 'DMARC', 'stav' => 'varovani', 'zprava' => 'p=none jen sleduje, nic nechrání. Doporučeno p=quarantine ('.$dmarc[0].').'],
            in_array($politika, ['quarantine', 'reject'], true) => ['zaznam' => 'DMARC', 'stav' => 'ok', 'zprava' => $dmarc[0]],
            default => ['zaznam' => 'DMARC', 'stav' => 'chyba', 'zprava' => 'Záznam nemá platnou politiku p= ('.$dmarc[0].').'],
        };
    }

    /**
     * MX a SPF u schránky Forpsi. DKIM se nekontroluje – selektor volí Forpsi
     * (u exportex.cz f2026) a z DNS ho nejde zjistit.
     *
     * @return list<array{zaznam: string, stav: 'ok'|'varovani'|'chyba', zprava: string}>
     */
    private static function overForpsi(string $domena): array
    {
        $mx = array_map(fn ($r) => strtolower(rtrim($r['target'] ?? '', '.')), self::dotaz($domena, DNS_MX));
        $forpsiMx = array_filter($mx, fn ($h) => str_ends_with($h, '.forpsi.com'));
        $spf = array_values(array_filter(self::texty($domena), fn ($t) => str_starts_with(strtolower($t), 'v=spf1')));

        return [
            match (true) {
                $mx === [] => ['zaznam' => 'MX', 'stav' => 'chyba', 'zprava' => 'Doména nemá MX – pošta pro ni nechodí nikam.'],
                count($forpsiMx) === count($mx) => ['zaznam' => 'MX', 'stav' => 'ok', 'zprava' => 'Pošta chodí do Forpsi ('.implode(', ', $mx).').'],
                $forpsiMx !== [] => ['zaznam' => 'MX', 'stav' => 'chyba', 'zprava' => 'Vedle Forpsi je i jiný MX – pošta se tříští: '.implode(', ', $mx).'.'],
                default => ['zaznam' => 'MX', 'stav' => 'varovani', 'zprava' => 'Pošta pro doménu chodí jinam než do Forpsi: '.implode(', ', $mx).'.'],
            },
            match (true) {
                $spf === [] => ['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => 'Chybí – příjemci nemají jak ověřit, že Forpsi smí za doménu posílat.'],
                count($spf) > 1 => ['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => 'Víc SPF záznamů najednou – neplatí ani jeden. Slouč je do jednoho.'],
                ! str_contains(strtolower($spf[0]), 'include:_spf.forpsi.com') => ['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => 'Chybí include:_spf.forpsi.com ('.$spf[0].').'],
                str_contains($spf[0], '+all') => ['zaznam' => 'SPF', 'stav' => 'chyba', 'zprava' => '+all povoluje posílat za doménu komukoli ('.$spf[0].').'],
                str_contains($spf[0], '-all') || str_contains($spf[0], '~all') => ['zaznam' => 'SPF', 'stav' => 'ok', 'zprava' => $spf[0]],
                default => ['zaznam' => 'SPF', 'stav' => 'varovani', 'zprava' => 'Bez -all na konci nic nezakazuje ('.$spf[0].').'],
            },
        ];
    }

    /** @return list<string> */
    private static function texty(string $host): array
    {
        return array_values(array_filter(array_map(
            fn ($r) => $r['txt'] ?? (isset($r['entries']) ? implode('', $r['entries']) : null),
            self::dotaz($host, DNS_TXT),
        )));
    }

    private static function dotaz(string $host, int $typ): array
    {
        if (self::$resolver) {
            return (self::$resolver)($host, $typ);
        }

        return @dns_get_record($host, $typ) ?: [];
    }
}
