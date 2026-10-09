@if ($platba->testovaci())TESTOVACÍ PLATBA – zaplatit jde jen testovací kartou, nic se nestrhne.

@endif
Dobrý den{{ $platba->jmeno ? ' '.$platba->jmeno : '' }},

posíláme odkaz k zaplacení.

Za: {{ $platba->popis }}
Částka: {{ $platba->castkaKc() }}

Zaplatit: {{ $platba->odkazKZaplaceni() }}

{{ \App\Support\ZakladniUdaje::get('nazev') }}
@if (\App\Support\ZakladniUdaje::get('email')){{ \App\Support\ZakladniUdaje::get('email') }}
@endif
