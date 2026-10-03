@php($texty = \App\Support\TextyStavuWebu::nacti())
@include('stav-webu._rozvrzeni', [
    'ikona' => '✦',
    'nadpis' => $texty['pripravujeme_nadpis'],
    'text' => $texty['pripravujeme_text'],
])
