<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Žádné prvky ve vzhledu prohlížeče (Safari a Chrome je kreslí každý jinak):
 * roletky, výběr data, zaškrtávátka. Ve Filamentu globálně native(false),
 * natvrdo kreslené <select> převleče public/js/vyber.js, na webu vlastní CSS
 * s roletkou stejně vysokou jako pole.
 */
class ZadneNativniPrvkyTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_nekresli_nativni_roletky_ani_kalendar(): void
    {
        $this->assertFalse(Select::make('role')->isNative());
        $this->assertFalse(SelectFilter::make('stav')->isNative());
        $this->assertFalse(DatePicker::make('od')->isNative());
        $this->assertFalse(DateTimePicker::make('kdy')->isNative());
    }

    public function test_administrace_nacte_nahradu_natvrdo_kreslenych_roletek(): void
    {
        $this->assertFileExists(public_path('js/vyber.js'));
        $this->assertFileExists(public_path('css/vyber.css'));

        $spravce = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($spravce)->get('/admin')
            ->assertOk()
            ->assertSee('js/vyber.js', false)
            ->assertSee('css/vyber.css', false);
    }

    public function test_web_ma_vlastni_pole_roletky_a_zaskrtavatka(): void
    {
        $css = (string) file_get_contents(resource_path('views/layouts/verejna.blade.php'));

        // Roletka stejně vysoká jako pole – appearance: none, jinak si prohlížeč výšku přepíše.
        $this->assertMatchesRegularExpression('/input:not\(\[type=hidden\]\)[^{]*select[^{]*\{[^}]*height: 46px;[^}]*appearance: none;/', $css);
        $this->assertMatchesRegularExpression('/input\[type=checkbox\][^{]*\{[^}]*appearance: none;/', $css);

        $lista = (string) file_get_contents(resource_path('views/pravni/cookie-lista.blade.php'));
        $this->assertMatchesRegularExpression('/\.cc-kat input \{[^}]*appearance: none;/', $lista);
        $this->assertStringNotContainsString('accent-color', $lista);
    }
}
