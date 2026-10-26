<?php

namespace Tests\Feature;

use App\Models\MailLog;
use App\Support\Posta\StavPosty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Na produkci nikdy mailer log / array: e-mail by se tvářil jako odeslaný
 * a nikomu by nedošel. simren:zdravi (blok posta) to hlásí portálu jako
 * problém a záznam v Logy → E-maily říká „neodesláno“. Lokálně a na testu
 * je log v pořádku.
 */
class PostaNaProdukciTest extends TestCase
{
    use RefreshDatabase;

    private function naProdukci(callable $co): void
    {
        $this->app['env'] = 'production';

        try {
            $co();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_zdravi_hlasi_produkci_s_mailerem_log(): void
    {
        config(['mail.default' => 'log']);

        $this->naProdukci(function () {
            $stav = StavPosty::proZdravi();

            $this->assertFalse($stav['ok']);
            $this->assertSame('log', $stav['mailer']);
            $this->assertStringContainsString('jen zapisují do logu', $stav['zprava']);
        });

        // Mimo produkci jen obecná hláška o propojení (test smí posílat do logu).
        $this->assertStringNotContainsString('jen zapisují do logu', StavPosty::proZdravi()['zprava']);
    }

    public function test_e_mail_na_produkci_s_mailerem_log_neni_odeslany(): void
    {
        config(['mail.default' => 'log']);

        $this->naProdukci(fn () => Mail::raw('Potvrzení objednávky', fn ($m) => $m->to('jana@pekarna.cz')->subject('Objednávka')));

        $log = MailLog::query()->sole();
        $this->assertSame(MailLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('jen zapisují do logu', $log->error);

        // Lokálně / na testu zůstává „odesláno“ (log je tam záměr).
        Mail::raw('Test', fn ($m) => $m->to('test@simren.cz')->subject('Test'));
        $this->assertSame(MailLog::STATUS_SENT, MailLog::query()->latest('id')->first()->status);
    }
}
