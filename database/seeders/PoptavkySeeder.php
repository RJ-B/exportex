<?php

namespace Database\Seeders;

use App\Models\Zprava;
use Illuminate\Database\Seeder;

/**
 * Exportex: dvě vymyšlené poptávky (česká a anglická), ať jsou Zprávy z webu
 * na co se podívat. Jen lokálně (JenLokalne) – na serveru jsou skutečné poptávky.
 *
 *     php artisan db:seed --class=PoptavkySeeder
 */
class PoptavkySeeder extends Seeder
{
    use JenLokalne;

    public function run(): void
    {
        $this->jenLokalne();

        Zprava::query()->firstOrCreate(['email' => 'jana.zkusebni@example.cz'], [
            'jmeno' => 'Jana', 'prijmeni' => 'Zkušební', 'firma' => 'Hotel U Zkoušky s.r.o.',
            'telefon' => '+420 600 000 001', 'jazyk' => 'cs',
            'zprava' => "Dobrý den,\npoptáváme hotelové froté – 800 osušek 70×140 cm, 550 g/m², bílé s bordurou. Dodání do Brna do konce března.",
        ]);

        $anglicka = Zprava::query()->firstOrCreate(['email' => 'john@example.com'], [
            'jmeno' => 'John', 'prijmeni' => 'Example', 'firma' => 'Example Textiles Ltd', 'jazyk' => 'en',
            'zprava' => 'Hello, we are looking for 300 kg of combed ring-spun yarn Ne 30/1 for a knitting trial. Please send MOQ and lead time.',
        ]);

        if ($anglicka->wasRecentlyCreated) {
            $anglicka->forceFill(['precteno_at' => now()])->save();
        }
    }
}
