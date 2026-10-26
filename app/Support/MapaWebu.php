<?php

namespace App\Support;

use App\Enums\StavWebu;
use App\Models\Nastaveni;
use App\Support\Seo\NastaveniSeo;
use App\Support\Seo\Seo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Co web nabízí vyhledávačům: /robots.txt a /sitemap.xml.
 *
 * Indexuje se jen produkce s webem Online. Testovací web (*.test.simren.cz),
 * „Připravujeme“ a „Údržba“ mají v robots.txt `Disallow: /`, hlavičku
 * X-Robots-Tag i meta robots noindex (BezpecnostniHlavicky, Seo – obojí podle
 * indexovat()). Mapa webu = úvod, zapnuté sekce z Obsahu webu a právní stránky
 * s datem poslední změny a obrázkem pro sdílení; projekt doplní svoje stránky
 * (články, produkty…) v polozky().
 *
 * hreflang šablona nemá – web je jen česky. Projekt s víc jazyky ho doplní do
 * Seo (link rel=alternate) i sem (xhtml:link).
 */
class MapaWebu
{
    public static function indexovat(): bool
    {
        return app()->isProduction() && StavWebu::aktualni() === StavWebu::Online;
    }

    public static function robots(): string
    {
        if (! self::indexovat()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $zakazane = ['/admin', '/nastaveni-hesla', '/oznameni'];

        if (config('sablona.doplnky.platby')) {
            $zakazane[] = '/platby/';
        }

        return "User-agent: *\n".implode('', array_map(fn ($cesta) => "Disallow: {$cesta}\n", $zakazane))
            ."\nSitemap: ".url('/sitemap.xml')."\n";
    }

    /**
     * Položky sitemap.xml – absolutní adresa bez kotvy, datum poslední změny
     * (ISO 8601 s posunem) a obrázky stránky.
     *
     * @return list<array{loc: string, lastmod: ?CarbonInterface, obrazky: list<string>}>
     */
    public static function polozky(): array
    {
        $obsahWebu = self::posledniZmena(['zaklad.', 'web.', 'seo.']);
        $polozky = [self::polozka(url('/'), $obsahWebu, 'uvod')];

        foreach (SekceWebu::menu() as $sekce) {
            // Exportex: kontakt je formulář na úvodu (/kontakt jen přesměruje na #kontakt).
            if ($sekce['klic'] === 'formular') {
                continue;
            }

            if (filled($sekce['odkaz']) && ! str_contains($sekce['odkaz'], '#')) {
                $polozky[] = self::polozka(url($sekce['odkaz']), $obsahWebu, $sekce['klic']);
            }
        }

        $polozky[] = self::polozka(route('ochrana-udaju'), self::posledniZmena(['zaklad.', 'gdpr.']), 'ochrana-udaju');

        // Projekt: další stránky webu, např. články blogu:
        // foreach (Clanek::query()->publikovane()->get() as $c) {
        //     $polozky[] = ['loc' => route('blog.clanek', $c->slug), 'lastmod' => $c->updated_at,
        //                   'obrazky' => array_filter([Seo::infoObrazku($c->foto)['url'] ?? null])];
        // }

        return collect($polozky)->unique('loc')->values()->all();
    }

    /**
     * Jen adresy (zpětná kompatibilita).
     *
     * @return list<string>
     */
    public static function adresy(): array
    {
        return array_column(self::polozky(), 'loc');
    }

    /** @return array{loc: string, lastmod: ?CarbonInterface, obrazky: list<string>} */
    private static function polozka(string $adresa, ?CarbonInterface $zmena, string $klicSeo): array
    {
        $obrazek = Seo::infoObrazku(NastaveniSeo::stranka($klicSeo)['obrazek'] ?? null)
            ?? Seo::infoObrazku(NastaveniSeo::get('obrazek'));

        return ['loc' => $adresa, 'lastmod' => $zmena, 'obrazky' => $obrazek ? [$obrazek['url']] : []];
    }

    /** Kdy se naposledy změnilo nastavení s danými předponami (obsah stránek šablony). */
    private static function posledniZmena(array $predpony): ?CarbonInterface
    {
        $cas = rescue(fn () => Nastaveni::query()
            ->where(fn ($q) => collect($predpony)->each(fn ($p) => $q->orWhere('klic', 'like', $p.'%')))
            ->max('updated_at'), null, false);

        return $cas ? Carbon::parse($cas) : null;
    }
}
