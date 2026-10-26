<?php

namespace Tests\Feature;

use App\Enums\StavWebu;
use App\Models\Nastaveni;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Vyhledávače, https a jedna doména: testovací web se neindexuje, produkce
 * má robots.txt a sitemap.xml z aplikace, www. jde na doménu z APP_URL.
 */
class VyhledavaceADomenaTest extends TestCase
{
    use RefreshDatabase;

    private function produkce(string $url = 'https://firma.cz'): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => $url]);
        URL::useOrigin($url);
        AppServiceProvider::https();   // jako při startu aplikace s tímhle APP_URL
    }

    public function test_testovaci_web_se_neindexuje(): void
    {
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertOk()->assertSeeText('Disallow: /')->assertDontSeeText('Sitemap');
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_produkce_ma_robots_a_mapu_webu(): void
    {
        $this->produkce();

        $this->get('https://firma.cz/')->assertHeaderMissing('X-Robots-Tag');
        $this->get('https://firma.cz/robots.txt')->assertOk()
            ->assertSeeText('Disallow: /admin')
            ->assertSeeText('Sitemap: https://firma.cz/sitemap.xml');

        $mapa = $this->get('https://firma.cz/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')->getContent();
        $this->assertStringContainsString('<loc>https://firma.cz</loc>', $mapa);
        $this->assertStringContainsString('<loc>https://firma.cz/ochrana-osobnich-udaju</loc>', $mapa);
        $this->assertStringNotContainsString('<loc>https://firma.cz/kontakt</loc>', $mapa);   // Exportex: /kontakt přesměruje na formulář na úvodu

        // Administrace nikdy.
        $this->get('https://firma.cz/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_pripravujeme_se_neindexuje_ani_na_produkci(): void
    {
        $this->produkce();
        Nastaveni::nastav(StavWebu::KLIC, StavWebu::Pripravujeme->value);

        $this->get('https://firma.cz/robots.txt')->assertSeeText('Disallow: /')->assertDontSeeText('Sitemap');
        $this->get('https://firma.cz/sitemap.xml')->assertNotFound();
    }

    public function test_odkazy_a_hsts_podle_app_url(): void
    {
        $this->produkce('https://firma.cz');
        URL::useOrigin('http://firma.cz');   // proxy: aplikace vidí http

        $this->assertStringStartsWith('https://', route('kontakt'));
        $this->get('https://firma.cz/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_www_a_dalsi_domeny_presmeruji_na_app_url(): void
    {
        $this->produkce();
        config(['sablona.dalsi_domeny' => ['firma.eu']]);

        $this->get('https://www.firma.cz/zasady-cookies?x=1')->assertStatus(301)->assertRedirect('https://firma.cz/zasady-cookies?x=1');   // Exportex: /kontakt přesměruje na formulář na úvodu
        $this->get('https://firma.eu/')->assertStatus(301)->assertRedirect('https://firma.cz/');
        $this->get('https://www.firma.cz/admin/login')->assertStatus(301);
        $this->get('https://firma.cz/zasady-cookies')->assertOk();
        // Cizí doména (alias, o kterém projekt neví) se nepřesměrovává.
        $this->get('https://jina.cz/zasady-cookies')->assertOk();
    }

    public function test_lokalne_se_nepresmerovava(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $this->get('http://localhost/zasady-cookies')->assertOk();
    }
}
