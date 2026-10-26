<?php

namespace App\Platby\Brany;

use App\Platby\ChybaBrany;
use App\Platby\MetodaPlatby;
use App\Platby\Platba;
use App\Platby\Rezim;
use App\Platby\StavPlatby;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Comgate – REST API 2.0 (https://apidoc.comgate.cz/en/api/rest/).
 *
 * Přihlášení HTTP Basic (merchant : heslo z Klientského portálu Comgate),
 * JSON, částky v haléřích. Platba: POST payment.json → redirect; stav:
 * GET payment/transId/{id}.json; zrušení: DELETE tamtéž (jen PENDING);
 * vrácení: POST refund.json (jen PAID); ověření údajů: GET method.json.
 *
 * Výsledek platby Comgate posílá na „URL pro předání výsledku platby“, která
 * se nastavuje v Klientském portálu (Integrace → Nastavení obchodu) – adresu
 * ukazuje administrace (Nastavení → Platební brána). Obsahu se nevěří, stav se
 * vždy zjistí dotazem.
 */
class Comgate implements Brana
{
    public function __construct(
        private readonly string $merchant,
        private readonly string $heslo,
        private readonly Rezim $rezim = Rezim::Ostry,
        /** @var list<string> metody obchodu, které se zákazníkovi nenabízí (NastaveniPlateb::comgateVypnute) */
        private readonly array $vypnute = [],
    ) {}

    public function kod(): string
    {
        return 'comgate';
    }

    public function nazev(): string
    {
        return __('platby.comgate.comgate');
    }

    public function rezim(): Rezim
    {
        return $this->rezim;
    }

    /** Testovací platba Comgate (nic se nestrhne) – mimo ostrý režim. */
    private function test(): bool
    {
        return $this->rezim !== Rezim::Ostry;
    }

    public function zaloz(Platba $platba): ZalozenaPlatba
    {
        $navrat = route('platby.navrat', $platba);

        $odpoved = $this->json(fn (PendingRequest $h) => $h->post('payment.json', [
            'price' => $platba->castka,
            'curr' => $platba->mena,
            // Comgate: 1–16 znaků, ukáže se zákazníkovi a ve výpisu.
            'label' => Str::limit(Str::ascii($platba->popis) ?: 'Platba', 16, ''),
            'refId' => $platba->reference ?: $platba->verejne_id,
            // Předvolba (Apple Pay / Google Pay) otevře rovnou peněženku, jinak výběr metod
            // bez metod vypnutých v administraci.
            'method' => $platba->predvolba?->kodComgate() ?? self::vyraz($this->vypnute),
            'email' => $platba->email,
            'fullName' => $platba->celeJmeno() ?: $platba->email,
            'country' => 'CZ',
            'lang' => app()->getLocale(),
            'test' => $this->test(),
            // Návratové adresy s každou platbou – web nezávisí na tom, co je v portálu Comgate.
            'url_paid' => $navrat,
            'url_cancelled' => $navrat,
            'url_pending' => $navrat,
        ]), __('platby.comgate.zalozeni_platby'));

        if (blank($odpoved['transId'] ?? null) || blank($odpoved['redirect'] ?? null)) {
            throw new ChybaBrany(__('platby.comgate.comgate_nevratil_cislo_ani_adresu_platby'));
        }

        return new ZalozenaPlatba($odpoved['transId'], $odpoved['redirect'], self::bezTajemstvi($odpoved));
    }

    public function stav(Platba $platba): StavZBrany
    {
        $odpoved = $this->json(fn (PendingRequest $h) => $h->get('payment/transId/'.rawurlencode((string) $platba->externi_id).'.json'), __('platby.comgate.overeni_stavu'));

        $stav = match (strtoupper((string) ($odpoved['status'] ?? ''))) {
            'PAID' => StavPlatby::Zaplacena,
            'CANCELLED' => StavPlatby::Zrusena,
            // AUTHORIZED = předautorizace (šablona ji nepoužívá) – peníze ještě nepřišly.
            'PENDING', 'AUTHORIZED' => StavPlatby::Ceka,
            default => throw new ChybaBrany(__('platby.comgate.comgate_vratil_neznamy_stav', ['hodnota' => ($odpoved['status'] ?? '')])),
        };

        return new StavZBrany(
            stav: $stav,
            castka: is_numeric($odpoved['price'] ?? null) ? (int) $odpoved['price'] : null,
            metoda: $odpoved['method'] ?? null,
            poznamka: filled($odpoved['paymentErrorReason'] ?? null) ? __('platby.comgate.duvod', ['payment_error_reason' => $odpoved['paymentErrorReason']]) : null,
            data: self::bezTajemstvi($odpoved),
        );
    }

    /**
     * Apple Pay a Google Pay jsou u Comgate součást platby kartou: brána je ukáže,
     * když má obchod zapnutou kartu CARD_CZ_COMGATE nebo CARD_CZ_CSOB_2
     * (https://apidoc.comgate.cz/en/applepay-a-googlepay); předvolí se kódem
     * APPLEPAY_REDIRECT / GOOGLEPAY_REDIRECT.
     */
    public function predvolby(): array
    {
        $kody = collect(array_keys($this->metody() ?? []));
        $karta = $kody->contains(fn (string $k) => in_array($k, ['CARD_CZ_COMGATE', 'CARD_CZ_CSOB_2'], true));

        return array_values(array_filter(MetodaPlatby::cases(), fn (MetodaPlatby $m) => $karta
            || $kody->contains(fn (string $k) => str_starts_with($k, strtok($m->kodComgate(), '_')))));
    }

    /**
     * Metody, které má obchod v Comgate povolené (method.json): kód → název a skupina.
     * Null = Comgate teď neodpovídá. Výpis se drží 10 minut, chyba minutu –
     * aplikace se ptá často a brána nesmí čekat.
     *
     * @return array<string, array{nazev: string, skupina: string}>|null
     */
    public function metody(): ?array
    {
        $klic = 'platby:comgate:metody:'.hash('sha256', $this->merchant.'|'.$this->heslo.'|'.$this->rezim->value);

        if (is_array($ulozene = Cache::get($klic))) {
            return $ulozene['metody'];
        }

        try {
            $odpoved = $this->json(fn (PendingRequest $h) => $h->get('method.json', ['lang' => app()->getLocale(), 'curr' => 'CZK', 'country' => 'CZ']), __('platby.comgate.vypis_metod'));
        } catch (ChybaBrany) {
            Cache::put($klic, ['metody' => null], now()->addMinute());

            return null;
        }

        $metody = collect((array) ($odpoved['methods'] ?? []))
            ->mapWithKeys(function ($m) {
                $kod = strtoupper((string) (is_array($m) ? ($m['id'] ?? '') : $m));

                return $kod === '' ? [] : [$kod => [
                    'nazev' => (string) (is_array($m) ? ($m['name'] ?? $kod) : $kod),
                    'skupina' => (string) (is_array($m) ? ($m['groupLabel'] ?? $m['group'] ?? '') : ''),
                ]];
            })
            ->all();

        Cache::put($klic, ['metody' => $metody], now()->addMinutes(10));

        return $metody;
    }

    /**
     * Výraz pro `method` (https://apidoc.comgate.cz/en/metody-platebni-brany):
     * „ALL-KOD-KOD“ = vše povolené u obchodu kromě vypnutých.
     *
     * @param  list<string>  $vypnute
     */
    public static function vyraz(array $vypnute): string
    {
        $kody = array_values(array_unique(array_filter(array_map(
            fn ($k) => preg_replace('/[^A-Z0-9_]/', '', strtoupper((string) $k)), $vypnute))));

        return implode('-', ['ALL', ...$kody]);
    }

    public function umiZrusit(): bool
    {
        return true;
    }

    public function zrus(Platba $platba): void
    {
        $this->json(fn (PendingRequest $h) => $h->delete('payment/transId/'.rawurlencode((string) $platba->externi_id).'.json'), __('platby.comgate.zruseni_platby'));
    }

    public function umiVratit(): bool
    {
        return true;
    }

    public function vrat(Platba $platba, int $castka): void
    {
        $this->json(fn (PendingRequest $h) => $h->post('refund.json', [
            'transId' => $platba->externi_id,
            'amount' => $castka,
            'curr' => $platba->mena,
            'test' => $this->test(),
            'refId' => $platba->reference ?: $platba->verejne_id,
        ]), __('platby.comgate.vraceni_platby'));
    }

    public function overSpojeni(): string
    {
        $odpoved = $this->json(fn (PendingRequest $h) => $h->get('method.json', ['lang' => app()->getLocale(), 'curr' => 'CZK', 'country' => 'CZ']), __('platby.comgate.overeni_spojeni'));

        return __('platby.comgate.spojeni_s_comgate_funguje_platebnich', ['count' => count((array) ($odpoved['methods'] ?? []))]);
    }

    /** Comgate posílá v oznámení i heslo obchodu – musí sedět. */
    public function webhookPravy(Request $request): bool
    {
        $heslo = $request->input('secret');

        return blank($heslo) || hash_equals($this->heslo, (string) $heslo);
    }

    public static function zWebhooku(Request $request): array
    {
        return [
            'externi_id' => $request->input('transId') ?: null,
            'verejne_id' => null,
            'stav' => $request->input('status') ?: null,
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('platby.comgate.url'), '/').'/')
            ->withBasicAuth($this->merchant, $this->heslo)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(5);
    }

    /**
     * Odpověď → pole; HTTP chyba nebo `code` ≠ 0 = ChybaBrany s čitelným důvodem.
     * Výpis metod (method.json) vrací jen `methods` bez `code` – chybějící kód
     * u úspěšné HTTP odpovědi je v pořádku.
     */
    private function json(\Closure $pozadavek, string $co): array
    {
        try {
            /** @var Response $odpoved */
            $odpoved = $pozadavek($this->http());
        } catch (ConnectionException $e) {
            throw new ChybaBrany(__('platby.comgate.comgate_neodpovida', ['co' => $co, 'get_message' => $e->getMessage()]), previous: $e);
        }

        if ($odpoved->status() === 401 || $odpoved->status() === 403) {
            throw new ChybaBrany(__('platby.comgate.comgate_odmitl_prihlaseni_zkontroluj', ['co' => $co]));
        }

        $data = (array) $odpoved->json();

        if (! $odpoved->successful() || (isset($data['code']) && (int) $data['code'] !== 0)) {
            throw new ChybaBrany('Comgate: '.$co.' selhalo (HTTP '.$odpoved->status()
                .(isset($data['code']) ? __('platby.comgate.kod', ['code' => $data['code']]) : '').')'
                .(filled($data['message'] ?? null) ? ' – '.$data['message'] : '').'.');
        }

        return $data;
    }

    private static function bezTajemstvi(array $data): array
    {
        return array_diff_key($data, array_flip(['secret', 'merchant']));
    }
}
