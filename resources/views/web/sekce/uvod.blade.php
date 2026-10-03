{{-- ===== HERO ===== Obsah webu → Úvod --}}
@use('App\Support\Preklad')
@use('App\Support\ObsahWebu')
@php
    $nadpisCs = e($s['nadpis']).' <em>'.e($s['nadpis_zvyrazneny']).'</em>';
    $nadpisEn = e($s['nadpis_en']).' <em>'.e($s['nadpis_zvyrazneny_en']).'</em>';
    $formular = \App\Support\SekceWebu::zapnuta('formular');
    $sortiment = \App\Support\SekceWebu::zapnuta('sortiment');
@endphp
<section class="hero" id="top">
  <div class="hero__media">
    <img src="{{ ObsahWebu::obrazek($s['foto']) }}" width="2400" height="1350" alt="{{ $s['foto_popis'] }}" fetchpriority="high" decoding="async">
  </div>
  <div class="hero__veil"></div>
  <div class="hero__grid"></div>

  <div class="hero__in">
    <div class="hero__top" data-en-in style="--en-d:80ms">
      <span class="hero__kicker" {{ Preklad::attr($s['stitek'], $s['stitek_en']) }}>{{ $s['stitek'] }}</span>
      @if ($s['trasa'])<span class="hero__route">{{ $s['trasa'] }}</span>@endif
    </div>

    <h1 data-en-in style="--en-d:180ms"
        {{ Preklad::html($nadpisCs, $nadpisEn) }}>{!! $nadpisCs !!}</h1>

    <p class="hero__lead" data-en-in style="--en-d:280ms"
       {{ Preklad::attr($s['text'], $s['text_en']) }}>{{ $s['text'] }}</p>

    <div class="hero__cta" data-en-in style="--en-d:380ms">
      @if ($formular)
      <a href="#kontakt"  class="btn btn--primary btn--lg" {{ Preklad::attr($s['tlacitko'], $s['tlacitko_en']) }}>{{ $s['tlacitko'] }}</a>
      @endif
      @if ($sortiment)
      <a href="#sortiment" class="btn btn--glass btn--lg"  {{ Preklad::attr($s['tlacitko_druhe'], $s['tlacitko_druhe_en']) }}>{{ $s['tlacitko_druhe'] }}</a>
      @endif
    </div>

    @if (! empty($s['cisla']))
    <div class="stats" data-en-in style="--en-d:480ms">
      @foreach ($s['cisla'] as $cislo)
      @php($hodnotaCs = Preklad::znacka($cislo['hodnota'] ?? ''))
      <div class="stat">
        <div class="stat__k" {{ Preklad::attr($cislo['nazev'] ?? '', $cislo['nazev_en'] ?? '') }}>{{ $cislo['nazev'] ?? '' }}</div>
        <div class="stat__v"@if (str_contains($hodnotaCs, '<sup>')) style="white-space:nowrap"@endif {{ Preklad::html($hodnotaCs, Preklad::znacka($cislo['hodnota_en'] ?? '') ?: $hodnotaCs) }}>{!! $hodnotaCs !!}</div>
        @if (filled($cislo['podpis'] ?? null))
        <div class="stat__s" {{ Preklad::attr($cislo['podpis'], $cislo['podpis_en'] ?? '') }}>{{ $cislo['podpis'] }}</div>
        @endif
      </div>
      @endforeach
    </div>
    @endif
  </div>

  <div class="scrollhint" aria-hidden="true"><i></i><span data-cs="Scroll" data-en="Scroll">Scroll</span></div>
</section>
