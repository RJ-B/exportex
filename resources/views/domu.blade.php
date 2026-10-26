{{-- Úvodní stránka (dřív index.html). Úvod je jádro webu, další sekce se skládají
     v pořadí a podle zapnutí z Obsahu webu → Hlavička a patička → Sekce na webu;
     čísla 01, 02… a střídání pozadí se počítají podle pořadí. --}}
@extends('layouts.web')
@use('App\Support\Preklad')
@use('App\Support\ObsahWebu')

@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $web = rtrim(config('web.url'), '/');
    $uvod = ObsahWebu::sekce('uvod');
@endphp

@section('hlava')
<link rel="alternate" hreflang="cs" href="{{ $web }}/">
<link rel="alternate" hreflang="en" href="{{ $web }}/?lang=en">
<link rel="alternate" hreflang="x-default" href="{{ $web }}/">
<meta property="og:locale:alternate" content="en_GB">
@endsection

@push('preload')
<link rel="preload" as="image" href="{{ ObsahWebu::obrazek($uvod['foto']) }}" fetchpriority="high">
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
{{-- ===== Modal (detail sortimentu) ===== --}}
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
  <div class="modal__bg" data-close></div>
  <div class="modal__box" id="modalBox">
    <div class="modal__head">
      <h3 id="modalTitle"></h3>
      <button type="button" class="modal__close" data-close data-cs="{{ __('web.domu.zavrit') }}" data-en="{{ __('web.domu.zavrit', [], 'en') }}">{{ __('web.domu.zavrit') }}</button>
    </div>
    <div class="modal__media"><img id="modalImg" alt=""></div>
    <div class="modal__body">
      <div class="modal__tag" id="modalTag"></div>
      <ul class="points" id="modalPoints"></ul>
      <div class="specs" id="modalSpecs"></div>
      <div class="terms" id="modalTerms"></div>
      @sekce('formular')
      <div style="margin-top:20px">
        <a href="#kontakt" class="btn btn--primary btn--lg btn--block" id="modalCta" data-close>{{ __('web.domu.poptat_tento_sortiment') }}</a>
      </div>
      @endsekce
    </div>
  </div>
</div>

{{-- Obsah pro skript (sortiment, kroky, trasa, doklady, ukázky, popisky formuláře) –
     JSON se nespouští, takže CSP nemusí povolovat vložené skripty. --}}
<script type="application/json" id="exportex-data">{!! json_encode(ObsahWebu::proSkript(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush
