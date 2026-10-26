<?php

namespace App\Support\Seo;

use App\Models\Nastaveni;
use App\Support\SekceWebu;
use Illuminate\Support\Facades\Route;

/**
 * Nastavení SEO a sdílení (Obsah webu → SEO a měření): výchozí obrázek pro
 * sdílení, kdo web provozuje (organizace / místní firma) pro strukturovaná
 * data a vlastní titulek, popis a obrázek jednotlivých stránek.
 *
 * Výchozí popis webu je dál `web.meta_popis` (NastaveniWebu); název, adresa,
 * IČO, kontakty, logo a sociální sítě se berou z Hlavičky a patičky – tady se
 * nic z toho neopakuje.
 */
class NastaveniSeo
{
    public const SUBJEKT_ORGANIZACE = 'organizace';

    public const SUBJEKT_MISTNI_FIRMA = 'mistni_firma';

    /**
     * Druhy místní firmy (podtypy schema.org/LocalBusiness), které Google zná.
     * Klíč jde do JSON-LD jako @type, hodnota je popisek v administraci.
     */
    /** @return array<string, string> */
    public static function kategorie(): array
    {
        return [
            'LocalBusiness' => __('seo.nastaveni_seo.jina_mistni_firma'),
            'Store' => __('seo.nastaveni_seo.obchod'),
            'GroceryStore' => __('seo.nastaveni_seo.potraviny'),
            'ClothingStore' => __('seo.nastaveni_seo.obleceni'),
            'FurnitureStore' => __('seo.nastaveni_seo.nabytek'),
            'HardwareStore' => __('seo.nastaveni_seo.zelezarstvi'),
            'ComputerStore' => __('seo.nastaveni_seo.pocitace_a_elektronika'),
            'Florist' => __('seo.nastaveni_seo.kvetinarstvi'),
            'PetStore' => __('seo.nastaveni_seo.chovatelske_potreby'),
            'Restaurant' => __('seo.nastaveni_seo.restaurace'),
            'CafeOrCoffeeShop' => __('seo.nastaveni_seo.kavarna'),
            'BarOrPub' => __('seo.nastaveni_seo.bar_a_hospoda'),
            'Bakery' => __('seo.nastaveni_seo.pekarna'),
            'HealthClub' => __('seo.nastaveni_seo.fitness_centrum'),
            'SportsActivityLocation' => __('seo.nastaveni_seo.sportoviste'),
            'BeautySalon' => __('seo.nastaveni_seo.kosmeticky_salon'),
            'HairSalon' => __('seo.nastaveni_seo.kadernictvi'),
            'DaySpa' => __('seo.nastaveni_seo.wellness'),
            'AutoRepair' => __('seo.nastaveni_seo.autoservis'),
            'AutoDealer' => __('seo.nastaveni_seo.prodej_aut'),
            'GeneralContractor' => __('seo.nastaveni_seo.stavebni_firma'),
            'HomeAndConstructionBusiness' => __('seo.nastaveni_seo.remeslne_sluzby'),
            'Electrician' => __('seo.nastaveni_seo.elektrikar'),
            'Plumber' => __('seo.nastaveni_seo.instalater'),
            'HVACBusiness' => __('seo.nastaveni_seo.topeni_a_klimatizace'),
            'RoofingContractor' => __('seo.nastaveni_seo.pokryvac'),
            'HousePainter' => __('seo.nastaveni_seo.malir_a_naterac'),
            'Locksmith' => __('seo.nastaveni_seo.zamecnik'),
            'MovingCompany' => __('seo.nastaveni_seo.stehovani'),
            'ProfessionalService' => __('seo.nastaveni_seo.odborne_sluzby'),
            'AccountingService' => __('seo.nastaveni_seo.ucetnictvi'),
            'LegalService' => __('seo.nastaveni_seo.pravni_sluzby'),
            'Attorney' => __('seo.nastaveni_seo.advokat'),
            'FinancialService' => __('seo.nastaveni_seo.financni_sluzby'),
            'InsuranceAgency' => __('seo.nastaveni_seo.pojistovna'),
            'RealEstateAgent' => __('seo.nastaveni_seo.realitni_kancelar'),
            'EmploymentAgency' => __('seo.nastaveni_seo.personalni_agentura'),
            'TravelAgency' => __('seo.nastaveni_seo.cestovni_kancelar'),
            'MedicalBusiness' => __('seo.nastaveni_seo.zdravotnicke_sluzby'),
            'Dentist' => __('seo.nastaveni_seo.zubar'),
            'Physician' => __('seo.nastaveni_seo.lekar'),
            'Optician' => __('seo.nastaveni_seo.optika'),
            'VeterinaryCare' => __('seo.nastaveni_seo.veterina'),
            'ChildCare' => __('seo.nastaveni_seo.pece_o_deti'),
            'LodgingBusiness' => __('seo.nastaveni_seo.ubytovani'),
            'Hotel' => __('seo.nastaveni_seo.hotel'),
            'EntertainmentBusiness' => __('seo.nastaveni_seo.zabava'),
        ];
    }

    /** Dny pro otevírací dobu: hodnota schema.org => popisek. */
    /** @return array<string, string> */
    public static function dny(): array
    {
        return [
            'Monday' => __('seo.nastaveni_seo.pondeli'),
            'Tuesday' => __('seo.nastaveni_seo.utery'),
            'Wednesday' => __('seo.nastaveni_seo.streda'),
            'Thursday' => __('seo.nastaveni_seo.ctvrtek'),
            'Friday' => __('seo.nastaveni_seo.patek'),
            'Saturday' => __('seo.nastaveni_seo.sobota'),
            'Sunday' => __('seo.nastaveni_seo.nedele'),
        ];
    }

    /** Pole v Nastaveni (seo.<pole>); JSON jsou otevírací doba a stránky. */
    public const POLE = ['obrazek', 'subjekt', 'kategorie', 'oteviraci_doba', 'geo_sirka', 'geo_delka', 'stranky'];

    private const JSON = ['oteviraci_doba', 'stranky'];

    /** @return array<string, mixed> */
    public static function nacti(): array
    {
        $hodnoty = [];

        foreach (self::POLE as $pole) {
            $hodnota = rescue(fn () => self::cti($pole), null, false);
            $hodnoty[$pole] = in_array($pole, self::JSON, true) ? (array) json_decode((string) $hodnota, true) : $hodnota;
        }

        $hodnoty['subjekt'] = $hodnoty['subjekt'] ?: self::SUBJEKT_ORGANIZACE;

        return $hodnoty;
    }

    public static function get(string $pole): mixed
    {
        return self::nacti()[$pole] ?? null;
    }

    public static function uloz(array $data): void
    {
        foreach (self::POLE as $pole) {
            if (! array_key_exists($pole, $data)) {
                continue;
            }

            $hodnota = $data[$pole];

            if (in_array($pole, self::JSON, true)) {
                $hodnota = $pole === 'stranky' ? self::cisteStranky((array) $hodnota) : self::cistaOteviraciDoba((array) $hodnota);
                $hodnota = $hodnota === [] ? null : json_encode($hodnota, JSON_UNESCAPED_UNICODE);
            } else {
                $hodnota = trim((string) $hodnota);
                $hodnota = $hodnota === '' ? null : $hodnota;
            }

            self::zapis($pole, $hodnota);
        }
    }

    /**
     * Vlastní titulek, popis a obrázek stránky (prázdné = výchozí).
     *
     * @return array{titulek: ?string, popis: ?string, obrazek: ?string}
     */
    public static function stranka(?string $klic): array
    {
        $ulozeno = $klic ? ((array) (self::get('stranky')[$klic] ?? [])) : [];

        return [
            'titulek' => filled($ulozeno['titulek'] ?? null) ? $ulozeno['titulek'] : null,
            'popis' => filled($ulozeno['popis'] ?? null) ? $ulozeno['popis'] : null,
            'obrazek' => filled($ulozeno['obrazek'] ?? null) ? $ulozeno['obrazek'] : null,
        ];
    }

    /**
     * Stránky webu, kterým jde v SEO a měření nastavit vlastní titulek a popis:
     * úvod, stránky sekcí webu (Obsah webu, i vypnuté – správce je připraví
     * dopředu) a právní stránky. Projekt se stránkami mimo sekce (články…)
     * dává SEO přímo do jejich formuláře (SeoPole).
     *
     * @return array<string, array{nazev: string, adresa: string}> klíč => název a adresa
     */
    public static function stranky(): array
    {
        $stranky = ['uvod' => ['nazev' => __('seo.nastaveni_seo.uvod'), 'adresa' => '/']];

        foreach (SekceWebu::stranky() as $klic => $trida) {
            $odkaz = rescue(fn () => $trida::odkazSekce(), null, false);

            if (filled($odkaz) && ! str_contains($odkaz, '#')) {
                $stranky[$klic] = ['nazev' => $trida::nazevSekce(), 'adresa' => $odkaz];
            }
        }

        if (Route::has('ochrana-udaju')) {
            $stranky['ochrana-udaju'] = ['nazev' => __('seo.nastaveni_seo.ochrana_osobnich_udaju'), 'adresa' => route('ochrana-udaju', absolute: false)];
        }

        if (Route::has('cookies')) {
            $stranky['cookies'] = ['nazev' => __('seo.nastaveni_seo.zasady_pouzivani_cookies'), 'adresa' => route('cookies', absolute: false)];
        }

        return $stranky;
    }

    /** Je zvolená místní firma (LocalBusiness) místo organizace? */
    public static function mistniFirma(): bool
    {
        return self::get('subjekt') === self::SUBJEKT_MISTNI_FIRMA;
    }

    /** @return array<string, array{titulek: ?string, popis: ?string, obrazek: ?string}> */
    private static function cisteStranky(array $stranky): array
    {
        $ciste = [];

        foreach ($stranky as $klic => $udaje) {
            $udaje = array_map(fn ($h) => is_string($h) ? (trim($h) === '' ? null : trim($h)) : (is_array($h) ? (array_values($h)[0] ?? null) : $h), (array) $udaje);
            $udaje = array_filter(array_intersect_key($udaje, array_flip(['titulek', 'popis', 'obrazek'])), 'filled');

            if ($udaje !== []) {
                $ciste[(string) $klic] = $udaje;
            }
        }

        return $ciste;
    }

    /** @return list<array{dny: list<string>, od: string, do: string}> */
    private static function cistaOteviraciDoba(array $radky): array
    {
        $ciste = [];

        foreach (array_values($radky) as $radek) {
            $dny = array_values(array_intersect(array_keys(self::dny()), (array) ($radek['dny'] ?? [])));
            $od = trim((string) ($radek['od'] ?? ''));
            $do = trim((string) ($radek['do'] ?? ''));

            if ($dny !== [] && preg_match('/^\d{1,2}:\d{2}$/', $od) && preg_match('/^\d{1,2}:\d{2}$/', $do)) {
                $ciste[] = ['dny' => $dny, 'od' => self::cas($od), 'do' => self::cas($do)];
            }
        }

        return $ciste;
    }

    private static function cas(string $cas): string
    {
        [$h, $m] = explode(':', $cas);

        return sprintf('%02d:%02d', (int) $h, (int) $m);
    }

    // ---- Úložiště. Projekt s vlastním modelem nastavení mění jen tyhle dvě metody. ----

    private static function cti(string $pole): ?string
    {
        return Nastaveni::hodnota('seo.'.$pole);
    }

    private static function zapis(string $pole, ?string $hodnota): void
    {
        Nastaveni::nastav('seo.'.$pole, $hodnota);
    }
}
