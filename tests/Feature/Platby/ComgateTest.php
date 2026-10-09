<?php

namespace Tests\Feature\Platby;

use App\Platby\ChybaBrany;
use App\Platby\NastaveniPlateb;
use App\Platby\Platba;
use App\Platby\Rezim;
use App\Platby\StavPlatby;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Comgate REST API 2.0 jako brána klienta v ostrém režimu na produkci. */
class ComgateTest extends TestCase
{
    use SPlatbami;

    private const URL = 'https://payments.comgate.cz/v2.0';

    private string $stav = 'PENDING';

    private bool $chybaZalozeni = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->produkce();
        NastaveniPlateb::uloz(['brana' => 'comgate', 'comgate_merchant' => '498621', 'comgate_secret' => 'tajne-heslo-obchodu']);
        NastaveniPlateb::oznacOvereno('comgate');
        NastaveniPlateb::uloz(['rezim' => NastaveniPlateb::REZIM_OSTRY]);

        Http::fake(fn (Request $r) => match (true) {
            $r->url() === self::URL.'/payment.json' && $this->chybaZalozeni => Http::response(['code' => 1309, 'message' => 'Incorrect amount'], 400),
            $r->url() === self::URL.'/payment.json' => Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'AB12-CD34-EF56', 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=AB12-CD34-EF56'], 201),
            $r->url() === self::URL.'/payment/transId/AB12-CD34-EF56.json' && $r->method() === 'GET' => Http::response([
                'code' => 0, 'message' => 'OK', 'test' => 'false', 'price' => '125050', 'curr' => 'CZK', 'transId' => 'AB12-CD34-EF56',
                'status' => $this->stav, 'method' => 'CARD_CZ_CSOB_2', 'secret' => 'tajne-heslo-obchodu',
            ]),
            $r->url() === self::URL.'/payment/transId/AB12-CD34-EF56.json' && $r->method() === 'DELETE' => Http::response(['code' => 0, 'message' => 'OK']),
            $r->url() === self::URL.'/refund.json' => Http::response(['code' => 0, 'message' => 'OK']),
            str_starts_with($r->url(), self::URL.'/method.json') => Http::response(['code' => 0, 'message' => 'OK', 'methods' => [['id' => 'CARD_CZ_CSOB_2'], ['id' => 'BANK_CZ_RB']]]),
            default => Http::response(['code' => 1400, 'message' => 'Bad request'], 400),
        });
    }

    public function test_ostra_platba_az_po_vraceni(): void
    {
        $platba = $this->platby()->zaloz($this->pozadavek());

        $this->assertSame(Rezim::Ostry, $platba->rezim);
        $this->assertSame('comgate', $platba->brana);
        $this->assertSame('AB12-CD34-EF56', $platba->externi_id);
        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/payment.json'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('498621:tajne-heslo-obchodu'))
            && $r['price'] === 125050 && $r['curr'] === 'CZK' && $r['test'] === false
            && mb_strlen($r['label']) <= 16 && $r['refId'] === '2026-0042'
            && $r['email'] === 'jana.novakova@example.cz' && $r['fullName'] === 'Jana Nováková'
            && $r['url_paid'] === 'https://pekarna.cz/platby/'.$platba->verejne_id.'/navrat');

        // Oznámení se špatným heslem = 403, bez dotazu na stav.
        $this->post('/platby/webhook/comgate', ['transId' => 'AB12-CD34-EF56', 'status' => 'PAID', 'secret' => 'zle'])->assertForbidden();
        $this->assertSame(StavPlatby::Ceka, $platba->refresh()->stav);

        $this->stav = 'PAID';
        $this->post('/platby/webhook/comgate', ['merchant' => '498621', 'transId' => 'AB12-CD34-EF56', 'status' => 'PAID', 'secret' => 'tajne-heslo-obchodu'])
            ->assertOk()->assertSee('code=0&message=OK', false);

        $platba->refresh();
        $this->assertSame(StavPlatby::Zaplacena, $platba->stav);
        $this->assertSame('CARD_CZ_CSOB_2', $platba->metoda);
        $this->assertArrayNotHasKey('secret', $platba->udalosti->last()->data['brana'], 'Heslo obchodu se do historie neukládá.');
        $this->assertArrayNotHasKey('secret', $platba->udalosti->last()->data['podnet']);

        $this->platby()->vrat($platba, 25000, 'Sleva za zpoždění');

        $this->assertSame(StavPlatby::CastecneVracena, $platba->refresh()->stav);
        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/refund.json' && $r['transId'] === 'AB12-CD34-EF56' && $r['amount'] === 25000 && $r['test'] === false);
    }

    public function test_zruseni_a_overeni_spojeni(): void
    {
        $platba = $this->platby()->zaloz($this->pozadavek());
        $this->platby()->zrus($platba);

        $this->assertSame(StavPlatby::Zrusena, $platba->refresh()->stav);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE');

        $this->assertStringContainsString('2 platebních metod', NastaveniPlateb::branaKlienta('comgate')->overSpojeni());
    }

    public function test_chyba_comgate_je_citelna_a_v_chybach(): void
    {
        $this->chybaZalozeni = true;

        try {
            $this->platby()->zaloz($this->pozadavek());
            $this->fail('Odmítnutá platba nesmí projít.');
        } catch (ChybaBrany $e) {
            $this->assertStringContainsString('kód 1309', $e->getMessage());
            $this->assertStringContainsString('Incorrect amount', $e->getMessage());
        }

        $this->assertSame(StavPlatby::Chyba, Platba::query()->first()->stav);
        $this->assertDatabaseHas('error_logs', ['exception' => ChybaBrany::class]);
    }
}
