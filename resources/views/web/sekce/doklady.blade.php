{{-- ===== DOKLADY A CLO ===== Obsah webu → Doklady a clo. Karty kreslí main.js. --}}
@use('App\Support\Preklad')
<section class="section {{ $tlo }}" id="doklady">
  <div class="wrap">
    @include('web.sekce._hlavicka')
    <h2 class="h2" data-rv style="--rv-d:60ms; max-width:20ch; margin-bottom:44px"
        {{ Preklad::attr($s['nadpis'], $s['nadpis_en']) }}>{{ $s['nadpis'] }}</h2>

    <div class="grid grid--compliance" id="compliance"></div>
  </div>
</section>
