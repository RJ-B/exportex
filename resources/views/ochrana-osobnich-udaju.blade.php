{{-- Ochrana osobních údajů (dřív soukromi.html). Česky je to vzor šablony Sim&Ren
     (pravni/_ochrana-obsah), který se skládá sám ze Základních údajů a z toho, co web
     dělá; údaje k doplnění v Obsahu webu → Ochrana osobních údajů. Anglicky je
     souhrn se stejnými údaji (pravni/_ochrana-en) – závazné je české znění. --}}
@extends('layouts.web')

@php
    $u = \App\Support\ZakladniUdaje::nacti();
@endphp

@section('titulek', __('web.ochrana_osobnich_udaju.ochrana_osobnich_udaju'))
@section('seo_klic', 'ochrana-udaju')
@section('popis', __('web.ochrana_osobnich_udaju.jak_zpracovava_osobni_udaje_z_poptavek', ['hodnota' => ($u['firma'] ?: $u['nazev'])]))

@section('obsah')
<main id="obsah" class="section section--base">
  <div class="wrap doc">
    <div class="eyebrow"><span class="num">§</span><span class="lbl" data-cs="{{ __('web.ochrana_osobnich_udaju.soukromi') }}" data-en="{{ __('web.ochrana_osobnich_udaju.soukromi', [], 'en') }}">{{ __('web.ochrana_osobnich_udaju.soukromi') }}</span></div>
    <h1 class="h2" data-cs="{{ __('web.ochrana_osobnich_udaju.ochrana_osobnich_udaju') }}" data-en="{{ __('web.ochrana_osobnich_udaju.ochrana_osobnich_udaju', [], 'en') }}">{{ __('web.ochrana_osobnich_udaju.ochrana_osobnich_udaju') }}</h1>

    <div data-jazyk="cs">
      @include('pravni._ochrana-obsah')
    </div>
    <div data-jazyk="en" lang="en" hidden>
      @include('pravni._ochrana-en')
    </div>
  </div>
</main>
@endsection
