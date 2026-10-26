@php($texty = \App\Support\TextyStavuWebu::nacti())
{{-- Text a předpokládaný konec údržby z CRM (UdrzbaWebu::plan) mají přednost před textem z administrace. --}}
@php($plan = \App\Support\UdrzbaWebu::plan())
@include('stav-webu._rozvrzeni', [
    'ikona' => '⚙',
    'nadpis' => $texty['udrzba_nadpis'],
    'text' => $plan['text'] ?? $texty['udrzba_text'],
    'konec' => \App\Support\UdrzbaWebu::vetaKonce($plan['konec']),
])
