<?php

namespace Tests\Feature\Fakturace;

use App\Fakturace\Fakturace as KlientFakturace;
use App\Fakturace\FakturaceUdalost;
use App\Fakturace\Filament\Stranky\Fakturace;
use App\Fakturace\PodpisWebhooku;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zapnutý doplněk Fakturace: stav napojení (klíče od portálu), bankovní účet
 * pro doklady přes API fakturace (jen správce, testovací napojení jen čte),
 * vystavení dokladu a webhook s podpisem V2 (každé doručení jednou).
 */
class FakturaceTest extends TestCase
{
    use RefreshDatabase;

    private const TAJEMSTVI = 'tajemstvi-webhooku-fakturace-123';

    /** Účet, jak ho fakturace drží (PATCH ho mění). */
    private array $ucet = ['cislo' => null, 'iban' => null, 'iban_citelny' => null, 'swift' => null, 'banka' => null];

    public function createApplication()
    {
        putenv('SABLONA_DOPLNEK_FAKTURACE=1');

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['fakturace.url' => 'https://fakturace.test', 'fakturace.token' => 'fk_web', 'fakturace.webhook_tajemstvi' => self::TAJEMSTVI, 'fakturace.rezim' => 'produkce']);
    }

    protected function tearDown(): void
    {
        putenv('SABLONA_DOPLNEK_FAKTURACE');
        parent::tearDown();
    }

    private function falesnaFakturace(bool $lzeMenit = true): void
    {
        Http::fake(['fakturace.test/api/v1/firma' => function (Request $r) use ($lzeMenit) {
            if ($r->method() === 'PATCH') {
                if (! $lzeMenit) {
                    return Http::response(['message' => 'Testovací napojení údaje firmy jen čte.'], 403);
                }

                if ($r['cislo_uctu'] === '123456789/0800') {
                    return Http::response(['message' => 'Neplatné číslo účtu.', 'errors' => ['cislo_uctu' => ['Číslo účtu neprošlo kontrolou podle ČNB.']]], 422);
                }

                $this->ucet = ['cislo' => $r['cislo_uctu'], 'iban' => 'CZ6508000000192000145399', 'iban_citelny' => 'CZ65 0800 0000 1920 0014 5399', 'swift' => 'GIBACZPX', 'banka' => 'Česká spořitelna, a.s.'];
            }

            return Http::response(['firma' => ['nazev' => 'Pekárna Novák s.r.o.', 'bankovni_ucet' => $this->ucet], 'lze_menit' => $lzeMenit]);
        }]);
    }

    private function spravce(string $role = 'admin'): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => $role])->save());
    }

    public function test_stav_napojeni_podle_klicu_od_portalu(): void
    {
        $this->assertSame('napojeno', app(KlientFakturace::class)->stav()['stav']);

        config(['fakturace.rezim' => 'test']);
        $this->assertSame('test', app(KlientFakturace::class)->stav()['stav']);

        config(['fakturace.token' => null]);
        $this->assertSame('ceka', app(KlientFakturace::class)->stav()['stav']);
        $this->assertNull(app(KlientFakturace::class)->udajeFirmy()['firma']);
        Http::assertNothingSent();

        // FAKTURACE_URL ze starších .env i s /api/v1.
        config(['fakturace.url' => 'https://fakturace.test/api/v1']);
        $this->assertSame('https://fakturace.test/api/v1', KlientFakturace::api());
    }

    public function test_spravce_ulozi_bankovni_ucet_ve_fakturaci(): void
    {
        $this->falesnaFakturace();
        $this->assertContains(Fakturace::class, Filament::getPanel('admin')->getPages());

        Livewire::actingAs($this->spravce())->test(Fakturace::class)
            ->assertSee('Napojeno')
            ->assertSee('Účet zatím není vyplněný')
            ->set('data.cislo_uctu', '19-2000145399/0800')
            ->call('uloz')
            ->assertHasNoErrors()
            ->assertNotified('Účet uložen ve fakturaci');

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->url() === 'https://fakturace.test/api/v1/firma'
            && $r['cislo_uctu'] === '19-2000145399/0800' && $r->hasHeader('Authorization', 'Bearer fk_web'));
        $this->assertSame('fakturace.ucet', AuditLog::query()->latest('id')->value('event'));

        // Nový účet se ukáže hned (cache se po uložení obnoví).
        Livewire::actingAs($this->spravce())->test(Fakturace::class)
            ->assertSee('CZ65 0800 0000 1920 0014 5399')
            ->assertSet('data.cislo_uctu', '19-2000145399/0800');
    }

    public function test_neplatny_ucet_ukaze_chybu_u_pole(): void
    {
        $this->falesnaFakturace();

        Livewire::actingAs($this->spravce())->test(Fakturace::class)
            ->set('data.cislo_uctu', '123456789/0800')
            ->call('uloz')
            ->assertHasErrors(['data.cislo_uctu']);
    }

    public function test_testovaci_napojeni_ucet_jen_ukaze(): void
    {
        config(['fakturace.rezim' => 'test']);
        $this->falesnaFakturace(lzeMenit: false);

        Livewire::actingAs($this->spravce())->test(Fakturace::class)
            ->assertSee('Testovací režim')
            ->set('data.cislo_uctu', '19-2000145399/0800')
            ->call('uloz')
            ->assertNotified('Účet tady změnit nejde');

        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_stranka_jen_pro_spravce(): void
    {
        $this->falesnaFakturace();

        $this->actingAs($this->spravce('klient'))->get(Fakturace::getUrl())->assertForbidden();
        $this->actingAs($this->spravce('superadmin'))->get(Fakturace::getUrl())->assertOk()->assertSee('Bankovní účet pro doklady');
    }

    public function test_starsi_fakturace_bez_udaju_firmy_nic_nerozbije(): void
    {
        Http::fake(['fakturace.test/api/v1/firma' => Http::response(['message' => 'Not Found'], 404)]);

        Livewire::actingAs($this->spravce())->test(Fakturace::class)
            ->assertOk()
            ->assertSee('bankovní účet se nastavuje ve fakturaci');
    }

    public function test_vystaveni_dokladu(): void
    {
        Http::fake(['fakturace.test/api/v1/invoices' => Http::response(['document' => ['id' => 42, 'number' => 'FV-2026-07-0001', 'public_url' => 'https://fakturace.test/d/abc']], 201)]);

        $doklad = app(KlientFakturace::class)->vystav([
            'customer' => ['name' => 'Jana Nováková', 'email' => 'jana@seznam.cz'],
            'items' => [['description' => 'Permanentka', 'quantity' => 1, 'unit_price' => 1200]],
            'external_id' => 'objednavka-7', 'idempotency_key' => 'objednavka-7', 'issue' => true,
        ]);

        $this->assertSame('FV-2026-07-0001', $doklad['number']);
        Http::assertSent(fn (Request $r) => $r['idempotency_key'] === 'objednavka-7');
    }

    public function test_webhook_jen_s_podpisem_a_kazde_doruceni_jednou(): void
    {
        Event::fake([FakturaceUdalost::class]);
        $telo = json_encode(['id' => 'dor-1', 'event' => 'invoice.paid', 'data' => ['document' => ['id' => 42, 'number' => 'FV-2026-07-0001', 'external_id' => 'objednavka-7']]]);
        $posli = fn (string $podpis) => $this->call('POST', '/webhooky/fakturace', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKTURACE_SIGNATURE_V2' => $podpis], $telo);

        $posli('t='.time().',v1=spatne')->assertStatus(401);
        $posli(PodpisWebhooku::podepis($telo, self::TAJEMSTVI, time() - 600))->assertStatus(401);
        Event::assertNotDispatched(FakturaceUdalost::class);

        $posli(PodpisWebhooku::podepis($telo, self::TAJEMSTVI))->assertOk();
        $posli(PodpisWebhooku::podepis($telo, self::TAJEMSTVI))->assertOk();

        Event::assertDispatchedTimes(FakturaceUdalost::class, 1);
        Event::assertDispatched(FakturaceUdalost::class, fn (FakturaceUdalost $u) => $u->udalost === 'invoice.paid' && $u->doklad()['external_id'] === 'objednavka-7');
    }
}
