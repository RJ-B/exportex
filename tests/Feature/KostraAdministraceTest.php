<?php

namespace Tests\Feature;

use App\Enums\StavWebu;
use App\Events\SekceWebuZmeneny;
use App\Filament\Pages\HlavickaPaticka;
use App\Filament\Pages\OchranaOsobnichUdaju;
use App\Filament\Pages\Posta;
use App\Filament\Pages\StavWebu as StavWebuStranka;
use App\Filament\Support\CastObsahuWebu;
use App\Models\Nastaveni;
use App\Models\User;
use App\Support\NastaveniWebu;
use App\Support\OchranaUdaju;
use App\Support\SekceWebu;
use App\Support\TextyStavuWebu;
use App\Support\ZakladniUdaje as Udaje;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/** Kostra administrace: Nastavení (Základní údaje) a Provoz (Stav webu), Přehled. */
class KostraAdministraceTest extends TestCase
{
    use RefreshDatabase;

    private function ucet(string $role): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => $role])->save());
    }

    public function test_stav_webu_prepina_jen_superadmin(): void
    {
        $this->actingAs($this->ucet('admin'))->get(StavWebuStranka::getUrl())->assertForbidden();

        $this->actingAs($this->ucet('superadmin'));
        Livewire::test(StavWebuStranka::class)
            ->callAction(TestAction::make('prepnout')->arguments(['stav' => 'udrzba']))
            ->assertNotified('Web je teď ve stavu Údržba');

        $this->assertSame(StavWebu::Udrzba, StavWebu::aktualni());
        $this->assertNotNull(Nastaveni::hodnota(StavWebu::KLIC.'.zmena'));
    }

    public function test_navstevnik_vidi_podle_stavu_prihlaseny_vzdy_web(): void
    {
        Udaje::uloz(['nazev' => 'Pekárna U Nováků', 'email' => 'info@pekarna.cz']);

        $this->get('/')->assertOk()->assertDontSee('Připravujeme');

        Nastaveni::nastav(StavWebu::KLIC, 'pripravujeme');
        $this->get('/')->assertOk()->assertSee('Připravujeme')->assertSee('Pekárna U Nováků')->assertSee('info@pekarna.cz');

        Nastaveni::nastav(StavWebu::KLIC, 'udrzba');
        $this->get('/')->assertStatus(503)->assertHeader('Retry-After')->assertSee('Web se právě upravuje');

        // Kontrola zdraví a administrace běží v každém stavu; přihlášený vidí web.
        $this->get('/zdravi')->assertOk();
        $this->actingAs($this->ucet('admin'))->get('/')->assertOk()->assertDontSee('Web se právě upravuje');
    }

    public function test_hlavicka_a_paticka_ulozi_spravce(): void
    {
        $this->actingAs($this->ucet('klient'))->get(HlavickaPaticka::getUrl())->assertForbidden();

        Livewire::actingAs($this->ucet('admin'))->test(HlavickaPaticka::class)
            ->set('data.tagline', 'Pečeme každý den')
            ->set('data.nazev', 'Pekárna U Nováků')
            ->set('data.ico', '1234')
            ->call('uloz')
            ->assertHasErrors(['data.ico'])
            ->set('data.ico', '12345678')
            ->call('uloz')
            ->assertHasNoErrors();

        $this->assertSame('12345678', Udaje::get('ico'));
        $this->assertSame('Pečeme každý den', NastaveniWebu::get('tagline'));
    }

    /** @return array<string, string> skupina => položky (jak je vidí superadmin) */
    private function menu(?string $podoba): array
    {
        // Podoba rozhoduje i o routách (cluster) – aplikace se musí nastartovat znovu.
        putenv('SABLONA_OBSAH_WEBU='.($podoba ?? 'aplikace'));
        $this->refreshApplication();
        $this->artisan('migrate');
        putenv('SABLONA_OBSAH_WEBU');
        $this->actingAs($this->ucet('superadmin'));

        $html = $this->get('/admin')->assertOk()->getContent();

        return [
            'zobrazit_web' => str_contains($html, 'Zobrazit web'),
            'skupina_obsah' => (bool) preg_match('/fi-sidebar-group-label[^>]*>\s*Obsah webu/u', $html),
            'cluster_obsah' => str_contains($html, '/admin/obsah-webu'),
            'kontakt' => (bool) preg_match('/fi-sidebar-item-label[^>]*>\s*Kontakt a formulář/u', $html),
            'posta' => (bool) preg_match('/fi-sidebar-item-label[^>]*>\s*Pošta/u', $html),
        ];
    }

    public function test_podoba_jednoduchy_web_sekce_primo_v_menu(): void
    {
        $this->assertSame(['zobrazit_web' => true, 'skupina_obsah' => true, 'cluster_obsah' => false, 'kontakt' => true, 'posta' => false], $this->menu('menu'));
        $this->get(HlavickaPaticka::getUrl())->assertOk();
    }

    public function test_podoba_web_s_provozem_obsah_jako_jedna_polozka(): void
    {
        $this->assertSame(['zobrazit_web' => true, 'skupina_obsah' => false, 'cluster_obsah' => true, 'kontakt' => false, 'posta' => false], $this->menu('sekce'));
        $this->assertStringContainsString('/admin/obsah-webu/', HlavickaPaticka::getUrl());
    }

    public function test_podoba_aplikace_bez_webu_posta_v_nastaveni(): void
    {
        $this->assertSame(['zobrazit_web' => false, 'skupina_obsah' => false, 'cluster_obsah' => false, 'kontakt' => false, 'posta' => true], $this->menu(null));
        $this->get(HlavickaPaticka::getUrl())->assertForbidden();
        $this->get(Posta::getUrl())->assertOk()->assertDontSee('Zprávy z formuláře');
    }

    public function test_prijemce_formulare(): void
    {
        Udaje::uloz(['email' => 'info@pekarna.cz']);
        $this->assertSame('info@pekarna.cz', NastaveniWebu::prijemceFormulare());

        NastaveniWebu::uloz(['formular_prijemce' => 'objednavky@pekarna.cz']);
        $this->assertSame('objednavky@pekarna.cz', NastaveniWebu::prijemceFormulare());
    }

    public function test_prehled_ukaze_stav_aplikace(): void
    {
        $this->actingAs($this->ucet('superadmin'))->get('/admin')
            ->assertOk()
            ->assertSee('Stav aplikace')
            ->assertSee('Pošta')
            ->assertSee('Nepropojeno')
            ->assertSee('Nevyřešené chyby');

        $this->actingAs($this->ucet('admin'))->get('/admin')
            ->assertOk()
            ->assertSee('Pošta')
            ->assertDontSee('Nevyřešené chyby');
    }

    public function test_vlastni_text_udrzby(): void
    {
        TextyStavuWebu::uloz(['udrzba_nadpis' => 'Pracujeme na webu', 'udrzba_text' => '']);
        Nastaveni::nastav(StavWebu::KLIC, 'udrzba');

        $this->get('/')->assertStatus(503)->assertSee('Pracujeme na webu')->assertSee('Probíhá krátká údržba');

        Livewire::actingAs($this->ucet('superadmin'))->test(StavWebuStranka::class)
            ->assertSet('data.udrzba_nadpis', 'Pracujeme na webu')
            ->set('data.pripravujeme_text', 'Otevíráme v listopadu.')
            ->call('uloz')
            ->assertHasNoErrors();

        $this->assertSame('Otevíráme v listopadu.', TextyStavuWebu::nacti()['pripravujeme_text']);
    }

    public function test_sekci_jde_vypnout_prepinacem_a_seradit(): void
    {
        // Exportex: sekce úvodní stránky (Obsah webu) + Kontakt a Měření ze šablony.
        $this->assertEqualsCanonicalizing(
            ['formular' => 'Kontakt', 'mereni' => 'Měření návštěvnosti', 'sortiment' => 'Sortiment', 'jak' => 'Jak to funguje', 'trasa' => 'Trasa', 'doklady' => 'Doklady a clo', 'onas' => 'O nás', 'reference' => 'Ukázky zakázek'],
            SekceWebu::vsechny(),
        );
        // Výchozí pořadí = pořadí sekcí na webu před převodem (01–07); Kontakt na konci.
        $this->assertSame(['sortiment', 'jak', 'trasa', 'doklady', 'onas', 'reference', 'formular'], array_keys(SekceWebu::stranky()));
        $this->assertContains(['klic' => 'formular', 'nazev' => 'Kontakt', 'odkaz' => '/kontakt'], SekceWebu::menu());
        $this->assertSame(160, Posta::getNavigationSort());

        // Přepínač a pořadí v Hlavičce a patičce.
        Livewire::actingAs($this->ucet('admin'))->test(HlavickaPaticka::class)
            ->assertSet('data.sekce', fn ($sekce) => array_values($sekce)[6]['klic'] === 'formular' && array_values($sekce)[6]['zapnuto'] === true)
            // Tabulka posílá jen název a přepínač – klíč se dohledá podle názvu.
            ->set('data.sekce', [['nazev' => 'Kontakt', 'zapnuto' => false]])
            ->call('uloz')
            ->assertHasNoErrors();
        $this->assertFalse(SekceWebu::zapnuta('formular'));
        $this->assertNotContains('formular', array_column(SekceWebu::menu(), 'klic'), 'Vypnutá sekce v menu webu není.');

        // Přepínač v hlavičce stránky sekce.
        Livewire::test(Posta::class)->callAction('prepnoutSekci');
        $this->assertTrue(SekceWebu::zapnuta('formular'));

        // Veřejná routa sekce: vypnutá = 404 pro návštěvníka, přihlášený ji vidí.
        SekceWebu::nastav('formular', false);
        auth()->logout();
        $this->get('/kontakt')->assertNotFound();
        $this->actingAs($this->ucet('admin'))->get('/kontakt')->assertRedirect(url('/').'#kontakt');

        // Hlavička a patička vypnout nejde.
        $this->assertNull(HlavickaPaticka::klicSekce());
    }

    public function test_ochrana_osobnich_udaju_se_sklada_z_udaju_webu(): void
    {
        Udaje::uloz(['nazev' => 'Pekárna U Nováků', 'firma' => 'Pekárna U Nováků s.r.o.', 'email' => 'info@pekarna.cz', 'ico' => '']);
        // Exportex má z migrace vyplněné údaje a zapnuté smlouvy – test šablony začíná od prázdna.
        OchranaUdaju::uloz(['smlouvy' => false]);
        $this->assertContains('IČO – Hlavička a patička', OchranaUdaju::chybejici());

        // Přístupná vždy – i v Údržbě.
        Nastaveni::nastav(StavWebu::KLIC, 'udrzba');
        $html = $this->get('/ochrana-osobnich-udaju')->assertOk()->getContent();

        $this->assertStringContainsString('Pekárna U Nováků s.r.o.', $html);
        $this->assertStringContainsString('info@pekarna.cz', $html);
        $this->assertStringContainsString('Zpráva z kontaktního formuláře', $html);
        $this->assertStringNotContainsString('Google', $html, 'Bez měření Google v textu není.');
        $this->assertStringNotContainsString('Uzavření a plnění smlouvy', $html);

        // Měření s Google Analytics, smlouvy a vypnutý formulář.
        NastaveniWebu::uloz(['ga4_id' => 'G-TEST123']);
        OchranaUdaju::uloz(['smlouvy' => true, 'ucinnost_od' => '2026-10-01', 'dalsi_prijemci' => 'platební brána Comgate, a.s.']);
        SekceWebu::nastav('formular', false);

        $html = $this->get('/ochrana-osobnich-udaju')->getContent();
        $this->assertStringContainsString('Google Ireland Limited', $html);
        $this->assertStringContainsString('Předávání mimo Evropský hospodářský prostor', $html);
        $this->assertStringContainsString('Uzavření a plnění smlouvy', $html);
        $this->assertStringContainsString('Účinné od 1. 10. 2026', $html);
        $this->assertStringContainsString('platební brána Comgate', $html);
        $this->assertStringNotContainsString('Zpráva z kontaktního formuláře', $html);

        // Měření vypnuté přepínačem = Google z textu zmizí, i když ID zůstalo.
        SekceWebu::nastav('mereni', false);
        $this->assertStringNotContainsString('Google', $this->get('/ochrana-osobnich-udaju')->getContent());

        // Administrace: stránka se uloží a upozorní na chybějící údaje.
        Livewire::actingAs($this->ucet('admin'))->test(OchranaOsobnichUdaju::class)
            ->assertSee('Co ještě chybí')
            ->set('data.formular_doba', '1 rok')
            ->call('uloz')
            ->assertHasNoErrors();
        $this->assertSame('1 rok', OchranaUdaju::nacti()['formular_doba']);
    }

    public function test_cookie_lista_jen_kdyz_web_meri(): void
    {
        // Bez měřicích nástrojů lišta není – souhlas není potřeba.
        $this->get('/')->assertOk()->assertDontSee('cc-lista', false);
        $this->get('/zasady-cookies')->assertOk()->assertSee('Žádné analytické ani marketingové cookies');

        NastaveniWebu::uloz(['ga4_id' => 'G-TEST123', 'sklik_id' => '123456']);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('id="cc-lista"', $html);
        $this->assertStringContainsString("gtag('consent', 'default'", $html);
        // Měřicí skripty do souhlasu inertní.
        $this->assertMatchesRegularExpression('/<script type="text\/plain" data-cookie="analytics" data-src="https:\/\/www.googletagmanager.com\/gtag\/js\?id=G-TEST123">/', $html);
        $this->assertStringContainsString('data-cookie="marketing"', $html);
        $this->assertStringContainsString('Odmítnout vše', $html);

        $zasady = $this->get('/zasady-cookies')->getContent();
        $this->assertStringContainsString('Google Analytics 4', $zasady);
        $this->assertStringContainsString('Sklik', $zasady);
        $this->assertStringContainsString('souhlas_cookies', $zasady);
        $ochrana = $this->get('/ochrana-osobnich-udaju')->getContent();
        $this->assertStringContainsString('Sklik', $ochrana);
        $this->assertStringContainsString('vyhodnocování reklamy', $ochrana);

        // Vypnutá lišta nebo vypnuté měření = žádné měření ani lišta.
        NastaveniWebu::uloz(['cookie_lista' => false]);
        $this->assertStringNotContainsString('googletagmanager', $this->get('/')->getContent());
        NastaveniWebu::uloz(['cookie_lista' => true]);
        SekceWebu::nastav('mereni', false);
        $this->assertStringNotContainsString('googletagmanager', $this->get('/')->getContent());
    }

    public function test_zmena_sekci_se_ozve_projektu(): void
    {
        Event::fake([SekceWebuZmeneny::class]);

        SekceWebu::nastav('formular', false);
        SekceWebu::nastavPoradi(['formular']);

        Event::assertDispatchedTimes(SekceWebuZmeneny::class, 2);
        $this->assertSame(['formular'], SekceWebu::ulozenePoradi());
    }

    public function test_jadro_webu_nejde_vypnout_a_sekce_jen_na_strance_neni_v_menu(): void
    {
        Filament::getPanel('admin')->pages([SekceRozvrhTest::class, SekceGalerieTest::class]);
        SekceWebu::nastavPoradi(['galerie-test', 'formular', 'rozvrh-test']);

        // Jádro: uložené „vypnuto“ neplatí a přepínač v hlavičce není.
        SekceWebu::nastav('rozvrh-test', false);
        $this->assertTrue(SekceWebu::zapnuta('rozvrh-test'));
        $this->assertNull((new \ReflectionMethod(SekceRozvrhTest::class, 'prepinacSekce'))->invoke(new SekceRozvrhTest));
        $this->assertNull(SekceRozvrhTest::getNavigationBadge());

        // Galerie je na stránce a v pořadí, v menu webu ne.
        $testovaci = fn (array $klice) => array_values(array_intersect($klice, ['galerie-test', 'formular', 'rozvrh-test']));
        $this->assertSame(['galerie-test', 'formular', 'rozvrh-test'], $testovaci(SekceWebu::naWebu()));
        $this->assertSame(['formular', 'rozvrh-test'], $testovaci(array_column(SekceWebu::menu(), 'klic')));

        SekceWebu::nastav('galerie-test', false);
        $this->assertSame(['formular', 'rozvrh-test'], $testovaci(SekceWebu::naWebu()));
    }
}

/** Sekce jádra pro test: jde přesunout, ne vypnout. */
class SekceRozvrhTest extends Page
{
    use CastObsahuWebu;

    protected static ?string $navigationLabel = 'Rozvrh';

    public static function klicSekce(): ?string
    {
        return 'rozvrh-test';
    }

    public static function odkazSekce(): ?string
    {
        return '/#rozvrh';
    }

    public static function vypnoutJde(): bool
    {
        return false;
    }
}

/** Sekce jen na stránce: v pořadí, ne v menu webu. */
class SekceGalerieTest extends Page
{
    use CastObsahuWebu;

    protected static ?string $navigationLabel = 'Galerie';

    public static function klicSekce(): ?string
    {
        return 'galerie-test';
    }

    public static function odkazSekce(): ?string
    {
        return '/#galerie';
    }

    public static function vMenuWebu(): bool
    {
        return false;
    }
}
