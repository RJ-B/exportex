<?php

namespace Tests\Feature;

use App\Filament\Resources\ErrorLogResource\Pages\ListErrorLogs;
use App\Models\ErrorLog;
use App\Models\User;
use App\Services\ErrorLogger;
use App\Support\ChybyAplikace;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Chyby aplikace pro portál Sim&Ren (simren:chyby): výpis podle otisku,
 * vyřešení a vrácení z portálu a nový výskyt, který chybu vrátí.
 */
class ChybyProPortalTest extends TestCase
{
    use RefreshDatabase;

    private function chyba(string $zprava = 'Košík nenalezen'): ErrorLog
    {
        app(ErrorLogger::class)->report(new \RuntimeException($zprava));

        return ErrorLog::query()->latest('id')->first();
    }

    public function test_vypis_pro_portal(): void
    {
        $chyba = $this->chyba();

        $this->artisan('simren:chyby', ['--json' => true])->assertSuccessful();

        $vypis = ChybyAplikace::vypis();
        $this->assertSame(1, $vypis['verze']);
        $this->assertTrue($vypis['ok']);
        $radek = $vypis['chyby'][0];
        $this->assertSame($chyba->fingerprint, $radek['otisk']);
        $this->assertSame('RuntimeException', $radek['vyjimka']);
        $this->assertSame('tests/Feature/ChybyProPortalTest.php', $radek['soubor']);
        $this->assertSame(1, $radek['pocet']);
        $this->assertNull($radek['vyreseno']);
        $this->assertStringNotContainsString(base_path(), (string) $radek['trace'], 'Cesty v trace jsou relativní.');
    }

    public function test_od_vraci_jen_zmenene_a_vsechny_nevyresene(): void
    {
        $stara = $this->chyba('Stará vyřešená');
        $stara->forceFill(['resolved_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->saveQuietly();
        ErrorLog::query()->whereKey($stara->id)->update(['updated_at' => now()->subDays(2)]);
        $this->chyba('Nevyřešená');

        $vypis = ChybyAplikace::vypis(now()->subHour()->getTimestamp());

        $this->assertSame(['Nevyřešená'], collect($vypis['chyby'])->pluck('zprava')->all());
    }

    public function test_vyreseni_a_vraceni_z_portalu(): void
    {
        $chyba = $this->chyba();

        $vysledek = ChybyAplikace::vyresit($chyba->fingerprint, 'Jana Nováková (portál)', 'Opraveno ve verzi 1.4.2');
        $this->assertTrue($vysledek['ok']);
        $chyba->refresh();
        $this->assertNotNull($chyba->resolved_at);
        $this->assertSame('Jana Nováková (portál)', $chyba->resolvedByLabel());
        $this->assertSame('Opraveno ve verzi 1.4.2', $chyba->resolution_note);

        $radek = ChybyAplikace::vypis()['chyby'][0];
        $this->assertSame('Jana Nováková (portál)', $radek['vyresil']);
        $this->assertSame('Opraveno ve verzi 1.4.2', $radek['poznamka']);

        $this->assertTrue(ChybyAplikace::vratit($chyba->fingerprint)['ok']);
        $chyba->refresh();
        $this->assertNull($chyba->resolved_at);
        $this->assertNull($chyba->resolution_note);

        $this->assertFalse(ChybyAplikace::vyresit(str_repeat('0', 64), 'x', null)['ok']);
        $this->assertFalse(ChybyAplikace::vratit('../neco')['ok']);
    }

    public function test_novy_vyskyt_vyresenou_chybu_vrati(): void
    {
        $chyba = $this->chyba();
        ChybyAplikace::vyresit($chyba->fingerprint, 'Jana (portál)', 'Opraveno');

        $this->chyba();

        $chyba->refresh();
        $this->assertNull($chyba->resolved_at);
        $this->assertNull($chyba->resolved_by_name);
        $this->assertNull($chyba->resolution_note);
        $this->assertSame(2, $chyba->occurrences);
    }

    public function test_vyreseno_v_administraci_s_poznamkou(): void
    {
        $chyba = $this->chyba();
        $spravce = User::forceCreate(['email' => 'spravce@example.cz', 'jmeno' => 'Karel', 'prijmeni' => 'Dvořák', 'role' => 'superadmin', 'password' => 'x']);

        Livewire::actingAs($spravce)->test(ListErrorLogs::class)
            ->callAction(TestAction::make('vyresit')->table($chyba), ['resolution_note' => 'Chyběl sloupec, doplněná migrace.']);

        $chyba->refresh();
        $this->assertNotNull($chyba->resolved_at);
        $this->assertSame('Chyběl sloupec, doplněná migrace.', $chyba->resolution_note);
        $this->assertSame($spravce->id, $chyba->resolved_by);
        $this->assertSame($spravce->getFilamentName(), $chyba->resolvedByLabel());
    }
}
