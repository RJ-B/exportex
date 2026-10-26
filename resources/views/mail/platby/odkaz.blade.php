@if ($platba->skutecna()){!! __('platby.odkaz.skutecna_platba_pres_testovaci_branu') !!}

@elseif ($platba->testovaci()){!! __('platby.odkaz.testovaci_platba_zaplatit_jde_jen') !!}

@endif
{!! __('platby.odkaz.dobry_den', ['platba' => $platba->jmeno ? ' '.$platba->jmeno : '']) !!}

{!! __('platby.odkaz.posilame_odkaz_k_zaplaceni') !!}

{!! __('platby.odkaz.za_castka', ['popis' => $platba->popis, 'castka_kc' => $platba->castkaKc()]) !!}

{!! __('platby.odkaz.zaplatit', ['odkaz_k_zaplaceni' => $platba->odkazKZaplaceni()]) !!}

{{ \App\Support\ZakladniUdaje::get('nazev') }}
@if (\App\Support\ZakladniUdaje::get('email')){{ \App\Support\ZakladniUdaje::get('email') }}
@endif
