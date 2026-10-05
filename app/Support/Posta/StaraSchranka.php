<?php

namespace App\Support\Posta;

use App\Models\Nastaveni;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Stará schránka aplikace z doby před Poštou – převezme se při prvním
 * propojení (Propojit s poštou → „Převzít stávající schránku“).
 *
 * Kde bývá (v tomhle pořadí):
 *  1. `nastaveni` – starší šablona (Administrace → Pošta): posta.uzivatel,
 *     posta.heslo (zašifrované), posta.host, posta.port, posta.sifrovani
 *     (smtps | tls), posta.jmeno,
 *  2. `.env` – MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD… (mailer smtp; místní
 *     server a prázdné heslo se nepočítají).
 *
 * Postup: Propojeni::dokonci() pošle údaje (i heslo) Poště ze serveru
 * aplikace (POST /prevzeti-schranky), aplikace pak pošle zkušební e-mail
 * a jakmile Pošta potvrdí odeslání (webhook / dotaz na stav), smaz()
 * starou schránku z databáze odstraní (hlavně heslo). SMTP v .env aplikace
 * smazat neumí – .env zakládá portál; stránka Pošta to připomene.
 *
 * Projekt s jiným úložištěm staré schránky (vlastní model, tabulka firmy,
 * IMAP Odeslaných…) mění jen cti() a smaz() – docs/prevod-na-postu.md.
 */
class StaraSchranka
{
    /** Klíče staré schránky v `nastaveni` (smaže je smaz()). */
    public const KLICE = ['uzivatel', 'heslo', 'host', 'port', 'sifrovani', 'jmeno', 'kontrola'];

    /**
     * Údaje staré schránky i s heslem (jen pro odeslání Poště), nebo null.
     *
     * @return array{adresa: string, jmeno: ?string, uzivatel: string, heslo: string, smtp_host: string, smtp_port: int, smtp_sifrovani: string, imap_zapnuto: bool, imap_host: ?string, imap_port: ?int, imap_sifrovani: ?string, imap_slozka: ?string, zdroj: string}|null
     */
    public static function zjisti(): ?array
    {
        try {
            $udaje = static::cti();
        } catch (Throwable) {
            return null;   // bez databáze (instalace) není co převzít
        }

        if (! $udaje || blank($udaje['heslo'] ?? null) || blank($udaje['smtp_host'] ?? null)
            || ! filter_var($udaje['adresa'] ?? null, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $udaje['adresa'] = strtolower(trim($udaje['adresa']));

        return $udaje + ['imap_zapnuto' => false, 'imap_host' => null, 'imap_port' => null, 'imap_sifrovani' => null, 'imap_slozka' => null];
    }

    /** Totéž bez hesla – pro zobrazení. @return array{adresa: string, smtp_host: string, zdroj: string}|null */
    public static function popis(): ?array
    {
        $udaje = static::zjisti();

        return $udaje ? ['adresa' => $udaje['adresa'], 'smtp_host' => $udaje['smtp_host'], 'zdroj' => $udaje['zdroj']] : null;
    }

    /**
     * Zkušební e-mail po převzetí: id zprávy v Poště si zapamatovat – až ho
     * Pošta potvrdí jako odeslaný, stará schránka se smaže (poDoruceni).
     */
    public static function zkouska(string $postaId): void
    {
        $prevzeti = Propojeni::prevzeti();

        if ($prevzeti && empty($prevzeti['smazano']) && ($prevzeti['ok'] ?? false)) {
            Propojeni::zapisPrevzeti(['zkouska_id' => $postaId] + $prevzeti);
        }
    }

    /** Pošta potvrdila odeslání zprávy (Webhook::aktualizuj): byla to zkouška po převzetí? */
    public static function poDoruceni(string $postaId): void
    {
        $prevzeti = Propojeni::prevzeti();

        if (! $prevzeti || ! empty($prevzeti['smazano']) || ($prevzeti['zkouska_id'] ?? null) !== $postaId) {
            return;
        }

        static::smazPoPrevzeti();
    }

    /** Smaže starou schránku a poznamená to k převzetí. */
    public static function smazPoPrevzeti(): void
    {
        $smazano = static::smaz();
        $prevzeti = Propojeni::prevzeti() ?? [];

        Propojeni::zapisPrevzeti(['smazano' => now()->toIso8601String(), 'env_zbyva' => ! $smazano && ($prevzeti['zdroj'] ?? null) === 'env'] + $prevzeti);
    }

    // ---- Úložiště. Při převodu projektu s jinou starou schránkou se mění jen cti() a smaz(). ----

    /** Stará schránka z úložiště (null = žádná). Heslo rozšifrované. */
    protected static function cti(): ?array
    {
        // 1) Starší šablona: Administrace → Pošta (klíče posta.* v nastaveni, jen skutečné řádky).
        $radky = Nastaveni::query()->whereIn('klic', array_map(fn ($k) => 'posta.'.$k, self::KLICE))->pluck('hodnota', 'klic');
        $heslo = self::desifruj($radky['posta.heslo'] ?? null);

        if ($heslo !== null && filled($radky['posta.uzivatel'] ?? null)) {
            $uzivatel = trim((string) $radky['posta.uzivatel']);
            $sifrovani = (string) ($radky['posta.sifrovani'] ?? 'smtps');

            return [
                // Seznam: přihlašovací jméno = adresa; jinak odesílatel z konfigurace.
                'adresa' => filter_var($uzivatel, FILTER_VALIDATE_EMAIL) ? $uzivatel : (string) config('mail.from.address'),
                'jmeno' => filled($radky['posta.jmeno'] ?? null) ? (string) $radky['posta.jmeno'] : null,
                'uzivatel' => $uzivatel,
                'heslo' => $heslo,
                'smtp_host' => trim((string) ($radky['posta.host'] ?? 'smtp.seznam.cz')),
                'smtp_port' => (int) ($radky['posta.port'] ?? 465),
                'smtp_sifrovani' => $sifrovani === 'smtps' ? 'smtps' : 'starttls',
                'zdroj' => 'nastaveni',
            ];
        }

        // 2) .env (MAIL_*) – jen skutečný server s heslem.
        $smtp = (array) config('mail.mailers.smtp');
        $host = strtolower(trim((string) ($smtp['host'] ?? '')));
        $heslo = (string) ($smtp['password'] ?? '');

        if ($host === '' || in_array($host, ['127.0.0.1', 'localhost', 'mailpit', 'mailhog', '::1'], true)
            || blank($smtp['username'] ?? null) || $heslo === '' || $heslo === 'null') {
            return null;
        }

        $port = (int) ($smtp['port'] ?? 587);
        $adresa = (string) (config('mail.from.address') ?: $smtp['username']);

        return [
            'adresa' => filter_var($adresa, FILTER_VALIDATE_EMAIL) ? $adresa : (string) $smtp['username'],
            'jmeno' => filled(config('mail.from.name')) ? (string) config('mail.from.name') : null,
            'uzivatel' => (string) $smtp['username'],
            'heslo' => $heslo,
            'smtp_host' => $host,
            'smtp_port' => $port,
            'smtp_sifrovani' => ($smtp['scheme'] ?? null) === 'smtps' || $port === 465 ? 'smtps' : 'starttls',
            'zdroj' => 'env',
        ];
    }

    /** Smaže starou schránku z úložiště. false = nejde (je v .env – ten spravuje portál). */
    protected static function smaz(): bool
    {
        $radky = Nastaveni::query()->whereIn('klic', array_map(fn ($k) => 'posta.'.$k, self::KLICE))->pluck('klic');

        foreach ($radky as $klic) {
            Nastaveni::smaz($klic);
        }

        return $radky->isNotEmpty();
    }

    private static function desifruj(?string $sifra): ?string
    {
        try {
            return $sifra ? Crypt::decryptString($sifra) : null;
        } catch (Throwable) {
            return null;   // jiný klíč aplikace – heslo nejde přečíst, převzít není co
        }
    }
}
