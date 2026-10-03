@php($texty = \App\Support\TextyStavuWebu::nacti())
@include('stav-webu._rozvrzeni', [
    'ikona' => '⚙',
    'nadpis' => $texty['udrzba_nadpis'],
    'text' => $texty['udrzba_text'],
])
