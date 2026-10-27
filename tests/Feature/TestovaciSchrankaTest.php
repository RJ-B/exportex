<?php

namespace Tests\Feature;

use App\Models\MailLog;
use App\Models\Nastaveni;
use App\Support\Posta\PresmerovaniNaTest;
use App\Support\Posta\Propojeni;
use App\Support\Posta\StaraSchranka;
use App\Support\Posta\StavPosty;
use App\Support\Posta\TestovaciSchranka;
use App\Support\Prenos\Casti;
use App\Support\Prenos\PoImportu;
use App\Support\Zdravi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as Oznameni;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Testovací schránka test@simren.cz (docs/testovaci-schranka.md): na testu
 * jde všechna pošta jen na test@simren.cz, produkce beze změny a s testovacím
 * odesílatelem hlásí chybu v /zdravi, testovací adresa se nepřenese.
 */
class TestovaciSchrankaTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<MessageSent> */
    private array $odeslane = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Event::listen(MessageSent::class, fn (MessageSent $e) => $this->odeslane[] = $e);
    }

    private function prostredi(string $prostredi): void
    {
        $this->app['env'] = $prostredi;
        Mail::forgetMailers();
    }

    private function posledni(): Email
    {
        $this->assertNotEmpty($this->odeslane, 'Nic se neodeslalo.');

        return end($this->odeslane)->message;
    }

    private function posliZakaznikovi(): void
    {
        Mail::raw('Objednávka je připravená.', fn ($m) => $m
            ->from('info@pekarna-novak.cz', 'Pekárna Novák')
            ->to('jana.novakova@seznam.cz', 'Jana Nováková')
            ->cc('karel@centrum.cz')
            ->bcc('archiv@pekarna-novak.cz')
            ->replyTo('objednavky@pekarna-novak.cz')
            ->subject('Objednávka 2026-118'));
    }

    public function test_na_testu_jde_vse_jen_na_testovaci_schranku(): void
    {
        $this->prostredi('staging');
        $this->posliZakaznikovi();

        $email = $this->posledni();
        $this->assertSame(['test@simren.cz'], array_map(fn ($a) => $a->getAddress(), $email->getTo()));
        $this->assertSame('test@simren.cz', $email->getFrom()[0]->getAddress());
        $this->assertSame([], $email->getCc());
        $this->assertSame([], $email->getBcc());
        $this->assertSame([], $email->getReplyTo());
        $this->assertSame('[TEST → jana.novakova@seznam.cz, karel@centrum.cz, archiv@pekarna-novak.cz] Objednávka 2026-118', $email->getSubject());
        $this->assertSame('jana.novakova@seznam.cz, karel@centrum.cz, archiv@pekarna-novak.cz', $email->getHeaders()->get(PresmerovaniNaTest::HLAVICKA_PRIJEMCI)->getBodyAsString());
        $this->assertSame('info@pekarna-novak.cz', $email->getHeaders()->get(PresmerovaniNaTest::HLAVICKA_ODESILATEL)->getBodyAsString());
        $this->assertSame('objednavky@pekarna-novak.cz', $email->getHeaders()->get(PresmerovaniNaTest::HLAVICKA_ODPOVED)->getBodyAsString());

        // Obálka (skuteční příjemci SMTP / Pošty) taky jen testovací adresa.
        $this->assertSame(['test@simren.cz'], array_map(fn ($a) => $a->getAddress(), end($this->odeslane)->sent->getEnvelope()->getRecipients()));

        // Logy → E-maily: co doopravdy odešlo.
        $log = MailLog::sole();
        $this->assertSame('test@simren.cz', $log->to_email);
        $this->assertSame(['test@simren.cz'], $log->recipients);
        $this->assertStringStartsWith('[TEST → jana.novakova@seznam.cz', $log->subject);
    }

    public function test_fronta_a_notifikace_taky(): void
    {
        $this->prostredi('staging');

        Mail::to('jana.novakova@seznam.cz')->queue(new MailZFrontyNaTest);
        $this->assertSame('test@simren.cz', $this->posledni()->getTo()[0]->getAddress());
        $this->assertSame('[TEST → jana.novakova@seznam.cz] Z fronty', $this->posledni()->getSubject());

        Notification::route('mail', 'karel@centrum.cz')->notify(new class extends Oznameni
        {
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)->subject('Upozornění')->line('Text');
            }
        });
        $this->assertSame('test@simren.cz', $this->posledni()->getTo()[0]->getAddress());
        $this->assertSame('[TEST → karel@centrum.cz] Upozornění', $this->posledni()->getSubject());
    }

    public function test_pres_postu_na_testu_jen_testovaci_adresa(): void
    {
        $this->prostredi('staging');
        Nastaveni::nastav('posta.url', 'https://posta.test');
        Nastaveni::nastav('posta.token', Crypt::encryptString('pst_testovaci'));
        Nastaveni::nastav('posta.adresy', json_encode([['adresa' => 'test@simren.cz', 'jmeno' => null]]));
        Propojeni::pouzij();
        Mail::forgetMailers();
        Http::fake(['https://posta.test/api/v1/zpravy' => Http::response(['id' => '01K6POSTA0000000000000000A', 'stav' => 've_fronte'], 202)]);

        $this->posliZakaznikovi();

        Http::assertSent(fn (Request $r) => $r['komu'] === [['adresa' => 'test@simren.cz', 'jmeno' => null]]
            && ($r['kopie'] ?? []) === [] && ($r['skryta_kopie'] ?? []) === [] && ($r['odpovedet_na'] ?? []) === []
            && $r['od']['adresa'] === 'test@simren.cz'
            && str_starts_with($r['predmet'], '[TEST → jana.novakova@seznam.cz'));
    }

    public function test_mimo_test_zapnuto_jen_s_testovacim_odesilatelem(): void
    {
        // Lokálně / v testech beze změny…
        $this->assertFalse(TestovaciSchranka::zapnuta());
        $this->posliZakaznikovi();
        $this->assertSame('jana.novakova@seznam.cz', $this->posledni()->getTo()[0]->getAddress());

        // …dokud se neposílá z testovací schránky.
        config(['mail.from.address' => 'TEST@simren.cz']);
        Mail::forgetMailers();
        $this->assertTrue(TestovaciSchranka::zapnuta());
        $this->posliZakaznikovi();
        $this->assertSame('test@simren.cz', $this->posledni()->getTo()[0]->getAddress());
    }

    public function test_produkce_nic_nepresmeruje_ani_s_testovacim_odesilatelem(): void
    {
        config(['mail.from.address' => 'test@simren.cz', 'posta.od' => 'test@simren.cz']);
        $this->prostredi('production');

        try {
            $this->assertFalse(TestovaciSchranka::zapnuta());
            $this->posliZakaznikovi();

            $email = $this->posledni();
            $this->assertSame('jana.novakova@seznam.cz', $email->getTo()[0]->getAddress());
            $this->assertSame('karel@centrum.cz', $email->getCc()[0]->getAddress());
            $this->assertSame('Objednávka 2026-118', $email->getSubject());
            $this->assertFalse($email->getHeaders()->has(PresmerovaniNaTest::HLAVICKA_PRIJEMCI));
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_zdravi_v_produkci_hlasi_testovaciho_odesilatele(): void
    {
        $this->prostredi('production');

        try {
            $this->assertTrue(Zdravi::tvrde()['posta_test']['ok']);
            $this->get('/zdravi')->assertOk();

            config(['mail.from.address' => 'test@simren.cz']);
            $this->assertFalse(Zdravi::tvrde()['posta_test']['ok']);
            $this->get('/zdravi')->assertStatus(503)->assertDontSeeText('simren');

            // I když ji přidělí Pošta.
            config(['mail.from.address' => 'info@pekarna-novak.cz']);
            Nastaveni::nastav('posta.token', Crypt::encryptString('pst_testovaci'));
            Nastaveni::nastav('posta.adresy', json_encode([['adresa' => 'test@simren.cz', 'jmeno' => null]]));
            $this->assertFalse(Zdravi::tvrde()['posta_test']['ok']);
            $this->assertSame('test@simren.cz', StavPosty::proZdravi()['od']);
        } finally {
            $this->app['env'] = 'testing';
        }

        // Mimo produkci kontrola není (test z testovací schránky posílat má).
        $this->assertArrayNotHasKey('posta_test', Zdravi::tvrde());
    }

    public function test_zdravi_posta_hlasi_odesilatele_a_smtp_uzivatele(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'Info@Pekarna-Novak.cz', 'mail.mailers.smtp.username' => 'TEST@simren.cz', 'mail.mailers.smtp.password' => 'tajne']);

        $stav = StavPosty::proZdravi();
        $this->assertSame('info@pekarna-novak.cz', $stav['od']);
        $this->assertSame('test@simren.cz', $stav['smtp_uzivatel']);
        $this->assertStringNotContainsString('tajne', json_encode($stav));
    }

    public function test_testovaci_adresa_se_neprenese_ani_neprevezme(): void
    {
        // Přenos částí: nastavení s testovací adresou ne.
        $definice = ['jen' => [], 'krome' => []];
        $this->assertFalse(Casti::patri('nastaveni', $definice, ['klic' => 'web.email', 'hodnota' => ' Test@Simren.cz']));
        $this->assertTrue(Casti::patri('nastaveni', $definice, ['klic' => 'web.email', 'hodnota' => 'info@pekarna-novak.cz']));

        // Stará schránka test@simren.cz se Poště k převzetí nenabízí.
        Nastaveni::nastav('posta.uzivatel', 'test@simren.cz');
        Nastaveni::nastav('posta.heslo', Crypt::encryptString('heslo-schranky'));
        Nastaveni::nastav('posta.host', 'smtp.seznam.cz');
        $this->assertNull(StaraSchranka::zjisti());

        // Po importu celé databáze se zapomene i s nastavením, které ji obsahuje.
        Nastaveni::nastav('web.email', 'test@simren.cz');
        Nastaveni::nastav('web.telefon', '+420 777 000 000');
        $vysledek = app(PoImportu::class)->proved();

        $this->assertSame('zapomenuta', $vysledek['testovaci_schranka']);
        $this->assertFalse(Nastaveni::query()->where('klic', 'like', 'posta.%')->exists());
        $this->assertFalse(Nastaveni::query()->whereKey('web.email')->exists());
        $this->assertSame('+420 777 000 000', Nastaveni::hodnota('web.telefon'));
    }
}

/** Mailable do fronty (anonymní třída se serializovat nedá). */
class MailZFrontyNaTest extends Mailable implements ShouldQueue
{
    use Queueable;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Z fronty');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Ahoj</p>');
    }
}
