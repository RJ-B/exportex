{{-- Ochrana osobních údajů (dřív soukromi.html). Česky je to vzor šablony Sim&Ren
     (pravni/_ochrana-obsah), který se skládá sám ze Základních údajů a z toho, co web
     dělá; údaje k doplnění v Obsahu webu → Ochrana osobních údajů. Anglicky je
     souhrn se stejnými údaji (pravni/_ochrana-en) – závazné je české znění. --}}
@extends('layouts.web')

@php
    $u = \App\Support\ZakladniUdaje::nacti();
@endphp

@section('titulek', 'Ochrana osobních údajů — '.$u['nazev'])
@section('popis', 'Jak '.($u['firma'] ?: $u['nazev']).' zpracovává osobní údaje z poptávek a komunikace a jaká máte práva.')

@section('obsah')
<main id="obsah" class="section section--base">
  <div class="wrap doc">
    <div class="eyebrow"><span class="num">§</span><span class="lbl" data-cs="Soukromí" data-en="Privacy">Soukromí</span></div>
    <h1 class="h2" data-cs="Ochrana osobních údajů" data-en="Privacy notice">Ochrana osobních údajů</h1>

    <div data-jazyk="cs">
      @include('pravni._ochrana-obsah')
    </div>
    <div data-jazyk="en" lang="en" hidden>
      @include('pravni._ochrana-en')
    </div>
  </div>
</main>
@endsection
