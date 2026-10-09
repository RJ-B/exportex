<?php

namespace Tests\Feature\Platby;

use App\Models\User;
use App\Platby\NastaveniPlateb;
use App\Platby\Platby;
use App\Platby\PozadavekPlatby;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

/**
 * Testy se zapnutým doplňkem Platby (sablona.doplnky.platby). Doplněk se čte
 * při startu aplikace (routy, migrace, panel) – proto env před setUp().
 */
trait SPlatbami
{
    use RefreshDatabase;

    public function createApplication()
    {
        putenv('SABLONA_DOPLNEK_PLATBY=1');
        $app = parent::createApplication();
        NastaveniPlateb::zapomen();

        return $app;
    }

    /**
     * Databáze v paměti se migruje jednou za běh (RefreshDatabase) – a to
     * bez doplňku, když jako první běžel jiný test. Tabulky plateb proto
     * doplní každý test (v transakci testu, po něm zmizí).
     */
    protected function setUpSPlatbami(): void
    {
        if (! Schema::hasTable('platby')) {
            foreach (glob(database_path('migrations/platby/*.php')) as $migrace) {
                (require $migrace)->up();
            }
        }
    }

    protected function tearDown(): void
    {
        putenv('SABLONA_DOPLNEK_PLATBY');
        parent::tearDown();
    }

    protected function spravce(string $role = 'admin'): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => $role])->save());
    }

    protected function pozadavek(array $zmeny = []): PozadavekPlatby
    {
        return new PozadavekPlatby(...array_merge([
            'castka' => 125050,
            'popis' => 'Objednávka 2026-0042',
            'email' => 'jana.novakova@example.cz',
            'jmeno' => 'Jana',
            'prijmeni' => 'Nováková',
            'reference' => '2026-0042',
        ], $zmeny));
    }

    protected function platby(): Platby
    {
        return app(Platby::class);
    }

    /** Produkce s APP_URL na veřejné doméně. */
    protected function produkce(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://pekarna.cz']);
        URL::useOrigin('https://pekarna.cz');
        AppServiceProvider::https();
        NastaveniPlateb::zapomen();
    }

    /** Testovací brána Sim&Ren v .env (Mo.one test). */
    protected function testovaciUdaje(): void
    {
        config([
            'platby.simulace' => false,
            'platby.test.moone.client_id' => '3f2a0000-0000-4000-8000-000000000001',
            'platby.test.moone.client_secret' => str_repeat('ab', 32),
        ]);
        NastaveniPlateb::zapomen();
    }
}
