{{-- ===== SORTIMENT ===== Obsah webu → Sortiment. Filtry a karty kreslí main.js z dat webu. --}}
@use('App\Support\Preklad')
<section class="section {{ $tlo }}" id="sortiment">
  <div class="wrap">
    @include('web.sekce._hlavicka')
    <h2 class="h2" data-rv style="--rv-d:60ms; max-width:22ch"
        {{ Preklad::attr($s['nadpis'], $s['nadpis_en']) }}>{{ $s['nadpis'] }}</h2>
    <p class="lead" data-rv style="--rv-d:120ms"
       {{ Preklad::attr($s['popis'], $s['popis_en']) }}>{{ $s['popis'] }}</p>

    <div class="chips" id="filters" data-rv style="--rv-d:180ms; display:flex; gap:8px; flex-wrap:wrap; margin-bottom:36px" role="group" aria-label="Filtr sortimentu"></div>

    <div class="grid grid--products" id="products" aria-live="polite"></div>
  </div>
</section>
