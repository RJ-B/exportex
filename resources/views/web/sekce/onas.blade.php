{{-- ===== O NÁS ===== Obsah webu → O nás --}}
@use('App\Support\Preklad')
@use('App\Support\ObsahWebu')
<section class="section {{ $tlo }}" id="onas">
  <div class="wrap about">
    <div>
      @include('web.sekce._hlavicka')
      <h2 class="h2" data-rv style="--rv-d:60ms; margin-bottom:24px"
          {{ Preklad::attr($s['nadpis'], $s['nadpis_en']) }}>{{ $s['nadpis'] }}</h2>
      <p data-rv style="--rv-d:120ms"
         {{ Preklad::attr($s['text'], $s['text_en']) }}>{{ $s['text'] }}</p>
      @if (filled($s['text_druhy']))
      <p data-rv style="--rv-d:180ms"
         {{ Preklad::attr($s['text_druhy'], $s['text_druhy_en']) }}>{{ $s['text_druhy'] }}</p>
      @endif
    </div>

    <figure class="about__fig" data-rv="zoom" style="--rv-d:140ms; margin:0">
      <img src="{{ ObsahWebu::obrazek($s['foto']) }}" width="1200" height="800" alt="{{ $s['foto_popis'] }}" loading="lazy" decoding="async">
      <figcaption class="about__cap">
        <b {{ Preklad::attr($s['popisek'], $s['popisek_en']) }}>{{ $s['popisek'] }}</b>
        @if (filled($s['popisek_misto']))<i>{{ $s['popisek_misto'] }}</i>@endif
      </figcaption>
    </figure>
  </div>
</section>
