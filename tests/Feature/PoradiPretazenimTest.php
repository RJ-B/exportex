<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;
use App\Support\SekceWebu;

/**
 * Pravidlo: pořadí se nikdy nezadává číslem ve formuláři, jen přetažením
 * v seznamu; nový záznam jde na konec.
 */
class PoradiPretazenimTest extends TestCase
{
    use RefreshDatabase;

    public function test_formulare_nemaji_ciselne_pole_poradi(): void
    {
        $nalezeno = [];

        foreach ((new Finder)->files()->name('*.php')->in(base_path('app')) as $soubor) {
            $kod = $soubor->getContents();

            if (preg_match("/(?:TextInput|NumberInput|Number|Text|Select)::make\(\s*['\"](?:sort_order|sort|order|poradi|razeni|position|pozice)['\"]/i", $kod)) {
                $nalezeno[] = $soubor->getRelativePathname();
            }
        }

        $this->assertSame([], $nalezeno, 'Pořadí se zadává přetažením v seznamu, ne číslem ve formuláři.');
    }

    public function test_nova_sekce_bez_ulozeneho_poradi_jde_na_konec(): void
    {
        $pocet = count(SekceWebu::stranky());
        $this->assertGreaterThan(0, $pocet);

        // Uložené pořadí neobsahuje žádnou existující sekci – žádná nesmí zmizet,
        // všechny (nové) skončí na konci řazení.
        SekceWebu::nastavPoradi(["neexistujici-sekce"]);

        $this->assertCount($pocet, SekceWebu::stranky());
    }
}
