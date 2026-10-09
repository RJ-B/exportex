<?php

namespace Tests\Feature\Platby;

use App\Platby\ChybaBrany;
use App\Platby\Platba;
use App\Platby\Rezim;
use App\Platby\StavPlatby;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Mo.one (testovací brána Sim&Ren): token se cachuje, REST v PascalCase,
 * webhook v camelCase bez podpisu → stav vždy dotazem na status.
 */
class MoOneTest extends TestCase
{
    use SPlatbami;

    private const URL = 'https://api-test.znpay.tech';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testovaciUdaje();
    }

    /** Co brána odpoví na dotaz na stav (mění se v průběhu testu). */
    private string $stav = 'Initiated';

    private float $castka = 1250.50;

    /** @var list<int> HTTP kódy, které status vrátí dřív než odpověď (401, 500…) */
    private array $chybyStavu = [];

    private string $token = 'jwt-1';

    /** PublicID, která brána přidělí (první UDPVPE, další UDPVPF…). */
    private string $dalsiId = 'UDPVPE';

    private function brana(string $stav = 'Initiated', float $castka = 1250.50): void
    {
        $this->stav = $stav;
        $this->castka = $castka;

        Http::fake(function (Request $r) {
            return match (true) {
                $r->url() === self::URL.'/payment/api/auth/token' => Http::response(['AccessToken' => $this->token, 'TokenType' => 'Bearer', 'ExpiresIn' => 3600]),
                $r->url() === self::URL.'/payment/api/transactions/initiate' => Http::response(['Transaction' => [
                    'PublicID' => $id = $this->dalsiId++, 'Amount' => 1250.50, 'TipAmount' => 0, 'CurrencyCode' => 'CZK',
                    'PayPointPublicID' => null, 'RedirectUrl' => 'https://app.mo.one/pay/'.$id,
                ]]),
                str_ends_with($r->url(), '/status') => ($kod = array_shift($this->chybyStavu))
                    ? Http::response(null, $kod)
                    : Http::response(['PublicID' => 'UDPVPE', 'Amount' => $this->castka, 'TipAmount' => 0, 'CurrencyCode' => 'CZK', 'Status' => $this->stav]),
                str_ends_with($r->url(), '/cancel') => Http::response(['PublicID' => 'UDPVPE', 'Status' => 'Cancelled']),
                default => Http::response(null, 404),
            };
        });
    }

    public function test_zalozeni_posle_pascal_case_a_presmeruje_na_mo_one(): void
    {
        $this->brana();
        config(['app.url' => 'https://pekarna.test.simren.cz']);
        URL::useOrigin('https://pekarna.test.simren.cz');
        AppServiceProvider::https();

        $platba = $this->platby()->zaloz($this->pozadavek());

        $this->assertSame(Rezim::Testovaci, $platba->rezim);
        $this->assertSame('moone', $platba->brana);
        $this->assertSame('UDPVPE', $platba->externi_id);
        $this->assertSame(StavPlatby::Ceka, $platba->stav);
        $this->get($platba->odkazKZaplaceni())->assertRedirect('https://app.mo.one/pay/UDPVPE');

        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/payment/api/auth/token'
            && $r['ClientID'] === '3f2a0000-0000-4000-8000-000000000001' && $r['ClientSecret'] === str_repeat('ab', 32));
        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/payment/api/transactions/initiate'
            && $r->hasHeader('Authorization', 'Bearer jwt-1')
            && $r['Amount'] == 1250.50
            && $r['CurrencyCode'] === 'CZK'
            && $r['ExternalTransactionID'] === $platba->verejne_id
            && $r['ReturnUrl'] === 'https://pekarna.test.simren.cz/platby/'.$platba->verejne_id.'/navrat'
            && $r['CallbackUrl'] === 'https://pekarna.test.simren.cz/platby/webhook/moone'
            && ! isset($r['PayPointPublicID']));
    }

    public function test_lokalne_bez_callbacku_a_token_jen_jednou(): void
    {
        $this->brana();

        $this->platby()->zaloz($this->pozadavek(['reference' => 'A']));
        $this->platby()->zaloz($this->pozadavek(['reference' => 'B']));

        Http::assertSentCount(3);   // 1× token + 2× initiate (limit 10 tokenů za minutu)
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/initiate') && ! isset($r['CallbackUrl']));
    }

    public function test_webhook_bez_podpisu_se_overi_dotazem_na_stav(): void
    {
        $this->brana('Initiated');
        $platba = $this->platby()->zaloz($this->pozadavek());

        // Webhook tvrdí Success, status API říká Initiated → zůstává čekat.
        $this->postJson('/platby/webhook/moone', [
            'transactionPublicID' => 'UDPVPE', 'externalID' => $platba->verejne_id, 'status' => 'Success',
            'amount' => 1250.50, 'currencyCode' => 'CZK', 'occurredAt' => '2026-10-09T12:00:00Z',
        ])->assertOk();
        $this->assertSame(StavPlatby::Ceka, $platba->refresh()->stav);
        $this->assertStringContainsString('Stav beze změny', (string) $platba->udalosti->last()->poznamka);

        $this->stav = 'Success';
        $this->postJson('/platby/webhook/moone', ['transactionPublicID' => 'UDPVPE', 'externalID' => $platba->verejne_id, 'status' => 'Success'])->assertOk();
        $this->postJson('/platby/webhook/moone', ['transactionPublicID' => 'UDPVPE', 'externalID' => $platba->verejne_id, 'status' => 'Success'])->assertOk();

        $this->assertSame(StavPlatby::Zaplacena, $platba->refresh()->stav);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === self::URL.'/payment/api/transactions/UDPVPE/status');
        $this->assertSame(1, $platba->udalosti->where('stav_na', StavPlatby::Zaplacena)->where('stav_z', StavPlatby::Ceka)->count());
    }

    public function test_neznama_platba_ve_webhooku_je_404_a_stav_nejde_overit_503(): void
    {
        $this->brana();
        $this->postJson('/platby/webhook/moone', ['transactionPublicID' => 'NEZNAMA', 'status' => 'Success'])->assertNotFound();

        $platba = $this->platby()->zaloz($this->pozadavek());
        $this->chybyStavu = [500];

        $this->postJson('/platby/webhook/moone', ['transactionPublicID' => 'UDPVPE', 'status' => 'Success'])->assertStatus(503);
        $this->assertSame(StavPlatby::Ceka, $platba->refresh()->stav);
        $this->assertDatabaseHas('error_logs', ['exception' => ChybaBrany::class]);
    }

    public function test_navrat_se_statusem_v_adrese_neverime(): void
    {
        $this->brana('Initiated');
        $platba = $this->platby()->zaloz($this->pozadavek());

        $this->get('/platby/'.$platba->verejne_id.'/navrat?status=success&transactionId=UDPVPE')->assertRedirect(route('platby.vysledek', $platba));

        $this->assertSame(StavPlatby::Ceka, $platba->refresh()->stav);
        $this->get(route('platby.vysledek', $platba))->assertSee('Čekáme na potvrzení platby');
    }

    public function test_jina_castka_nez_nase_se_neprijme(): void
    {
        $this->brana('Success', 1.00);
        $platba = $this->platby()->zaloz($this->pozadavek());

        $this->platby()->overStav($platba);

        $this->assertSame(StavPlatby::Ceka, $platba->refresh()->stav);
        $this->assertStringContainsString('nepřijato', (string) $platba->udalosti->last()->poznamka);
    }

    public function test_fail_a_cancel(): void
    {
        $this->brana('Fail');
        $platba = $this->platby()->zaloz($this->pozadavek());
        $this->platby()->overStav($platba);
        $this->assertSame(StavPlatby::Zamitnuta, $platba->refresh()->stav);

        $this->stav = 'Initiated';
        $druha = $this->platby()->zaloz($this->pozadavek(['reference' => 'B']));
        $this->platby()->zrus($druha);

        $this->assertSame(StavPlatby::Zrusena, $druha->refresh()->stav);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/UDPVPF/cancel'));
    }

    public function test_zruseny_token_se_jednou_obnovi(): void
    {
        $this->brana();
        $platba = $this->platby()->zaloz($this->pozadavek());

        // Mo.one token zrušilo (obměna secretu) – status vrátí 401, nový token projde.
        $this->token = 'jwt-2';
        $this->chybyStavu = [401];
        $this->stav = 'Success';

        $this->platby()->overStav($platba);

        $this->assertSame(StavPlatby::Zaplacena, $platba->refresh()->stav);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/status') && $r->hasHeader('Authorization', 'Bearer jwt-2'));
    }

    public function test_spatne_udaje_jsou_citelna_chyba(): void
    {
        Http::fake([self::URL.'/payment/api/auth/token' => Http::response(null, 403)]);

        try {
            $this->platby()->zaloz($this->pozadavek());
            $this->fail('Bez platného tokenu platba nevznikne.');
        } catch (ChybaBrany $e) {
            $this->assertStringContainsString('odmítlo přístupové údaje', $e->getMessage());
        }

        $this->assertSame(StavPlatby::Chyba, Platba::query()->first()->stav);
    }
}
