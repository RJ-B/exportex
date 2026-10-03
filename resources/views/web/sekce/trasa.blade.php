{{-- ===== TRASA ===== Obsah webu → Trasa. Mapa je pevná, oblouk a popisky kreslí main.js. --}}
@use('App\Support\Preklad')
<section class="section {{ $tlo }}" id="trasa">
  <div class="wrap">
    @include('web.sekce._hlavicka')
    <h2 class="h2" data-rv style="--rv-d:60ms; max-width:22ch"
        {{ Preklad::attr($s['nadpis'], $s['nadpis_en']) }}>{{ $s['nadpis'] }}</h2>
    <p class="lead" data-rv style="--rv-d:120ms; margin-bottom:44px"
       {{ Preklad::attr($s['popis'], $s['popis_en']) }}>{{ $s['popis'] }}</p>


    <div class="routebox glass" data-rv="zoom" style="--rv-d:240ms">
@include('web._mapa')
    </div>
  </div>
</section>
