<?php

namespace App\Support\Posta;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * E-mailová schránka webu (Nastavení → E-mailová schránka): ze které schránky
 * web posílá. Klient si vybere poskytovatele a zadá adresu, jméno a heslo –
 * schránka nemusí být na doméně webu (gmail.com, seznam.cz… jdou taky).
 *
 * API Pošty `/api/v1/schranka` (kontrakt „E-mailová schránka z webu“, 27. 10. 2026):
 * PUT nejdřív ověří přihlášení a nepovedené nic neuloží; nastavená schránka je
 * hned výchozím odesílatelem; DELETE = zpět na systémovou. Testovací web jen čte.
 *
 * Údaje drží Pošta. Heslo jde jen ze serveru webu do Pošty při uložení; web ho
 * NEUKLÁDÁ (ani do databáze, ani do logu, ani do cache) a do formuláře se nevrací.
 * V cache je jen stav bez tajných údajů, aby Přehled nevolal API pořád.
 */
class SchrankaWebu
{
    private const CACHE = 'posta.schranka_webu';

    /** Pole, která formulář smí poslat do Pošty. */
    private const POLE = ['poskytovatel', 'adresa', 'jmeno', 'heslo', 'uzivatel', 'smtp_host', 'smtp_port', 'smtp_sifrovani'];

    public function __construct(private Klient $klient) {}

    public static function zapnuto(): bool
    {
        return Propojeni::propojeno();
    }

    /**
     * Stav z Pošty: `schranka` (null = vlastní nemá), `odesila`, `systemova`, `smi_menit`, `proc_ne`, `dns`.
     *
     * @return array<string, mixed>
     */
    public function stav(bool $znovu = false): array
    {
        if ($znovu) {
            Cache::forget(self::CACHE);
        }

        return Cache::remember(self::CACHE, 600, fn () => $this->klient->spravuj('GET', '/schranka'));
    }

    /** Má web vlastní schránku? (Přehled → Dokončete nastavení; bez spojení s Poštou ne.) */
    public static function nastavena(): bool
    {
        return self::zapnuto() && rescue(fn () => filled(app(self::class)->stav()['schranka']['adresa'] ?? null), false, report: false);
    }

    /**
     * Nastaví nebo změní schránku. Pošta nejdřív ověří přihlášení – nepovedené
     * nic neuloží a skončí výjimkou s důvodem. Bez hesla se stejnou adresou =
     * jen změna jména.
     *
     * @param  array<string, mixed>  $data  poskytovatel, adresa, jmeno, heslo, uzivatel, smtp_*
     * @return array<string, mixed> odpověď Pošty (vysledek, schranka, overeni, stav)
     */
    public function uloz(array $data): array
    {
        $telo = array_filter(array_intersect_key($data, array_flip(self::POLE)), fn ($v) => $v !== null && $v !== '');

        try {
            $odpoved = $this->klient->spravuj('PUT', '/schranka', $telo + array_filter(['kdo' => self::kdo()]));
        } finally {
            Cache::forget(self::CACHE);
        }

        if (is_array($odpoved['stav'] ?? null)) {
            Cache::put(self::CACHE, $odpoved['stav'], 600);
        }

        return $odpoved;
    }

    /** Zkušební e-mail z nastavené schránky. @return array<string, mixed> stav zprávy */
    public function zkouska(string $komu): array
    {
        return $this->klient->spravuj('POST', '/schranka/zkouska', [
            'komu' => $komu,
            'predmet' => __('posta.schranky_firmy.zkusebni_e_mail_z', ['name' => config('app.name')]),
            'text' => __('posta.schranky_firmy.dobry_den_toto_je_zkusebni_e', ['adresa' => $this->stav()['schranka']['adresa'] ?? '']),
        ]);
    }

    /** SPF / DKIM / DMARC domény schránky ($zkontrolovat = hned znovu). @return array<string, mixed> */
    public function dns(bool $zkontrolovat = false): array
    {
        if (! $zkontrolovat && is_array($this->stav()['dns'] ?? null)) {
            return $this->stav()['dns'];
        }

        $dns = $zkontrolovat ? $this->klient->spravuj('POST', '/schranka/dns/kontrola') : $this->klient->spravuj('GET', '/schranka/dns');
        Cache::forget(self::CACHE);

        return $dns;
    }

    /** Zpět na systémovou schránku (schránku nastavenou webem Pošta smaže i s heslem, když ji nikdo jiný nepoužívá). */
    public function vratNaSystemovou(): void
    {
        try {
            $odpoved = $this->klient->spravuj('DELETE', '/schranka');
        } finally {
            Cache::forget(self::CACHE);
        }

        if (is_array($odpoved['stav'] ?? null)) {
            Cache::put(self::CACHE, $odpoved['stav'], 600);
        }
    }

    /** Adresa na přihlášení přes Google / Microsoft (podepsaná, 10 minut, jednou); návrat na $navrat. */
    public function oauth(string $poskytovatel, string $adresa, ?string $jmeno, string $navrat, string $stav): string
    {
        return (string) $this->klient->spravuj('POST', '/schranka/oauth/'.rawurlencode($poskytovatel), array_filter([
            'adresa' => strtolower(trim($adresa)), 'jmeno' => $jmeno, 'navrat' => $navrat, 'stav' => $stav, 'kdo' => self::kdo(),
        ]))['url'];
    }

    /** Předvolby Pošty (jen kvůli přihlášení přes Google / Microsoft); hodinu v cache, bez spojení prázdné. @return list<array<string, mixed>> */
    public function predvolby(): array
    {
        if (! self::zapnuto()) {
            return [];
        }

        return rescue(fn () => Cache::remember('posta.predvolby', 3600, fn () => (array) ($this->klient->spravuj('GET', '/predvolby')['predvolby'] ?? [])), [], report: false);
    }

    public static function zapomen(): void
    {
        Cache::forget(self::CACHE);
    }

    /** Chyba API jako text pro člověka (hlášky Pošty jsou pro člověka, heslo v nich nikdy není). */
    public static function chyba(Throwable $e): string
    {
        return $e->getMessage() ?: __('pruvodce.emailova_schranka.ted_nejde_nacist');
    }

    /** Kdo schránku nastavil (do Aktivity Pošty a pro portál) – jméno, ne e-mail. */
    private static function kdo(): ?string
    {
        $user = auth()->user();

        return $user ? trim(($user->jmeno ?? '').' '.($user->prijmeni ?? '')) ?: null : null;
    }
}
