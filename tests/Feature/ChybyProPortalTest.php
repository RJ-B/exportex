<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Support\ChybyAplikace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chyby aplikace pro portál Sim&Ren (simren:chyby, ze šablony): výpis podle
 * otisku, vyřešení a vrácení z portálu.
 */
class ChybyProPortalTest extends TestCase
{
    use RefreshDatabase;

    private function chyba(string $zprava = 'Košík nenalezen', ?string $vyreseno = null): ErrorLog
    {
        return ErrorLog::forceCreate([
            'fingerprint' => hash('sha256', $zprava),
            'level' => 'error',
            'exception' => \RuntimeException::class,
            'message' => $zprava,
            'file' => base_path('app/Http/Kosik.php'),
            'line' => 12,
            'trace' => '#0 '.base_path('app/Http/Kosik.php').'(12): pridat()',
            'occurrences' => 3,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
            'resolved_at' => $vyreseno,
        ]);
    }

    public function test_vypis_vyreseni_a_vraceni(): void
    {
        $chyba = $this->chyba();

        $this->artisan('simren:chyby', ['--json' => true])->assertSuccessful();

        $radek = ChybyAplikace::vypis()['chyby'][0];
        $this->assertSame($chyba->fingerprint, $radek['otisk']);
        $this->assertSame('app/Http/Kosik.php', $radek['soubor']);
        $this->assertSame(3, $radek['pocet']);
        $this->assertStringNotContainsString(base_path(), (string) $radek['trace']);
        $this->assertNull($radek['vyreseno']);

        $this->assertTrue(ChybyAplikace::vyresit($chyba->fingerprint, 'Jana Nováková (portál)', 'Opraveno ve verzi 1.4.2')['ok']);
        $radek = ChybyAplikace::vypis()['chyby'][0];
        $this->assertNotNull($radek['vyreseno']);
        $this->assertSame('Jana Nováková (portál)', $radek['vyresil']);
        $this->assertSame('Opraveno ve verzi 1.4.2', $radek['poznamka']);

        $this->assertTrue(ChybyAplikace::vratit($chyba->fingerprint)['ok']);
        $this->assertNull($chyba->fresh()->resolved_at);
        $this->assertFalse(ChybyAplikace::vratit('../neco')['ok']);
    }

    public function test_od_vraci_zmenene_a_nevyresene(): void
    {
        $stara = $this->chyba('Stará vyřešená', now()->subDays(2)->toDateTimeString());
        ErrorLog::query()->whereKey($stara->id)->update(['updated_at' => now()->subDays(2)]);
        $this->chyba('Nevyřešená');

        $this->assertSame(['Nevyřešená'], collect(ChybyAplikace::vypis(now()->subHour()->getTimestamp())['chyby'])->pluck('zprava')->all());
    }
}
