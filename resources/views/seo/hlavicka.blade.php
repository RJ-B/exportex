{{-- Hlavička stránky pro vyhledávače a sociální sítě (App\Support\Seo\Seo::html()).
     Patří do <head> každého layoutu webu místo vlastního <title>. --}}
@php
    $titulek = $seo->titulek();
    $popis = $seo->popisStranky();
    $obrazek = $seo->obrazekSdileni();
@endphp
<title>{{ $titulek }}</title>
@if ($popis)
<meta name="description" content="{{ $popis }}">
@endif
<meta name="robots" content="{{ $seo->robots() }}">
<link rel="canonical" href="{{ $seo->kanonicka() }}">
<meta property="og:type" content="{{ $seo->typStranky() }}">
<meta property="og:site_name" content="{{ $seo->nazevWebu() }}">
<meta property="og:locale" content="{{ \App\Support\Jazyky\Jazyky::regionalni() }}">
<meta property="og:title" content="{{ $titulek }}">
@if ($popis)
<meta property="og:description" content="{{ $popis }}">
@endif
<meta property="og:url" content="{{ $seo->kanonicka() }}">
@if ($obrazek)
<meta property="og:image" content="{{ $obrazek['url'] }}">
@if ($obrazek['typ'])
<meta property="og:image:type" content="{{ $obrazek['typ'] }}">
@endif
@if ($obrazek['sirka'] && $obrazek['vyska'])
<meta property="og:image:width" content="{{ $obrazek['sirka'] }}">
<meta property="og:image:height" content="{{ $obrazek['vyska'] }}">
@endif
<meta property="og:image:alt" content="{{ $titulek }}">
@endif
<meta name="twitter:card" content="{{ $obrazek ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $titulek }}">
@if ($popis)
<meta name="twitter:description" content="{{ $popis }}">
@endif
@if ($obrazek)
<meta name="twitter:image" content="{{ $obrazek['url'] }}">
@endif
<script type="application/ld+json">{!! $seo->jsonLd() !!}</script>
