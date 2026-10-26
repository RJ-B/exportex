@if ($zkouska){!! __('maily.oznameni_text.zkouska_takhle_oznameni_uvidi_prijemci') !!}

@endif
{{ $oznameni->titulek }}
{{ str_repeat('=', min(60, mb_strlen($oznameni->titulek))) }}
@if ($oznameni->udalost_od)

{!! __('maily.oznameni_text.kdy', ['format' => $oznameni->udalost_od->format(__('formaty.datum_cas'))]) !!}@if ($oznameni->udalost_do) – {{ $oznameni->udalost_do->format(__('formaty.datum_cas')) }}@endif

@endif

{{ $oznameni->textProsty() }}
@if ($odkaz)

{{ $oznameni->odkaz_text ?: 'Zobrazit' }}: {{ $odkaz }}
@endif

---
@if ($odhlaseni)
{!! __('maily.oznameni_text.dostavate_to_protoze_jste_souhlasili_se', ['nazev_webu' => $nazevWebu, 'odhlaseni' => $odhlaseni]) !!}
@else
{!! __('maily.oznameni_text.dostavate_to_protoze_mate_ucet_na', ['nazev_webu' => $nazevWebu]) !!}
@endif
@if ($predvolby)
{!! __('maily.oznameni_text.predvolby_oznameni', ['predvolby' => $predvolby]) !!}
@endif
