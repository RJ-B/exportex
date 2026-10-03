{{-- Cookies a místní úložiště (dřív cookies.html). Česky vzor šablony Sim&Ren
     (pravni/_cookies-obsah – nezbytné cookies a měření podle SEO a měření) a k němu
     co si web ukládá v prohlížeči; anglicky totéž v pravni/_cookies-en. --}}
@extends('layouts.web')

@php
    $u = \App\Support\ZakladniUdaje::nacti();
@endphp

@section('titulek', 'Cookies a místní úložiště — '.$u['nazev'])
@section('popis', 'Jaké cookies web '.$u['nazev'].' používá a co si ukládá v prohlížeči: zvolený jazyk, světlý či tmavý režim a pozici na stránce.')

@section('obsah')
<main id="obsah" class="section section--base">
  <div class="wrap doc">
    <div class="eyebrow"><span class="num">§</span><span class="lbl">Cookies</span></div>
    <h1 class="h2" data-cs="Cookies a místní úložiště" data-en="Cookies and local storage">Cookies a místní úložiště</h1>

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
