<?php

namespace Tests\Feature;

use App\Filament\Auth\Profil;
use App\Filament\Rozlozeni;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Jednotné rozložení administrace (App\Filament\Rozlozeni): logo a název v levém
 * menu, „Správa aplikace“ dole jen pro superadmina, drobečky v liště, vpravo
 * zvoneček a avatar (Profil, Zabezpečení, Odhlásit), bez globálního hledání;
 * na mobilu účet nahoře v menu.
 */
class RozlozeniAdministraceTest extends TestCase
{
    use RefreshDatabase;

    private function ucet(string $role): User
    {
        return tap(User::factory()->create(['jmeno' => 'Jana', 'prijmeni' => 'Dvořáková']), fn ($u) => $u->forceFill(['role' => $role])->save());
    }

    public function test_bez_globalniho_hledani_s_drobecky_a_uctem_v_mobilnim_menu(): void
    {
        $this->assertNull(Filament::getPanel('admin')->getGlobalSearchProvider());

        $html = $this->actingAs($this->ucet('admin'))->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('fi-global-search', $html);
        $this->assertStringContainsString('css/rozlozeni.css', $html);
        $this->assertStringContainsString('js/rozlozeni.js', $html);
        $this->assertStringContainsString('class="simren-drobecky"', $html);
        $this->assertStringContainsString('class="simren-znacka"', $html);
        $this->assertStringContainsString('class="simren-ucet-mobil"', $html);
        // Zvoneček oznámení vedle avatara.
        $this->assertStringContainsString('ozn-zvonecek', $html);
    }

    private function vidiSpravuAplikace(string $role): bool
    {
        $html = $this->actingAs($this->ucet($role))->get('/admin')->assertOk()->getContent();

        return (bool) preg_match('/fi-sidebar-group-label[^>]*>\s*'.preg_quote(Rozlozeni::SPRAVA_APLIKACE, '/').'/u', $html);
    }

    public function test_sprava_aplikace_vidi_superadmin(): void
    {
        $this->assertTrue($this->vidiSpravuAplikace('superadmin'));
    }

    public function test_sprava_aplikace_admin_nevidi(): void
    {
        $this->assertFalse($this->vidiSpravuAplikace('admin'));
    }

    public function test_profil_ma_jmeno_a_prijmeni_zvlast(): void
    {
        $ja = $this->ucet('admin');

        $this->actingAs($ja)->get(Filament::getPanel('admin')->getProfileUrl())->assertOk()->assertSee('Příjmení');

        Livewire::test(Profil::class)
            ->set('data.jmeno', 'Petra')
            ->set('data.prijmeni', 'Malá')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Petra', 'Malá', 'Petra Malá'], [$ja->fresh()->jmeno, $ja->fresh()->prijmeni, $ja->fresh()->name]);
    }
}
