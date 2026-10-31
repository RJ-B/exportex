<?php

namespace Tests\Feature;

use App\Filament\Pages\HlavickaPaticka;
use App\Models\User;
use App\Support\IkonaWebu;
use App\Support\LogoWebu;
use App\Support\NastaveniWebu;
use App\Support\Pruvodce\Kroky\IkonaALogo;
use App\Support\Pruvodce\Pruvodce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Ikona v záložce prohlížeče: z loga (nebo vlastní ikony) při uložení, bez loga výchozí. */
class IkonaWebuTest extends TestCase
{
    use RefreshDatabase;

    /** Soubory „ve vzhledu webu“ (public/), které test založil. */
    private array $pevne = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        // Mechanismus šablony (nahrané logo) – exportex má logo pevně ve vzhledu, to testuje poslední test.
        config(['sablona.vzhled' => ['logo' => null, 'ikona' => null]]);
    }

    protected function tearDown(): void
    {
        foreach ($this->pevne as $soubor) {
            @unlink(public_path($soubor));
        }

        parent::tearDown();
    }

    /** Logo/ikona pevně ve vzhledu webu (config sablona.vzhled). */
    private function pevne(string $co, string $obsah, string $pripona = 'png'): string
    {
        $soubor = 'test-vzhled-'.$co.'-'.uniqid().'.'.$pripona;
        file_put_contents(public_path($soubor), $obsah);
        $this->pevne[] = $soubor;
        config(['sablona.vzhled.'.$co => $soubor]);

        return $soubor;
    }

    private function spravce(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['role' => 'admin'])->save());
    }

    /** PNG s jednou barvou a průhledným okrajem kolem. */
    private function obrazek(int $sirka, int $vyska, array $rgb, int $pruhlednyOkraj = 0): string
    {
        $im = imagecreatetruecolor($sirka + 2 * $pruhlednyOkraj, $vyska + 2 * $pruhlednyOkraj);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledrectangle($im, $pruhlednyOkraj, $pruhlednyOkraj, $pruhlednyOkraj + $sirka - 1, $pruhlednyOkraj + $vyska - 1, imagecolorallocate($im, ...$rgb));
        imagesavealpha($im, true);
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} r, g, b, alfa (0 = plná, 127 = průhledná) */
    private function pixel(string $png, int $x, int $y): array
    {
        $im = imagecreatefromstring($png);
        $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));

        return [$c['red'], $c['green'], $c['blue'], $c['alpha']];
    }

    private function ulozLogo(string $png, string $pole = 'logo'): void
    {
        Storage::disk('public')->put("web/{$pole}.png", $png);
        NastaveniWebu::uloz([$pole => "web/{$pole}.png"]);
    }

    public function test_bez_loga_je_vychozi_ikona_ne_prazdny_soubor(): void
    {
        foreach ([32, 180, 192] as $velikost) {
            $this->assertSame([$velikost, $velikost], array_slice(getimagesize(public_path("ikona-{$velikost}.png")), 0, 2));
        }

        // /favicon.ico pro ty, kdo se ptají rovnou (ICO s PNG uvnitř).
        $this->assertStringStartsWith("\x00\x00\x01\x00", (string) file_get_contents(public_path('favicon.ico')));
        $this->assertGreaterThan(100, filesize(public_path('favicon.ico')));

        $this->assertSame(asset('ikona-32.png'), IkonaWebu::url(32));
        // Exportex: web má vlastní ikony („e“ z loga), ikona z loga je v administraci.
        $this->get('/admin/login')->assertOk()
            ->assertSee('<link rel="icon" type="image/png" sizes="32x32" href="'.asset('ikona-32.png').'">', false)
            ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="'.asset('ikona-180.png').'">', false);
    }

    public function test_ikona_vznikne_z_loga_ctvercova_s_poctem_stran(): void
    {
        // Široké logo 400 × 100 s průhledným okrajem 20 px.
        $this->ulozLogo($this->obrazek(400, 100, [200, 30, 30], 20));

        foreach ([32, 180, 192] as $velikost) {
            $png = Storage::disk('public')->get("web/ikona/ikona-{$velikost}.png");
            $this->assertSame([$velikost, $velikost], array_slice(getimagesizefromstring($png), 0, 2));
        }

        $png32 = Storage::disk('public')->get('web/ikona/ikona-32.png');
        // Průhledný okraj loga se ořízl – logo vyplní celou šířku, poměr 4 : 1 zůstal (8 px na výšku).
        $this->assertSame([200, 30, 30, 0], $this->pixel($png32, 0, 16));
        $this->assertSame(127, $this->pixel($png32, 16, 2)[3], 'Nad logem je čtverec průhledný.');
        // iPhone: bílé pozadí místo průhlednosti (iOS by ji vyplnil černou).
        $this->assertSame([255, 255, 255, 0], $this->pixel(Storage::disk('public')->get('web/ikona/ikona-180.png'), 90, 5));

        $this->assertStringContainsString('/storage/web/ikona/ikona-32.png?v=', IkonaWebu::url(32));
        $this->get('/admin/login')->assertOk()->assertSee('/storage/web/ikona/ikona-180.png?v=', false);   // Exportex: web má vlastní ikony
    }

    public function test_vlastni_ikona_ma_prednost_a_bez_loga_se_vrati_vychozi(): void
    {
        $this->ulozLogo($this->obrazek(400, 100, [200, 30, 30]));
        $this->ulozLogo($this->obrazek(64, 64, [20, 90, 200]), 'ikona');

        $this->assertSame([20, 90, 200, 0], $this->pixel(Storage::disk('public')->get('web/ikona/ikona-32.png'), 2, 2));

        // Bez ikony zase z loga, bez obojího výchozí.
        NastaveniWebu::uloz(['ikona' => null]);
        $this->assertSame([200, 30, 30, 0], $this->pixel(Storage::disk('public')->get('web/ikona/ikona-32.png'), 16, 16));

        NastaveniWebu::uloz(['logo' => null]);
        Storage::disk('public')->assertMissing('web/ikona/ikona-32.png');
        $this->assertSame(asset('ikona-32.png'), IkonaWebu::url(32));
    }

    public function test_rozbite_logo_nezastavi_ulozeni_a_plati_vychozi_ikona(): void
    {
        Storage::disk('public')->put('web/logo.png', 'tohle neni obrazek');

        NastaveniWebu::uloz(['logo' => 'web/logo.png', 'tagline' => 'Pečeme od roku 1990']);

        $this->assertSame('Pečeme od roku 1990', NastaveniWebu::get('tagline'));
        Storage::disk('public')->assertMissing('web/ikona/ikona-32.png');
        $this->assertSame(asset('ikona-32.png'), IkonaWebu::url(32));
    }

    public function test_administrace_ma_ikonu_a_logo_se_nahrava_v_hlavicce(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('<link rel="icon" type="image/png" sizes="32x32" href="'.asset('ikona-32.png').'">', false);

        // Exportex: logo je pevně ve vzhledu webu – v Hlavičce a patičce se nenahrává.
    }

    public function test_krok_pruvodce_vede_na_logo_a_odskrtne_se_nahranim(): void
    {
        $krok = app(IkonaALogo::class);
        $this->assertTrue($krok->zobrazit());
        $this->assertStringEndsWith('/admin/hlavicka-a-paticka#logo', $krok->url());
        $this->assertFalse($krok->hotovo());

        // Sekce s kotvou je na stránce, kam krok vede.
        $this->actingAs($spravce = $this->spravce())->get('/admin/hlavicka-a-paticka')->assertOk()
            ->assertSee('id="logo"', false)
            ->assertSee(__('administrace.hlavicka_paticka.logo_a_ikona'));

        Livewire::actingAs($spravce)->test(HlavickaPaticka::class)
            ->set('data.nazev', 'Pekárna U Nováků')
            ->set('data.logo', UploadedFile::fake()->createWithContent('logo.png', $this->obrazek(300, 100, [200, 30, 30])))
            ->call('uloz')
            ->assertHasNoErrors();

        $this->assertTrue($krok->hotovo());
        // Nahrané logo se uloží jako WebP (pravidlo o fotkách), průhlednost zůstane.
        $this->assertStringEndsWith('.webp', NastaveniWebu::get('logo'));
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get(NastaveniWebu::get('logo')))['mime']);
    }

    public function test_administrace_ukazuje_stejne_logo_jako_web(): void
    {
        $spravce = $this->spravce();

        // Bez loga výchozí ikona a název.
        $this->actingAs($spravce)->get('/admin')->assertOk()
            ->assertSee('<img src="'.asset('ikona-192.png').'" alt="" width="28" height="28">', false)
            ->assertSee('simren-znacka-nazev', false);

        // Široké logo (nápis) stojí samo, název je v něm.
        $this->ulozLogo($this->obrazek(400, 100, [200, 30, 30]));
        $this->assertTrue(LogoWebu::siroke());
        $this->actingAs($spravce)->get('/admin')->assertOk()
            ->assertSee('class="simren-znacka-logo" src="'.LogoWebu::url().'"', false)
            ->assertDontSee('simren-znacka-nazev', false);

        // Čtvercová značka – vedle ní název.
        $this->ulozLogo($this->obrazek(100, 100, [20, 90, 200]));
        $this->assertFalse(LogoWebu::siroke());
        $this->actingAs($spravce)->get('/admin')->assertOk()
            ->assertSee('<img src="'.LogoWebu::url().'" alt="" width="28" height="28">', false)
            ->assertSee('simren-znacka-nazev', false);
    }

    public function test_pevne_logo_ve_vzhledu_webu_bere_administrace_sama(): void
    {
        $logo = $this->pevne('logo', $this->obrazek(400, 100, [200, 30, 30]));
        $spravce = $this->spravce();

        $this->assertTrue(LogoWebu::pevne());
        // Krok průvodce se neukazuje – není co nastavit.
        $this->assertNotContains('IkonaALogo', Pruvodce::kroky()->map->klic()->all());

        // Logo v menu administrace, ikona v záložce vznikla z loga sama (bez uložení).
        $this->actingAs($spravce)->get('/admin')->assertOk()
            ->assertSee('src="'.asset($logo).'?v=', false)
            ->assertSee('/storage/web/ikona/ikona-32.png?v=', false);
        $this->assertSame([200, 30, 30, 0], $this->pixel(Storage::disk('public')->get('web/ikona/ikona-32.png'), 0, 16));

        // V Hlavičce a patičce se logo nenahrává.
        $this->actingAs($spravce)->get('/admin/hlavicka-a-paticka')->assertOk()
            ->assertDontSee('id="logo"', false);

        // Pevná ikona (čtverec) má před logem přednost a neořezává se (pozadí ikony zůstane).
        $this->pevne('ikona', $this->obrazek(48, 48, [20, 90, 200], 8));
        Storage::disk('public')->deleteDirectory('web/ikona');
        IkonaWebu::url(32);
        $png32 = Storage::disk('public')->get('web/ikona/ikona-32.png');
        $this->assertSame(127, $this->pixel($png32, 1, 1)[3], 'Okraj hotové ikony zůstal.');
        $this->assertSame([20, 90, 200, 0], $this->pixel($png32, 16, 16));

        // SVG ikonu GD nepřečte → z loga.
        $this->pevne('ikona', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64"/></svg>', 'svg');
        IkonaWebu::obnov();
        $this->assertSame([200, 30, 30, 0], $this->pixel(Storage::disk('public')->get('web/ikona/ikona-32.png'), 0, 16));
    }

    public function test_pevne_logo_novejsi_nez_ikona_ikonu_obnovi(): void
    {
        $logo = $this->pevne('logo', $this->obrazek(100, 100, [200, 30, 30]));
        IkonaWebu::url(32);
        $this->assertSame([200, 30, 30, 0], $this->pixel(Storage::disk('public')->get('web/ikona/ikona-32.png'), 16, 16));

        // Nasazení nového loga (soubor novější než ikona).
        file_put_contents(public_path($logo), $this->obrazek(100, 100, [20, 90, 200]));
        touch(public_path($logo), time() + 60);
        clearstatcache();

        IkonaWebu::url(32);
        $this->assertSame([20, 90, 200, 0], $this->pixel(Storage::disk('public')->get('web/ikona/ikona-32.png'), 16, 16));
    }

    public function test_exportex_administrace_ma_logo_a_ikonu_z_vzhledu_webu(): void
    {
        // Skutečné nastavení projektu (setUp ho pro testy šablony vypnul).
        config(['sablona.vzhled' => (require config_path('sablona.php'))['vzhled']]);

        $this->assertSame('assets/img/logo.webp', LogoWebu::pevnySoubor('logo'));
        $this->assertSame('assets/img/icon-192.png', LogoWebu::pevnySoubor('ikona'));
        $this->assertNotContains('IkonaALogo', Pruvodce::kroky()->map->klic()->all());

        $spravce = $this->spravce();
        $this->actingAs($spravce)->get('/admin')->assertOk()
            ->assertSee('class="simren-znacka-logo" src="'.asset('assets/img/logo.webp').'?v=', false)
            ->assertSee('/storage/web/ikona/ikona-32.png?v=', false);

        // Ikona v záložce administrace = „e“ z ikony webu (tmavý čtverec), ne výchozí.
        $png = Storage::disk('public')->get('web/ikona/ikona-192.png');
        $this->assertSame([192, 192], array_slice(getimagesizefromstring($png), 0, 2));
        $this->assertLessThan(40, $this->pixel($png, 10, 96)[0]);
    }
}
