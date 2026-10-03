<?php

namespace App\Support;

/**
 * Strukturovaná data pro vyhledávače (schema.org, JSON-LD v hlavičce úvodní
 * stránky) – dřív natvrdo v index.html. Firma a kontakty ze Základních údajů,
 * kontaktní osoba z Obsahu webu → Kontakt – texty, sortiment z Obsahu webu →
 * Sortiment, pevná část z config/web.php.
 */
class StrukturovanaData
{
    /** @return array<string, mixed> */
    public static function web(): array
    {
        $u = ZakladniUdaje::nacti();
        $c = config('web.jsonld');
        $url = rtrim((string) config('web.url'), '/').'/';
        $telefon = preg_replace('/[^0-9+]/', '', (string) $u['telefon']) ?: null;
        $osoba = ObsahWebu::sekce('kontakt')['osoba'] ?? null;
        $firma = $u['firma'] ?: $u['nazev'];

        // „Na Poříčí 1070/19, Nové Město, 110 00 Praha 1“ → ulice, PSČ, obec, městská část.
        preg_match('/^([^,]+),.*?(\d{3}\s?\d{2})\s+(.+)$/u', str_replace("\n", ', ', (string) $u['adresa']), $adresa);
        $obec = isset($adresa[3]) ? trim($adresa[3]) : null;

        $organizace = array_filter([
            '@type' => 'Organization',
            '@id' => $url.'#organizace',
            'name' => $firma,
            'url' => $url,
            'logo' => ['@type' => 'ImageObject', 'url' => $url.'assets/img/logo.png', 'width' => 532, 'height' => 120],
            'image' => $url.'assets/img/og.webp',
            'description' => $c['popis'],
            'areaServed' => array_map(fn ($o) => ['@type' => 'Place', 'name' => $o], $c['oblast']),
            'address' => $adresa ? [
                '@type' => 'PostalAddress',
                'streetAddress' => trim($adresa[1]),
                'addressLocality' => $obec ? preg_replace('/\s+\d+$/', '', $obec) : null,
                'postalCode' => $adresa[2],
                'addressRegion' => $obec,
                'addressCountry' => 'CZ',
            ] : null,
            'contactPoint' => [array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'sales',
                'name' => $osoba,
                'email' => $u['email'],
                'telephone' => $telefon,
                'availableLanguage' => ['cs', 'en'],
                'areaServed' => 'EU',
            ])],
            'knowsAbout' => $c['zna'],
            'legalName' => $u['firma'],
            'identifier' => $u['ico'] ? [['@type' => 'PropertyValue', 'name' => 'IČO', 'value' => $u['ico']]] : null,
            'foundingDate' => $c['zalozeno'],
            'email' => $u['email'],
            'telephone' => $telefon,
        ], fn ($v) => $v !== null && $v !== '');

        $sortiment = ObsahWebu::sekce('sortiment');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $organizace,
                [
                    '@type' => 'WebSite',
                    '@id' => $url.'#web',
                    'url' => $url,
                    'name' => $c['nazev_webu'],
                    'inLanguage' => ['cs', 'en'],
                    'publisher' => ['@id' => $url.'#organizace'],
                    'description' => $c['popis_webu'],
                ],
                [
                    '@type' => 'Service',
                    '@id' => $url.'#sluzba',
                    'name' => $c['sluzba'],
                    'serviceType' => $c['typ_sluzby'],
                    'provider' => ['@id' => $url.'#organizace'],
                    'areaServed' => ['@type' => 'Place', 'name' => $c['oblast'][0]],
                    'hasOfferCatalog' => [
                        '@type' => 'OfferCatalog',
                        'name' => 'Sortiment',
                        'itemListElement' => array_map(fn ($p) => [
                            '@type' => 'Offer',
                            'itemOffered' => ['@type' => 'Product', 'name' => $p['nazev'] ?? '', 'description' => $p['popis'] ?? ''],
                        ], array_values((array) $sortiment['produkty'])),
                    ],
                ],
            ],
        ];
    }
}
