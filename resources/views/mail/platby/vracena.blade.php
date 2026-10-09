@if ($platba->testovaci())TESTOVACÍ PLATBA – nic se nevrací, jen zkouška platební brány.

@endif
Dobrý den{{ $platba->jmeno ? ' '.$platba->jmeno : '' }},

posíláme vám zpět {{ \App\Platby\Platba::kc($castka, $platba->mena) }} za „{{ $platba->popis }}“@if ($platba->reference) (č. {{ $platba->reference }})@endif.
Peníze dorazí stejnou cestou, jakou jste platili – podle banky zpravidla do několika pracovních dnů.

{{ \App\Support\ZakladniUdaje::get('nazev') }}
@if (\App\Support\ZakladniUdaje::get('email')){{ \App\Support\ZakladniUdaje::get('email') }}
@endif
