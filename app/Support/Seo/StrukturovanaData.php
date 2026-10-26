<?php

namespace App\Support\Seo;

use App\Support\NastaveniWebu;
use App\Support\ZakladniUdaje;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;

/**
 * Strukturovaná data (JSON-LD, schema.org) – co Google ukáže ve výsledcích:
 * kdo web provozuje (Organization / LocalBusiness), web (WebSite), stránka
 * (WebPage), drobečková navigace (BreadcrumbList), článek (Article) a produkt
 * s nabídkou (Product + Offer).
 *
 * Všechno z údajů, které web už má (Hlavička a patička, SEO a měření, obsah
 * stránky). Co vyplněné není, v datech chybí – nic se nedoplňuje odhadem.
 * Uzly se odkazují přes @id (#organizace, #web), takže stačí jeden <script>.
 */
final class StrukturovanaData
{
    public static function idSubjektu(): string
    {
        return Seo::zaklad().'/#organizace';
    }

    public static function idWebu(): string
    {
        return Seo::zaklad().'/#web';
    }

    /**
     * Provozovatel: Organization, nebo LocalBusiness (podtyp podle kategorie),
     * když je v SEO a měření zvolená místní firma. Místní firma bez adresy
     * zůstane organizací – Google adresu u LocalBusiness vyžaduje.
     */
    public static function subjekt(): array
    {
        $udaje = ZakladniUdaje::nacti();
        $web = NastaveniWebu::nacti();
        $seo = NastaveniSeo::nacti();

        $mistni = $seo['subjekt'] === NastaveniSeo::SUBJEKT_MISTNI_FIRMA;
        $adresa = self::adresa($mistni ? ($udaje['provozovna'] ?: $udaje['adresa']) : ($udaje['adresa'] ?: $udaje['provozovna']));
        $mistni = $mistni && $adresa !== null;

        $typ = $mistni ? (array_key_exists((string) $seo['kategorie'], NastaveniSeo::kategorie()) ? $seo['kategorie'] : 'LocalBusiness') : 'Organization';
        $logo = Seo::infoObrazku($web['logo'] ?? null);
        $obrazek = Seo::infoObrazku($seo['obrazek'] ?? null) ?? $logo;

        $uzel = [
            '@type' => $typ,
            '@id' => self::idSubjektu(),
            'name' => $udaje['nazev'],
            'legalName' => filled($udaje['firma']) && $udaje['firma'] !== $udaje['nazev'] ? $udaje['firma'] : null,
            'url' => Seo::zaklad(),
            'logo' => $logo ? self::obrazek($logo) : null,
            'image' => $mistni && $obrazek ? $obrazek['url'] : null,
            'description' => Seo::text($web['meta_popis'] ?? null) ?? Seo::text($web['tagline'] ?? null),
            'email' => $udaje['email'] ?: null,
            'telephone' => $udaje['telefon'] ?: null,
            'address' => $adresa,
            'vatID' => $udaje['dic'] ?: null,
            'identifier' => filled($udaje['ico']) ? ['@type' => 'PropertyValue', 'propertyID' => __('seo.strukturovana_data.ico'), 'value' => $udaje['ico']] : null,
            'sameAs' => array_values(array_filter(array_map(fn ($sit) => $web[$sit] ?? null, ['facebook', 'instagram', 'linkedin', 'youtube', 'tiktok']))) ?: null,
        ];

        if ($mistni) {
            $uzel['openingHoursSpecification'] = array_map(fn (array $r) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $r['dny'],
                'opens' => $r['od'],
                'closes' => $r['do'],
            ], (array) $seo['oteviraci_doba']) ?: null;

            if (is_numeric($seo['geo_sirka'] ?? null) && is_numeric($seo['geo_delka'] ?? null)) {
                $uzel['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $seo['geo_sirka'], 'longitude' => (float) $seo['geo_delka']];
            }
        }

        return self::bezPrazdnych($uzel);
    }

    /** Web; s vyhledáváním (config sablona.vyhledavani) i SearchAction. */
    public static function web(?string $nazev = null, bool $publisher = true): array
    {
        $uzel = array_filter([
            '@type' => 'WebSite',
            '@id' => self::idWebu(),
            'url' => Seo::zaklad(),
            'name' => $nazev ?? ZakladniUdaje::get('nazev'),
            'inLanguage' => 'cs-CZ',
            'publisher' => $publisher ? ['@id' => self::idSubjektu()] : null,
        ]);

        $vyhledavani = (array) config('sablona.vyhledavani');

        if (filled($vyhledavani['routa'] ?? null) && Route::has($vyhledavani['routa'])) {
            $parametr = $vyhledavani['parametr'] ?? 'q';
            $uzel['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route($vyhledavani['routa']).'?'.$parametr.'={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $uzel;
    }

    public static function stranka(Seo $seo): array
    {
        $obrazek = $seo->obrazekSdileni();

        return self::bezPrazdnych([
            '@type' => 'WebPage',
            '@id' => $seo->kanonicka().'#stranka',
            'url' => $seo->kanonicka(),
            'name' => $seo->titulek(),
            'description' => $seo->popisStranky(),
            'inLanguage' => 'cs-CZ',
            'isPartOf' => ['@id' => self::idWebu()],
            'about' => $seo->jeUvod() ? ['@id' => self::idSubjektu()] : null,
            'primaryImageOfPage' => $obrazek ? self::obrazek($obrazek) : null,
            'breadcrumb' => $seo->jeUvod() ? null : ['@id' => $seo->kanonicka().'#drobecky'],
        ]);
    }

    /** @param list<array{0: string, 1: string}> $drobecky */
    public static function drobecky(array $drobecky, string $kanonicka): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $kanonicka.'#drobecky',
            'itemListElement' => array_map(fn (array $d, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $d[0],
                'item' => $d[1],
            ], $drobecky, array_keys($drobecky)),
        ];
    }

    /**
     * Provozovatel z jiných údajů než Základních (SaaS: firma na své adrese).
     * Prázdné hodnoty se vynechají.
     */
    public static function organizace(string $nazev, ?string $email = null, ?string $telefon = null, ?string $ico = null, ?string $adresa = null, ?string $logo = null): array
    {
        $logo = Seo::infoObrazku($logo);

        return self::bezPrazdnych([
            '@type' => 'Organization',
            '@id' => self::idSubjektu(),
            'name' => $nazev,
            'url' => Seo::zaklad(),
            'logo' => $logo ? self::obrazek($logo) : null,
            'email' => $email,
            'telephone' => $telefon,
            'address' => self::adresa($adresa),
            'identifier' => filled($ico) ? ['@type' => 'PropertyValue', 'propertyID' => __('seo.strukturovana_data.ico'), 'value' => $ico] : null,
        ]);
    }

    /**
     * Článek / novinka (projekt s blogem). Povinné: titulek a datum vydání.
     *
     *     Seo::stranka($clanek->nazev)->typ('article')->data(StrukturovanaData::clanek(
     *         titulek: $clanek->nazev, vydano: $clanek->vydano_at, upraveno: $clanek->updated_at,
     *         obrazek: $clanek->foto, autor: $clanek->autor?->celeJmeno(), popis: $clanek->perex,
     *     ))
     */
    public static function clanek(
        string $titulek,
        CarbonInterface $vydano,
        ?CarbonInterface $upraveno = null,
        ?string $obrazek = null,
        ?string $autor = null,
        ?string $popis = null,
        ?string $adresa = null,
    ): array {
        $adresa ??= Seo::stranka()->kanonicka();
        $foto = Seo::infoObrazku($obrazek);

        return self::bezPrazdnych([
            '@type' => 'Article',
            '@id' => $adresa.'#clanek',
            'headline' => Seo::text($titulek, 110),
            'description' => Seo::text($popis),
            'image' => $foto ? [$foto['url']] : null,
            'datePublished' => $vydano->toIso8601String(),
            'dateModified' => ($upraveno ?? $vydano)->toIso8601String(),
            'author' => filled($autor) ? ['@type' => 'Person', 'name' => $autor] : ['@id' => self::idSubjektu()],
            'publisher' => ['@id' => self::idSubjektu()],
            'mainEntityOfPage' => ['@id' => $adresa.'#stranka'],
            'inLanguage' => 'cs-CZ',
        ]);
    }

    /**
     * Produkt s cenou (e-shop, ceník). Cena v Kč s DPH tak, jak ji vidí zákazník.
     *
     * @param  string  $dostupnost  InStock | OutOfStock | PreOrder | …
     */
    public static function produkt(
        string $nazev,
        string|int|float $cena,
        ?string $popis = null,
        ?string $obrazek = null,
        ?string $kod = null,
        string $mena = 'CZK',
        string $dostupnost = 'InStock',
        ?string $adresa = null,
        ?string $znacka = null,
    ): array {
        $adresa ??= Seo::stranka()->kanonicka();
        $foto = Seo::infoObrazku($obrazek);

        return self::bezPrazdnych([
            '@type' => 'Product',
            '@id' => $adresa.'#produkt',
            'name' => $nazev,
            'description' => Seo::text($popis, 5000),
            'image' => $foto ? [$foto['url']] : null,
            'sku' => $kod,
            'brand' => filled($znacka) ? ['@type' => 'Brand', 'name' => $znacka] : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => $adresa,
                'price' => number_format((float) $cena, 2, '.', ''),
                'priceCurrency' => $mena,
                'availability' => 'https://schema.org/'.$dostupnost,
                'seller' => ['@id' => self::idSubjektu()],
            ],
        ]);
    }

    /**
     * Volný text adresy („Dlouhá 12\n110 00 Praha 1“) → PostalAddress. Když
     * nejde poznat PSČ a obec, zůstane adresa textem – neodhaduje se.
     */
    public static function adresa(?string $text): array|string|null
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $casti = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $text)), 'filled'));
        $zeme = 'CZ';

        if ($casti !== [] && preg_match('/^(Česká republika|Česko|CZ|Czech Republic|Slovensko|Slovenská republika|SK)$/iu', end($casti))) {
            $zeme = preg_match('/^(Slovensko|Slovenská republika|SK)$/iu', array_pop($casti)) ? 'SK' : 'CZ';
        }

        foreach ($casti as $i => $cast) {
            if (preg_match('/^(\d{3}\s?\d{2})\s+(.+)$/u', $cast, $m)) {
                $ulice = implode(', ', array_slice($casti, 0, $i));

                return self::bezPrazdnych([
                    '@type' => 'PostalAddress',
                    'streetAddress' => $ulice !== '' ? $ulice : null,
                    'postalCode' => str_replace(' ', '', $m[1]),
                    'addressLocality' => $m[2],
                    'addressCountry' => $zeme,
                ]);
            }
        }

        return $text;
    }

    /** @param array{url: string, sirka: ?int, vyska: ?int} $obrazek */
    private static function obrazek(array $obrazek): array
    {
        return self::bezPrazdnych([
            '@type' => 'ImageObject',
            'url' => $obrazek['url'],
            'width' => $obrazek['sirka'],
            'height' => $obrazek['vyska'],
        ]);
    }

    private static function bezPrazdnych(array $uzel): array
    {
        return array_filter($uzel, fn ($h) => $h !== null && $h !== '' && $h !== []);
    }
}
