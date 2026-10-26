<?php

namespace Tests\Feature;

use App\Filament\Auth\Prihlaseni;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Přihlášení do /admin: uživatel aplikace se správným heslem dostane
 * srozumitelné „nemá přístup“, špatné heslo a neznámý e-mail dál stejnou
 * obecnou hlášku (nedá se zjišťovat, které účty existují).
 */
class PrihlaseniDoAdministraceTest extends TestCase
{
    use RefreshDatabase;

    private const HESLO = 'Spravne-heslo-123';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function uzivatel(string $role): User
    {
        return tap(User::factory()->create(['email' => $role.'@example.cz', 'password' => self::HESLO]),
            fn (User $u) => $u->forceFill(['role' => $role])->save());
    }

    private function prihlas(string $email, string $heslo)
    {
        return Livewire::test(Prihlaseni::class)
            ->fillForm(['email' => $email, 'password' => $heslo])
            ->call('authenticate');
    }

    public function test_uzivatel_aplikace_se_spravnym_heslem_se_dozvi_ze_nema_pristup(): void
    {
        $this->uzivatel('klient');

        $this->prihlas('klient@example.cz', self::HESLO)
            ->assertHasFormErrors(['email'])
            ->assertSee('Tento účet nemá přístup do administrace.');

        $this->assertGuest();
    }

    public function test_spatne_heslo_ani_neznamy_ucet_neprozradi_ze_ucet_existuje(): void
    {
        $this->uzivatel('klient');
        $obecna = __('filament-panels::auth/pages/login.messages.failed');

        $this->prihlas('klient@example.cz', 'spatne-heslo')
            ->assertHasFormErrors(['email'])
            ->assertSee($obecna)
            ->assertDontSee('nemá přístup do administrace');

        $this->prihlas('nikdo@example.cz', self::HESLO)
            ->assertHasFormErrors(['email'])
            ->assertSee($obecna)
            ->assertDontSee('nemá přístup do administrace');

        $this->assertGuest();
    }

    public function test_spravce_se_prihlasi(): void
    {
        $spravce = $this->uzivatel('admin');

        $this->prihlas('admin@example.cz', self::HESLO)->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($spravce);
    }
}
