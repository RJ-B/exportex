<?php

namespace App\Support\Seo;

use App\Support\Fotky;
use App\Support\MapaWebu;
use App\Support\NastaveniWebu;
use App\Support\ZakladniUdaje;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Hlavička veřejné stránky pro vyhledávače a sociální sítě: <title>, meta
 * description, canonical, robots, Open Graph, Twitter Card a JSON-LD.
 *
 * Do <head> každého layoutu webu (místo vlastního <title>):
 *
 *     {{ \App\Support\Seo\Seo::stranka('Kontakt', klic: 'formular')->popis($popis)->html() }}
 *     {{ \App\Support\Seo\Seo::uvod()->html() }}
 *
 * Co vyhrává: vlastní titulek / popis / obrázek stránky z SEO a měření (nebo
 * ze SeoPole u článku) → co předá stránka (popis z obsahu, fotka) → výchozí
 * popis a obrázek webu → podtitulek a logo. Nic se nevymýšlí – co není
 * vyplněné, ve výstupu chybí.
 */
final class Seo
{
    /** Délky, které Google ukáže celé. */
    public const DELKA_TITULKU = 70;

    public const DELKA_POPISU = 160;

    private ?string $titulek;

    private ?string $klic;

    private bool $uvod = false;

    private ?string $popis = null;

    private ?string $obrazek = null;

    private string $typ = 'website';

    /** @var list<array{0: string, 1: string}> */
    private array $drobecky = [];

    /** @var list<array<string, mixed>> */
    private array $data = [];

    private bool $noindex = false;

    /** Vlastní název webu (SaaS: firma na své adrese) – jinak Základní údaje. */
    private ?string $nazev = null;

    /** @var array<string, mixed>|false|null vlastní uzel provozovatele; null = z údajů webu, false = žádný */
    private array|false|null $subjekt = null;

    /** @var array{titulek: ?string, popis: ?string, obrazek: ?string} */
    private array $vlastni = ['titulek' => null, 'popis' => null, 'obrazek' => null];

    /** @var array<string, array{0: int, 1: int}|null> */
    private static array $rozmery = [];

    private function __construct(?string $titulek, ?string $klic)
    {
        $this->titulek = $titulek;
        $this->klic = $klic;
        $this->vlastni = NastaveniSeo::stranka($klic);
    }

    /**
     * Podstránka webu.
     *
     * @param  string|null  $titulek  název stránky bez názvu webu („Kontakt“)
     * @param  string|null  $klic  klíč stránky v SEO a měření (sekce webu, „ochrana-udaju“…)
     */
    public static function stranka(?string $titulek = null, ?string $klic = null): self
    {
        return new self($titulek, $klic);
    }

    /** Úvodní stránka (bez drobečkové navigace, titulek = název webu). */
    public static function uvod(): self
    {
        $seo = new self(null, 'uvod');
        $seo->uvod = true;

        return $seo;
    }

    /** Popis z obsahu stránky (perex, úvodní odstavec). Zkrátí se na 160 znaků. */
    public function popis(?string $popis): self
    {
        $this->popis = self::text($popis);

        return $this;
    }

    /** Obrázek stránky – cesta na disku public nebo absolutní adresa. */
    public function obrazek(?string $obrazek): self
    {
        $this->obrazek = filled($obrazek) ? $obrazek : null;

        return $this;
    }

    /** og:type – website, article, product… */
    public function typ(string $typ): self
    {
        $this->typ = $typ;

        return $this;
    }

    /**
     * Drobečková navigace mezi úvodem a touto stránkou, např. [['Blog', route('blog')]].
     * Úvod a aktuální stránka se doplní samy.
     *
     * @param  list<array{0: string, 1: string}>  $drobecky
     */
    public function drobecky(array $drobecky): self
    {
        $this->drobecky = array_values($drobecky);

        return $this;
    }

    /** Další uzel JSON-LD (StrukturovanaData::clanek(), ::produkt()…). */
    public function data(array $uzel): self
    {
        $this->data[] = $uzel;

        return $this;
    }

    /** Stránka, která do vyhledávačů nepatří (výsledek platby, potvrzení…). */
    public function noindex(bool $noindex = true): self
    {
        $this->noindex = $noindex;

        return $this;
    }

    /** Název webu, když není ze Základních údajů (SaaS: web firmy na její adrese). */
    public function nazev(?string $nazev): self
    {
        $this->nazev = filled($nazev) ? $nazev : null;

        return $this;
    }

    /**
     * Provozovatel ve strukturovaných datech, když to není provozovatel webu
     * (SaaS: firma na své adrese – StrukturovanaData::organizace()). false = žádný.
     *
     * @param  array<string, mixed>|false  $uzel
     */
    public function subjekt(array|false $uzel): self
    {
        $this->subjekt = $uzel === false ? false : ['@id' => StrukturovanaData::idSubjektu()] + $uzel;

        return $this;
    }

    /**
     * Vlastní SEO obsahu (článek, produkt) uložené přes SeoPole – sloupec
     * `seo` (JSON: titulek, popis, obrazek). Má přednost před stránkou.
     */
    public function zModelu(?Model $model, string $sloupec = 'seo'): self
    {
        $seo = (array) ($model?->getAttribute($sloupec) ?? []);

        foreach (['titulek', 'popis', 'obrazek'] as $pole) {
            if (filled($seo[$pole] ?? null)) {
                $this->vlastni[$pole] = $seo[$pole];
            }
        }

        return $this;
    }

    // ---- Výsledné hodnoty ----

    public function nazevWebu(): string
    {
        return $this->nazev ?? (string) ZakladniUdaje::get('nazev');
    }

    /** <title>: vlastní titulek, jinak „Stránka – Název webu“ (úvod: „Název – podtitulek“). */
    public function titulek(): string
    {
        if (filled($this->vlastni['titulek'])) {
            return $this->vlastni['titulek'];
        }

        $nazev = $this->nazevWebu();

        if ($this->uvod || blank($this->titulek)) {
            $tagline = $this->nazev === null ? NastaveniWebu::get('tagline') : null;

            return $this->uvod && filled($tagline) ? $nazev.' – '.$tagline : $nazev;
        }

        return $this->titulek.' – '.$nazev;
    }

    /** Název stránky bez názvu webu (do drobečků a og:title u podstránek). */
    public function nazevStranky(): string
    {
        return $this->uvod ? $this->nazevWebu() : ($this->titulek ?: $this->titulek());
    }

    public function popisStranky(): ?string
    {
        return self::text($this->vlastni['popis'])
            ?? $this->popis
            ?? self::text(NastaveniWebu::get('meta_popis'))
            ?? self::text(NastaveniWebu::get('tagline'));
    }

    /** Kanonická adresa: doména z APP_URL, bez parametrů a kotvy. */
    public function kanonicka(): string
    {
        $zaklad = self::zaklad();
        $cesta = trim(request()->path(), '/');

        return $cesta === '' ? $zaklad : $zaklad.'/'.$cesta;
    }

    public function robots(): string
    {
        return $this->noindex || ! MapaWebu::indexovat()
            ? 'noindex, nofollow'
            : 'index, follow, max-image-preview:large';
    }

    /**
     * Obrázek pro sdílení s rozměry (og:image:width/height).
     *
     * @return array{url: string, sirka: ?int, vyska: ?int, typ: ?string}|null
     */
    public function obrazekSdileni(): ?array
    {
        $kandidati = [$this->vlastni['obrazek'], $this->obrazek, NastaveniSeo::get('obrazek'), NastaveniWebu::get('logo')];

        foreach ($kandidati as $obrazek) {
            // Sítě (LinkedIn…) WebP nečtou – starší WebP se použije jako JPEG kopie.
            if ($info = self::infoObrazku(Fotky::jpegSdileni($obrazek))) {
                return $info;
            }
        }

        return null;
    }

    /** Uzly JSON-LD pro tuto stránku (@graph). */
    public function strukturovanaData(): array
    {
        $graf = [
            $this->subjekt ?? StrukturovanaData::subjekt(),
            StrukturovanaData::web($this->nazevWebu(), publisher: $this->subjekt !== false),
            StrukturovanaData::stranka($this),
        ];

        if (! $this->uvod) {
            $graf[] = StrukturovanaData::drobecky($this->vsechnyDrobecky(), $this->kanonicka());
        }

        return array_values(array_filter([...$graf, ...$this->data]));
    }

    /**
     * Drobečky včetně úvodu a aktuální stránky.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function vsechnyDrobecky(): array
    {
        if ($this->uvod) {
            return [[__('seo.seo.uvod'), self::zaklad()]];
        }

        return [[__('seo.seo.uvod'), self::zaklad()], ...$this->drobecky, [$this->nazevStranky(), $this->kanonicka()]];
    }

    public function jeUvod(): bool
    {
        return $this->uvod;
    }

    public function typStranky(): string
    {
        return $this->typ;
    }

    public function html(): HtmlString
    {
        return new HtmlString(view('seo.hlavicka', ['seo' => $this])->render());
    }

    /** JSON-LD do <script>: bez `</script>` uvnitř (JSON_HEX_TAG), čitelná čeština. */
    public function jsonLd(): string
    {
        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => $this->strukturovanaData()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT,
        );
    }

    // ---- Pomocné ----

    /** Kořen webu z APP_URL (bez lomítka na konci). */
    public static function zaklad(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /** Text bez HTML a nadbytečných mezer, zkrácený na celé slovo. */
    public static function text(?string $text, int $delka = self::DELKA_POPISU): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $delka) {
            return $text;
        }

        $kratsi = mb_substr($text, 0, $delka - 1);
        $mezera = mb_strrpos($kratsi, ' ');

        return rtrim($mezera > $delka / 2 ? mb_substr($kratsi, 0, $mezera) : $kratsi, ' ,.;:–-').'…';
    }

    /**
     * Absolutní adresa a rozměry obrázku z disku public (nebo adresy).
     *
     * @return array{url: string, sirka: ?int, vyska: ?int, typ: ?string}|null
     */
    public static function infoObrazku(?string $obrazek): ?array
    {
        if (blank($obrazek)) {
            return null;
        }

        if (preg_match('#^https?://#i', $obrazek)) {
            return ['url' => $obrazek, 'sirka' => null, 'vyska' => null, 'typ' => null];
        }

        $disk = Storage::disk('public');

        if (! rescue(fn () => $disk->exists($obrazek), false, false)) {
            return null;
        }

        $rozmery = self::$rozmery[$obrazek] ??= rescue(function () use ($disk, $obrazek) {
            $info = @getimagesize($disk->path($obrazek));

            return $info ? [$info[0], $info[1], $info['mime'] ?? null] : null;
        }, null, false);

        $url = $disk->url($obrazek);

        // Disk public skládá adresu z APP_URL; relativní (bez APP_URL) doplní kořen webu.
        if (! preg_match('#^https?://#i', $url)) {
            $url = self::zaklad().'/'.ltrim($url, '/');
        }

        return ['url' => $url, 'sirka' => $rozmery[0] ?? null, 'vyska' => $rozmery[1] ?? null, 'typ' => $rozmery[2] ?? null];
    }

    /** Pro testy – rozměry obrázků se drží po dobu požadavku. */
    public static function zapomenRozmery(): void
    {
        self::$rozmery = [];
    }

    /** Zkrácený titulek pro náhled (Google ukáže zhruba 60–70 znaků). */
    public static function zkratTitulek(string $titulek): string
    {
        return Str::limit($titulek, self::DELKA_TITULKU, '…');
    }
}
