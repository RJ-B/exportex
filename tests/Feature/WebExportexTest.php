<?php

namespace Tests\Feature;

use App\Filament\Pages\HlavickaPaticka;
use App\Filament\Pages\Web\KontaktTexty;
use App\Filament\Pages\Web\ONas;
use App\Filament\Pages\Web\Sortiment;
use App\Filament\Pages\Web\Uvod;
use App\Models\User;
use App\Support\ObsahWebu;
use App\Support\SekceWebu;
use App\Support\ZakladniUdaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Veřejný web exportex.cz po převodu ze statického webu na šablonu: obsah, sekce, SEO, ochrana údajů. */
class WebExportexTest extends TestCase
{
    use RefreshDatabase;

    private function spravce(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => 'admin'])->save());
    }

    /** Obsah pro skript webu (dřív assets/js/data.js) z HTML stránky. */
    private function dataWebu(string $html): array
    {
        preg_match('#<script type="application/json" id="exportex-data">(.*?)</script>#s', $html, $m);

        return json_decode($m[1] ?? 'null', true);
    }

    public function test_uvodni_stranka_ma_stejny_obsah_jako_pred_prevodem(): void
    {
        $this->get('/')->assertOk()->assertSeeInOrder([
            'Český sourcingový a importní partner', 'TAŠKENT — PRAHA',
            'Textil z Uzbekistánu a Střední Asie <em>od výroby až do Vašeho skladu.</em>',
            'Jeden odpovědný partner v Praze', 'Poslat poptávku', 'Prohlédnout sortiment',
            'Dovozní clo', '0 %', 'OEKO-TEX<sup>®</sup>', 'Na místě',
            '<span class="num">01</span>', 'Co pro Vás zajistíme a dovezeme.',
            '<span class="num">02</span>', 'Šest kroků od poptávky po vykládku.',
            '<span class="num">03</span>', 'Ze Střední Asie do celé Evropy.', 'map__land',
            '<span class="num">04</span>', 'Nulové clo stojí na správných dokladech o původu.',
            '<span class="num">05</span>', 'Most mezi středoasijskou výrobou a Evropou.', 'TAŠKENT · 2026',
            '<span class="num">06</span>', 'Příklady toho, co pro Vás zvládneme.',
            '<span class="num">07</span>', 'Řekněte nám, co potřebujete.', 'Tomáš Mikyska', 'mikyska@exportex.cz', '+420 734 479 684',
            'https://wa.me/420734479684', 'tg://resolve?phone=420734479684',
            'exportex s.r.o.', 'Na Poříčí 1070/19, Nové Město, 110 00 Praha 1', 'IČO 17671833',
            'Zapsaná u Městského soudu v Praze, oddíl C, vložka 374806',
            '© '.now()->year.' exportex s.r.o. — Textil z Uzbekistánu a Střední Asie do EU.', 'codeing.cz',
        ], false);
    }

    public function test_anglicke_texty_jsou_u_kazdeho_prvku(): void
    {
        $html = $this->get('/')->getContent();

        foreach ([
            'data-en="Czech sourcing &amp;amp; import partner"',
            'data-en="Textiles from Uzbekistan and Central Asia &lt;em&gt;from the mill to your warehouse.&lt;/em&gt;"',
            'data-en="What we source and import for you."',
            'We are a partner with control on the line, not an email reseller."',
            'data-en="Na Poříčí 1070/19, Nové Město, 110 00 Prague 1, Czech Republic"',
            'data-en="Registered at the Municipal Court in Prague, section C, entry 374806"',
        ] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_data_pro_skript_jako_v_puvodnim_data_js(): void
    {
        $data = $this->dataWebu($this->get('/')->getContent());

        $this->assertSame(['Vše', 'Froté a domácí textil', 'Pletené úplety', 'Konfekce', 'Tkaniny a příze'], $data['cs']['filters']);
        $this->assertSame(['All', 'Terry & home textiles', 'Knitted fabrics', 'Cut & sew', 'Wovens & yarn'], $data['en']['filters']);
        $this->assertCount(7, $data['cs']['products']);
        $this->assertSame([
            'tag' => 'Froté', 'cat' => 'Froté a domácí textil', 'img' => '/assets/img/product-frotte.webp?v=20260924',
            'title' => 'Froté — ručníky, župany',
        ], array_intersect_key($data['cs']['products'][0], array_flip(['tag', 'cat', 'img', 'title'])));
        $this->assertSame('Terry & home textiles', $data['en']['products'][1]['cat']);
        $this->assertSame(['k' => 'Gramáž', 'v' => '400–650 g/m²'], $data['cs']['products'][0]['specs'][0]);
        $this->assertSame('MOQ from 500 kg / type · delivery 2–4 weeks · FCA/DAP', $data['en']['products'][5]['terms']);
        $this->assertSame(['n' => '06', 't' => 'Doručení', 'd' => 'Zboží doručíme až do Vašeho skladu v EU.'], $data['cs']['steps'][5]);
        $this->assertSame(['city' => 'Uzbekistan', 'sub' => 'CENTRAL ASIA · PRODUCTION'], $data['en']['mapFrom']);
        $this->assertSame(['GSP+', 'OEKO-TEX', 'REACH'], array_column($data['cs']['compliance'], 'k'));
        $this->assertSame(['Retail', 'HORECA', 'Private label'], array_column($data['en']['cases'], 'badge'));
        $this->assertSame('ODPOVÍDÁME DO 1 PRACOVNÍHO DNE', $data['cs']['form']['note']);
        $this->assertSame(['email' => 'mikyska@exportex.cz', 'telefon' => '+420 734 479 684', 'ochrana' => '/ochrana-osobnich-udaju'], $data['kontakt']);
    }

    public function test_menu_webu_v_puvodnim_poradi(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertMatchesRegularExpression('/class="nav__link" href="#sortiment"[^>]*>Sortiment<.*href="#jak"[^>]*>Jak to funguje<.*href="#trasa"[^>]*>Trasa<.*href="#doklady"[^>]*>Doklady a clo<.*href="#onas"[^>]*>O nás<.*href="#reference"[^>]*>Ukázky zakázek<.*href="#kontakt"[^>]*>Kontakt</s', $html);
        // Na podstránkách vedou odkazy na úvodní stránku.
        $this->get('/zasady-cookies')->assertSee('href="/#sortiment"', false)->assertDontSee('id="progress"', false);
    }

    public function test_seo_zustava(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<title>Exportex — textil z Uzbekistánu a Střední Asie do EU</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Textil z Uzbekistánu a Střední Asie do EU: froté, ložní prádlo, úplety, konfekce, tkaniny a příze.', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://exportex.cz/">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="https://exportex.cz/?lang=en">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://exportex.cz/assets/img/og.webp">', $html);
        $this->assertStringContainsString('<link rel="manifest" href="/site.webmanifest">', $html);

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $jsonld = json_decode($m[1], true);
        $organizace = $jsonld['@graph'][0];
        $this->assertSame('exportex s.r.o.', $organizace['name']);
        $this->assertSame(['@type' => 'PostalAddress', 'streetAddress' => 'Na Poříčí 1070/19', 'addressLocality' => 'Praha', 'postalCode' => '110 00', 'addressRegion' => 'Praha 1', 'addressCountry' => 'CZ'], $organizace['address']);
        $this->assertSame('Tomáš Mikyska', $organizace['contactPoint'][0]['name']);
        $this->assertSame('+420734479684', $organizace['telephone']);
        $this->assertSame([['@type' => 'PropertyValue', 'name' => 'IČO', 'value' => '17671833']], $organizace['identifier']);
        $this->assertCount(7, $jsonld['@graph'][2]['hasOfferCatalog']['itemListElement']);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<loc>https://exportex.cz/</loc>', false)
            ->assertSee('hreflang="en" href="https://exportex.cz/?lang=en"', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://exportex.cz/sitemap.xml')->assertSee('Disallow: /admin');
        $this->assertFileExists(public_path('site.webmanifest'));
        $this->assertFileExists(public_path('favicon.ico'));
    }

    public function test_stare_adresy_a_www_presmeruji(): void
    {
        $this->get('/soukromi.html')->assertStatus(301)->assertRedirect('/ochrana-osobnich-udaju');
        $this->get('/cookies.html')->assertStatus(301)->assertRedirect('/zasady-cookies');
        $this->get('/index.html')->assertStatus(301)->assertRedirect('/');
        $this->get('http://www.exportex.cz/zasady-cookies')->assertStatus(301)->assertRedirect('https://exportex.cz/zasady-cookies');
        $this->get('/neexistuje')->assertNotFound()->assertSee('Tady nic není.');
    }

    public function test_texty_sekce_upravi_spravce_a_ulozi_jen_svoje(): void
    {
        $this->actingAs(tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => 'klient'])->save()))
            ->get(Sortiment::getUrl())->assertForbidden();

        Livewire::actingAs($this->spravce())->test(Sortiment::class)
            ->assertSet('data.nadpis', 'Co pro Vás zajistíme a dovezeme.')
            ->set('data.nadpis', 'Textil, který pro Vás dovezeme.')
            ->set('data.nadpis_en', 'Textiles we import for you.')
            ->call('uloz')
            ->assertHasNoErrors();

        $this->assertSame('Textil, který pro Vás dovezeme.', ObsahWebu::sekce('sortiment')['nadpis']);
        $this->assertCount(7, ObsahWebu::sekce('sortiment')['produkty']);
        $this->assertSame('Šest kroků od poptávky po vykládku.', ObsahWebu::sekce('jak')['nadpis']);

        auth()->logout();
        $this->get('/')->assertSee('Textil, který pro Vás dovezeme.')->assertSee('data-en="Textiles we import for you."', false)
            ->assertDontSee('Co pro Vás zajistíme a dovezeme.');

        $uvod = Livewire::actingAs($this->spravce())->test(Uvod::class);
        $uvod->set('data.cisla.'.array_key_first($uvod->get('data.cisla')).'.hodnota', '0 % díky GSP+')
            ->call('uloz')
            ->assertHasNoErrors();
        auth()->logout();
        $this->get('/')->assertSee('0 % díky GSP+');
    }

    public function test_karta_sortimentu_se_upravi_v_obou_jazycich(): void
    {
        $stranka = Livewire::actingAs($this->spravce())->test(Sortiment::class);
        $klic = array_key_first($stranka->get('data.produkty'));

        $stranka->set("data.produkty.{$klic}.nazev", 'Froté — ručníky, osušky, župany')
            ->set("data.produkty.{$klic}.nazev_en", 'Terry — towels, bath sheets, robes')
            ->set("data.produkty.{$klic}.kategorie", 'Konfekce')
            ->call('uloz')
            ->assertHasNoErrors();

        auth()->logout();
        $data = $this->dataWebu($this->get('/')->getContent());
        $this->assertSame('Froté — ručníky, osušky, župany', $data['cs']['products'][0]['title']);
        $this->assertSame('Terry — towels, bath sheets, robes', $data['en']['products'][0]['title']);
        $this->assertSame('Cut & sew', $data['en']['products'][0]['cat']);
        $this->assertSame('Froté — ručníky, osušky, župany', json_decode(json_encode(\App\Support\StrukturovanaData::web()), true)['@graph'][2]['hasOfferCatalog']['itemListElement'][0]['itemOffered']['name']);
    }

    public function test_puvodni_fotky_po_ulozeni_zustanou(): void
    {
        Livewire::actingAs($this->spravce())->test(Sortiment::class)->call('uloz')->assertHasNoErrors();
        Livewire::test(Uvod::class)->call('uloz')->assertHasNoErrors();
        Livewire::test(ONas::class)->call('uloz')->assertHasNoErrors();

        $this->assertSame('/assets/img/product-frotte.webp?v=20260924', ObsahWebu::sekce('sortiment')['produkty'][array_key_first(ObsahWebu::sekce('sortiment')['produkty'])]['foto']);
        $this->assertSame('/assets/img/hero.webp', ObsahWebu::sekce('uvod')['foto']);

        auth()->logout();
        $this->get('/')->assertSee('src="/assets/img/hero.webp"', false)->assertSee('src="/assets/img/about.webp"', false)
            ->assertSee('/assets/img/product-frotte.webp?v=20260924', false);
    }

    public function test_nahrana_fotka_se_ulozi_jako_webp(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->spravce())->test(ONas::class)
            ->set('data.foto', [])
            ->set('data.foto.nova', UploadedFile::fake()->image('prejimka.jpg', 2400, 1600))
            ->call('uloz')->assertHasNoErrors();

        $foto = ObsahWebu::sekce('onas')['foto'];
        $foto = is_array($foto) ? reset($foto) : $foto;
        $this->assertStringStartsWith('o-nas/', $foto);
        $this->assertStringEndsWith('.webp', $foto);
        Storage::disk('public')->assertExists($foto);

        auth()->logout();
        $this->get('/')->assertSee('src="/storage/'.$foto.'"', false);
    }

    public function test_vypnuta_sekce_zmizi_a_cisla_se_precisluji(): void
    {
        SekceWebu::nastav('trasa', false);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('Ze Střední Asie do celé Evropy.', $html);
        $this->assertStringNotContainsString('href="#trasa"', $html);
        $this->assertStringNotContainsString('map__land', $html);
        $this->assertMatchesRegularExpression('#<span class="num">03</span>\s*<span class="lbl"[^>]*>Doklady a clo</span>#', $html);
        $this->assertStringContainsString('<span class="num">06</span>', $html);
        $this->assertStringNotContainsString('<span class="num">07</span>', $html);
    }

    public function test_poradi_sekci_z_obsahu_webu(): void
    {
        SekceWebu::nastavPoradi(['reference', 'sortiment', 'jak', 'trasa', 'doklady', 'onas', 'formular']);

        $html = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('#<span class="num">01</span>\s*<span class="lbl"[^>]*>Ukázky zakázek</span>#', $html);
        // Pozadí sekcí se střídá podle pořadí: první je „alt“ jako dřív Sortiment.
        $this->assertStringContainsString('<section class="section section--alt" id="reference">', $html);
        $this->assertStringContainsString('<section class="section section--base" id="sortiment">', $html);
    }

    public function test_vypnuty_formular_schova_poptavku(): void
    {
        SekceWebu::nastav('formular', false);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="form"', $html);
        $this->assertStringNotContainsString('btn--nav', $html);
        $this->assertStringNotContainsString('id="modalCta"', $html);
        $this->assertStringContainsString('Co pro Vás zajistíme a dovezeme.', $html);
    }

    public function test_kontakty_ze_zakladnich_udaju_a_obsahu_webu(): void
    {
        ZakladniUdaje::uloz(['telefon' => '+420 777 000 111', 'email' => 'poptavky@exportex.cz']);
        Livewire::actingAs($this->spravce())->test(KontaktTexty::class)
            ->set('data.osoba', 'Jana Nováková')
            ->set('data.telegram', false)
            ->call('uloz')->assertHasNoErrors();
        auth()->logout();

        $this->get('/')->assertSee('+420 777 000 111')->assertSee('tel:+420777000111', false)
            ->assertSee('https://wa.me/420777000111', false)->assertDontSee('tg://resolve', false)
            ->assertSee('poptavky@exportex.cz')->assertSee('Jana Nováková')->assertDontSee('Tomáš Mikyska')
            ->assertDontSee('734 479 684');
    }

    public function test_paticka_v_obou_jazycich_z_hlavicky_a_paticky(): void
    {
        Livewire::actingAs($this->spravce())->test(HlavickaPaticka::class)
            ->assertSet('data.paticka.pruh_en', 'Textiles from Uzbekistan & Central Asia to the EU.')
            ->set('data.paticka.text', 'Textil ze Střední Asie pro firmy v EU.')
            ->set('data.paticka.text_en', 'Textiles from Central Asia for EU companies.')
            ->call('uloz')->assertHasNoErrors();

        $this->assertSame('Textil ze Střední Asie pro firmy v EU.', ObsahWebu::sekce('paticka')['text']);
        auth()->logout();
        $this->get('/')->assertSee('Textil ze Střední Asie pro firmy v EU.')
            ->assertSee('data-en="Textiles from Central Asia for EU companies."', false);
    }

    public function test_text_ze_spravy_se_na_webu_escapuje(): void
    {
        ObsahWebu::uloz('sortiment', ['nadpis' => 'Ručníky <script>alert(1)</script>'] + ObsahWebu::sekce('sortiment'));
        ObsahWebu::uloz('reference', ['polozky' => [['stitek' => 'X', 'nazev' => '</script><script>alert(2)</script>', 'nazev_en' => 'x', 'popis' => 'y', 'popis_en' => 'y']]] + ObsahWebu::sekce('reference'));

        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
        $this->assertStringContainsString('Ručníky &lt;script&gt;', $html);
        $this->assertSame('</script><script>alert(2)</script>', $this->dataWebu($html)['cs']['cases'][0]['t']);
    }

    public function test_ochrana_osobnich_udaju_a_cookies(): void
    {
        $this->get('/ochrana-osobnich-udaju')->assertOk()->assertSeeInOrder([
            '<meta name="robots" content="noindex, follow">',
            'Ochrana osobních údajů',
            'Účinné od 3. 10. 2026',
            'exportex s.r.o.', 'Na Poříčí 1070/19, Nové Město, 110 00 Praha 1', 'IČO: 17671833',
            'Zapsaná u Městského soudu v Praze, oddíl C, vložka 374806', 'mikyska@exportex.cz', '+420 734 479 684',
            'název firmy', 'obsah poptávky', 'nejdéle 3 roky',
            'Uzavření a plnění smlouvy',
            'WhatsApp či Telegram', 'nevyužíváme k marketingu',
            'INTERNET CZ, a.s. (Forpsi)', 'Contabo GmbH', 'SimRen s.r.o.',
            'Úřadu pro ochranu osobních údajů',
            'data-jazyk="en"', 'the Czech version is binding', 'Who is the controller', 'Company number: 17671833',
        ], false);

        $this->get('/zasady-cookies')->assertOk()->assertSeeInOrder([
            'Cookies a místní úložiště',
            'Nezbytné cookies', config('session.cookie'), 'XSRF-TOKEN',
            'Žádné analytické ani marketingové cookies web nepoužívá',
            'exportex-lang-v2', 'exportex-theme-v2', 'exportex-scroll',
            'WhatsApp a Telegram',
            'data-jazyk="en"', 'Strictly necessary cookies', 'No analytics, no advertising',
        ], false);

        // Cookie lišta jen s měřením (Obsah webu → SEO a měření).
        $this->get('/')->assertDontSee('id="cc-lista"', false);
        \App\Support\NastaveniWebu::uloz(['ga4_id' => 'G-TEST12345']);
        $this->get('/')->assertSee('id="cc-lista"', false)->assertSee('Nastavení cookies');
    }

    public function test_stranky_obsahu_webu_se_otevrou(): void
    {
        $this->actingAs($this->spravce());

        foreach ([Uvod::class, Sortiment::class, \App\Filament\Pages\Web\JakToFunguje::class, \App\Filament\Pages\Web\Trasa::class,
            \App\Filament\Pages\Web\DokladyClo::class, ONas::class, \App\Filament\Pages\Web\UkazkyZakazek::class, KontaktTexty::class,
            HlavickaPaticka::class] as $stranka) {
            $this->get($stranka::getUrl())->assertOk();
            Livewire::test($stranka)->call('uloz')->assertHasNoErrors();
        }
        // Pošta bez hesla neuloží (heslo zadá správce) – jen se otevře.
        $this->get(\App\Filament\Pages\Posta::getUrl())->assertOk()->assertSee('smtp.forpsi.com');

        // Uložení beze změny nic nepokazí – web je pořád jako před převodem.
        auth()->logout();
        $this->assertSame(ObsahWebu::VYCHOZI['sortiment']['nadpis'], ObsahWebu::sekce('sortiment')['nadpis']);
        $this->get('/')->assertOk()->assertSee('Co pro Vás zajistíme a dovezeme.');
    }
}
