<?php

namespace Tests\Feature;

use App\Events\RecenzentPripraven;
use App\Models\User;
use App\Support\UcetRecenzenta;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Účet pro kontrolu obchodu z portálu: běžný uživatel s heslem z portálu
 * (jen na standardním vstupu), idempotentní, mimo statistiky.
 */
class UcetRecenzentaTest extends TestCase
{
    use RefreshDatabase;

    private const HESLO = 'Kontrola-obchodu-2026';

    /** Spustí příkaz jako shim: JSON na standardním vstupu. */
    private function spust(array $vstup): array
    {
        $proud = fopen('php://memory', 'r+');
        fwrite($proud, json_encode($vstup));
        rewind($proud);

        $input = new ArrayInput(['--json' => true]);
        $input->setStream($proud);
        $output = new BufferedOutput;

        $prikaz = $this->app->make(Kernel::class)->all()['simren:recenzent'];
        $prikaz->setLaravel($this->app);
        $kod = $prikaz->run($input, $output);

        return ['kod' => $kod, ...json_decode(trim($output->fetch()), true)];
    }

    public function test_zalozi_bezny_overeny_ucet_s_heslem_mimo_statistiky(): void
    {
        $v = $this->spust(['email' => 'Test@Example.cz', 'heslo' => self::HESLO]);

        $this->assertSame(0, $v['kod']);
        $this->assertTrue($v['created']);
        $this->assertArrayNotHasKey('heslo', $v);

        $user = User::where('email', 'test@example.cz')->firstOrFail();
        $this->assertSame('klient', $user->role);
        $this->assertTrue((bool) $user->recenzent);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->jeSpravce());
        $this->assertTrue(Hash::check(self::HESLO, $user->password));
        $this->assertSame(UcetRecenzenta::JMENO, $user->jmeno);

        User::forceCreate(['email' => 'clen@example.cz', 'role' => 'klient', 'password' => 'x']);
        $this->assertSame(['clen@example.cz'], User::query()->bezSuperadminu()->pluck('email')->all());
    }

    public function test_opakovani_nic_nezmeni_a_nove_heslo_prenastavi(): void
    {
        $this->spust(['email' => 'test@example.cz', 'heslo' => self::HESLO]);
        $hash = User::where('email', 'test@example.cz')->value('password');

        $znovu = $this->spust(['email' => 'test@example.cz', 'heslo' => self::HESLO]);
        $this->assertFalse($znovu['created']);
        $this->assertFalse($znovu['heslo_zmeneno']);
        $this->assertSame($hash, User::where('email', 'test@example.cz')->value('password'));

        $nove = $this->spust(['email' => 'test@example.cz', 'heslo' => 'Uplne-jine-heslo-2026']);
        $this->assertTrue($nove['heslo_zmeneno']);
        $this->assertTrue(Hash::check('Uplne-jine-heslo-2026', User::where('email', 'test@example.cz')->value('password')));
        $this->assertSame(1, User::where('email', 'test@example.cz')->count());
    }

    public function test_existujici_bezny_ucet_se_jen_oznaci(): void
    {
        $user = User::forceCreate(['email' => 'test@example.cz', 'role' => 'klient', 'password' => 'puvodni', 'jmeno' => 'Jan', 'prijmeni' => 'Novák']);

        $v = $this->spust(['email' => 'test@example.cz', 'heslo' => self::HESLO]);

        $this->assertFalse($v['created']);
        $this->assertTrue((bool) $user->fresh()->recenzent);
        $this->assertSame('Jan', $user->fresh()->jmeno);
        $this->assertSame('klient', $user->fresh()->role);
    }

    public function test_spravce_se_na_recenzenta_nepredela(): void
    {
        $user = User::forceCreate(['email' => 'test@example.cz', 'role' => 'admin', 'password' => 'puvodni']);

        $v = $this->spust(['email' => 'test@example.cz', 'heslo' => self::HESLO]);

        $this->assertSame(1, $v['kod']);
        $this->assertFalse($v['ok']);
        $this->assertStringContainsString('správce', $v['chyba']);
        $this->assertTrue(Hash::check('puvodni', $user->fresh()->password));
        $this->assertFalse((bool) $user->fresh()->recenzent);
    }

    public function test_neplatny_vstup_nic_nezalozi(): void
    {
        $this->assertFalse($this->spust(['email' => 'neni-email', 'heslo' => self::HESLO])['ok']);
        $this->assertFalse($this->spust(['email' => 'test@example.cz', 'heslo' => 'kratke'])['ok']);
        $this->assertFalse($this->spust([])['ok']);
        $this->assertSame(0, User::count());
    }

    public function test_aplikace_si_doplni_data_udalosti(): void
    {
        Event::fake([RecenzentPripraven::class]);

        $this->spust(['email' => 'test@example.cz', 'heslo' => self::HESLO]);
        $this->spust(['email' => 'test@example.cz', 'heslo' => self::HESLO]);

        Event::assertDispatched(RecenzentPripraven::class, fn (RecenzentPripraven $e) => $e->novy && $e->user->email === 'test@example.cz');
        Event::assertDispatchedTimes(RecenzentPripraven::class, 2);
    }

    public function test_chyba_posluchace_ucet_nezalozi(): void
    {
        Event::listen(RecenzentPripraven::class, fn () => throw new \RuntimeException('Chybí ukázková data.'));

        $v = $this->spust(['email' => 'test@example.cz', 'heslo' => self::HESLO]);

        $this->assertFalse($v['ok']);
        $this->assertSame('Chybí ukázková data.', $v['chyba']);
        $this->assertSame(0, User::count());
    }
}
