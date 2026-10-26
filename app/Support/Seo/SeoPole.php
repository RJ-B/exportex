<?php

namespace App\Support\Seo;

use App\Support\Fotky;
use App\Support\NastaveniWebu;
use App\Support\ZakladniUdaje;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * SEO pole do administrace: vlastní titulek, popis a obrázek pro sdílení
 * (JPEG 1200 × 630 – sítě WebP nečtou) s náhledem, jak stránku uvidí Google a Facebook.
 *
 * Stránky webu je mají v SEO a měření. Obsah projektu (článek, produkt,
 * služba) je dostane do svého formuláře – model má sloupec `seo` (JSON,
 * cast 'array') a stránka pak Seo::stranka($nazev)->zModelu($clanek):
 *
 *     SeoPole::sekce(adresa: fn ($record) => $record ? route('blog.clanek', $record->slug) : null)
 */
final class SeoPole
{
    /** Nahrání obrázku pro sdílení – ořízne na 1200 × 630 a uloží jako JPEG (LinkedIn a další sítě WebP nečtou). */
    public static function obrazek(string $nazev): FileUpload
    {
        return Fotky::poleSdileni(FileUpload::make($nazev))
            ->disk('public')
            ->directory('web/sdileni')
            ->live()
            ->helperText(__('seo.seo_pole.orizne_se_na_1200_630_px'));
    }

    /**
     * Titulek, popis, obrázek a náhled pod jednou cestou stavu (např. `stranky.uvod`, `seo`).
     *
     * @return list<Component>
     */
    public static function pole(string $cesta, string|\Closure|null $adresa = null, string|\Closure|null $nazev = null, bool $uvod = false): array
    {
        return [
            TextInput::make($cesta.'.titulek')->label(__('seo.seo_pole.titulek_pro_vyhledavace'))->maxLength(Seo::DELKA_TITULKU)->live(onBlur: true)
                ->placeholder(fn ($component) => self::vychoziTitulek($component->evaluate($nazev), $uvod))
                ->helperText(__('seo.seo_pole.do_70_znaku_prazdne_nazev_stranky')),
            Textarea::make($cesta.'.popis')->label(__('seo.seo_pole.popis_pro_vyhledavace'))->rows(3)->maxLength(Seo::DELKA_POPISU)->live(onBlur: true)
                ->placeholder(fn () => NastaveniWebu::get('meta_popis'))
                ->helperText(__('seo.seo_pole.do_160_znaku_prazdne_vychozi_popis')),
            self::obrazek($cesta.'.obrazek')->label(__('seo.seo_pole.obrazek_pro_sdileni'))->columnSpanFull(),
            Html::make(fn ($get, $component) => self::nahled(
                $component->evaluate($adresa),
                $get($cesta.'.titulek') ?: self::vychoziTitulek($component->evaluate($nazev), $uvod),
                $get($cesta.'.popis'),
                $get($cesta.'.obrazek'),
                $uvod,
            ))->columnSpanFull(),
        ];
    }

    /** Sbalitelná sekce „Vyhledávače a sdílení“ do formuláře obsahu (sloupec `seo`). */
    public static function sekce(string|\Closure|null $adresa = null, string $sloupec = 'seo', string $poleNazvu = 'nazev'): Section
    {
        return Section::make(__('seo.seo_pole.vyhledavace_a_sdileni'))
            ->description(__('seo.seo_pole.nepovinne_jinak_nazev_perex_a_fotka'))
            ->collapsible()->collapsed()
            ->columns(2)
            ->schema(self::pole($sloupec, $adresa, fn ($get) => $get($poleNazvu)));
    }

    /** Náhled výsledku v Googlu a sdílení na Facebooku (live v administraci). */
    public static function nahled(?string $adresa, ?string $titulek, ?string $popis, mixed $obrazek, bool $uvod = false): Htmlable
    {
        $zaklad = Seo::zaklad();
        $adresa = $adresa ? (str_starts_with($adresa, 'http') ? $adresa : $zaklad.'/'.ltrim($adresa, '/')) : $zaklad;
        $popis = Seo::text($popis) ?? Seo::text(NastaveniWebu::get('meta_popis')) ?? Seo::text(NastaveniWebu::get('tagline'));

        return view('filament.seo.nahled', [
            'adresa' => rtrim($adresa, '/'),
            'domena' => (string) parse_url($zaklad, PHP_URL_HOST),
            'titulek' => Seo::zkratTitulek($titulek ?: self::vychoziTitulek(null, $uvod)),
            'popis' => $popis,
            'obrazek' => self::adresaObrazku($obrazek) ?? self::adresaObrazku(NastaveniSeo::get('obrazek')) ?? self::adresaObrazku(NastaveniWebu::get('logo')),
            'nazevWebu' => ZakladniUdaje::get('nazev'),
        ]);
    }

    private static function vychoziTitulek(?string $nazev, bool $uvod): string
    {
        $web = (string) ZakladniUdaje::get('nazev');

        if ($uvod) {
            $tagline = NastaveniWebu::get('tagline');

            return filled($tagline) ? $web.' – '.$tagline : $web;
        }

        return filled($nazev) ? $nazev.' – '.$web : $web;
    }

    /** Uložená cesta, nebo právě nahraný (ještě neuložený) soubor. */
    private static function adresaObrazku(mixed $obrazek): ?string
    {
        if (is_array($obrazek)) {
            $obrazek = array_values($obrazek)[0] ?? null;
        }

        try {
            if ($obrazek instanceof TemporaryUploadedFile) {
                return $obrazek->isPreviewable() ? $obrazek->temporaryUrl() : null;
            }

            if (is_string($obrazek) && $obrazek !== '' && Storage::disk('public')->exists($obrazek)) {
                return Storage::disk('public')->url($obrazek);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
