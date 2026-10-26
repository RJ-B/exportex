<?php

namespace Tests\Feature;

use App\Enums\StavWebu;
use App\Models\AuditLog;
use App\Models\Nastaveni;
use App\Models\User;
use App\Support\TextyStavuWebu;
use App\Support\UdrzbaWebu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `simren:udrzba stav|zapnout|vypnout --json` – Stav webu „Údržba“ pro portál
 * Sim&Ren (jedno tlačítko Vypnout údržbu u instalace vypne artisan down i tohle).
 */
class UdrzbaWebuTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: int, 1: array<string, mixed>} */
    private function prikaz(string $akce): array
    {
        $kod = Artisan::call('simren:udrzba', ['akce' => $akce, '--json' => true]);
        $radky = array_values(array_filter(explode("\n", trim(Artisan::output()))));

        return [$kod, json_decode(end($radky), true, flags: JSON_THROW_ON_ERROR)];
    }

    public function test_stav_bez_zmeny(): void
    {
        [$kod, $v] = $this->prikaz('stav');

        $this->assertSame(0, $kod);
        $this->assertTrue($v['ok']);
        $this->assertFalse($v['zmeneno']);
        $this->assertSame(['stav' => 'online', 'nazev' => 'Online', 'zapnuta' => false, 'od' => null, 'text' => null, 'konec' => null], $v['stav_webu']);
        $this->assertSame(0, AuditLog::where('event', 'stav_webu.zmenen')->count(), 'Zjištění nic nezapisuje.');
    }

    public function test_zapnout_a_vypnout_idempotentne_s_aktivitou(): void
    {
        [$kod, $v] = $this->prikaz('zapnout');
        $this->assertSame(0, $kod);
        $this->assertTrue($v['zmeneno']);
        $this->assertSame('udrzba', $v['stav_webu']['stav']);
        $this->assertTrue($v['stav_webu']['zapnuta']);
        $this->assertNotNull($v['stav_webu']['od']);
        $this->assertSame(StavWebu::Udrzba, StavWebu::aktualni());
        $this->assertStringStartsWith('Portál Sim&Ren · ', Nastaveni::hodnota(StavWebu::KLIC.'.zmena'));

        // Veřejný web je zavřený, /zdravi běží dál.
        $this->get('/')->assertStatus(503);
        $this->get('/zdravi')->assertOk();

        [, $v] = $this->prikaz('zapnout');
        $this->assertFalse($v['zmeneno'], 'Zapnutá se znovu nezapíná.');

        [$kod, $v] = $this->prikaz('vypnout');
        $this->assertSame(0, $kod);
        $this->assertTrue($v['zmeneno']);
        $this->assertSame(['stav' => 'online', 'nazev' => 'Online', 'zapnuta' => false, 'od' => null, 'text' => null, 'konec' => null], $v['stav_webu']);

        [, $v] = $this->prikaz('vypnout');
        $this->assertFalse($v['zmeneno'], 'Vypnutá se nevypíná.');

        $this->assertSame(
            ['Stav webu: Údržba zapnuta', 'Stav webu: Údržba vypnuta → Online'],
            AuditLog::where('event', 'stav_webu.zmenen')->orderBy('id')->pluck('summary')->all(),
        );
    }

    public function test_pripravujeme_se_nemeni(): void
    {
        Nastaveni::nastav(StavWebu::KLIC, StavWebu::Pripravujeme->value);

        [$kod, $v] = $this->prikaz('vypnout');
        $this->assertSame(0, $kod);
        $this->assertFalse($v['zmeneno'], 'Vypnutí údržby nesmí otevřít nespuštěný web.');
        $this->assertSame('pripravujeme', $v['stav_webu']['stav']);

        [$kod, $v] = $this->prikaz('zapnout');
        $this->assertSame(1, $kod);
        $this->assertFalse($v['ok']);
        $this->assertStringContainsString('Připravujeme', $v['chyba']);
        $this->assertSame(StavWebu::Pripravujeme, StavWebu::aktualni());
    }

    public function test_neznama_akce_je_chyba(): void
    {
        [$kod, $v] = $this->prikaz('restart');

        $this->assertSame(1, $kod);
        $this->assertFalse($v['ok']);
        $this->assertSame(StavWebu::Online, StavWebu::aktualni());
    }

    public function test_shim_pozna_prikaz_podle_souboru(): void
    {
        // portal-deploy hledá app/Support/UdrzbaWebu.php – bez něj příkaz nespustí;
        // S_TEXTEM v něm = umí --vstup (text a konec z CRM), pripravujeme a spustit.
        $this->assertFileExists(app_path('Support/UdrzbaWebu.php'));
        $this->assertStringContainsString('S_TEXTEM', (string) file_get_contents(app_path('Support/UdrzbaWebu.php')));
    }

    public function test_udrzba_z_crm_s_textem_a_koncem(): void
    {
        $this->travelTo(today()->setTime(10, 0));
        $konec = today()->setTime(14, 30);

        $v = UdrzbaWebu::proved('zapnout', ['text' => 'Nasazujeme novou verzi rezervací.', 'konec' => $konec->toIso8601String()]);

        $this->assertTrue($v['zmeneno']);
        $this->assertSame('Nasazujeme novou verzi rezervací.', $v['stav_webu']['text']);
        $this->assertTrue($konec->equalTo($v['stav_webu']['konec']));

        $odpoved = $this->get('/');
        $odpoved->assertStatus(503)
            ->assertSee('Nasazujeme novou verzi rezervací.')
            ->assertSee('Hotovo přibližně v 14:30.');
        $this->assertSame((string) (4.5 * 3600), $odpoved->headers->get('Retry-After'));
        $this->get('/zdravi')->assertOk();

        // Přihlášený (správce) vidí web dál celý.
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
        auth()->logout();

        // Znovu zapnout = jen nový text a konec, stav se nemění.
        $v = UdrzbaWebu::proved('zapnout', ['text' => '', 'konec' => null]);
        $this->assertFalse($v['zmeneno']);
        $this->assertNull($v['stav_webu']['text']);
        $this->get('/')->assertStatus(503)->assertSee(TextyStavuWebu::vychozi()['udrzba_text'])->assertDontSee('Hotovo přibližně');

        // Po konci se nic neslibuje; vypnutí plán smaže.
        UdrzbaWebu::proved('zapnout', ['text' => 'Ještě chvíli', 'konec' => today()->setTime(9, 0)->toIso8601String()]);
        $this->get('/')->assertSee('Ještě chvíli')->assertDontSee('Hotovo přibližně');
        UdrzbaWebu::proved('vypnout');
        $this->assertNull(Nastaveni::hodnota(UdrzbaWebu::KLIC_TEXT));
        $this->assertNull(Nastaveni::hodnota(UdrzbaWebu::KLIC_KONEC));
        $this->get('/')->assertOk();
    }

    public function test_starsi_portal_bez_vstupu_text_nemeni(): void
    {
        [$kod, $v] = $this->prikaz('zapnout');

        $this->assertSame(0, $kod);
        $this->assertNull($v['stav_webu']['text']);
        $this->assertNull($v['stav_webu']['konec']);
    }

    public function test_neplatny_konec_je_chyba(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UdrzbaWebu::proved('zapnout', ['konec' => 'zítra odpoledne']);
    }

    public function test_pripravujeme_a_spusteni(): void
    {
        [$kod, $v] = $this->prikaz('pripravujeme');
        $this->assertSame(0, $kod);
        $this->assertTrue($v['zmeneno']);
        $this->assertSame('pripravujeme', $v['stav_webu']['stav']);
        $this->get('/')->assertOk()->assertSee(TextyStavuWebu::vychozi()['pripravujeme_nadpis']);

        // Údržba ani vypnutí údržby Připravujeme neotevřou.
        [$kod] = $this->prikaz('zapnout');
        $this->assertSame(1, $kod);
        [, $v] = $this->prikaz('vypnout');
        $this->assertSame('pripravujeme', $v['stav_webu']['stav']);

        [$kod, $v] = $this->prikaz('spustit');
        $this->assertSame(0, $kod);
        $this->assertTrue($v['zmeneno']);
        $this->assertSame('online', $v['stav_webu']['stav']);

        [, $v] = $this->prikaz('spustit');
        $this->assertFalse($v['zmeneno'], 'Spuštěný web zůstává.');
    }

    public function test_pripravujeme_z_udrzby_nejde_a_spustit_udrzbu_nevypne(): void
    {
        $this->prikaz('zapnout');

        [$kod, $v] = $this->prikaz('pripravujeme');
        $this->assertSame(1, $kod);
        $this->assertStringContainsString('údržbě', $v['chyba']);

        [, $v] = $this->prikaz('spustit');
        $this->assertFalse($v['zmeneno']);
        $this->assertSame('udrzba', $v['stav_webu']['stav']);
    }
}
