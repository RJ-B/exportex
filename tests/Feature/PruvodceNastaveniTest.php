<?php

namespace Tests\Feature;

use App\Filament\Pages\Prehled;
use App\Filament\Widgets\DokonceteNastaveni;
use App\Models\User;
use App\Support\Pruvodce\Krok;
use App\Support\Pruvodce\Kroky;
use App\Support\Pruvodce\Pruvodce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Přehled → Dokončete nastavení: kroky z config/pruvodce.php se zaškrtnutím,
 * box zmizí, když je vše hotovo, jde skrýt a zase ukázat; jen pro správce.
 */
class PruvodceNastaveniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Exportex má údaje firmy a SEO ve výchozím nastavení (config/nastaveni.php) – testy šablony počítají s prázdným webem.
        config(['nastaveni.vychozi' => []]);
    }

    private function spravce(string $role = 'admin'): User
    {
        return User::forceCreate(['email' => $role.'@pekarna-novak.cz', 'jmeno' => 'Jana', 'prijmeni' => 'Nováková', 'role' => $role, 'password' => 'x']);
    }

    public function test_kroky_sablony_a_box_na_prehledu(): void
    {
        config(['sablona.doplnky.platby' => false]);
        $this->actingAs($this->spravce());

        $kroky = Pruvodce::stav();
        $this->assertSame(['UdajeFirmy', 'EmailovaSchranka', 'PravniTexty', 'IkonaALogo', 'SeoGoogle', 'DruhyKrokPrihlaseni'], $kroky->pluck('klic')->all());
        $this->assertTrue($kroky->every(fn ($k) => $k['hotovo'] === false && filled($k['url'])));

        $this->assertTrue(DokonceteNastaveni::canView());
        Livewire::test(DokonceteNastaveni::class)
            ->assertSee(__('pruvodce.pruvodce_nastaveni.dokoncete_nastaveni'))
            ->assertSee(__('pruvodce.kroky.emailova_schranka'))
            ->assertSee(__('pruvodce.pruvodce_nastaveni.hotovo_z', ['hotovo' => 0, 'celkem' => 6]));
        $this->get(Prehled::getUrl())->assertOk()->assertSee(__('pruvodce.pruvodce_nastaveni.dokoncete_nastaveni'));

        // Platby jen se zapnutým doplňkem.
        config(['sablona.doplnky.platby' => true]);
        $this->assertContains('Platby', Pruvodce::kroky()->map(fn (Krok $k) => $k->klic())->all());
    }

    public function test_hotove_kroky_a_box_zmizi(): void
    {
        $this->actingAs($this->spravce());
        config(['pruvodce.kroky' => [KrokHotovy::class, Kroky\SeoGoogle::class]]);
        $this->assertSame([true, false], Pruvodce::stav()->pluck('hotovo')->all());
        $this->assertTrue(DokonceteNastaveni::canView());

        \App\Support\NastaveniWebu::uloz(['meta_popis' => 'Pekárna v Brně.']);
        $this->assertTrue(Pruvodce::hotovo());
        $this->assertFalse(DokonceteNastaveni::canView());
    }

    public function test_skryt_a_zase_ukazat(): void
    {
        $this->actingAs($this->spravce());
        config(['pruvodce.kroky' => [Kroky\SeoGoogle::class]]);

        Livewire::test(DokonceteNastaveni::class)->call('skryt');
        $this->assertTrue(Pruvodce::skryty());
        $this->assertFalse(DokonceteNastaveni::canView());

        Livewire::test(Prehled::class)->assertActionVisible('pruvodce')->callAction('pruvodce');
        $this->assertFalse(Pruvodce::skryty());
        $this->assertTrue(DokonceteNastaveni::canView());
    }

    public function test_jen_spravce(): void
    {
        $this->actingAs($this->spravce('klient'));
        $this->assertFalse(DokonceteNastaveni::canView());
        Livewire::test(DokonceteNastaveni::class)->call('skryt')->assertForbidden();
    }

    public function test_krok_ktery_spadne_neni_hotovy(): void
    {
        config(['pruvodce.kroky' => [KrokSpadne::class]]);
        $this->assertFalse(Pruvodce::stav()->first()['hotovo']);
    }
}

class KrokHotovy extends Krok
{
    public function nazev(): string
    {
        return 'Hotový';
    }

    public function popis(): string
    {
        return '';
    }

    public function url(): ?string
    {
        return null;
    }

    public function hotovo(): bool
    {
        return true;
    }
}

class KrokSpadne extends KrokHotovy
{
    public function hotovo(): bool
    {
        throw new \RuntimeException('API nedostupné');
    }
}
