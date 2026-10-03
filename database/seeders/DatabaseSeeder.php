<?php

namespace Database\Seeders;

use App\Models\Zprava;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Jen pro lokální vývoj: dvě vymyšlené poptávky (česká a anglická), ať jsou
 * Zprávy z webu na co se podívat. NIKDY na server – na produkci i testu tam
 * jsou skutečné poptávky a seeder by se mezi ně vmíchal.
 *
 * Správce se zakládá příkazem simren:spravce (README), ne seederem.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Testovací data jen lokálně (APP_ENV=local) – na server nikdy.');
        }

        Zprava::create([
            'jmeno' => 'Jana', 'prijmeni' => 'Zkušební', 'firma' => 'Hotel U Zkoušky s.r.o.',
            'email' => 'jana.zkusebni@example.cz', 'telefon' => '+420 600 000 001', 'jazyk' => 'cs',
            'zprava' => "Dobrý den,\npoptáváme hotelové froté – 800 osušek 70×140 cm, 550 g/m², bílé s bordurou. Dodání do Brna do konce března.",
        ]);

        Zprava::create([
            'jmeno' => 'John', 'prijmeni' => 'Example', 'firma' => 'Example Textiles Ltd',
            'email' => 'john@example.com', 'jazyk' => 'en',
            'zprava' => 'Hello, we are looking for 300 kg of combed ring-spun yarn Ne 30/1 for a knitting trial. Please send MOQ and lead time.',
        ])->forceFill(['precteno_at' => now()])->save();
    }
}
