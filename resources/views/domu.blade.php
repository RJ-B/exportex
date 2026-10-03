{{-- Úvodní stránka (dřív index.html). Úvod je jádro webu, další sekce se skládají
     v pořadí a podle zapnutí z Obsahu webu → Hlavička a patička → Sekce na webu;
     čísla 01, 02… a střídání pozadí se počítají podle pořadí. --}}
@extends('layouts.web')
@use('App\Support\Preklad')
@use('App\Support\ObsahWebu')

@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $tagline = \App\Support\NastaveniWebu::get('tagline');
    $titulek = $u['nazev'].($tagline ? ' — '.$tagline : '');
    $web = rtrim(config('web.url'), '/');
    $uvod = ObsahWebu::sekce('uvod');
@endphp

@section('titulek', $titulek)
@section('popis', \App\Support\NastaveniWebu::get('meta_popis') ?: config('web.popis'))
@section('robots', 'index, follow, max-image-preview:large, max-snippet:-1')

@section('hlava')
<link rel="canonical" href="{{ $web }}/">
<link rel="alternate" hreflang="cs" href="{{ $web }}/">
<link rel="alternate" hreflang="en" href="{{ $web }}/?lang=en">
<link rel="alternate" hreflang="x-default" href="{{ $web }}/">

<!-- Open Graph / Twitter -->
<meta property="og:type" content="website">
<meta property="og:locale" content="cs_CZ">
<meta property="og:locale:alternate" content="en_GB">
<meta property="og:site_name" content="{{ $u['nazev'] }}">
<meta property="og:url" content="{{ $web }}/">
<meta property="og:title" content="{{ $titulek }}">
<meta property="og:description" content="{{ config('web.og_popis') }}">
<meta property="og:image" content="{{ $web }}/assets/img/og.webp">
<meta property="og:image:type" content="image/webp">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ config('web.og_obrazek_popis') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $titulek }}">
<meta name="twitter:description" content="{{ config('web.twitter_popis') }}">
<meta name="twitter:image" content="{{ $web }}/assets/img/og.webp">
@endsection

@push('preload')
<link rel="preload" as="image" href="{{ ObsahWebu::obrazek($uvod['foto']) }}" fetchpriority="high">
@endpush

@push('jsonld')
<!-- Strukturovaná data pro vyhledávače -->
<script type="application/ld+json">{!! json_encode(\App\Support\StrukturovanaData::web(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT) !!}</script>
@endpush

@section('obsah')
<main id="obsah">

@include('web.sekce.uvod', ['s' => $uvod])

@foreach (ObsahWebu::sekceNaWebu() as $i => $sekce)
@include('web.sekce.'.$sekce['klic'], [
    's' => ObsahWebu::sekce(ObsahWebu::SEKCE[$sekce['klic']]['obsah']),
    'cislo' => $sekce['cislo'],
    'tlo' => $i % 2 === 0 ? 'section--alt' : 'section--base',
])
@endforeach

</main>
@endsection

@push('konec')
<!-- ===== Modal (detail sortimentu) ===== -->
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
  <div class="modal__bg" data-close></div>
  <div class="modal__box" id="modalBox">
    <div class="modal__head">
      <h3 id="modalTitle"></h3>
      <button type="button" class="modal__close" data-close data-cs="Zavřít" data-en="Close">Zavřít</button>
    </div>
    <div class="modal__media"><img id="modalImg" alt=""></div>
    <div class="modal__body">
      <div class="modal__tag" id="modalTag"></div>
      <ul class="points" id="modalPoints"></ul>
      <div class="specs" id="modalSpecs"></div>
      <div class="terms" id="modalTerms"></div>
      @sekce('formular')
      <div style="margin-top:20px">
        <a href="#kontakt" class="btn btn--primary btn--lg btn--block" id="modalCta" data-close>Poptat tento sortiment</a>
      </div>
      @endsekce
    </div>
  </div>
</div>

{{-- Obsah pro skript (sortiment, kroky, trasa, doklady, ukázky, popisky formuláře) –
     JSON se nespouští, takže CSP nemusí povolovat vložené skripty. --}}
<script type="application/json" id="exportex-data">{!! json_encode(ObsahWebu::proSkript(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush
