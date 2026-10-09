@if ($platba->testovaci())TESTOVACÍ PLATBA – nic se nestrhlo, jen zkouška platební brány.

@endif
Dobrý den{{ $platba->jmeno ? ' '.$platba->jmeno : '' }},

děkujeme, platba je zaplacená.

Za: {{ $platba->popis }}
@if ($platba->reference)Číslo: {{ $platba->reference }}
@endif
Částka: {{ $platba->castkaKc() }}
Zaplaceno: {{ $platba->zaplaceno_v?->format('j. n. Y H:i') }}

{{ \App\Support\ZakladniUdaje::get('nazev') }}
@if (\App\Support\ZakladniUdaje::get('email')){{ \App\Support\ZakladniUdaje::get('email') }}
@endif
