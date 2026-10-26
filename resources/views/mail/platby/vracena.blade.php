@if ($platba->testovaci()){!! __('platby.vracena.testovaci_platba_nic_se_nevraci_jen') !!}

@endif
{!! __('platby.vracena.dobry_den', ['platba' => $platba->jmeno ? ' '.$platba->jmeno : '']) !!}

{!! __('platby.vracena.posilame_vam_zpet_za', ['kc' => \App\Platby\Platba::kc($castka, $platba->mena), 'popis' => $platba->popis]) !!}@if ($platba->reference) {!! __('platby.vracena.c', ['reference' => $platba->reference]) !!}@endif{!! __('platby.vracena.penize_dorazi_stejnou_cestou_jakou_jste') !!}

{{ \App\Support\ZakladniUdaje::get('nazev') }}
@if (\App\Support\ZakladniUdaje::get('email')){{ \App\Support\ZakladniUdaje::get('email') }}
@endif
