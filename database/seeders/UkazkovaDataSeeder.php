<?php

namespace Database\Seeders;

use App\Enums\KanalOznameni;
use App\Enums\ZavaznostOznameni;
use App\Models\Oznameni;
use App\Models\OznameniSkupina;
use App\Models\User;
use App\Models\Zprava;
use App\Platby\Brany\Simulace;
use App\Platby\NastaveniPlateb;
use App\Platby\Platba;
use App\Platby\Platby;
use App\Platby\PozadavekPlatby;
use App\Platby\StavPlatby;
use App\Support\Oznameni\Oznam;
use Illuminate\Database\Seeder;

/**
 * Ukázková data pro lokální vývoj: klient a dvě zprávy z webu (přečtená
 * a nepřečtená) a oznámení (odeslané provozní, pruh s odstávkou, koncept
 * novinky, skupina příjemců), se zapnutým doplňkem Platby i tři platby
 * (zaplacená, čekající, zamítnutá), ať administrace nevypadá prázdná.
 * Jen lokálně (JenLokalne).
 *
 *     php artisan db:seed --class=UkazkovaDataSeeder
 *
 * Opakovatelný: nic nepřepisuje, jen založí, co chybí.
 */
class UkazkovaDataSeeder extends Seeder
{
    use JenLokalne;

    public function run(): void
    {
        $this->jenLokalne();

        User::query()->firstOrCreate(['email' => 'jana.dvorakova@example.cz'], [
            'jmeno' => 'Jana',
            'prijmeni' => 'Dvořáková',
        ]);

        Zprava::query()->firstOrCreate(['email' => 'petr.svoboda@example.cz'], [
            'jmeno' => 'Petr',
            'prijmeni' => 'Svoboda',
            'telefon' => '+420 602 123 456',
            'zprava' => 'Dobrý den, poptávám údržbu zahrady o rozloze 800 m² na jaro. Děkuji.',
        ]);

        $prectena = Zprava::query()->firstOrCreate(['email' => 'marie.kralova@example.cz'], [
            'jmeno' => 'Marie',
            'prijmeni' => 'Králová',
            'zprava' => 'Máte volný termín na konzultaci příští týden?',
        ]);

        if ($prectena->wasRecentlyCreated) {
            $prectena->forceFill(['precteno_at' => now()->subDay()])->save();
        }

        $this->oznameni();
        $this->platby();
    }

    /**
     * Doplněk Platby (jen zapnutý, přes simulaci): zaplacená, čekající
     * a zamítnutá platba – ať je v Platbách vidět, co je v pořádku a co ne.
     */
    private function platby(): void
    {
        if (! config('sablona.doplnky.platby') || ! NastaveniPlateb::simulaceDovolena() || Platba::query()->exists()) {
            return;
        }

        $platby = app(Platby::class);
        $nova = fn (string $popis, string $kc, string $jmeno, string $prijmeni, string $email, string $cislo) => $platby->zaloz(new PozadavekPlatby(
            castka: Platby::halere($kc), popis: $popis, email: $email, jmeno: $jmeno, prijmeni: $prijmeni, reference: $cislo,
        ));

        $zaplacena = $nova('Údržba zahrady – jaro', '4 800', 'Petr', 'Svoboda', 'petr.svoboda@example.cz', '2026-0012');
        Simulace::nastav($zaplacena, StavPlatby::Zaplacena);
        $platby->overStav($zaplacena, 'simulace');

        $nova('Konzultace 1 hodina', '1 200', 'Marie', 'Králová', 'marie.kralova@example.cz', '2026-0013');

        $zamitnuta = $nova('Záloha na výsadbu živého plotu', '15 000', 'Jana', 'Dvořáková', 'jana.dvorakova@example.cz', '2026-0014');
        Simulace::nastav($zamitnuta, StavPlatby::Zamitnuta);
        $platby->overStav($zamitnuta, 'simulace');
    }

    /** Oznámení: jedno v pořádku, jedno, které něco hlásí (odstávka v pruhu), a koncept. */
    private function oznameni(): void
    {
        $jana = User::query()->where('email', 'jana.dvorakova@example.cz')->first();
        $petr = User::query()->firstOrCreate(['email' => 'petr.svoboda@example.cz'], ['jmeno' => 'Petr', 'prijmeni' => 'Svoboda']);

        $skupina = OznameniSkupina::query()->firstOrCreate(['nazev' => 'Stálí zákazníci'], ['popis' => 'Objednávají aspoň jednou měsíčně']);
        $skupina->clenove()->syncWithoutDetaching([$jana->id, $petr->id]);

        Oznam::provozni('Objednávka 2026-104 je na cestě')
            ->text("Balík jsme předali **PPL**, doručení čekejte zítra mezi 8:00 a 12:00.\n\nČíslo zásilky: 7012 3456 789.")
            ->odkaz('/kontakt', 'Sledovat zásilku')
            ->komu($jana)
            ->kanaly(KanalOznameni::Centrum)
            ->klic('ukazka-objednavka-104')
            ->posli();

        $nedele = now()->next('Sunday')->setTime(22, 0);
        Oznam::servisni('Plánovaná odstávka v neděli večer')
            ->text('Kvůli aktualizaci serveru bude web asi 30 minut nedostupný. Objednávky odeslané předtím se nezmění.')
            ->vsem()
            ->kanaly(KanalOznameni::Centrum, KanalOznameni::Pruh)
            ->zavaznost(ZavaznostOznameni::Varovani)
            ->odstavka($nedele, $nedele->copy()->addMinutes(30))
            ->pruh(null, $nedele->copy()->addHour())
            ->klic('ukazka-odstavka')
            ->posli();

        Oznameni::query()->firstOrCreate(['titulek' => 'Nově objednávky i přes mobil'], [
            'druh' => 'novinky',
            'text' => "Od příštího měsíce si objednáte i z telefonu.\n\n- přehled objednávek\n- opakování jedním klepnutím",
            'kanaly' => [KanalOznameni::Centrum->value, KanalOznameni::Email->value],
            'cileni' => ['komu' => 'vybrani', 'skupiny' => [$skupina->id]],
        ]);
    }
}
