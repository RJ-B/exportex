@if ($platba->skutecna()){!! __('platby.prijata.skutecna_platba_pres_testovaci_branu') !!}

@elseif ($platba->testovaci()){!! __('platby.prijata.testovaci_platba_nic_se_nestrhlo_jen') !!}

@endif
{!! __('platby.prijata.dobry_den', ['platba' => $platba->jmeno ? ' '.$platba->jmeno : '']) !!}

{!! __('platby.prijata.dekujeme_platba_je_zaplacena') !!}

{!! __('platby.prijata.za', ['popis' => $platba->popis]) !!}
@if ($platba->reference){!! __('platby.prijata.cislo', ['reference' => $platba->reference]) !!}
@endif
{!! __('platby.prijata.castka_zaplaceno', ['castka_kc' => $platba->castkaKc(), 'format' => $platba->zaplaceno_v?->format(__('formaty.datum_cas'))]) !!}

{{ \App\Support\ZakladniUdaje::get('nazev') }}
@if (\App\Support\ZakladniUdaje::get('email')){{ \App\Support\ZakladniUdaje::get('email') }}
@endif
