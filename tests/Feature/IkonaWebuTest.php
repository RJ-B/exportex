<?php

namespace Tests\Feature;

use App\Filament\Pages\HlavickaPaticka;
use App\Models\User;
use App\Support\IkonaWebu;
use App\Support\NastaveniWebu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Ikona v záložce prohlížeče: z loga (nebo vlastní ikony) při uložení, bez loga výchozí. */
class IkonaWebuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
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
}
