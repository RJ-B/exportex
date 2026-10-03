{{-- ===== JAK TO FUNGUJE ===== Obsah webu → Jak to funguje. Kroky kreslí main.js. --}}
@use('App\Support\Preklad')
<section class="section {{ $tlo }}" id="jak">
  <div class="wrap">
    @include('web.sekce._hlavicka')
    <h2 class="h2" data-rv style="--rv-d:60ms; max-width:20ch; margin-bottom:44px"
        {{ Preklad::attr($s['nadpis'], $s['nadpis_en']) }}>{{ $s['nadpis'] }}</h2>

    <div class="steps" id="steps"></div>
  </div>
</section>
