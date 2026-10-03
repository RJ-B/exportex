<?php

namespace Tests\Feature;

use App\Filament\Resources\Uzivatele\Pages\CreateUzivatel;
use App\Filament\Resources\Uzivatele\Pages\EditUzivatel;
use App\Filament\Resources\Uzivatele\Pages\ListUzivatele;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Uživatelé a role: admin (majitel) spravuje své lidi, superadminy nevidí;
 * hesla nikdo nezadává, nový správce dostane odkaz.
 */
class UzivateleTest extends TestCase
{
    use RefreshDatabase;

    private function ucet(string $role, string $email): User
    {
        return User::forceCreate(['email' => $email, 'jmeno' => 'Petr', 'prijmeni' => 'Dvořák', 'role' => $role, 'password' => 'x']);
    }

    public function test_admin_superadminy_nevidi(): void
    {
        $admin = $this->ucet('admin', 'majitel@example.cz');
        $vyvojar = $this->ucet('superadmin', 'vyvojar@example.cz');
        $klient = $this->ucet('klient', 'klient@example.cz');

        $this->actingAs($admin);

        Livewire::test(ListUzivatele::class)
            ->assertCanSeeTableRecords([$admin, $klient])
            ->assertCanNotSeeTableRecords([$vyvojar]);
    }

    public function test_novy_spravce_dostane_odkaz_na_heslo(): void
    {
        Notification::fake();
        $this->actingAs($this->ucet('admin', 'majitel@example.cz'));

        Livewire::test(CreateUzivatel::class)
            ->fillForm(['jmeno' => 'Eva', 'prijmeni' => 'Svobodová', 'email' => 'eva@example.cz', 'role' => 'admin'])
            ->call('create')
            ->assertHasNoFormErrors();

        $eva = User::where('email', 'eva@example.cz')->sole();
        $this->assertSame('admin', $eva->role);
        $this->assertNull($eva->password);
        Notification::assertSentTo($eva, ResetPassword::class, fn (ResetPassword $n) => str_contains($n->url, '/admin/password-reset/reset'));
    }

    public function test_admin_neprideli_superadmina(): void
    {
        $this->actingAs($this->ucet('admin', 'majitel@example.cz'));

        Livewire::test(CreateUzivatel::class)
            ->fillForm(['jmeno' => 'X', 'prijmeni' => 'Y', 'email' => 'x@example.cz', 'role' => 'superadmin'])
            ->call('create')
            ->assertHasFormErrors(['role']);

        $this->assertNull(User::where('email', 'x@example.cz')->first());
    }

    public function test_vlastni_roli_si_nikdo_nezmeni(): void
    {
        $admin = $this->ucet('admin', 'majitel@example.cz');
        $this->actingAs($admin);

        Livewire::test(EditUzivatel::class, ['record' => $admin->getKey()])
            ->fillForm(['role' => 'klient'])
            ->call('save');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_klient_do_uzivatelu_nesmi(): void
    {
        $this->actingAs($this->ucet('klient', 'klient@example.cz'))->get('/admin/uzivatele')->assertForbidden();
    }
}
