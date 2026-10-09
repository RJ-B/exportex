<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Support\CasAplikace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pravidlo času (CLAUDE.md → Čas): aplikace i databáze žijí v Europe/Prague,
 * mezi aplikacemi chodí ISO 8601 s posunem a čas zvenku se převede do pásma
 * aplikace. Bez toho by Eloquent uložil „18:24:07Z“ jako 18:24, i když u nás
 * bylo 20:24 (projekt založený ve 20:00 ukazoval 18:00).
 */
class CasVPasmuAplikaceTest extends TestCase
{
    use RefreshDatabase;

    private function chyba(array $casy): ErrorLog
    {
        return ErrorLog::create(['fingerprint' => str_repeat('a', 40), 'level' => 'error', 'exception' => 'RuntimeException', 'message' => 'Test', 'occurrences' => 1] + $casy);
    }

    private function surove(string $sloupec): string
    {
        return (string) DB::table('error_logs')->value($sloupec);
    }

    public function test_aplikace_bezi_v_ceskem_case(): void
    {
        $this->assertSame('Europe/Prague', config('app.timezone'));
        $this->assertSame('Europe/Prague', date_default_timezone_get());
    }

    public function test_cas_s_pasmem_se_ulozi_jako_mistni(): void
    {
        $this->chyba([
            'first_seen_at' => '2026-10-08T18:24:07Z',
            'last_seen_at' => Carbon::parse('2026-01-15T18:00:00+00:00'),
            // Carbon 3: createFromTimestamp() bez pásma vrací UTC.
            'resolved_at' => Carbon::createFromTimestamp(Carbon::parse('2026-10-08 20:00:00')->getTimestamp()),
        ]);

        $this->assertSame('2026-10-08 20:24:07', $this->surove('first_seen_at'));
        $this->assertSame('2026-01-15 19:00:00', $this->surove('last_seen_at'));
        $this->assertSame('2026-10-08 20:00:00', $this->surove('resolved_at'));
    }

    public function test_mistni_cas_zustava(): void
    {
        $this->chyba(['first_seen_at' => '2026-10-08 20:24:07', 'last_seen_at' => now()]);

        $this->assertSame('2026-10-08 20:24:07', $this->surove('first_seen_at'));
        $this->assertSame(now()->format('Y-m-d H:i:s'), $this->surove('last_seen_at'));
    }

    public function test_cas_zvenku_pro_zobrazeni(): void
    {
        $this->assertSame('8. 10. 2026 20:24', CasAplikace::zVenku('2026-10-08T18:24:07Z')?->format('j. n. Y H:i'));
        $this->assertSame('8. 10. 2026 20:24', CasAplikace::zVenku('2026-10-08 20:24:07')?->format('j. n. Y H:i'));
        $this->assertSame('8. 10. 2026 20:00', CasAplikace::zVenku(Carbon::parse('2026-10-08 20:00')->getTimestamp())?->format('j. n. Y H:i'));
        $this->assertNull(CasAplikace::zVenku(null));
        $this->assertNull(CasAplikace::zVenku(''));
        $this->assertNull(CasAplikace::zVenku('nesmysl'));
    }

    public function test_api_posila_cas_s_posunem(): void
    {
        $this->assertSame('2026-10-08T20:24:07+02:00', Carbon::parse('2026-10-08 20:24:07')->toIso8601String());
    }
}
