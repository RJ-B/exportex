<?php

namespace Tests\Feature;

use App\Filament\Pages\Posta as PostaStranka;
use App\Models\AuditLog;
use App\Models\Nastaveni;
use App\Models\User;
use App\Support\Posta;
use App\Support\PostaDns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Pošta přes schránku nastavenou v aplikaci + návod a kontrola DNS domény. */
class PostaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nikdy se nepřipojovat ke skutečnému SMTP. Heslo „spatne“ přihlášení odmítne.
        Posta::$prihlaseni = function (array $n, string $heslo): void {
            if ($heslo === 'spatne') {
                throw new \RuntimeException('535 5.7.8 Authentication failed');
            }
        };
    }

    protected function tearDown(): void
    {
        PostaDns::$resolver = null;
        Posta::$prihlaseni = null;
        parent::tearDown();
    }

    private function spravce(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_bez_schranky_plati_env(): void
    {
        Posta::pouzij();

        $this->assertSame('array', config('mail.default'));   // phpunit.xml
        $this->assertFalse(Posta::kompletni());
    }

    public function test_ulozena_schranka_prepne_odesilani_a_heslo_je_sifrovane(): void
    {
        Posta::uloz(['uzivatel' => 'info@firma.cz', 'jmeno' => 'Firma', 'host' => 'smtp.seznam.cz', 'port' => '465', 'sifrovani' => 'smtps', 'heslo' => 'Tajne-heslo1']);

        $this->assertNotSame('Tajne-heslo1', Nastaveni::query()->whereKey('posta.heslo')->value('hodnota'));
        $this->assertTrue(Posta::kompletni());
        $this->assertSame('schranka', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.schranka.scheme'));
        $this->assertSame('Tajne-heslo1', config('mail.mailers.schranka.password'));
        $this->assertSame(['address' => 'info@firma.cz', 'name' => 'Firma'], config('mail.from'));

        // Prázdné heslo při dalším uložení = nechat stávající.
        Posta::uloz(['uzivatel' => 'info@firma.cz', 'heslo' => null]);
        $this->assertTrue(Posta::kompletni());
    }

    public function test_heslo_se_neobjevi_v_aktivite(): void
    {
        $this->actingAs($this->spravce());
        Posta::uloz(['uzivatel' => 'info@firma.cz', 'heslo' => 'Prvni-heslo1']);
        Posta::uloz(['uzivatel' => 'info@firma.cz', 'heslo' => 'Druhe-heslo2']);

        $zapisy = AuditLog::query()->get()->map(fn ($l) => json_encode([$l->old_values ?? null, $l->new_values ?? null, $l->toArray()]))->implode(' ');
        $sifra = Nastaveni::query()->whereKey('posta.heslo')->value('hodnota');

        $this->assertStringNotContainsString('heslo1', $zapisy);
        $this->assertStringNotContainsString(substr($sifra, 0, 30), $zapisy);
        $this->assertStringContainsString('heslo skryto', $zapisy);
    }

    public function test_stranka_heslo_nevraci_a_diakritiku_odmitne(): void
    {
        Posta::uloz(['uzivatel' => 'info@firma.cz', 'heslo' => 'Tajne-heslo1']);

        Livewire::actingAs($this->spravce())->test(PostaStranka::class)
            ->assertSet('data.heslo', null)
            ->assertDontSee('Tajne-heslo1')
            ->assertSee('Posílá se přes schránku info@firma.cz')
            ->set('data.heslo', 'Příliš-české1')
            ->call('uloz')
            ->assertHasFormErrors(['heslo' => 'regex']);
    }

    public function test_klient_se_na_postu_nedostane(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'klient']))
            ->get('/admin/posta')
            ->assertForbidden();
    }

    public function test_zkusebni_email_odejde_prihlasenemu(): void
    {
        $spravce = $this->spravce();

        Livewire::actingAs($spravce)->test(PostaStranka::class)->callAction('zkusebni');

        // Bez schránky platí .env (v testu transport array); odchozí mail se zapíše do Logy → E-maily.
        $this->assertTrue(\App\Models\MailLog::query()->where('to_email', $spravce->email)->exists());
    }

    public function test_navod_a_kontrola_dns(): void
    {
        $navod = collect(PostaDns::navod('firma.cz', 'info@firma.cz'));
        $this->assertTrue($navod->contains('hodnota', 'szn1._domainkey.seznam.cz'));
        $this->assertTrue($navod->contains('hodnota', 'v=DMARC1; p=quarantine; rua=mailto:info@firma.cz'));

        PostaDns::$resolver = fn (string $host, int $typ) => match (true) {
            $typ === DNS_MX => [['target' => 'abc.mx1.emailprofi.seznam.cz'], ['target' => 'abc.mx2.emailprofi.seznam.cz']],
            $host === 'firma.cz' => [['txt' => 'v=spf1 include:spf.seznam.cz -all'], ['txt' => 'google-site-verification=x']],
            $host === 'szn1._domainkey.firma.cz', $host === 'szn2._domainkey.firma.cz' => [['txt' => 'v=DKIM1; k=rsa; p=MIIB']],
            $host === '_dmarc.firma.cz' => [['txt' => 'v=DMARC1; p=none']],
            default => [],
        };

        $stav = collect(PostaDns::over('firma.cz'))->pluck('stav', 'zaznam')->all();
        $this->assertSame('abc', PostaDns::prefixMx('firma.cz'));
        $this->assertSame('abc.mx2.emailprofi.seznam.cz (priorita 10)', PostaDns::navod('firma.cz', null, 'abc')[0]['hodnota']);

        $this->assertSame(['MX' => 'ok', 'SPF' => 'ok', 'DKIM szn1' => 'ok', 'DKIM szn2' => 'ok', 'DKIM szn3' => 'chyba', 'DMARC' => 'varovani'], $stav);
    }

    public function test_dva_spf_zaznamy_jsou_chyba(): void
    {
        PostaDns::$resolver = fn (string $host, int $typ) => $host === 'firma.cz' && $typ === DNS_TXT
            ? [['txt' => 'v=spf1 include:spf.seznam.cz -all'], ['txt' => 'v=spf1 include:_spf.google.com ~all']]
            : [];

        $spf = collect(PostaDns::over('firma.cz'))->firstWhere('zaznam', 'SPF');

        $this->assertSame('chyba', $spf['stav']);
    }

    public function test_kontrola_prihlaseni_se_propise_do_zdravi(): void
    {
        $this->artisan('posta:kontrola')->assertSuccessful();
        $this->assertFalse(\App\Support\Zdravi::diagnostika()['posta']['nastavena']);

        // Odmítnuté přihlášení (třeba změněné heslo) = /zdravi to ukáže.
        Posta::uloz(['uzivatel' => 'info@firma.cz', 'heslo' => 'spatne']);
        $this->artisan('posta:kontrola')->assertFailed();

        $posta = \App\Support\Zdravi::diagnostika()['posta'];
        $this->assertTrue($posta['nastavena']);
        $this->assertFalse($posta['ok']);
        $this->assertStringContainsString('POZOR', Posta::popisStavu());
    }

    public function test_nefunkcni_heslo_se_neulozi(): void
    {
        Livewire::actingAs($this->spravce())->test(PostaStranka::class)
            ->set('data.uzivatel', 'info@firma.cz')
            ->set('data.heslo', 'spatne')
            ->call('uloz')
            ->assertHasErrors(['data.heslo']);

        $this->assertFalse(Posta::kompletni());
        $this->assertStringContainsString('Seznam heslo odmítl', Posta::otestuj(['uzivatel' => 'info@firma.cz', 'host' => 'smtp.seznam.cz', 'heslo' => 'spatne'])['zprava']);
        // Exportex: hláška podle serveru – u Forpsi i u jiného SMTP ne „Seznam“.
        $this->assertStringContainsString('Forpsi heslo odmítl', Posta::otestuj(['uzivatel' => 'info@firma.cz', 'host' => 'smtp.forpsi.com', 'heslo' => 'spatne'])['zprava']);
        $this->assertStringContainsString('Server pošty (mail.firma.cz) heslo odmítl', Posta::otestuj(['uzivatel' => 'info@firma.cz', 'host' => 'mail.firma.cz', 'heslo' => 'spatne'])['zprava']);
    }

    public function test_bezplatna_schranka_nema_navod_na_dns(): void
    {
        $this->assertTrue(Posta::bezplatna('vlasta@seznam.cz'));
        $this->assertFalse(Posta::bezplatna('info@firma.cz'));

        Livewire::actingAs($this->spravce())->test(PostaStranka::class)
            ->set('data.host', 'smtp.seznam.cz')
            ->set('data.uzivatel', 'vlasta@seznam.cz')
            ->assertSee('DNS se nenastavuje')
            ->assertDontSee('szn1._domainkey');
    }

    // ---- Exportex: pošta u Forpsi (jiný SMTP server než Seznam) ----

    public function test_po_migraci_je_predvyplneny_forpsi_bez_hesla(): void
    {
        $this->assertSame(['uzivatel' => 'mikyska@exportex.cz', 'host' => 'smtp.forpsi.com', 'port' => '465', 'sifrovani' => 'smtps', 'heslo_ulozeno' => false],
            collect(Posta::nacti())->only(['uzivatel', 'host', 'port', 'sifrovani', 'heslo_ulozeno'])->all());
        $this->assertFalse(Posta::kompletni(), 'Heslo zadá správce sám – bez něj se nic nepřepíná.');
        // Portál zapisuje DNS pošty jen u Seznamu – u Forpsi doménu nedostane.
        $this->assertNull(Posta::domenaSchranky());

        Livewire::actingAs($this->spravce())->test(PostaStranka::class)
            ->assertSet('data.poskytovatel', 'forpsi')
            ->assertSet('data.host', 'smtp.forpsi.com')
            ->assertSet('data.heslo', null)
            ->assertDontSee('Seznam jinou nepustí')
            ->assertDontSee('Doménu zaregistruj v');
    }

    public function test_forpsi_se_ulozi_a_posila_se_pres_nej(): void
    {
        $prihlaseni = [];
        Posta::$prihlaseni = function (array $n, string $heslo) use (&$prihlaseni): void {
            $prihlaseni[] = [$n['host'], (int) $n['port'], $n['sifrovani']];
        };

        Livewire::actingAs($this->spravce())->test(PostaStranka::class)
            ->set('data.poskytovatel', 'seznam')
            ->assertSet('data.host', 'smtp.seznam.cz')
            ->set('data.poskytovatel', 'forpsi')
            ->assertSet('data.host', 'smtp.forpsi.com')
            ->assertSet('data.port', '465')
            ->assertSet('data.sifrovani', 'smtps')
            ->set('data.heslo', 'Testovaci-heslo1')
            ->call('uloz')
            ->assertHasNoErrors();

        $this->assertSame([['smtp.forpsi.com', 465, 'smtps']], array_slice($prihlaseni, 0, 1));
        $this->assertTrue(Posta::kompletni());
        $this->assertSame('smtp.forpsi.com', config('mail.mailers.schranka.host'));
        $this->assertSame('smtps', config('mail.mailers.schranka.scheme'));
        $this->assertSame('mikyska@exportex.cz', config('mail.from.address'));
    }

    public function test_jiny_smtp_server_jde_zadat_rucne(): void
    {
        Livewire::actingAs($this->spravce())->test(PostaStranka::class)
            ->set('data.poskytovatel', 'jiny')
            ->set('data.host', 'mail.firma.cz')
            ->set('data.port', '587')
            ->set('data.sifrovani', 'tls')
            ->set('data.heslo', 'Testovaci-heslo1')
            ->call('uloz')
            ->assertHasNoErrors()
            ->assertSee('tady se nekontrolují');

        $this->assertSame(['mail.firma.cz', '587', 'tls'], array_values(collect(Posta::nacti())->only(['host', 'port', 'sifrovani'])->all()));
        $this->assertSame('smtp', config('mail.mailers.schranka.scheme'));
    }

    public function test_dns_forpsi_jako_u_exportex_je_v_poradku(): void
    {
        // Skutečné záznamy exportex.cz: MX mxavas.forpsi.com, SPF Forpsi, DMARC quarantine, DKIM f2026.
        PostaDns::$resolver = fn (string $host, int $typ) => match (true) {
            $typ === DNS_MX && $host === 'exportex.cz' => [['target' => 'mxavas.forpsi.com']],
            $typ === DNS_TXT && $host === 'exportex.cz' => [['txt' => 'v=spf1 include:_spf.forpsi.com -all']],
            $typ === DNS_TXT && $host === '_dmarc.exportex.cz' => [['txt' => 'v=DMARC1; p=quarantine']],
            default => [],
        };

        $this->assertSame('forpsi', PostaDns::poskytovatel('smtp.forpsi.com'));
        $this->assertSame(['MX' => 'ok', 'SPF' => 'ok', 'DMARC' => 'ok'], collect(PostaDns::over('exportex.cz', 'forpsi'))->pluck('stav', 'zaznam')->all());

        $navod = collect(PostaDns::navod('exportex.cz', 'mikyska@exportex.cz', null, 'forpsi'));
        $this->assertTrue($navod->contains('hodnota', 'v=spf1 include:_spf.forpsi.com -all'));
        $this->assertFalse($navod->contains(fn ($r) => str_contains($r['hodnota'], 'seznam')));

        // Na stránce: kontrola podle Forpsi, žádné CNAME na Seznam.
        Livewire::actingAs($this->spravce())->test(PostaStranka::class)
            ->call('zkontrolujDns')
            ->assertSet('dns', fn ($dns) => collect($dns)->pluck('stav')->unique()->values()->all() === ['ok'])
            ->assertSee('include:_spf.forpsi.com')
            ->assertDontSee('szn1._domainkey');
    }

    public function test_spf_bez_forpsi_je_chyba(): void
    {
        PostaDns::$resolver = fn (string $host, int $typ) => match (true) {
            $typ === DNS_MX => [['target' => 'mxavas.forpsi.com']],
            $host === 'firma.cz' => [['txt' => 'v=spf1 include:spf.seznam.cz -all']],
            default => [],
        };

        $stav = collect(PostaDns::over('firma.cz', 'forpsi'))->pluck('stav', 'zaznam')->all();
        $this->assertSame(['MX' => 'ok', 'SPF' => 'chyba', 'DMARC' => 'chyba'], $stav);
    }
}
