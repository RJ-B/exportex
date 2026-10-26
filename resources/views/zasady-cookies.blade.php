{{-- Cookies a místní úložiště (dřív cookies.html). Česky vzor šablony Sim&Ren
     (pravni/_cookies-obsah – nezbytné cookies a měření podle SEO a měření) a k němu
     co si web ukládá v prohlížeči; anglicky totéž v pravni/_cookies-en. --}}
@extends('layouts.web')

@php
    $u = \App\Support\ZakladniUdaje::nacti();
@endphp

@section('titulek', __('web.zasady_cookies.cookies_a_mistni_uloziste'))
@section('seo_klic', 'cookies')
@section('popis', __('web.zasady_cookies.jake_cookies_web_pouziva_a_co', ['nazev' => $u['nazev']]))

@section('obsah')
<main id="obsah" class="section section--base">
  <div class="wrap doc">
    <div class="eyebrow"><span class="num">§</span><span class="lbl">{{ __('web.zasady_cookies.cookies') }}</span></div>
    <h1 class="h2" data-cs="{{ __('web.zasady_cookies.cookies_a_mistni_uloziste') }}" data-en="{{ __('web.zasady_cookies.cookies_a_mistni_uloziste', [], 'en') }}">{{ __('web.zasady_cookies.cookies_a_mistni_uloziste') }}</h1>

    <div data-jazyk="cs">
      @include('pravni._cookies-obsah')
      @include('pravni._uloziste-cs')
    </div>
    <div data-jazyk="en" lang="en" hidden>
      @include('pravni._cookies-en')
    </div>
  </div>
</main>
@endsection
