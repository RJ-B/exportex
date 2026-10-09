<?php

namespace Tests\Feature\Platby;

use App\Models\AuditLog;
use App\Platby\Brany\Simulace;
use App\Platby\Mail\PlatbaPrijata;
use App\Platby\Mail\PlatbaVracena;
use App\Platby\Platba;
use App\Platby\Platby;
use App\Platby\Rezim;
use App\Platby\StavPlatby;
use App\Platby\Udalosti\PlatbaZaplacena;
use App\Platby\UzZaplaceno;
use Database\Seeders\UkazkovaDataSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Celý průchod platbou přes simulaci (lokálně a v testech): založ → brána → návrat → stav. */
class SimulaceTest extends TestCase
{
    use SPlatbami;

    public function test_cely_pruchod_zaplacenim(): void
    {
        Mail::fake();
        Event::fake([PlatbaZaplacena::class]);

        $platba = $this->platby()->zaloz($this->pozadavek(['navratUrl' => '/objednavka/42']));

        $this->assertSame(StavPlatby::Ceka, $platba->stav);
        $this->assertSame(Rezim::Simulace, $platba->rezim);
        $this->assertSame(125050, $platba->castka);
        $this->assertTrue(Str::isUuid($platba->verejne_id));

        $this->get($platba->odkazKZaplaceni())->assertRedirect(route('platby.simulace', $platba));
        $this->get(route('platby.simulace', $platba))->assertOk()->assertSee("1\u{a0}250,50")->assertSee('TESTOVACÍ PLATBY');

        $this->post(route('platby.simulace.odeslat', $platba), ['vysledek' => 'zaplatit'])
            ->assertRedirect(route('platby.navrat', $platba));
        $this->get(route('platby.navrat', $platba))->assertRedirect('/objednavka/42?platba='.$platba->verejne_id);

        $platba->refresh();
        $this->assertSame(StavPlatby::Zaplacena, $platba->stav);
        $this->assertNotNull($platba->zaplaceno_v);
        $this->assertSame('SIMULACE', $platba->metoda);

        // Návrat podruhé, webhook, plánovač – nic se nezdvojí.
        $this->get(route('platby.navrat', $platba));
        $this->platby()->overStav($platba, 'overeni');

        Event::assertDispatchedTimes(PlatbaZaplacena::class, 1);
        Mail::assertSent(PlatbaPrijata::class, 1);
        Mail::assertSent(PlatbaPrijata::class, fn ($m) => $m->hasTo('jana.novakova@example.cz'));
        $this->assertSame(1, AuditLog::query()->where('event', 'platba.zaplacena')->count());
        $this->assertSame(
            [[null, 'zalozena'], ['zalozena', 'ceka'], ['ceka', 'zaplacena']],
            $platba->udalosti->filter(fn ($u) => $u->stav_z !== $u->stav_na)->map(fn ($u) => [$u->stav_z?->value, $u->stav_na?->value])->values()->all(),
        );
    }

    public function test_zamitnuta_platba_jde_zaplatit_znovu(): void
    {
        $platba = $this->platby()->zaloz($this->pozadavek());

        $this->post(route('platby.simulace.odeslat', $platba), ['vysledek' => 'zamitnout']);
        $this->get(route('platby.navrat', $platba))->assertRedirect(route('platby.vysledek', $platba));
        $this->get(route('platby.vysledek', $platba))->assertOk()->assertSee('Platba neproběhla')->assertSee('Zaplatit znovu');
        $this->assertSame(StavPlatby::Zamitnuta, $platba->refresh()->stav);

        $this->post(route('platby.znovu', $platba))->assertRedirect();
        $nova = Platba::query()->latest('id')->first();

        $this->assertNotSame($platba->id, $nova->id);
        $this->assertSame(StavPlatby::Ceka, $nova->stav);
        $this->assertSame($platba->castka, $nova->castka);
    }

    public function test_predmet_nejde_zaplatit_dvakrat_a_dvojklik_nezalozi_druhou_platbu(): void
    {
        $objednavka = $this->spravce('klient');   // jakýkoli model jako předmět platby

        $prvni = $this->platby()->zaloz($this->pozadavek(['predmet' => $objednavka]));
        $dvojklik = $this->platby()->zaloz($this->pozadavek(['predmet' => $objednavka]));

        $this->assertSame($prvni->id, $dvojklik->id, 'Rozpracovaná platba stejného předmětu se použije znovu.');
        $this->assertSame('App\\Models\\User:'.$objednavka->id, $prvni->klic);

        // Jiná částka (změněný košík) = stará se zruší, vznikne nová.
        $nova = $this->platby()->zaloz($this->pozadavek(['predmet' => $objednavka, 'castka' => 99900]));
        $this->assertNotSame($prvni->id, $nova->id);
        $this->assertSame(StavPlatby::Zrusena, $prvni->refresh()->stav);

        $this->post(route('platby.simulace.odeslat', $nova), ['vysledek' => 'zaplatit']);
        $this->get(route('platby.navrat', $nova));

        $this->expectException(UzZaplaceno::class);
        $this->platby()->zaloz($this->pozadavek(['predmet' => $objednavka, 'castka' => 99900]));
    }

    public function test_pozde_zaplacena_zrusena_platba_se_prijme_a_nahlasi(): void
    {
        $platba = $this->platby()->zaloz($this->pozadavek());
        $this->platby()->zrus($platba, 'administrace');
        $this->assertSame(StavPlatby::Zrusena, $platba->refresh()->stav);

        // Zákazník měl bránu otevřenou a zaplatil – peníze odešly, brána je autoritativní.
        Simulace::nastav($platba, StavPlatby::Zaplacena);
        $this->platby()->overStav($platba, 'webhook');

        $this->assertSame(StavPlatby::Zaplacena, $platba->refresh()->stav);
        $this->assertDatabaseHas('error_logs', ['exception' => \RuntimeException::class]);
    }

    public function test_vraceni_castecne_a_cele(): void
    {
        Mail::fake();
        $platba = $this->platby()->zaloz($this->pozadavek());
        Simulace::nastav($platba, StavPlatby::Zaplacena);
        $this->platby()->overStav($platba);

        $this->platby()->vrat($platba, 50000, 'Chyběl jeden dort');
        $this->assertSame(StavPlatby::CastecneVracena, $platba->refresh()->stav);
        $this->assertSame(75050, $platba->zbyvaVratit());

        $this->platby()->vrat($platba, 25000);
        $this->assertSame(StavPlatby::CastecneVracena, $platba->refresh()->stav);
        $this->assertSame(75000, $platba->vraceno);

        try {
            $this->platby()->vrat($platba, 60000);
            $this->fail('Vrátit víc, než zbývá, nejde.');
        } catch (\InvalidArgumentException) {
        }

        $this->platby()->vrat($platba, 50050);
        $this->assertSame(StavPlatby::Vracena, $platba->refresh()->stav);
        Mail::assertSent(PlatbaVracena::class, 3);

        // Brána o vrácení neví (drží „zaplaceno“) – vrácená zůstane vrácená.
        $this->platby()->overStav($platba);
        $this->assertSame(StavPlatby::Vracena, $platba->refresh()->stav);
    }

    public function test_planovac_overi_cekajici_a_zaseknute_zalozeni_oznaci_jako_chybu(): void
    {
        $platba = $this->platby()->zaloz($this->pozadavek());
        Simulace::nastav($platba, StavPlatby::Zaplacena);
        $this->assertFalse(Platby::maCekajici(), 'Čerstvá platba se ještě neověřuje.');

        $this->travel(10)->minutes();
        $this->assertTrue(Platby::maCekajici());

        $zaseknuta = $this->platby()->zaloz($this->pozadavek(['reference' => 'X']));
        $zaseknuta->forceFill(['stav' => StavPlatby::Zalozena, 'externi_id' => null])->save();
        $this->travel(2)->hours();

        $this->artisan('platby:over')->assertSuccessful();

        $this->assertSame(StavPlatby::Zaplacena, $platba->refresh()->stav);
        $this->assertSame(StavPlatby::Chyba, $zaseknuta->refresh()->stav);
    }

    public function test_halere_bez_plovouci_carky(): void
    {
        $this->assertSame(125050, Platby::halere('1 250,50'));
        $this->assertSame(1999, Platby::halere('19.99'));
        $this->assertSame(250000, Platby::halere(2500));
        $this->assertSame(10, Platby::halere('0,1'));

        $this->expectException(\InvalidArgumentException::class);
        Platby::halere('dvě stě');
    }

    public function test_ukazkova_data_maji_zaplacenou_cekajici_a_zamitnutou_platbu(): void
    {
        if (! class_exists(UkazkovaDataSeeder::class) || ! str_contains((string) file_get_contents(database_path('seeders/UkazkovaDataSeeder.php')), 'Platb')) {
            $this->markTestSkipped('Ukázková data projektu platby nezakládají.');
        }

        $this->seed(UkazkovaDataSeeder::class);
        $this->seed(UkazkovaDataSeeder::class);   // opakovatelný

        $this->assertSame(
            ['ceka', 'zamitnuta', 'zaplacena'],
            Platba::query()->pluck('stav')->map->value->sort()->values()->all(),
        );
    }
}
