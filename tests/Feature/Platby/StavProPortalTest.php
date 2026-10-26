<?php

namespace Tests\Feature\Platby;

use App\Models\Nastaveni;
use App\Platby\NastaveniPlateb;
use App\Platby\Platba;
use App\Platby\StavPlatby;
use App\Platby\StavPlateb;
use App\Support\Zdravi;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blok `platby` v `simren:zdravi --json` pro portál: brána, klíče jen ano/ne,
 * režim, poslední platba a chyba – a nikdy hodnoty klíčů.
 */
class StavProPortalTest extends TestCase
{
    use SPlatbami;

    private const TAJNE = 'tajne-heslo-obchodu-9f3k';

    /** Platba rovnou v databázi – bez volání brány (Http::preventStrayRequests). */
    private function platba(array $hodnoty): Platba
    {
        return tap(new Platba(['popis' => 'Objednávka 2026-0042']), fn (Platba $p) => $p->forceFill([
            'verejne_id' => (string) Str::uuid(),
            'brana' => 'simulace',
            'rezim' => 'simulace',
            'castka' => 125050,
            ...$hodnoty,
        ])->save());
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function udajeKlienta(): void
    {
        NastaveniPlateb::uloz(['brana' => 'comgate', 'comgate_merchant' => '498621', 'comgate_secret' => self::TAJNE]);
    }

    public function test_produkce_v_testovacim_rezimu_bez_klicu(): void
    {
        $this->produkce();
        $this->testovaciUdaje();

        $p = Zdravi::diagnostika()['platby'];

        $this->assertTrue($p['zapnuto']);
        $this->assertNull($p['brana']);
        $this->assertFalse($p['klice']);
        $this->assertSame('test', $p['rezim_volba']);
        $this->assertSame('test', $p['rezim']);
        $this->assertTrue($p['funguje']);
        $this->assertNull($p['posledni_platba']);
        $this->assertNull($p['posledni_chyba']);
    }

    public function test_produkce_ostre_s_overenymi_klici_a_posledni_platbou(): void
    {
        $this->produkce();
        $this->testovaciUdaje();
        $this->udajeKlienta();
        NastaveniPlateb::oznacOvereno('comgate');
        Nastaveni::nastav('platby.rezim', NastaveniPlateb::REZIM_OSTRY);

        $this->platba(['stav' => StavPlatby::Zaplacena, 'brana' => 'comgate', 'rezim' => 'ostry', 'zaplaceno_v' => now()->subHour()]);

        $p = StavPlateb::proZdravi();

        $this->assertSame('comgate', $p['brana']);
        $this->assertTrue($p['klice']);
        $this->assertTrue($p['overeno']);
        $this->assertSame('ostry', $p['rezim']);
        $this->assertNull($p['duvod']);
        $this->assertSame(now()->subHour()->toIso8601String(), $p['posledni_platba']['cas']);
    }

    public function test_ostry_rezim_bez_overeni_stoji_s_duvodem(): void
    {
        $this->produkce();
        $this->udajeKlienta();
        Nastaveni::nastav('platby.rezim', NastaveniPlateb::REZIM_OSTRY);

        $p = StavPlateb::proZdravi();

        $this->assertNull($p['rezim']);
        $this->assertFalse($p['funguje']);
        $this->assertTrue($p['klice']);
        $this->assertFalse($p['overeno']);
        $this->assertStringContainsString('nejsou ověřené', $p['duvod']);
    }

    public function test_posledni_chyba_brany_bez_tajnosti(): void
    {
        $this->udajeKlienta();
        $this->platba(['stav' => StavPlatby::Chyba, 'chyba' => 'Comgate odmítl přihlášení s heslem '.self::TAJNE.' (create).']);

        $p = StavPlateb::proZdravi();

        $this->assertStringContainsString('Comgate odmítl přihlášení', $p['posledni_chyba']['zprava']);
        $this->assertStringNotContainsString(self::TAJNE, json_encode($p));
        $this->assertStringContainsString('***', $p['posledni_chyba']['zprava']);
    }

    public function test_vystup_prikazu_nikdy_neobsahuje_klice(): void
    {
        $this->testovaciUdaje();
        $this->udajeKlienta();
        NastaveniPlateb::oznacOvereno('comgate');

        Artisan::call('simren:zdravi', ['--json' => true]);
        $vystup = Artisan::output();

        $this->assertArrayHasKey('platby', json_decode($vystup, true));
        $this->assertStringNotContainsString(self::TAJNE, $vystup);
        $this->assertStringNotContainsString('498621', $vystup);
        $this->assertStringNotContainsString(str_repeat('ab', 32), $vystup);
        $this->assertStringNotContainsString(Nastaveni::hodnota('platby.comgate.secret'), $vystup);

        Artisan::call('simren:zdravi');
        $this->assertStringContainsString('platby', Artisan::output());
    }

    public function test_platba_v_rezimu_testovaci_se_hlasi_s_rezimem(): void
    {
        $platba = $this->platby()->zaloz($this->pozadavek());
        $platba->forceFill(['stav' => StavPlatby::Zaplacena, 'zaplaceno_v' => now()])->save();

        $this->assertSame('simulace', StavPlateb::proZdravi()['posledni_platba']['rezim']);
        $this->assertSame(1, Platba::query()->count());
    }
}
