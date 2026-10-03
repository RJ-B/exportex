{{-- Číslo a štítek nad nadpisem sekce (eyebrow). --}}
@use('App\Support\Preklad')
<div class="eyebrow" data-rv>
  <span class="num">{{ $cislo }}</span>
  <span class="lbl" {{ Preklad::attr($s['stitek'], $s['stitek_en']) }}>{{ $s['stitek'] }}</span>
</div>
