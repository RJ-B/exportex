<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoMereni;
use App\Models\Nastaveni;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Fotky;
use App\Support\NastaveniWebu;
use App\Support\Seo\NastaveniSeo;
use App\Support\Seo\Seo;
use App\Support\Seo\StrukturovanaData;
use App\Support\ZakladniUdaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SEO a strukturovaná data: meta description, canonical, Open Graph, Twitter
 * Card, robots a JSON-LD na veřejných stránkách, mapa webu s lastmod
 * a obrázky, administrace SEO a měření.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Seo::zapomenRozmery();
        // Exportex: testy šablony počítají s prázdným projektem (bez výchozích údajů a titulku úvodu).
        config(['nastaveni.vychozi' => []]);
    }

    private function produkce(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://firma.cz']);
        URL::useOrigin('https://firma.cz');
        AppServiceProvider::https();
    }

    private function firma(): void
    {
        ZakladniUdaje::uloz([
            'nazev' => 'Pekárna U Nováků',
            'email' => 'info@pekarna.cz',
            'telefon' => '+420 777 123 456',
            'firma' => 'Pekárna U Nováků s.r.o.',
            'ico' => '12345678',
            'dic' => 'CZ12345678',
            'adresa' => "Dlouhá 12\n110 00 Praha 1",
        ]);
        NastaveniWebu::uloz([
            'tagline' => 'Kváskový chléb každé ráno',
            'meta_popis' => 'Rodinná pekárna v centru Prahy. Kváskový chléb, rohlíky a koláče z vlastní výroby každé ráno od šesti.',
            'facebook' => 'https://facebook.com/pekarna',
            'instagram' => 'https://instagram.com/pekarna',
        ]);
    }

    private function obrazekSdileni(): string
    {
        return Fotky::ulozSdileni(UploadedFile::fake()->image('pekarna.jpg', 2400, 1600), 'web/sdileni');
    }

    /** @return array<int, array<string, mixed>> uzly @graph z <script type="application/ld+json"> */
    private function jsonLd(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m), 'Stránka nemá JSON-LD.');
        $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $data['@context']);

        return $data['@graph'];
    }

    private function uzel(array $graf, string $typ): ?array
    {
        return collect($graf)->first(fn ($u) => $u['@type'] === $typ);
    }

    private function meta(string $html, string $atribut, string $nazev): ?string
    {
        return preg_match('#<meta '.$atribut.'="'.preg_quote($nazev, '#').'" content="([^"]*)">#', $html, $m) ? html_entity_decode($m[1]) : null;
    }

    public function test_uvod_ma_meta_popis_canonical_open_graph_a_twitter(): void
    {
        $this->produkce();
        $this->firma();
        NastaveniSeo::uloz(['obrazek' => $this->obrazekSdileni()]);

        $html = $this->get('https://firma.cz/?utm_source=facebook')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Pekárna U Nováků – Kváskový chléb každé ráno</title>', $html);
        $this->assertStringStartsWith('Rodinná pekárna v centru Prahy.', $this->meta($html, 'name', 'description'));
        $this->assertStringContainsString('<link rel="canonical" href="https://firma.cz">', $html);
        $this->assertSame('index, follow, max-image-preview:large', $this->meta($html, 'name', 'robots'));

        $this->assertSame('website', $this->meta($html, 'property', 'og:type'));
        $this->assertSame('cs_CZ', $this->meta($html, 'property', 'og:locale'));
        $this->assertSame('Pekárna U Nováků', $this->meta($html, 'property', 'og:site_name'));
        $this->assertSame('https://firma.cz', $this->meta($html, 'property', 'og:url'));
        $this->assertStringEndsWith('.jpg', $this->meta($html, 'property', 'og:image'));
        $this->assertStringStartsWith('https://firma.cz/', $this->meta($html, 'property', 'og:image'));
        $this->assertSame('1200', $this->meta($html, 'property', 'og:image:width'));
        $this->assertSame('630', $this->meta($html, 'property', 'og:image:height'));
        $this->assertSame('image/jpeg', $this->meta($html, 'property', 'og:image:type'));
        $this->assertSame('summary_large_image', $this->meta($html, 'name', 'twitter:card'));
        $this->assertSame($this->meta($html, 'property', 'og:image'), $this->meta($html, 'name', 'twitter:image'));
    }

    public function test_testovaci_web_ma_noindex_i_v_meta(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame('noindex, nofollow', $this->meta($html, 'name', 'robots'));
    }

    public function test_json_ld_organizace_web_a_stranka(): void
    {
        $this->produkce();
        $this->firma();

        $graf = $this->jsonLd($this->get('https://firma.cz/')->getContent());

        $organizace = $this->uzel($graf, 'Organization');
        $this->assertSame('https://firma.cz/#organizace', $organizace['@id']);
        $this->assertSame('Pekárna U Nováků', $organizace['name']);
        $this->assertSame('Pekárna U Nováků s.r.o.', $organizace['legalName']);
        $this->assertSame('https://firma.cz', $organizace['url']);
        $this->assertSame('info@pekarna.cz', $organizace['email']);
        $this->assertSame('+420 777 123 456', $organizace['telephone']);
        $this->assertSame('CZ12345678', $organizace['vatID']);
        $this->assertSame(['@type' => 'PropertyValue', 'propertyID' => 'IČO', 'value' => '12345678'], $organizace['identifier']);
        $this->assertSame(['https://facebook.com/pekarna', 'https://instagram.com/pekarna'], $organizace['sameAs']);
        $this->assertSame(['@type' => 'PostalAddress', 'streetAddress' => 'Dlouhá 12', 'postalCode' => '11000', 'addressLocality' => 'Praha 1', 'addressCountry' => 'CZ'], $organizace['address']);
        $this->assertArrayNotHasKey('openingHoursSpecification', $organizace);

        $web = $this->uzel($graf, 'WebSite');
        $this->assertSame('https://firma.cz', $web['url']);
        $this->assertSame('cs-CZ', $web['inLanguage']);
        $this->assertSame(['@id' => 'https://firma.cz/#organizace'], $web['publisher']);
        $this->assertArrayNotHasKey('potentialAction', $web, 'Web bez vyhledávání nemá SearchAction.');

        $stranka = $this->uzel($graf, 'WebPage');
        $this->assertSame('https://firma.cz', $stranka['url']);
        $this->assertNull($this->uzel($graf, 'BreadcrumbList'), 'Úvod drobečky nemá.');
    }

    public function test_podstranka_ma_drobecky_a_canonical_bez_parametru(): void
    {
        $this->produkce();
        $this->firma();

        $html = $this->get('https://firma.cz/zasady-cookies?utm_source=x#formular')->assertOk()->getContent();   // Exportex: /kontakt přesměruje na formulář na úvodu

        $this->assertStringContainsString('<title>Cookies a místní úložiště – Pekárna U Nováků</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://firma.cz/zasady-cookies">', $html);

        $drobecky = $this->uzel($this->jsonLd($html), 'BreadcrumbList');
        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Úvod', 'item' => 'https://firma.cz'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Cookies a místní úložiště', 'item' => 'https://firma.cz/zasady-cookies'],
        ], $drobecky['itemListElement']);
    }

    public function test_vlastni_titulek_popis_a_obrazek_stranky(): void
    {
        $this->produkce();
        $this->firma();
        NastaveniSeo::uloz(['stranky' => ['cookies' => [   // Exportex: /kontakt přesměruje na formulář na úvodu
            'titulek' => 'Objednávky dortů na přání | Pekárna U Nováků',
            'popis' => 'Dort na oslavu objednáte nejpozději tři dny předem.',
            'obrazek' => $this->obrazekSdileni(),
        ]]]);

        $html = $this->get('https://firma.cz/zasady-cookies')->getContent();

        $this->assertStringContainsString('<title>Objednávky dortů na přání | Pekárna U Nováků</title>', $html);
        $this->assertSame('Dort na oslavu objednáte nejpozději tři dny předem.', $this->meta($html, 'name', 'description'));
        $this->assertStringContainsString('/web/sdileni/', $this->meta($html, 'property', 'og:image'));
    }

    public function test_mistni_firma_s_oteviraci_dobou_a_polohou(): void
    {
        $this->produkce();
        $this->firma();
        ZakladniUdaje::uloz(['provozovna' => 'Krátká 3, 120 00 Praha 2']);
        NastaveniSeo::uloz([
            'subjekt' => NastaveniSeo::SUBJEKT_MISTNI_FIRMA,
            'kategorie' => 'Bakery',
            'oteviraci_doba' => [
                ['dny' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'od' => '6:00', 'do' => '18:00'],
                ['dny' => ['Saturday'], 'od' => '7:00', 'do' => '12:00'],
                ['dny' => [], 'od' => '7:00', 'do' => '12:00'],   // neúplný řádek se neuloží
            ],
            'geo_sirka' => '50.0755',
            'geo_delka' => '14.4378',
        ]);

        $firma = $this->uzel($this->jsonLd($this->get('https://firma.cz/')->getContent()), 'Bakery');

        $this->assertNotNull($firma, 'Místní firma má typ podle kategorie.');
        $this->assertSame('Krátká 3', $firma['address']['streetAddress'], 'Místní firma = adresa provozovny.');
        $this->assertSame('12000', $firma['address']['postalCode']);
        $this->assertCount(2, $firma['openingHoursSpecification']);
        $this->assertSame(['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Saturday'], 'opens' => '07:00', 'closes' => '12:00'], $firma['openingHoursSpecification'][1]);
        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 50.0755, 'longitude' => 14.4378], $firma['geo']);
    }

    public function test_mistni_firma_bez_adresy_zustane_organizaci(): void
    {
        $this->produkce();
        ZakladniUdaje::uloz(['nazev' => 'Pekárna']);
        NastaveniSeo::uloz(['subjekt' => NastaveniSeo::SUBJEKT_MISTNI_FIRMA, 'kategorie' => 'Bakery']);

        $graf = $this->jsonLd($this->get('https://firma.cz/')->getContent());

        $this->assertNotNull($this->uzel($graf, 'Organization'));
        $this->assertNull($this->uzel($graf, 'Bakery'));
    }

    public function test_vyhledavani_prida_search_action(): void
    {
        $this->produkce();
        config(['sablona.vyhledavani' => ['routa' => 'kontakt', 'parametr' => 'hledat']]);

        $web = $this->uzel($this->jsonLd($this->get('https://firma.cz/')->getContent()), 'WebSite');

        $this->assertSame('SearchAction', $web['potentialAction']['@type']);
        $this->assertSame('https://firma.cz/kontakt?hledat={search_term_string}', $web['potentialAction']['target']['urlTemplate']);
    }

    public function test_json_ld_se_neda_rozbit_textem(): void
    {
        ZakladniUdaje::uloz(['nazev' => 'Zlá </script><script>alert(1)</script> firma']);

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame('Zlá </script><script>alert(1)</script> firma', $this->uzel($this->jsonLd($html), 'Organization')['name']);
    }

    public function test_clanek_a_produkt(): void
    {
        $this->produkce();
        $this->get('https://firma.cz/');   // požadavek kvůli kanonické adrese

        $clanek = StrukturovanaData::clanek(
            titulek: 'Jak upéct kváskový chléb',
            vydano: Carbon::parse('2026-10-09 08:30', 'Europe/Prague'),
            autor: 'Jana Nováková',
            adresa: 'https://firma.cz/blog/kvaskovy-chleb',
        );

        $this->assertSame('Article', $clanek['@type']);
        $this->assertSame('Jak upéct kváskový chléb', $clanek['headline']);
        $this->assertSame('2026-10-09T08:30:00+02:00', $clanek['datePublished'], 'Čas v ISO 8601 s posunem.');
        $this->assertSame(['@type' => 'Person', 'name' => 'Jana Nováková'], $clanek['author']);

        $produkt = StrukturovanaData::produkt(nazev: 'Kváskový chléb 1 kg', cena: 89, kod: 'CHL-1', adresa: 'https://firma.cz/chleb');

        $this->assertSame('Product', $produkt['@type']);
        $this->assertSame(['@type', 'url', 'price', 'priceCurrency', 'availability', 'seller'], array_keys($produkt['offers']));
        $this->assertSame('89.00', $produkt['offers']['price']);
        $this->assertSame('CZK', $produkt['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $produkt['offers']['availability']);
    }

    public function test_mapa_webu_ma_lastmod_s_posunem_a_obrazky(): void
    {
        $this->produkce();
        $this->firma();
        NastaveniSeo::uloz(['obrazek' => $this->obrazekSdileni()]);

        $mapa = $this->get('https://firma.cz/sitemap.xml')->assertOk()->getContent();
        $xml = simplexml_load_string($mapa);

        $this->assertNotFalse($xml, 'sitemap.xml je platné XML.');
        $this->assertStringContainsString('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $mapa);
        $this->assertMatchesRegularExpression('#<lastmod>\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d</lastmod>#', $mapa);
        $this->assertMatchesRegularExpression('#<image:loc>https://firma\.cz/[^<]+\.jpg</image:loc>#', $mapa);
        $this->get('https://firma.cz/robots.txt')->assertSeeText('Sitemap: https://firma.cz/sitemap.xml');
    }

    public function test_obrazek_pro_sdileni_je_jpeg_1200_x_630_bez_metadat(): void
    {
        $cesta = $this->obrazekSdileni();

        // Výjimka z WebP: LinkedIn a další sítě WebP v náhledu odkazu nečtou.
        $this->assertStringEndsWith('.jpg', $cesta);
        $jpeg = Storage::disk('public')->get($cesta);
        $info = getimagesizefromstring($jpeg);
        $this->assertSame([1200, 630, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
        $this->assertSame(3, $info['channels'], 'RGB (sRGB), ne CMYK.');
        // Bez EXIF/XMP (APP1) a bez komentáře GD („CREATOR: gd-jpeg…“).
        $this->assertStringNotContainsString('CREATOR', $jpeg);
        $this->assertStringNotContainsString('Exif', $jpeg);
    }

    public function test_pruhledny_obrazek_pro_sdileni_je_na_bilem(): void
    {
        $png = imagecreatetruecolor(1200, 630);
        imagesavealpha($png, true);
        imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
        ob_start();
        imagepng($png);
        $data = (string) ob_get_clean();

        $jpeg = imagecreatefromstring(Fotky::naJpegSdileni($data));
        $barva = imagecolorsforindex($jpeg, imagecolorat($jpeg, 10, 10));

        $this->assertGreaterThan(245, $barva['red'] + 0);
        $this->assertGreaterThan(245, $barva['green'] + 0);
        $this->assertGreaterThan(245, $barva['blue'] + 0);
    }

    public function test_starsi_webp_pro_sdileni_dostane_jpeg_kopii(): void
    {
        $this->produkce();
        $this->firma();
        // Obrázek pro sdílení uložený dřív jako WebP – data se nemění.
        $webp = 'web/sdileni/stary.webp';
        $foto = UploadedFile::fake()->image('stary.jpg', 1200, 630);
        Storage::disk('public')->put($webp, Fotky::naWebp((string) file_get_contents($foto->getRealPath())));
        NastaveniSeo::uloz(['obrazek' => $webp]);

        $html = $this->get('https://firma.cz/')->assertOk()->getContent();

        $this->assertStringEndsWith('/web/sdileni/stary.jpg', $this->meta($html, 'property', 'og:image'));
        $this->assertSame('image/jpeg', $this->meta($html, 'property', 'og:image:type'));
        $this->assertSame('1200', $this->meta($html, 'property', 'og:image:width'));
        Storage::disk('public')->assertExists([$webp, 'web/sdileni/stary.jpg']);
        $this->assertSame($webp, NastaveniSeo::get('obrazek'));

        // Podruhé se kopie jen použije.
        $zmena = Storage::disk('public')->lastModified('web/sdileni/stary.jpg');
        $this->assertSame('web/sdileni/stary.jpg', Fotky::jpegSdileni($webp));
        $this->assertSame($zmena, Storage::disk('public')->lastModified('web/sdileni/stary.jpg'));
        // Jiný formát a adresa beze změny.
        $this->assertSame('web/logo.png', Fotky::jpegSdileni('web/logo.png'));
        $this->assertSame('https://cdn.cz/a.webp', Fotky::jpegSdileni('https://cdn.cz/a.webp'));
    }

    public function test_administrace_seo_ulozi_typ_subjektu_a_stranky(): void
    {
        $admin = tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => 'admin'])->save());

        Livewire::actingAs($admin)->test(SeoMereni::class)
            ->assertSuccessful()
            ->assertSee('Google')
            ->assertSee('Facebook')
            ->set('data.meta_popis', 'Rodinná pekárna v centru Prahy.')
            ->set('data.subjekt', NastaveniSeo::SUBJEKT_MISTNI_FIRMA)
            ->set('data.kategorie', 'Bakery')
            ->set('data.oteviraci_doba', [['dny' => ['Monday'], 'od' => '25:00', 'do' => '18:00']])
            ->call('uloz')
            ->assertHasErrors(['data.oteviraci_doba.0.od'])
            ->set('data.oteviraci_doba', [['dny' => ['Monday'], 'od' => '6:00', 'do' => '18:00']])
            ->set('data.stranky.uvod.titulek', 'Pekárna v centru Prahy')
            ->call('uloz')
            ->assertHasNoErrors();

        $this->assertSame('Rodinná pekárna v centru Prahy.', NastaveniWebu::get('meta_popis'));
        $this->assertTrue(NastaveniSeo::mistniFirma());
        $this->assertSame('Bakery', NastaveniSeo::get('kategorie'));
        $this->assertSame([['dny' => ['Monday'], 'od' => '06:00', 'do' => '18:00']], NastaveniSeo::get('oteviraci_doba'));
        $this->assertSame('Pekárna v centru Prahy', NastaveniSeo::stranka('uvod')['titulek']);
        $this->assertNull(Nastaveni::hodnota('seo.geo_sirka'));
    }

    public function test_vlastni_nazev_a_provozovatel_stranky(): void
    {
        $this->produkce();
        $this->firma();
        $this->get('https://firma.cz/');

        $seo = Seo::uvod()->nazev('Pobočka Brno')->subjekt(StrukturovanaData::organizace('Pobočka Brno s.r.o.', email: 'brno@pekarna.cz', ico: '87654321'));
        $graf = collect($seo->strukturovanaData());

        $this->assertSame('Pobočka Brno', $seo->titulek(), 'Vlastní název webu bez podtitulku hlavního webu.');
        $this->assertSame('Pobočka Brno s.r.o.', $graf->firstWhere('@type', 'Organization')['name']);
        $this->assertSame('https://firma.cz/#organizace', $graf->firstWhere('@type', 'Organization')['@id']);
        $this->assertSame('Pobočka Brno', $graf->firstWhere('@type', 'WebSite')['name']);

        $bez = collect(Seo::uvod()->subjekt(false)->strukturovanaData());
        $this->assertNull($bez->firstWhere('@type', 'Organization'));
        $this->assertArrayNotHasKey('publisher', $bez->firstWhere('@type', 'WebSite'));
    }
}
