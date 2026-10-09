<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Filament\Resources\ErrorLogResource\Pages\ListErrorLogs;
use App\Filament\Resources\MailLogResource\Pages\ListMailLogs;
use App\Models\AuditLog;
use App\Models\ErrorLog;
use App\Models\MailLog;
use App\Models\User;
use App\Services\ErrorLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Provozní logy (kanón Sim&Ren): e-maily, chyby, aktivita a kdo je vidí.
 */
class ProvozniLogyTest extends TestCase
{
    use RefreshDatabase;

    private function ucet(string $role): User
    {
        return User::forceCreate(['email' => $role.'@example.cz', 'jmeno' => 'Jana', 'prijmeni' => 'Nováková', 'role' => $role, 'password' => 'x']);
    }

    public function test_odeslany_mail_se_zapise_se_vsemi_prijemci(): void
    {
        Mail::raw('Dobrý den', fn ($m) => $m->to('klient@example.cz')->bcc('kopie@example.cz')->subject('Potvrzení objednávky'));

        $log = MailLog::sole();
        $this->assertSame(MailLog::STATUS_SENT, $log->status);
        $this->assertSame('Potvrzení objednávky', $log->subject);
        $this->assertSame(['klient@example.cz', 'kopie@example.cz'], $log->recipients);
        $this->assertNull($log->raw_mime);
    }

    public function test_opakovana_chyba_se_agreguje_i_s_ruznym_uuid(): void
    {
        $logger = app(ErrorLogger::class);

        foreach (['0b6f7c1e-1111-4a4a-8b8b-000000000001', '0b6f7c1e-2222-4a4a-8b8b-000000000002'] as $uuid) {
            $logger->report(new \RuntimeException("Objednávka {$uuid} nenalezena"));
        }

        $chyba = ErrorLog::sole();
        $this->assertSame(2, $chyba->occurrences);
        $this->assertNull($chyba->resolved_at);
    }

    public function test_udrzba_503_se_nezapise(): void
    {
        $logger = app(ErrorLogger::class);
        app()->maintenanceMode()->activate([]);

        try {
            $logger->report(new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'Service Unavailable'));
        } finally {
            app()->maintenanceMode()->deactivate();
        }

        $this->assertSame(0, ErrorLog::count(), 'Údržba (artisan down) není chyba.');

        $logger->report(new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'Platební brána neodpovídá'));
        $this->assertSame(1, ErrorLog::count(), 'Mimo údržbu je 503 chyba.');
    }

    public function test_udrzba_se_zapise_do_aktivity_a_ne_do_chyb(): void
    {
        $pruchod = 'pruchod-udrzbou';
        $this->artisan('down', ['--retry' => 60, '--secret' => $pruchod])->assertSuccessful();

        try {
            $this->get('/')->assertStatus(503);
        } finally {
            $this->artisan('up')->assertSuccessful();
        }

        $zapnuta = AuditLog::where('event', 'udrzba.zapnuta')->sole();
        $this->assertSame('Údržba zapnuta', $zapnuta->summary);
        $this->assertNull($zapnuta->user_id, 'Kdo = systém (příkaz na serveru).');
        $this->assertSame(60, $zapnuta->new_values['retry']);
        $this->assertStringNotContainsString($pruchod, json_encode($zapnuta->new_values), 'Tajný klíč údržby do logu nepatří.');

        $this->assertSame('Údržba vypnuta', AuditLog::where('event', 'udrzba.vypnuta')->sole()->summary);
        $this->assertSame(0, ErrorLog::count(), 'Údržba není chyba.');
    }

    public function test_stav_webu_se_zapise_citelne(): void
    {
        \App\Models\Nastaveni::nastav(\App\Enums\StavWebu::KLIC, 'udrzba');
        \App\Models\Nastaveni::nastav(\App\Enums\StavWebu::KLIC, 'online');

        $this->assertSame(
            ['Stav webu: Údržba zapnuta', 'Stav webu: Údržba vypnuta → Online'],
            AuditLog::where('event', 'stav_webu.zmenen')->orderBy('id')->pluck('summary')->all(),
        );
    }

    public function test_abort_500_se_zapise_a_404_ne(): void
    {
        Route::get('/_test/padne', fn () => abort(500, 'Něco se rozbilo'));

        $this->get('/_test/padne')->assertStatus(500);
        $this->get('/neexistuje')->assertNotFound();

        $this->assertSame(1, ErrorLog::count());
        $this->assertStringContainsString('Něco se rozbilo', ErrorLog::sole()->message);
    }

    public function test_zmena_role_se_zapise_do_aktivity_bez_hesla(): void
    {
        $user = $this->ucet('klient');
        $user->forceFill(['role' => 'admin', 'password' => 'nove-heslo'])->save();

        $zaznam = AuditLog::where('event', 'user.updated')->sole();
        $this->assertSame(['role' => 'klient'], $zaznam->old_values);
        $this->assertSame(['role' => 'admin'], $zaznam->new_values);
    }

    public function test_uklid_drzi_audit_rok_a_zbytek_podle_retence(): void
    {
        MailLog::create(['to_email' => 'a@example.cz', 'status' => 'sent'])->forceFill(['created_at' => now()->subDays(40)])->save();
        MailLog::create(['to_email' => 'b@example.cz', 'status' => 'sent']);
        AuditLog::create(['event' => 'user.updated'])->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->artisan('logs:prune')->assertSuccessful();

        $this->assertSame(['b@example.cz'], MailLog::pluck('to_email')->all());
        $this->assertSame(1, AuditLog::count());
    }

    public function test_logy_vidi_jen_superadmin(): void
    {
        $superadmin = $this->ucet('superadmin');
        $superadmin->forceFill(['role' => 'superadmin', 'prijmeni' => 'Svobodová'])->save();
        app(ErrorLogger::class)->report(new \RuntimeException('Platební brána neodpovídá'));
        MailLog::create(['to_email' => 'klient@example.cz', 'status' => 'failed', 'subject' => 'Faktura', 'error' => 'Timeout']);

        $this->actingAs($superadmin);
        $this->get('/admin/logy')->assertRedirect('/admin/logy/aktivita');
        $this->get('/admin/logy/aktivita')->assertOk()->assertSee('user.updated');
        $this->get('/admin/logy/maily')->assertOk()->assertSee('Selhalo');
        $this->get('/admin/logy/chyby')->assertOk()->assertSee('Platební brána neodpovídá');

        Livewire::test(ListAuditLogs::class)->mountTableAction('detail', AuditLog::first())->assertMountedActionModalSee(['Role v tu chvíli', 'Co se změnilo']);
        Livewire::test(ListErrorLogs::class)->mountTableAction('detail', ErrorLog::first())->assertMountedActionModalSee(['Výskytů', 'Zásobník volání']);
        Livewire::test(ListMailLogs::class)->mountTableAction('detail', MailLog::first())->assertMountedActionModalSee(['Chyba při odesílání', 'Pokusů']);
    }

    public function test_admin_logy_nevidi(): void
    {
        $this->actingAs($this->ucet('admin'))->get('/admin/logy/chyby')->assertForbidden();
    }

    public function test_chybu_jde_odskrtnout_a_zapise_se_kdo(): void
    {
        $superadmin = $this->ucet('superadmin');
        app(ErrorLogger::class)->report(new \RuntimeException('Chyba'));

        $this->actingAs($superadmin);
        Livewire::test(ListErrorLogs::class)->callTableAction('vyresit', ErrorLog::sole());

        $this->assertSame($superadmin->id, ErrorLog::sole()->resolved_by);
        $this->assertNotNull(ErrorLog::sole()->resolved_at);
    }
}
