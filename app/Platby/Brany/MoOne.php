<?php

namespace App\Platby\Brany;

use App\Platby\ChybaBrany;
use App\Platby\NastaveniPlateb;
use App\Platby\Platba;
use App\Platby\Rezim;
use App\Platby\StavPlatby;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Mo.one (ZNPay) – redirect flow podle integrační dokumentace (ZS-548).
 *
 * 1. POST /payment/api/auth/token {ClientID, ClientSecret} → JWT na hodinu
 *    (cache – token endpoint má limit 10 požadavků za minutu z IP),
 * 2. POST /payment/api/transactions/initiate → Transaction.PublicID a RedirectUrl,
 * 3. zákazník platí na stránce Mo.one a vrátí se na ReturnUrl?status=&transactionId=,
 * 4. webhook na CallbackUrl (camelCase, BEZ PODPISU) – jen podnět,
 * 5. GET /payment/api/transactions/{PublicID}/status – jediný autoritativní stav.
 * Zrušení: PUT …/cancel. Vrácení peněz API nemá – dělá se v aplikaci Mo.one.
 *
 * REST dokumentace uvádí PascalCase, ale API vrací camelCase – odpovědi se čtou bez ohledu na
 * velikost písmen (pole()); odesíláme PascalCase. Webhook je camelCase („transactionPublicID“, „externalID“).
 * Test: https://api-test.znpay.tech, produkce: https://api.znpay.tech.
 */
class MoOne implements Brana
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $url,
        private readonly Rezim $rezim,
    ) {}

    public function kod(): string
    {
        return 'moone';
    }

    public function nazev(): string
    {
        return 'Mo.one';
    }

    public function rezim(): Rezim
    {
        return $this->rezim;
    }

    public function zaloz(Platba $platba): ZalozenaPlatba
    {
        $telo = [
            // Částka v Kč (decimal) – u nás haléře.
            'Amount' => round($platba->castka / 100, 2),
            'CurrencyCode' => $platba->mena,
            'ReturnUrl' => route('platby.navrat', $platba),
            'ExternalTransactionID' => $platba->verejne_id,
        ];

        // Mo.one odmítne webhook na localhost a privátní adresy (SSRF ochrana) –
        // lokálně se stav zjistí po návratu zákazníka a plánovačem.
        if (NastaveniPlateb::verejnaAdresa(route('platby.webhook', 'moone'))) {
            $telo['CallbackUrl'] = route('platby.webhook', 'moone');
        }

        $odpoved = $this->volej(fn (PendingRequest $h) => $h->post('payment/api/transactions/initiate', $telo), __('platby.mo_one.zalozeni_platby'));
        $transakce = (array) self::pole($odpoved, 'Transaction');
        $id = self::pole($transakce, 'PublicID');
        $adresa = self::pole($transakce, 'RedirectUrl');

        if (blank($id) || blank($adresa)) {
            throw new ChybaBrany(__('platby.mo_one.mo_one_nevratilo_cislo_ani_adresu'));
        }

        return new ZalozenaPlatba((string) $id, (string) $adresa, $transakce);
    }

    public function stav(Platba $platba): StavZBrany
    {
        $odpoved = $this->volej(fn (PendingRequest $h) => $h->get('payment/api/transactions/'.rawurlencode((string) $platba->externi_id).'/status'), __('platby.mo_one.overeni_stavu'));

        $stav = self::pole($odpoved, 'Status');
        $castka = self::pole($odpoved, 'Amount');

        return new StavZBrany(
            stav: self::stavZ(is_string($stav) ? $stav : null),
            castka: is_numeric($castka) ? (int) round(((float) $castka) * 100) : null,
            metoda: null,
            poznamka: $stav === 'InvestigationNeeded' ? __('platby.mo_one.mo_one_platbu_proveruje') : null,
            data: $odpoved,
        );
    }

    /**
     * Mo.one metodu předvolit neumí (initiate má jen částku, měnu a adresy) –
     * co platební stránka nabídne, rozhoduje Mo.one.
     */
    public function predvolby(): array
    {
        return [];
    }

    public function umiZrusit(): bool
    {
        return true;
    }

    public function zrus(Platba $platba): void
    {
        $this->volej(fn (PendingRequest $h) => $h->put('payment/api/transactions/'.rawurlencode((string) $platba->externi_id).'/cancel'), __('platby.mo_one.zruseni_platby'));
    }

    public function umiVratit(): bool
    {
        return false;
    }

    public function vrat(Platba $platba, int $castka): void
    {
        throw new ChybaBrany(__('platby.mo_one.mo_one_vraceni_pres_api_neumi'));
    }

    public function overSpojeni(): string
    {
        Cache::forget($this->klicTokenu());
        $this->token();

        return __('platby.mo_one.prihlaseni_k_mo_one_funguje', ['url' => parse_url($this->url, PHP_URL_HOST)]);
    }

    /** Mo.one webhooky nepodepisuje – pravost dává až dotaz na stav. */
    public function webhookPravy(Request $request): bool
    {
        return true;
    }

    public static function zWebhooku(Request $request): array
    {
        return [
            'externi_id' => $request->input('transactionPublicID') ?: null,
            'verejne_id' => $request->input('externalID') ?: null,
            'stav' => $request->input('status') ?: null,
        ];
    }

    public static function stavZ(?string $stav): StavPlatby
    {
        return match ($stav) {
            'Success' => StavPlatby::Zaplacena,
            'Fail' => StavPlatby::Zamitnuta,
            'Cancelled' => StavPlatby::Zrusena,
            'Created', 'Initiated', 'Processing', 'InvestigationNeeded' => StavPlatby::Ceka,
            default => throw new ChybaBrany(__('platby.mo_one.mo_one_vratilo_neznamy_stav', ['stav' => $stav])),
        };
    }

    /** Volání s tokenem; při 401 (zrušený token) jednou s novým. */
    private function volej(\Closure $pozadavek, string $co, bool $znovu = true): array
    {
        try {
            /** @var Response $odpoved */
            $odpoved = $pozadavek($this->http()->withToken($this->token()));
        } catch (ConnectionException $e) {
            throw new ChybaBrany(__('platby.mo_one.mo_one_neodpovida', ['co' => $co, 'get_message' => $e->getMessage()]), previous: $e);
        }

        if ($odpoved->status() === 401 && $znovu) {
            Cache::forget($this->klicTokenu());

            return $this->volej($pozadavek, $co, false);
        }

        if (! $odpoved->successful()) {
            throw new ChybaBrany('Mo.one: '.$co.' selhalo (HTTP '.$odpoved->status().')'.self::duvod($odpoved).'.');
        }

        return (array) $odpoved->json();
    }

    private function token(): string
    {
        $klic = $this->klicTokenu();

        if ($token = Cache::get($klic)) {
            return $token;
        }

        // Jeden pro všechny souběžné požadavky – limit 10 za minutu.
        return Cache::lock($klic.':zamek', 15)->block(10, function () use ($klic) {
            if ($token = Cache::get($klic)) {
                return $token;
            }

            try {
                $odpoved = $this->http()->post('payment/api/auth/token', [
                    'ClientID' => $this->clientId,
                    'ClientSecret' => $this->clientSecret,
                ]);
            } catch (ConnectionException $e) {
                throw new ChybaBrany(__('platby.mo_one.mo_one_neodpovida_prihlaseni', ['get_message' => $e->getMessage()]), previous: $e);
            }

            if ($odpoved->status() === 403) {
                throw new ChybaBrany(__('platby.mo_one.mo_one_odmitlo_pristupove_udaje_client'));
            }

            if ($odpoved->status() === 429) {
                throw new ChybaBrany(__('platby.mo_one.mo_one_prilis_mnoho_prihlaseni_za'));
            }

            $token = self::pole((array) $odpoved->json(), 'AccessToken');

            if (! $odpoved->successful() || blank($token)) {
                throw new ChybaBrany(__('platby.mo_one.mo_one_prihlaseni_selhalo_http', ['status' => $odpoved->status(), 'duvod' => self::duvod($odpoved)]));
            }

            // Platí hodinu; obnoví se o dvě minuty dřív.
            Cache::put($klic, $token, max(60, (int) (self::pole((array) $odpoved->json(), 'ExpiresIn') ?? 3600) - 120));

            return $token;
        });
    }

    private function klicTokenu(): string
    {
        return 'platby:moone:token:'.hash('sha256', $this->url.'|'.$this->clientId.'|'.$this->clientSecret);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->url, '/').'/')
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(5);
    }

    /**
     * Pole z odpovědi BEZ ohledu na velikost písmen (dokumentace píše PascalCase,
     * skutečné API vrací camelCase); vnořené přes tečku, např. „Transaction.PublicID“.
     */
    public static function pole(mixed $data, string $cesta): mixed
    {
        foreach (explode('.', $cesta) as $klic) {
            if (! is_array($data)) {
                return null;
            }

            $nalezeno = null;

            foreach ($data as $k => $hodnota) {
                if (strcasecmp((string) $k, $klic) === 0) {
                    $nalezeno = $hodnota;
                    break;
                }
            }

            $data = $nalezeno;
        }

        return $data;
    }

    private static function duvod(Response $odpoved): string
    {
        $data = (array) $odpoved->json();
        $text = self::pole($data, 'Message') ?? self::pole($data, 'title');

        return is_string($text) && $text !== '' ? ' – '.mb_substr($text, 0, 200) : '';
    }
}
