<?php

namespace App\Support;

use App\Models\Nastaveni;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * Schránka, ze které aplikace posílá poštu (Administrace → Pošta).
 *
 * Skoro každý projekt něco posílá (formulář, upozornění, obnova hesla) a pošta
 * se nehostuje na našich serverech – schránky jsou u Seznamu. Nastavuje se
 * v aplikaci, ne v `.env`: mění se to (nové heslo, jiná schránka) a sahat kvůli
 * tomu na server je zbytečné.
 *
 * Heslo se ukládá ZAŠIFROVANÉ klíčem aplikace a zpátky do formuláře se nikdy
 * neposílá – Livewire by ho jinak vozil v prohlížeči. Dokud schránka není
 * nastavená, platí `.env` (lokálně a v testu `MAIL_MAILER=log`).
 */
class Posta
{
    public const VYCHOZI = [
        'uzivatel' => '',
        'jmeno' => '',
        'host' => 'smtp.seznam.cz',
        'port' => '465',
        'sifrovani' => 'smtps',
    ];

    public const SIFROVANI = [
        'smtps' => 'SSL/TLS (port 465)',
        'tls' => 'STARTTLS (port 587)',
    ];

    /**
     * Náhrada přihlášení k SMTP v testech: fn (array $nastaveni, string $heslo): void,
     * při chybě vyhodí výjimku. Testy se NESMÍ připojovat ke skutečnému Seznamu –
     * opakovaná chybná přihlášení by mohla zablokovat IP.
     */
    public static ?\Closure $prihlaseni = null;

    /** Heslo musí být ASCII: s diakritikou spadne přihlášení k SMTP dřív, než se zeptá serveru. */
    public const HESLO_ASCII = '/^[\x20-\x7E]*$/';

    /** Nastavení bez hesla (jen příznak, jestli je uložené). */
    public static function nacti(): array
    {
        return collect(self::VYCHOZI)
            ->map(fn ($vychozi, $klic) => self::cti($klic) ?? $vychozi)
            ->all() + ['heslo_ulozeno' => filled(self::cti('heslo'))];
    }

    /** Uloží nastavení; heslo jen když je vyplněné (prázdné = nechat stávající). */
    public static function uloz(array $data): void
    {
        foreach (array_keys(self::VYCHOZI) as $klic) {
            if (array_key_exists($klic, $data)) {
                self::zapis($klic, trim((string) $data[$klic]));
            }
        }

        if (filled($data['heslo'] ?? null)) {
            self::zapis('heslo', Crypt::encryptString((string) $data['heslo']));
        }

        // Platí hned (i pro zkušební e-mail v témže požadavku)…
        self::pouzij();
        Mail::forgetMailers();
        self::zkontroluj();
        // …a fronta drží mailer z doby, kdy nastartovala – musí se restartovat.
        Artisan::call('queue:restart');
    }

    public static function kompletni(): bool
    {
        $n = self::nacti();

        return filled($n['host']) && filled($n['uzivatel']) && $n['heslo_ulozeno'] && self::heslo() !== null;
    }

    /**
     * Přepne odesílání na nastavenou schránku. Volá se při vytvoření správce
     * pošty (AppServiceProvider), takže platí pro všechno, co aplikace posílá.
     * Bez kompletního nastavení nic nemění a platí `.env`.
     */
    public static function pouzij(): void
    {
        try {
            if (! self::kompletni()) {
                return;
            }
        } catch (Throwable) {
            return;   // bez databáze (instalace, první migrace) platí .env
        }

        $n = self::nacti();

        config([
            'mail.default' => 'schranka',
            'mail.mailers.schranka' => [
                'transport' => 'smtp',
                'scheme' => $n['sifrovani'] === 'smtps' ? 'smtps' : 'smtp',
                'host' => $n['host'],
                'port' => (int) $n['port'],
                'username' => $n['uzivatel'],
                'password' => self::heslo(),
                'timeout' => 20,
            ],
            // Seznam odmítne odesílatele jiného než přihlášenou schránku.
            'mail.from' => ['address' => $n['uzivatel'], 'name' => $n['jmeno'] ?: config('app.name')],
        ]);
    }

    /**
     * Zkusí se přihlásit k SMTP – nic neposílá. Heslo z formuláře, když je
     * vyplněné, jinak uložené.
     *
     * @return array{ok: bool, zprava: string}
     */
    public static function otestuj(array $data): array
    {
        $n = array_merge(self::nacti(), array_filter($data, fn ($v) => $v !== null && $v !== ''));
        $heslo = filled($data['heslo'] ?? null) ? (string) $data['heslo'] : self::heslo();

        if (blank($n['uzivatel']) || blank($heslo)) {
            return ['ok' => false, 'zprava' => 'Vyplň schránku a heslo.'];
        }

        try {
            if (self::$prihlaseni) {
                (self::$prihlaseni)($n, (string) $heslo);
            } else {
                $smtp = new EsmtpTransport($n['host'], (int) $n['port'], $n['sifrovani'] === 'smtps');
                $smtp->setUsername($n['uzivatel']);
                $smtp->setPassword((string) $heslo);
                $smtp->start();
                $smtp->stop();
            }

            return ['ok' => true, 'zprava' => 'Přihlášení k '.$n['host'].' v pořádku.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'zprava' => self::srozumitelne($e->getMessage(), $n['uzivatel'])];
        }
    }

    /**
     * Přihlásí se k uložené schránce a výsledek si zapamatuje (příkaz posta:kontrola,
     * každých 6 hodin a po uložení). Konfigurace může vypadat správně a heslo být
     * staré – po změně hesla schránky by jinak poptávky tiše přestaly odcházet.
     *
     * @return array{nastavena: bool, ok: ?bool, zprava: ?string, kdy: ?string}
     */
    public static function zkontroluj(): array
    {
        if (! self::kompletni()) {
            $vysledek = ['nastavena' => false, 'ok' => null, 'zprava' => null, 'kdy' => now()->toIso8601String()];
        } else {
            $test = self::otestuj([]);
            $vysledek = ['nastavena' => true, 'ok' => $test['ok'], 'zprava' => $test['zprava'], 'kdy' => now()->toIso8601String()];
        }

        self::zapis('kontrola', json_encode($vysledek));

        return $vysledek;
    }

    /** Poslední výsledek kontroly – pro /zdravi (portál) a stránku nastavení. */
    public static function posledniKontrola(): array
    {
        $ulozeno = json_decode((string) self::cti('kontrola'), true);

        return is_array($ulozeno) ? $ulozeno : ['nastavena' => self::kompletni(), 'ok' => null, 'zprava' => null, 'kdy' => null];
    }

    /** Chyba SMTP lidsky – technická hláška Seznamu nikomu neřekne, co dělat. */
    public static function srozumitelne(string $zprava, string $schranka): string
    {
        if (str_contains($zprava, '535') || stripos($zprava, 'authenticat') !== false) {
            return 'Seznam heslo odmítl – zkontroluj schránku a heslo.'
                .(self::bezplatna($schranka) ? ' U bezplatné schránky musí být v nastavení e-mailu povolený přístup z jiných aplikací (IMAP/SMTP).' : '');
        }

        if (stripos($zprava, 'Connection') !== false || stripos($zprava, 'timed out') !== false) {
            return 'K serveru pošty se nepodařilo připojit ('.mb_substr($zprava, 0, 150).').';
        }

        return mb_substr($zprava, 0, 250);
    }

    /**
     * Doména schránky pro portál (simren:zdravi --json → posta.domena): podle ní
     * portál sám zapíše DNS pošty Seznamu (MX, SPF, DKIM, DMARC), když je doména
     * v jeho zóně. Jen doména, nikdy celá adresa – a jen u schránky Email Profi
     * (server Seznamu, vlastní doména); u bezplatné schránky nebo jiného
     * poskytovatele null, DNS Seznamu by tam nepatřily.
     */
    public static function domenaSchranky(): ?string
    {
        $n = self::nacti();
        $schranka = strtolower(trim((string) $n['uzivatel']));
        $host = strtolower(rtrim(trim((string) $n['host']), '.'));

        if (! str_contains($schranka, '@') || self::bezplatna($schranka) || ! ($host === 'seznam.cz' || str_ends_with($host, '.seznam.cz'))) {
            return null;
        }

        $domena = substr((string) strrchr($schranka, '@'), 1);

        return preg_match('/\A([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z][a-z0-9-]*\z/', $domena) === 1 ? $domena : null;
    }

    /** Bezplatná schránka Seznamu – doména není klientova, DNS se nenastavuje. */
    public static function bezplatna(?string $schranka): bool
    {
        $domena = strtolower((string) substr((string) strrchr((string) $schranka, '@'), 1));

        return in_array($domena, ['seznam.cz', 'email.cz', 'post.cz', 'spoluzaci.cz', 'stream.cz'], true);
    }

    /** Čím se teď opravdu posílá – pro stránku nastavení. */
    public static function popisStavu(): string
    {
        if (self::kompletni()) {
            $kontrola = self::posledniKontrola();

            return 'Posílá se přes schránku '.self::nacti()['uzivatel'].'.'.match ($kontrola['ok'] ?? null) {
                false => ' POZOR: poslední přihlášení ke schránce selhalo ('.$kontrola['zprava'].') – nejspíš se změnilo heslo.',
                true => ' Přihlášení naposledy ověřeno '.\Illuminate\Support\Carbon::parse($kontrola['kdy'])->diffForHumans().'.',
                default => '',
            };
        }

        return match (config('mail.default')) {
            'log' => 'Schránka není nastavená – e-maily se jen zapisují do logu a nikomu neodejdou.',
            'array' => 'Schránka není nastavená – e-maily se nikam neposílají.',
            default => 'Schránka není nastavená – posílá se podle nastavení serveru (.env).',
        };
    }

    private static function heslo(): ?string
    {
        $sifra = self::cti('heslo');

        try {
            return $sifra ? Crypt::decryptString($sifra) : null;
        } catch (Throwable) {
            // Jiný klíč aplikace (třeba obnovená záloha) – heslo je potřeba zadat znovu.
            return null;
        }
    }

    // ---- Úložiště. Při převodu do projektu s jiným modelem nastavení se mění jen tyhle dvě metody. ----

    private static function cti(string $klic): ?string
    {
        return Nastaveni::hodnota('posta.'.$klic);
    }

    private static function zapis(string $klic, string $hodnota): void
    {
        Nastaveni::nastav('posta.'.$klic, $hodnota);
    }
}
