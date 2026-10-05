<?php

namespace App\Support\Posta;

use App\Models\Nastaveni;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Propojení aplikace s Poštou (posta.simren.cz) jedním tlačítkem –
 * autorizační kód + PKCE, stejně jako s Fakturací:
 *
 *  1. zacni() – ověřovací klíč zůstane v session, Pošta dostane jeho otisk.
 *  2. Správce Pošty přidělí schránky, ze kterých aplikace smí posílat,
 *     a povolí; Pošta vrátí prohlížeč na /posta/propojeni/navrat s kódem.
 *  3. dokonci() – aplikace ze SVÉHO serveru vymění kód za token a tajemství
 *     webhooků a uloží je ZAŠIFROVANÉ. Prohlížečem nic tajného neprojde.
 *
 * Převzetí stávající schránky (StaraSchranka): má-li aplikace ještě starou
 * schránku (nastavení nebo .env), žádost nese její adresu a `prevzeti=1`;
 * když ho správce Pošty povolí, dokonci() pošle Poště ze serveru aplikace
 * její SMTP údaje i s heslem (POST /prevzeti-schranky) – jednou, hned.
 *
 * Z portálu (výchozí u webů, které portál vytvoří): portál založí aplikaci
 * v Poště sám a klíče předá příkazem `posta:z-portalu` (stdin, PrikazZPortalu)
 * → zPortalu(). Bez klikání; stará schránka se převezme stejně jako výš.
 *
 * Záloha: rucne() – adresa, API klíč a tajemství z Pošty (Aplikace → Přidat ručně),
 * nebo .env (POSTA_TOKEN, POSTA_WEBHOOK_TAJEMSTVI, POSTA_OD) – platí jen, když
 * v nastavení propojení není. Token si aplikace obnovuje sama (posta:obnov-token denně).
 *
 * Úložiště jen v cti()/zapis()/smaz() – při převodu do projektu s jiným
 * modelem nastavení se mění jen ty.
 */
class Propojeni
{
    private const SESSION = 'posta.propojeni';

    /** Obnovit token, když mu zbývá méně dní. */
    private const OBNOVIT_DNI_PRED = 30;

    public static function propojeno(): bool
    {
        return self::token() !== null;
    }

    public static function url(): string
    {
        return rtrim((string) (self::cti('url') ?: config('posta.url')), '/');
    }

    /** Token z nastavení; když tam není, z .env (POSTA_TOKEN). */
    public static function token(): ?string
    {
        return self::desifruj(self::cti('token')) ?? (filled(config('posta.token')) ? (string) config('posta.token') : null);
    }

    /** Odkud je propojení: nastaveni (tlačítko, portál, ručně) | env | null. */
    public static function zdroj(): ?string
    {
        return match (true) {
            self::desifruj(self::cti('token')) !== null => 'nastaveni',
            filled(config('posta.token')) => 'env',
            default => null,
        };
    }

    /** @return list<string> tajemství webhooků (aktuální; po výměně dočasně i předchozí; bez nich .env) */
    public static function webhookTajemstvi(): array
    {
        $ulozene = array_values(array_filter([self::desifruj(self::cti('webhook_tajemstvi')), self::desifruj(self::cti('webhook_tajemstvi_predchozi'))]));

        return $ulozene !== [] ? $ulozene : array_values(array_filter([(string) config('posta.webhook_tajemstvi')]));
    }

    /** Adresy, ze kterých aplikace smí posílat (přidělil správce Pošty; bez nich POSTA_OD z .env). @return list<array{adresa: string, jmeno: ?string}> */
    public static function adresy(): array
    {
        $ulozene = array_values(array_filter((array) json_decode((string) self::cti('adresy'), true), fn ($a) => is_array($a) && filled($a['adresa'] ?? null)));

        if ($ulozene === [] && filter_var(config('posta.od'), FILTER_VALIDATE_EMAIL)) {
            return [['adresa' => strtolower((string) config('posta.od')), 'jmeno' => null]];
        }

        return $ulozene;
    }

    /** Výchozí odesílatel (vybraný v administraci, jinak první přidělená adresa). */
    public static function odesilatel(): ?array
    {
        $vybrana = strtolower((string) self::cti('od'));

        return collect(self::adresy())->first(fn ($a) => strtolower($a['adresa']) === $vybrana) ?? (self::adresy()[0] ?? null);
    }

    public static function nastavOdesilatele(?string $adresa): void
    {
        $adresa ? self::zapis('od', strtolower($adresa)) : self::smaz('od');
    }

    /** @return array{url: string, propojeno: bool, kdy: ?string, aplikace: ?string, token_plati_do: ?string, adresy: list<array>, od: ?array, rucne: bool} */
    public static function popis(): array
    {
        $info = json_decode((string) self::cti('propojeno'), true) ?: [];

        return [
            'url' => self::url(),
            'propojeno' => self::propojeno(),
            'kdy' => $info['kdy'] ?? null,
            'aplikace' => $info['aplikace'] ?? null,
            'rucne' => (bool) ($info['rucne'] ?? false),
            'z_portalu' => (bool) ($info['portal'] ?? false),
            'zdroj' => self::zdroj(),
            'token_plati_do' => self::cti('token_plati_do'),
            'adresy' => self::adresy(),
            'od' => self::odesilatel(),
        ];
    }

    /**
     * Přepne odesílání na Poštu – volá se při vytvoření správce pošty
     * (AppServiceProvider). Bez propojení platí .env (lokálně MAIL_MAILER=log).
     */
    public static function pouzij(): void
    {
        try {
            if (! self::propojeno()) {
                return;
            }
        } catch (Throwable) {
            return;   // bez databáze (instalace, první migrace) platí .env
        }

        $od = self::odesilatel();

        config([
            'mail.default' => 'posta',
            'mail.mailers.posta' => ['transport' => 'posta'],
            'mail.from' => ['address' => $od['adresa'] ?? config('mail.from.address'), 'name' => $od['jmeno'] ?? config('mail.from.name')],
        ]);
    }

    /**
     * Adresa souhlasu v Poště; ověřovací klíč zůstane v session.
     * $prevzit = nabídnout Poště převzetí staré schránky aplikace (je-li).
     */
    public function zacni(string $url, bool $prevzit = true): string
    {
        $url = rtrim(trim($url), '/');

        if (! preg_match('~^https://[a-z0-9.-]+(:\d+)?$~i', $url) && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Adresa Pošty musí být https.');
        }

        $state = Str::random(40);
        $klic = Str::random(64);
        $stara = $prevzit ? StaraSchranka::popis() : null;
        session()->put(self::SESSION, ['state' => $state, 'klic' => $klic, 'url' => $url, 'prevzit' => $stara['adresa'] ?? null]);

        return $url.'/propojeni?'.http_build_query([
            'name' => (string) config('app.name'),
            'slug' => Str::slug(parse_url((string) config('app.url'), PHP_URL_HOST) ?: (string) config('app.name')),
            'app_url' => url('/'),
            'redirect_uri' => route('posta.propojeni.navrat'),
            'webhook_url' => route('posta.webhook'),
            'from' => $stara['adresa'] ?? config('mail.from.address'),
            'prevzeti' => $stara ? '1' : null,
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $klic, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    /** Dokončí propojení z návratové adresy; při chybě výjimka se zprávou pro člověka. */
    public function dokonci(array $dotaz): array
    {
        $ulozene = session()->pull(self::SESSION);

        // state musí sedět – jinak by šlo podstrčit cizí kód.
        if (! $ulozene || ! hash_equals($ulozene['state'], (string) ($dotaz['state'] ?? ''))) {
            throw new RuntimeException('Propojení nejde dokončit – začni ho prosím znovu tlačítkem Propojit s poštou.');
        }

        if (($dotaz['error'] ?? null) === 'access_denied') {
            throw new RuntimeException('Propojení bylo v Poště zamítnuto.');
        }

        $odpoved = Http::baseUrl($ulozene['url'])->acceptJson()->timeout(15)->post('/api/v1/propojeni/token', [
            'code' => (string) ($dotaz['code'] ?? ''),
            'code_verifier' => $ulozene['klic'],
            'redirect_uri' => route('posta.propojeni.navrat'),
        ]);

        if (! $odpoved->successful() || blank($odpoved->json('token'))) {
            throw new RuntimeException('Pošta propojení nepotvrdila ('.($odpoved->json('message') ?? 'HTTP '.$odpoved->status()).'). Zkus to znovu.');
        }

        $this->uloz($ulozene['url'], (string) $odpoved->json('token'), $odpoved->json('webhook_tajemstvi'), (array) $odpoved->json('aplikace'), rucne: false);

        $vysledek = $odpoved->json();

        // Správce Pošty povolil převzetí staré schránky – předat ji hned (okno je 10 minut, jednou).
        $povoleno = strtolower((string) $odpoved->json('prevzeti.adresa'));
        if ($povoleno !== '' && $povoleno === ($ulozene['prevzit'] ?? null)) {
            $vysledek['prevzeti_vysledek'] = $this->prevezmi($povoleno);
        }

        return $vysledek;
    }

    /**
     * Pošle Poště údaje staré schránky (i heslo – jen server aplikace → Pošta)
     * a zapamatuje si výsledek; stará schránka se smaže až po zkušebním e-mailu.
     *
     * @return array{ok: bool, zprava: string}
     */
    public function prevezmi(string $adresa): array
    {
        $stara = StaraSchranka::zjisti();

        if (! $stara || $stara['adresa'] !== $adresa) {
            return ['ok' => false, 'zprava' => 'Stará schránka '.$adresa.' už v aplikaci není – v Poště ji přidej ručně.'];
        }

        try {
            $odpoved = app(Klient::class)->prevezmi(array_diff_key($stara, ['zdroj' => 1]));
        } catch (Throwable $e) {
            self::zapisPrevzeti(['adresa' => $adresa, 'zdroj' => $stara['zdroj'], 'ok' => false, 'zprava' => $e->getMessage(), 'kdy' => now()->toIso8601String()]);

            return ['ok' => false, 'zprava' => 'Převzetí schránky se nepovedlo: '.$e->getMessage()];
        }

        if (isset($odpoved['aplikace']['adresy'])) {
            self::zapis('adresy', json_encode($odpoved['aplikace']['adresy'], JSON_UNESCAPED_UNICODE));
        }
        self::nastavOdesilatele($adresa);

        $overeni = $odpoved['schranka']['overeni'] ?? null;
        $ok = ($odpoved['vysledek'] ?? null) === 'prirazena' || ($overeni['ok'] ?? false);
        $zprava = match (true) {
            ($odpoved['vysledek'] ?? null) === 'prirazena' => 'Schránka '.$adresa.' už v Poště byla – je přidělená aplikaci.',
            $ok => 'Pošta převzala schránku '.$adresa.' a přihlášení ověřila.',
            default => 'Pošta schránku '.$adresa.' převzala, ale přihlášení nefunguje: '.($overeni['zprava'] ?? '?').' Oprav ho v Poště.',
        };

        self::zapisPrevzeti(['adresa' => $adresa, 'zdroj' => $stara['zdroj'], 'vysledek' => $odpoved['vysledek'] ?? null, 'ok' => $ok, 'zprava' => $zprava, 'kdy' => now()->toIso8601String()]);

        return ['ok' => $ok, 'zprava' => $zprava];
    }

    /** Stav převzetí staré schránky (adresa, zdroj, ok, zprava, zkouska_id, smazano…), nebo null. */
    public static function prevzeti(): ?array
    {
        $data = json_decode((string) self::cti('prevzeti'), true);

        return is_array($data) ? $data : null;
    }

    public static function zapisPrevzeti(?array $data): void
    {
        $data ? self::zapis('prevzeti', json_encode($data, JSON_UNESCAPED_UNICODE)) : self::smaz('prevzeti');
    }

    /** Ruční napojení (záloha): API klíč a tajemství webhooků z Pošty. Ověří ho dotazem /ja. */
    public function rucne(string $url, string $token, ?string $tajemstvi): array
    {
        $url = rtrim(trim($url), '/');
        $odpoved = Http::baseUrl($url.'/api/v1')->withToken(trim($token))->acceptJson()->timeout(15)->get('/ja');

        if (! $odpoved->successful()) {
            throw new RuntimeException($odpoved->status() === 401 ? 'Pošta klíč nepřijala (neplatný nebo zrušený).' : 'Pošta vrátila chybu '.$odpoved->status().'.');
        }

        $this->uloz($url, trim($token), filled($tajemstvi) ? trim($tajemstvi) : null, (array) $odpoved->json('aplikace'), rucne: true);

        return $odpoved->json('aplikace');
    }

    /** Ověří spojení (GET /ja) a obnoví přidělené adresy. @return array{ok: bool, zprava: string} */
    public function otestuj(): array
    {
        try {
            $aplikace = app(Klient::class)->ja();
        } catch (Throwable $e) {
            return ['ok' => false, 'zprava' => $e->getMessage()];
        }

        self::zapis('adresy', json_encode($aplikace['adresy'] ?? [], JSON_UNESCAPED_UNICODE));
        self::zapis('token_plati_do', (string) ($aplikace['token_plati_do'] ?? ''));

        return ['ok' => true, 'zprava' => 'Připojeno k Poště jako „'.($aplikace['nazev'] ?? '?').'“. Smí posílat z: '
            .(collect($aplikace['adresy'] ?? [])->pluck('adresa')->implode(', ') ?: 'žádné adresy – přiděl je v Poště').'.'];
    }

    /** Obnoví token, když brzy vyprší (plánovač denně). true = obnoven. */
    public function obnovToken(bool $vzdy = false): bool
    {
        $platiDo = self::cti('token_plati_do');

        if (! self::propojeno() || (! $vzdy && (blank($platiDo) || now()->parse($platiDo)->subDays(self::OBNOVIT_DNI_PRED)->isFuture()))) {
            return false;
        }

        $odpoved = app(Klient::class)->obnovToken();
        self::zapis('token', Crypt::encryptString((string) $odpoved['token']));
        self::zapis('token_plati_do', (string) ($odpoved['token_plati_do'] ?? ''));

        return true;
    }

    /**
     * Klíče od portálu (posta:z-portalu): portál aplikaci v Poště založil sám
     * a token, tajemství a přidělené adresy předal na server aplikace stdinem.
     * Uloží se stejně jako po Propojit s poštou. Když Pošta otevřela převzetí
     * staré schránky (prevzeti.adresa) a aplikace ji pořád má, hned ji předá.
     *
     * @param  array{url?: string, token?: string, webhook_tajemstvi?: ?string, aplikace?: array, prevzeti?: ?array}  $data
     * @return array{ok: bool, aplikace: ?string, prevzeti: ?array}
     */
    public function zPortalu(array $data): array
    {
        $url = rtrim(trim((string) ($data['url'] ?? '')), '/');
        $token = trim((string) ($data['token'] ?? ''));

        if (! preg_match('~^https://[a-z0-9.-]+(:\d+)?$~i', $url) && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Adresa Pošty musí být https.');
        }

        if (! str_starts_with($token, 'pst_') || strlen($token) > 200) {
            throw new RuntimeException('Token Pošty chybí nebo nemá správný tvar.');
        }

        $aplikace = (array) ($data['aplikace'] ?? []);
        $tajemstvi = filled($data['webhook_tajemstvi'] ?? null) ? (string) $data['webhook_tajemstvi'] : null;
        $this->uloz($url, $token, $tajemstvi, $aplikace, rucne: false, zPortalu: true);

        // Vybraný odesílatel, který už aplikace nesmí, zapomenout (platí první přidělená).
        $vybrana = strtolower((string) self::cti('od'));
        if ($vybrana !== '' && ! collect(self::adresy())->contains(fn ($a) => strtolower($a['adresa']) === $vybrana)) {
            self::smaz('od');
        }

        $prevzeti = null;
        $adresa = strtolower((string) ($data['prevzeti']['adresa'] ?? ''));
        if ($adresa !== '' && (StaraSchranka::popis()['adresa'] ?? null) === $adresa) {
            $prevzeti = $this->prevezmi($adresa);
        }

        return ['ok' => true, 'aplikace' => $aplikace['slug'] ?? null, 'prevzeti' => $prevzeti];
    }

    /** Zapomene propojení bez volání Pošty (portál aplikaci v Poště odpojil sám). */
    public function zapomen(): void
    {
        foreach (['token', 'webhook_tajemstvi', 'webhook_tajemstvi_predchozi', 'token_plati_do', 'propojeno', 'adresy', 'od', 'prevzeti'] as $klic) {
            self::smaz($klic);
        }
    }

    /** Odpojí aplikaci: Pošta hned zneplatní klíče, aplikace je zapomene. */
    public function odpoj(): void
    {
        if (self::propojeno()) {
            try {
                app(Klient::class)->odpoj();
            } catch (Throwable $e) {
                report($e);   // v Poště jde aplikaci odpojit i ručně
            }
        }

        foreach (['token', 'webhook_tajemstvi', 'webhook_tajemstvi_predchozi', 'token_plati_do', 'propojeno', 'adresy', 'od', 'prevzeti'] as $klic) {
            self::smaz($klic);
        }
    }

    private function uloz(string $url, string $token, ?string $tajemstvi, array $aplikace, bool $rucne, bool $zPortalu = false): void
    {
        $stare = self::cti('webhook_tajemstvi');

        self::zapis('url', $url);
        self::zapis('token', Crypt::encryptString($token));

        if ($tajemstvi) {
            // Předchozí tajemství chvíli dobíhá (Pošta podepisuje oběma) – nechat ho platit.
            $stare ? self::zapis('webhook_tajemstvi_predchozi', $stare) : self::smaz('webhook_tajemstvi_predchozi');
            self::zapis('webhook_tajemstvi', Crypt::encryptString($tajemstvi));
        }

        self::zapis('token_plati_do', (string) ($aplikace['token_plati_do'] ?? ''));
        self::zapis('adresy', json_encode($aplikace['adresy'] ?? [], JSON_UNESCAPED_UNICODE));
        self::zapis('propojeno', json_encode(['kdy' => now()->toIso8601String(), 'aplikace' => $aplikace['nazev'] ?? null, 'rucne' => $rucne, 'portal' => $zPortalu], JSON_UNESCAPED_UNICODE));
    }

    private static function desifruj(?string $sifra): ?string
    {
        try {
            return $sifra ? Crypt::decryptString($sifra) : null;
        } catch (Throwable) {
            return null;   // jiný klíč aplikace (obnovená záloha) – propojit znovu
        }
    }

    // ---- Úložiště. Při převodu do projektu s jiným modelem nastavení se mění jen tyhle tři metody. ----

    private static function cti(string $klic): ?string
    {
        return Nastaveni::hodnota('posta.'.$klic);
    }

    private static function zapis(string $klic, string $hodnota): void
    {
        Nastaveni::nastav('posta.'.$klic, $hodnota);
    }

    private static function smaz(string $klic): void
    {
        Nastaveni::smaz('posta.'.$klic);
    }
}
