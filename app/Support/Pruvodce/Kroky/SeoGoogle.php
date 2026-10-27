<?php

namespace App\Support\Pruvodce\Kroky;

use App\Filament\Pages\SeoMereni;
use App\Support\NastaveniWebu;
use App\Support\Pruvodce\Krok;

/** Popis webu pro Google a sdílení (SEO a měření). */
class SeoGoogle extends Krok
{
    public function nazev(): string
    {
        return __('pruvodce.kroky.seo_google');
    }

    public function popis(): string
    {
        return __('pruvodce.kroky.seo_google_popis');
    }

    public function url(): ?string
    {
        return SeoMereni::getUrl();
    }

    public function hotovo(): bool
    {
        return filled(NastaveniWebu::get('meta_popis'));
    }
}
