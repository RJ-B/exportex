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
 * REST je PascalCase, webhook camelCase („transactionPublicID“, „externalID“).
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

        $odpoved = $this->volej(fn (PendingRequest $h) => $h->post('payment/api/transactions/initiate', $telo), 'založení platby');
        $transakce = (array) ($odpoved['Transaction'] ?? []);

        if (blank($transakce['PublicID'] ?? null) || blank($transakce['RedirectUrl'] ?? null)) {
            throw new ChybaBrany('Mo.one nevrátilo číslo ani adresu platby.');
        }

        return new ZalozenaPlatba($transakce['PublicID'], $transakce['RedirectUrl'], $transakce);
    }

    public function stav(Platba $platba): StavZBrany
    {
        $odpoved = $this->volej(fn (PendingRequest $h) => $h->get('payment/api/transactions/'.rawurlencode((string) $platba->externi_id).'/status'), 'ověření stavu');

        return new StavZBrany(
            stav: self::stavZ($odpoved['Status'] ?? null),
            castka: is_numeric($odpoved['Amount'] ?? null) ? (int) round(((float) $odpoved['Amount']) * 100) : null,
            metoda: null,
            poznamka: ($odpoved['Status'] ?? null) === 'InvestigationNeeded' ? 'Mo.one platbu prověřuje (InvestigationNeeded).' : null,
            data: $odpoved,
        );
    }

    public function umiZrusit(): bool
    {
        return true;
    }

    public function zrus(Platba $platba): void
    {
        $this->volej(fn (PendingRequest $h) => $h->put('payment/api/transactions/'.rawurlencode((string) $platba->externi_id).'/cancel'), 'zrušení platby');
    }

    public function umiVratit(): bool
    {
        return false;
    }

    public function vrat(Platba $platba, int $castka): void
    {
        throw new ChybaBrany('Mo.one vrácení přes API neumí – peníze se vrací v aplikaci Mo.one.');
    }

    public function overSpojeni(): string
    {
        Cache::forget($this->klicTokenu());
        $this->token();

        return 'Přihlášení k Mo.one funguje ('.parse_url($this->url, PHP_URL_HOST).').';
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
            default => throw new ChybaBrany('Mo.one vrátilo neznámý stav „'.$stav.'“.'),
        };
    }

    /** Volání s tokenem; při 401 (zrušený token) jednou s novým. */
    private function volej(\Closure $pozadavek, string $co, bool $znovu = true): array
    {
        try {
            /** @var Response $odpoved */
            $odpoved = $pozadavek($this->http()->withToken($this->token()));
        } catch (ConnectionException $e) {
            throw new ChybaBrany('Mo.one neodpovídá ('.$co.'): '.$e->getMessage(), previous: $e);
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
                throw new ChybaBrany('Mo.one neodpovídá (přihlášení): '.$e->getMessage(), previous: $e);
            }

            if ($odpoved->status() === 403) {
                throw new ChybaBrany('Mo.one odmítlo přístupové údaje (Client ID / Client secret) – zkontroluj je, případně vygeneruj nový secret v aplikaci Mo.one.');
            }

            if ($odpoved->status() === 429) {
                throw new ChybaBrany('Mo.one: příliš mnoho přihlášení za minutu – zkus to za chvíli.');
            }

            $token = $odpoved->json('AccessToken');

            if (! $odpoved->successful() || blank($token)) {
                throw new ChybaBrany('Mo.one: přihlášení selhalo (HTTP '.$odpoved->status().')'.self::duvod($odpoved).'.');
            }

            // Platí hodinu; obnoví se o dvě minuty dřív.
            Cache::put($klic, $token, max(60, (int) ($odpoved->json('ExpiresIn') ?? 3600) - 120));

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

    private static function duvod(Response $odpoved): string
    {
        $text = $odpoved->json('Message') ?? $odpoved->json('message') ?? $odpoved->json('title') ?? null;

        return is_string($text) && $text !== '' ? ' – '.mb_substr($text, 0, 200) : '';
    }
}
