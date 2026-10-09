@php($nadpis = 'Simulace platební brány')
@extends('platby._rozvrzeni')

@section('obsah')
    <p class="nazev">Jen lokálně a v testech – žádné peníze</p>
    <h1>Simulace platební brány</h1>
    <p>{{ $platba->popis }}@if ($platba->reference) · č. {{ $platba->reference }}@endif</p>
    <p class="castka">{{ $platba->castkaKc() }}</p>

    @if ($platba->stav->otevrena())
        <div class="tlacitka">
            <form method="post" action="{{ route('platby.simulace.odeslat', $platba) }}">@csrf<input type="hidden" name="vysledek" value="zaplatit"><button class="tlacitko" type="submit">Zaplatit</button></form>
            <form method="post" action="{{ route('platby.simulace.odeslat', $platba) }}">@csrf<input type="hidden" name="vysledek" value="zamitnout"><button class="tlacitko cervene" type="submit">Zamítnout</button></form>
            <form method="post" action="{{ route('platby.simulace.odeslat', $platba) }}">@csrf<input type="hidden" name="vysledek" value="zrusit"><button class="tlacitko druhe" type="submit">Zrušit</button></form>
        </div>
    @else
        <p>Platba už je {{ mb_strtolower($platba->stav->popis()) }}.</p>
        <div class="tlacitka"><a class="tlacitko druhe" href="{{ route('platby.vysledek', $platba) }}">Výsledek</a></div>
    @endif
@endsection
