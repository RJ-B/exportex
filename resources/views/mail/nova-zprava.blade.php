{!! __('maily.nova_zprava.nova_poptavka_z_formulare_na_webu', ['get' => \App\Support\ZakladniUdaje::get('nazev')]) !!}

{!! __('maily.nova_zprava.od', ['cele_jmeno' => $zprava->celeJmeno(), 'email' => $zprava->email]) !!}
@if ($zprava->firma){!! __('maily.nova_zprava.firma', ['firma' => $zprava->firma]) !!}
@endif
@if ($zprava->telefon){!! __('maily.nova_zprava.telefon', ['telefon' => $zprava->telefon]) !!}
@endif
@if ($zprava->jazyk === 'en'){!! __('maily.nova_zprava.jazyk_webu_anglictina_odpovezte_prosim') !!}
@endif
{!! __('maily.nova_zprava.odeslano', ['format' => $zprava->created_at->format('j. n. Y H:i')]) !!}

{{ $zprava->zprava }}

{!! __('maily.nova_zprava.odpovedet_muzete_primo_na_tento_e_2') !!}
