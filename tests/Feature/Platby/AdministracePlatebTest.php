<?php

namespace Tests\Feature\Platby;

use App\Platby\Brany\Simulace;
use App\Platby\Filament\Pages\ListPlatby;
use App\Platby\Filament\Pages\ViewPlatba;
use App\Platby\Filament\PlatbaResource;
use App\Platby\Mail\OdkazKZaplaceni;
use App\Platby\Platba;
use App\Platby\StavPlatby;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Administrace plateb: přehled se záložkami (Vše první), detail s historií, ověření, vrácení, odkaz k zaplacení. */
class AdministracePlatebTest extends TestCase
{
    use SPlatbami;

    public function test_prehled_a_detail_jen_pro_spravce(): void
    {
        $zaplacena = $this->platby()->zaloz($this->pozadavek());
        Simulace::nastav($zaplacena, StavPlatby::Zaplacena);
        $this->platby()->overStav($zaplacena);
        $cekajici = $this->platby()->zaloz($this->pozadavek(['reference' => '2026-0043', 'popis' => 'Objednávka 2026-0043']));

        $this->actingAs($this->spravce('klient'))->get(PlatbaResource::getUrl())->assertForbidden();

        $admin = $this->spravce();
        $this->actingAs($admin)->get(PlatbaResource::getUrl())->assertOk()->assertSeeInOrder(['Vše', 'Čeká', 'Zaplacené', 'Neúspěšné', 'Vrácené']);

        Livewire::actingAs($admin)->test(ListPlatby::class)
            ->assertCanSeeTableRecords([$zaplacena, $cekajici])
            ->set('activeTab', 'ceka')
            ->assertCanSeeTableRecords([$cekajici])
            ->assertCanNotSeeTableRecords([$zaplacena]);

        $this->actingAs($admin)->get(PlatbaResource::getUrl('view', ['record' => $zaplacena]))
            ->assertOk()
            ->assertSee('Historie')
            ->assertSee('Čeká na zaplacení')
            ->assertSee('TESTOVACÍ PLATBA');
    }

    public function test_overit_vratit_a_zrusit_z_detailu(): void
    {
        Mail::fake();
        $admin = $this->spravce();
        $platba = $this->platby()->zaloz($this->pozadavek());
        Simulace::nastav($platba, StavPlatby::Zaplacena);

        Livewire::actingAs($admin)->test(ViewPlatba::class, ['record' => $platba->verejne_id])
            ->callAction('overit')
            ->assertNotified('Stav: Zaplacena')
            ->callAction('vratit', ['castka' => '250,50', 'duvod' => 'Chyběl jeden koláč'])
            ->assertNotified('Peníze se vrací');

        $platba->refresh();
        $this->assertSame(StavPlatby::CastecneVracena, $platba->stav);
        $this->assertSame(25050, $platba->vraceno);
        $this->assertSame($admin->id, $platba->udalosti->last()->user_id, 'Historie ví, kdo peníze vrátil.');

        $druha = $this->platby()->zaloz($this->pozadavek(['reference' => 'B']));
        Livewire::actingAs($admin)->test(ViewPlatba::class, ['record' => $druha->verejne_id])
            ->callAction('zrusit', ['duvod' => 'Zákazník si to rozmyslel'])
            ->assertNotified('Platba je zrušená');
        $this->assertSame(StavPlatby::Zrusena, $druha->refresh()->stav);
    }

    public function test_nova_platba_odkazem(): void
    {
        Mail::fake();

        Livewire::actingAs($this->spravce())->test(ListPlatby::class)
            ->callAction('novaPlatba', [
                'popis' => 'Záloha na zahradní altán',
                'castka' => '12 500',
                'email' => 'petr.svoboda@example.cz',
                'jmeno' => 'Petr',
                'prijmeni' => 'Svoboda',
                'poslat' => true,
            ])
            ->assertHasNoActionErrors();

        $platba = Platba::query()->sole();
        $this->assertSame(1250000, $platba->castka);
        $this->assertSame(StavPlatby::Ceka, $platba->stav);
        Mail::assertSent(OdkazKZaplaceni::class, fn ($m) => $m->hasTo('petr.svoboda@example.cz'));
    }
}
