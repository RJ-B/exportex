<?php

namespace Tests\Feature;

use App\Filament\Pages\Posta as PostaStranka;
use App\Filament\Resources\MailLogResource\Pages\ListMailLogs;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\Nastaveni;
use App\Models\User;
use App\Support\Posta\Odchozi;
use App\Support\Posta\Propojeni;
use App\Support\Posta\StaraSchranka;
use App\Support\Zdravi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Ovladač Pošty (posta.simren.cz): Mail::to()->send() beze změny jde do API
 * Pošty, při nedostupnosti do odchozí fronty (stejný Idempotency-Key),
 * výsledek z webhooku s podpisem V2, Poslat znovu přes Poštu, propojení
 * s PKCE a stav pro portál. Do skutečné Pošty test nic nepošle.
 */
class PostaTest extends TestCase
{
    use RefreshDatabase;

    private const POSTA = 'https://posta.test';

    private const TAJEMSTVI = 'tajemstvi-webhooku-1';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function propoj(): void
    {
        Nastaveni::nastav('posta.url', self::POSTA);
        Nastaveni::nastav('posta.token', Crypt::encryptString('pst_testovaci'));
        Nastaveni::nastav('posta.webhook_tajemstvi', Crypt::encryptString(self::TAJEMSTVI));
        Nastaveni::nastav('posta.adresy', json_encode([['adresa' => 'info@pekarna-novak.cz', 'jmeno' => 'Pekárna Novák']]));
        Propojeni::pouzij();
        Mail::forgetMailers();
    }

    private function spravce(string $role = 'admin'): User
    {
        return User::forceCreate(['email' => $role.'@pekarna-novak.cz', 'jmeno' => 'Jana', 'prijmeni' => 'Nováková', 'role' => $role, 'password' => 'x']);
    }

    private function webhook(array $zprava, ?int $cas = null, string $tajemstvi = self::TAJEMSTVI, string $doruceni = 'd-1')
    {
        $telo = json_encode(['udalost' => 'zprava.odeslana', 'zprava' => $zprava]);
        $cas ??= now()->getTimestamp();

        return $this->call('POST', '/posta/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_POSTA_DELIVERY' => $doruceni,
            'HTTP_X_POSTA_SIGNATURE_V2' => 't='.$cas.',v1='.hash_hmac('sha256', $cas.'.'.$telo, $tajemstvi),
        ], $telo);
    }

    public function test_bez_propojeni_plati_env(): void
    {
        $this->assertSame('array', config('mail.default'));
        $this->assertFalse(Propojeni::propojeno());

        $posta = Zdravi::diagnostika()['posta'];
        $this->assertFalse($posta['ok']);
        $this->assertSame('posta', $posta['zdroj']);
        $this->assertStringContainsString('není propojená s Poštou', $posta['zprava']);
    }

    public function test_mail_jde_do_api_posty_a_vysledek_z_webhooku(): void
    {
        $this->propoj();
        Http::fake([self::POSTA.'/api/v1/zpravy' => Http::response(['id' => '01K6POSTA0000000000000000A', 'stav' => 've_fronte'], 202)]);

        Mail::raw('Dobrý den, objednávka je připravená.', fn ($m) => $m
            ->from('noreply@jina-domena.cz', 'Pekárna Novák')
            ->to('jana.novakova@seznam.cz', 'Jana Nováková')
            ->cc('karel@centrum.cz')
            ->bcc('archiv@pekarna-novak.cz')
            ->replyTo('objednavky@pekarna-novak.cz')
            ->subject('Objednávka 2026-118')
            ->attachData('%PDF-1.4', 'objednavka.pdf', ['mime' => 'application/pdf']));

        Http::assertSent(function (Request $r) {
            return $r->url() === self::POSTA.'/api/v1/zpravy'
                && $r->hasHeader('Authorization', 'Bearer pst_testovaci')
                && filled($r->header('Idempotency-Key')[0] ?? null)
                // Nepřidělený odesílatel se nahradí přidělenou adresou, jméno zůstane.
                && $r['od'] === ['adresa' => 'info@pekarna-novak.cz', 'jmeno' => 'Pekárna Novák']
                && $r['komu'] === [['adresa' => 'jana.novakova@seznam.cz', 'jmeno' => 'Jana Nováková']]
                && $r['kopie'] === [['adresa' => 'karel@centrum.cz', 'jmeno' => null]]
                && $r['skryta_kopie'] === [['adresa' => 'archiv@pekarna-novak.cz', 'jmeno' => null]]
                && $r['odpovedet_na'][0]['adresa'] === 'objednavky@pekarna-novak.cz'
                && $r['predmet'] === 'Objednávka 2026-118'
                && $r['text'] === 'Dobrý den, objednávka je připravená.'
                && $r['prilohy'][0] === ['nazev' => 'objednavka.pdf', 'typ' => 'application/pdf', 'obsah' => base64_encode('%PDF-1.4')];
        });

        $log = MailLog::sole();
        $this->assertSame(MailLog::STATUS_QUEUED, $log->status);
        $this->assertSame('01K6POSTA0000000000000000A', $log->posta_id);
        $this->assertSame('Ve frontě Pošty', $log->statusLabel());

        // Webhook: špatný podpis ani starý čas neprojde.
        $this->webhook(['id' => $log->posta_id, 'stav' => 'odeslano'], tajemstvi: 'cizi')->assertUnauthorized();
        $this->webhook(['id' => $log->posta_id, 'stav' => 'odeslano'], cas: now()->subMinutes(10)->getTimestamp())->assertUnauthorized();
        $this->assertSame(MailLog::STATUS_QUEUED, $log->fresh()->status);

        $this->webhook(['id' => $log->posta_id, 'stav' => 'odeslano', 'pokusu' => 2, 'odeslano' => now()->toIso8601String()])->assertOk();
        $this->assertSame(MailLog::STATUS_SENT, $log->fresh()->status);
        $this->assertSame(2, $log->fresh()->attempts);

        // Zpráva, ke které aplikace ještě nemá id (výsledek předběhl odpověď API) – 409, Pošta zopakuje.
        $this->webhook(['id' => '01K6NEZNAMA000000000000000', 'stav' => 'odeslano'], doruceni: 'd-neznama')->assertStatus(409);
        $this->webhook(['id' => '01K6NEZNAMA000000000000000', 'stav' => 'odeslano'], doruceni: 'd-neznama')->assertStatus(409);

        // Každé doručení jen jednou.
        $log->fresh()->update(['status' => MailLog::STATUS_QUEUED]);
        $this->webhook(['id' => $log->posta_id, 'stav' => 'nedoruceno', 'chyba' => 'x'])->assertOk()->assertJson(['message' => 'Už zpracováno.']);
        $this->assertSame(MailLog::STATUS_QUEUED, $log->fresh()->status);
    }

    public function test_posta_nedostupna_zprava_do_fronty_a_preda_se_se_stejnym_klicem(): void
    {
        $this->propoj();
        Http::fakeSequence(self::POSTA.'/api/v1/zpravy')
            ->push(['message' => 'Server Error'], 503)
            ->push(['message' => 'Server Error'], 503)
            ->push(['id' => '01K6POSTA0000000000000000B', 'stav' => 've_fronte'], 202);

        Mail::raw('Text', fn ($m) => $m->to('jana@seznam.cz')->subject('Rezervace'));

        // Aplikace jede dál; zpráva čeká lokálně.
        $log = MailLog::sole();
        $this->assertSame(MailLog::STATUS_QUEUED, $log->status);
        $this->assertNull($log->posta_id);
        $odchozi = Odchozi::sole();
        $this->assertSame($log->id, $odchozi->mail_log_id);
        $this->assertNotSame('{', substr((string) $odchozi->getRawOriginal('zprava'), 0, 1), 'obsah ve frontě je šifrovaný');

        $this->travel(2)->minutes();
        $this->artisan('posta:fronta')->assertSuccessful();
        $this->assertSame(2, Odchozi::sole()->pokusu);

        $this->travel(5)->minutes();
        $this->artisan('posta:fronta')->assertSuccessful();

        $this->assertSame(0, Odchozi::count());
        $this->assertSame('01K6POSTA0000000000000000B', $log->fresh()->posta_id);

        $klice = collect(Http::recorded())->map(fn ($z) => $z[0]->header('Idempotency-Key')[0] ?? null)->filter()->unique();
        $this->assertCount(1, $klice, 'všechny pokusy se stejným Idempotency-Key – Pošta zprávu nepošle dvakrát');

        $this->assertTrue(Zdravi::diagnostika()['posta']['ok']);
    }

    public function test_dlouha_nedostupnost_hlasi_portal(): void
    {
        $this->propoj();
        Http::fake([self::POSTA.'/*' => Http::response(['message' => 'down'], 502)]);

        Mail::raw('Text', fn ($m) => $m->to('jana@seznam.cz')->subject('Rezervace'));
        $this->travel(31)->minutes();

        $posta = Zdravi::diagnostika()['posta'];
        $this->assertFalse($posta['ok']);
        $this->assertStringContainsString('Pošta nepřijímá zprávy', $posta['zprava']);
    }

    public function test_odmitnuta_zprava_je_chyba_jako_u_smtp(): void
    {
        $this->propoj();
        Http::fake([self::POSTA.'/api/v1/zpravy' => Http::response(['message' => 'Neplatná data.', 'errors' => ['komu.0' => ['Neplatná adresa: x@']]], 422)]);

        try {
            Mail::raw('Text', fn ($m) => $m->to('jana@seznam.cz')->subject('Rezervace'));
            $this->fail('Odmítnutá zpráva musí vyhodit výjimku.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('Neplatná adresa', $e->getMessage());
        }

        $log = MailLog::sole();
        $this->assertSame(MailLog::STATUS_FAILED, $log->status);
        $this->assertFalse($log->isRetryable(), 'odmítnutou zprávu Pošta nedrží – poslat znovu jde jen z aplikace');
        $this->assertSame(0, Odchozi::count());
    }

    public function test_bez_webhooku_se_stav_zjisti_dotazem_a_nedorucenou_posle_posta_znovu(): void
    {
        $this->propoj();
        $id = '01K6POSTA0000000000000000C';
        Http::fake([
            self::POSTA.'/api/v1/zpravy' => Http::response(['id' => $id, 'stav' => 've_fronte'], 202),
            self::POSTA.'/api/v1/zpravy/'.$id => Http::response(['id' => $id, 'stav' => 'nedoruceno', 'pokusu' => 7, 'chyba' => 'Nedoručeno ani po 7 pokusech: 451']),
            self::POSTA.'/api/v1/zpravy/'.$id.'/znovu' => Http::response(['id' => $id, 'stav' => 've_fronte', 'pokusu' => 7], 202),
        ]);

        Mail::raw('Text', fn ($m) => $m->to('jana@seznam.cz')->subject('Rezervace'));
        $this->travel(16)->minutes();
        $this->artisan('posta:fronta')->assertSuccessful();

        $log = MailLog::sole();
        $this->assertSame(MailLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('Nedoručeno ani po 7 pokusech', $log->error);
        $this->assertTrue($log->isRetryable());

        Livewire::actingAs($this->spravce('superadmin'))->test(ListMailLogs::class)
            ->callTableAction('poslat_znovu', $log)
            ->assertNotified('Pošta zprávu zařadila znovu – výsledek uvidíš tady.');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/'.$id.'/znovu'));
        $this->assertSame(MailLog::STATUS_QUEUED, $log->fresh()->status);
        $this->assertNotNull($log->fresh()->failed_at, 'stopa po chybě zůstává');
    }

    public function test_propojeni_s_pkce_a_odpojeni(): void
    {
        $this->actingAs($this->spravce());

        Livewire::test(PostaStranka::class)
            ->assertSee('není propojená s Poštou')
            ->callAction('propojit', ['url' => self::POSTA])
            ->assertRedirectContains(self::POSTA.'/propojeni?');

        $ulozene = session('posta.propojeni');
        Http::fake([self::POSTA.'/api/v1/propojeni/token' => Http::response([
            'token' => 'pst_novy', 'webhook_tajemstvi' => 'nove-tajemstvi', 'token_plati_do' => now()->addYear()->toIso8601String(),
            'aplikace' => ['nazev' => 'Pekárna', 'adresy' => [['adresa' => 'info@pekarna-novak.cz', 'jmeno' => 'Pekárna Novák']], 'token_plati_do' => now()->addYear()->toIso8601String()],
        ])]);

        $this->get('/posta/propojeni/navrat?code=kod123&state='.$ulozene['state'])->assertRedirect(PostaStranka::getUrl());

        $this->assertTrue(Propojeni::propojeno());
        $this->assertSame('pst_novy', Propojeni::token());
        $this->assertNotSame('pst_novy', Nastaveni::hodnota('posta.token'), 'token je zašifrovaný');
        $this->assertSame('info@pekarna-novak.cz', Propojeni::odesilatel()['adresa']);
        Http::assertSent(fn (Request $r) => $r['code'] === 'kod123' && $r['code_verifier'] === $ulozene['klic']);

        // Cizí state neprojde.
        $this->get('/posta/propojeni/navrat?code=x&state=cizi');
        Http::assertSentCount(1);

        Http::fake([self::POSTA.'/api/v1/propojeni' => Http::response(['message' => 'Odpojeno.'])]);
        Livewire::test(PostaStranka::class)->callAction('odpojit')->assertNotified('Odpojeno od Pošty');
        $this->assertFalse(Propojeni::propojeno());
    }

    public function test_obnova_tokenu_pred_vyprsenim(): void
    {
        $this->propoj();
        Nastaveni::nastav('posta.token_plati_do', now()->addDays(20)->toIso8601String());
        Http::fake([self::POSTA.'/api/v1/token/obnovit' => Http::response(['token' => 'pst_obnoveny', 'token_plati_do' => now()->addYear()->toIso8601String()])]);

        $this->artisan('posta:obnov-token')->expectsOutput('Token obnoven.');

        $this->assertSame('pst_obnoveny', Propojeni::token());
    }

    public function test_tokeny_a_tajemstvi_do_aktivity_nejdou(): void
    {
        $this->actingAs($this->spravce());
        $this->propoj();

        $this->assertDatabaseMissing('audit_logs', ['new_values' => json_encode(['klic' => 'posta.token', 'hodnota' => Nastaveni::hodnota('posta.token')])]);
        $this->assertStringNotContainsString(Nastaveni::hodnota('posta.token'), AuditLog::query()->pluck('new_values')->toJson());
    }

    /** Stará schránka ze starší šablony (Administrace → Pošta). */
    private function staraSchranka(): void
    {
        Nastaveni::nastav('posta.uzivatel', 'info@pekarna-novak.cz');
        Nastaveni::nastav('posta.heslo', Crypt::encryptString('Stare-heslo-ř9'));
        Nastaveni::nastav('posta.host', 'smtp.seznam.cz');
        Nastaveni::nastav('posta.port', '465');
        Nastaveni::nastav('posta.sifrovani', 'smtps');
        Nastaveni::nastav('posta.jmeno', 'Pekárna Novák');
        Nastaveni::nastav('posta.kontrola', '{"nastavena":true,"ok":true}');
    }

    private function tokenOdpoved(?string $prevzeti): array
    {
        return [
            'token' => 'pst_novy', 'webhook_tajemstvi' => self::TAJEMSTVI, 'token_plati_do' => now()->addYear()->toIso8601String(),
            'aplikace' => ['nazev' => 'Pekárna', 'adresy' => [], 'token_plati_do' => now()->addYear()->toIso8601String()],
            'prevzeti' => $prevzeti ? ['adresa' => $prevzeti, 'plati_do' => now()->addMinutes(10)->toIso8601String()] : null,
        ];
    }

    public function test_prevzeti_stare_schranky_pri_propojeni_a_smazani_po_zkusebnim_emailu(): void
    {
        $this->actingAs($this->spravce());
        $this->staraSchranka();

        $stranka = Livewire::test(PostaStranka::class)
            ->assertSee('starou schránku')
            ->assertSee('info@pekarna-novak.cz');
        $stranka->callAction('propojit', ['url' => self::POSTA, 'prevzit' => true]);
        $adresa = $stranka->effects['redirect'] ?? '';
        parse_str((string) parse_url($adresa, PHP_URL_QUERY), $q);
        $this->assertSame(['info@pekarna-novak.cz', '1'], [$q['from'], $q['prevzeti']]);

        Http::fake([
            self::POSTA.'/api/v1/propojeni/token' => Http::response($this->tokenOdpoved('info@pekarna-novak.cz')),
            self::POSTA.'/api/v1/prevzeti-schranky' => Http::response([
                'vysledek' => 'zalozena',
                'schranka' => ['adresa' => 'info@pekarna-novak.cz', 'stav' => 'ok', 'overeni' => ['ok' => true, 'zprava' => 'Přihlášení v pořádku.']],
                'aplikace' => ['adresy' => [['adresa' => 'info@pekarna-novak.cz', 'jmeno' => 'Pekárna Novák']]],
            ]),
            self::POSTA.'/api/v1/zpravy' => Http::response(['id' => '01K6ZKOUSKA000000000000000', 'stav' => 've_fronte'], 202),
        ]);

        $this->get('/posta/propojeni/navrat?code=kod&state='.$q['state'])->assertRedirect(PostaStranka::getUrl());

        // Heslo jde jen ze serveru aplikace do Pošty, s novým tokenem.
        Http::assertSent(fn (Request $r) => $r->url() === self::POSTA.'/api/v1/prevzeti-schranky'
            && $r->hasHeader('Authorization', 'Bearer pst_novy')
            && $r['adresa'] === 'info@pekarna-novak.cz' && $r['heslo'] === 'Stare-heslo-ř9'
            && $r['smtp_host'] === 'smtp.seznam.cz' && $r['smtp_port'] === 465 && $r['smtp_sifrovani'] === 'smtps' && ! isset($r['zdroj']));

        $this->assertSame('info@pekarna-novak.cz', Propojeni::odesilatel()['adresa']);
        $this->assertTrue(Propojeni::prevzeti()['ok']);
        $this->assertNotNull(StaraSchranka::zjisti(), 'stará schránka zůstává do zkušebního e-mailu');
        $this->assertStringNotContainsString('Stare-heslo', (string) Nastaveni::hodnota('posta.prevzeti'));

        Livewire::test(PostaStranka::class)->assertSee('zkušební e-mail')->callAction('zkusebni');
        $this->assertSame('01K6ZKOUSKA000000000000000', Propojeni::prevzeti()['zkouska_id']);
        $this->assertNotNull(StaraSchranka::zjisti());

        // Pošta potvrdila odeslání zkoušky → stará schránka (heslo) pryč.
        $this->webhook(['id' => '01K6ZKOUSKA000000000000000', 'stav' => 'odeslano'])->assertOk();

        $this->assertNull(StaraSchranka::zjisti());
        $this->assertSame(0, Nastaveni::query()->whereIn('klic', ['posta.heslo', 'posta.uzivatel', 'posta.host', 'posta.kontrola'])->count());
        $this->assertNotNull(Propojeni::prevzeti()['smazano']);
        $this->assertTrue(Propojeni::propojeno(), 'propojení s Poštou zůstává');
    }

    public function test_bez_souhlasu_v_poste_se_nic_nepredava_a_neuspech_stare_nesmaze(): void
    {
        $this->actingAs($this->spravce());
        $this->staraSchranka();

        $stranka = Livewire::test(PostaStranka::class)->callAction('propojit', ['url' => self::POSTA, 'prevzit' => true]);
        parse_str((string) parse_url($stranka->effects['redirect'], PHP_URL_QUERY), $q);

        Http::fake([self::POSTA.'/api/v1/propojeni/token' => Http::response($this->tokenOdpoved(null))]);
        $this->get('/posta/propojeni/navrat?code=kod&state='.$q['state']);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'prevzeti-schranky'));
        $this->assertNull(Propojeni::prevzeti());
        $this->assertNotNull(StaraSchranka::zjisti());

        // Převzetí povolené, ale heslo nesedí: zapamatuje se, stará schránka zůstává, zkouška ji nesmaže.
        $this->assertFalse(app(Propojeni::class)->prevezmi('jina@pekarna-novak.cz')['ok']);
        Http::fake([self::POSTA.'/api/v1/prevzeti-schranky' => Http::response([
            'vysledek' => 'zalozena', 'schranka' => ['adresa' => 'info@pekarna-novak.cz', 'stav' => 'chyba', 'overeni' => ['ok' => false, 'zprava' => 'Seznam heslo odmítl.']],
            'aplikace' => ['adresy' => [['adresa' => 'info@pekarna-novak.cz', 'jmeno' => null]]],
        ])]);
        $vysledek = app(Propojeni::class)->prevezmi('info@pekarna-novak.cz');
        $this->assertFalse($vysledek['ok']);
        StaraSchranka::zkouska('01K6X');
        $this->assertArrayNotHasKey('zkouska_id', Propojeni::prevzeti());
        $this->assertNotNull(StaraSchranka::zjisti());
    }

    public function test_stara_schranka_z_env(): void
    {
        // Exportex má z převodu (udaje_exportex) schránku Forpsi bez hesla v nastaveni – tady jen .env.
        foreach (StaraSchranka::KLICE as $klic) {
            Nastaveni::smaz('posta.'.$klic);
        }

        config(['mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.username' => 'x', 'mail.mailers.smtp.password' => 'y']);
        $this->assertNull(StaraSchranka::zjisti(), 'místní server se nepočítá');

        config(['mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => 'smtps', 'host' => 'smtp.seznam.cz', 'port' => 465, 'username' => 'servis@pekarna-novak.cz', 'password' => 'Env-heslo-1'],
            'mail.from' => ['address' => 'servis@pekarna-novak.cz', 'name' => 'Pekárna']]);

        $stara = StaraSchranka::zjisti();
        $this->assertSame(['servis@pekarna-novak.cz', 'smtps', 'env'], [$stara['adresa'], $stara['smtp_sifrovani'], $stara['zdroj']]);

        Propojeni::zapisPrevzeti(['adresa' => 'servis@pekarna-novak.cz', 'zdroj' => 'env', 'ok' => true, 'zkouska_id' => 'Z1']);
        StaraSchranka::poDoruceni('Z1');
        $this->assertTrue(Propojeni::prevzeti()['env_zbyva'], '.env smaže portál – stránka to připomene');
    }
}
