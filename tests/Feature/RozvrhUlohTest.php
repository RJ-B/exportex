<?php

namespace Tests\Feature;

use App\Support\Rozvrh;
use App\Support\UdrzbaDatabaze;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ze šablony (rozvrh úloh, 24. 10. 2026): údržba rozložená v hodině podle posunu
 * aplikace (portál SIMREN_ROZVRH_POSUN, jinak z adresy webu) a denní úklid
 * provozních tabulek.
 */
class RozvrhUlohTest extends TestCase
{
    use RefreshDatabase;

    public function test_posun_vyrazu(): void
    {
        $this->assertSame('47 3 * * *', Rozvrh::posunVyraz('30 3 * * *', 17));
        $this->assertSame('15 3 * * *', Rozvrh::posunVyraz('30 3 * * *', 45));
        $this->assertSame('17 * * * *', Rozvrh::posunVyraz('0 * * * *', 17));
        $this->assertSame('2-59/5 * * * *', Rozvrh::posunVyraz('*/5 * * * *', 17));
        $this->assertSame('* * * * *', Rozvrh::posunVyraz('* * * * *', 17));
        $this->assertSame('0,30 * * * *', Rozvrh::posunVyraz('0,30 * * * *', 17));
    }

    public function test_posun_z_portalu_jinak_z_adresy(): void
    {
        config(['sablona.rozvrh_posun' => '23', 'app.url' => 'https://web.cz']);
        $this->assertSame(23, Rozvrh::posun());

        config(['sablona.rozvrh_posun' => null]);
        $this->assertSame(crc32('web.cz') % 60, Rozvrh::posun());
    }

    public function test_udrzba_je_rozlozena(): void
    {
        $posun = Rozvrh::posun();
        $udalosti = collect(app(Schedule::class)->events());
        $vyraz = fn (string $co) => $udalosti->first(fn (Event $e) => str_contains((string) $e->description, $co) || str_contains((string) $e->command, $co))?->expression;

        $this->assertSame(Rozvrh::posunVyraz('0 1 * * *', $posun), $vyraz('logs:prune'));
        $this->assertSame(Rozvrh::posunVyraz('0 0 * * *', $posun), $vyraz('udrzba-databaze'));
    }

    public function test_udrzba_databaze_uklidi_jen_prosle(): void
    {
        config(['cache.default' => 'database']);
        $ted = now()->getTimestamp();

        if (Schema::hasTable('cache')) {
            DB::table('cache')->insert([['key' => 'stara', 'value' => 'x', 'expiration' => $ted - 10], ['key' => 'platna', 'value' => 'x', 'expiration' => $ted + 3600]]);
        }

        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->insert([
                ['uuid' => 'a', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays(40)],
                ['uuid' => 'b', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDay()],
            ]);
        }

        $vysledek = UdrzbaDatabaze::spust();

        if (Schema::hasTable('cache')) {
            $this->assertSame(['platna'], DB::table('cache')->pluck('key')->all());
        }

        if (Schema::hasTable('failed_jobs')) {
            $this->assertSame(['b'], DB::table('failed_jobs')->pluck('uuid')->all());
        }

        $this->assertArrayHasKey('obnova_hesla', $vysledek);
    }
}
