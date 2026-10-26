<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Superadmini (vývojáři) se do statistik nikdy nepočítají – User::bezSuperadminu(). */
class StatistikyBezSuperadminuTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_vynecha_jen_superadminy(): void
    {
        foreach (['superadmin', 'admin', 'klient'] as $role) {
            tap(User::factory()->create(), fn (User $u) => $u->forceFill(['role' => $role])->save());
        }

        $this->assertSame(3, User::query()->count());
        $this->assertSame(['admin', 'klient'], User::query()->bezSuperadminu()->orderBy('role')->pluck('role')->all());
    }
}
