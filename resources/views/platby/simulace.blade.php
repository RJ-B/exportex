@php($nadpis = __('platby.simulace.simulace_platebni_brany'))
@extends('platby._rozvrzeni')

@section('obsah')
    <p class="nazev">{{ __('platby.simulace.jen_lokalne_a_v_testech_zadne') }}</p>
    <h1>{{ __('platby.simulace.simulace_platebni_brany') }}</h1>
    <p>{{ $platba->popis }}@if ($platba->reference) {{ __('platby.simulace.c', ['reference' => $platba->reference]) }}@endif</p>
    <p class="castka">{{ $platba->castkaKc() }}</p>
    @if ($platba->predvolba)
        <p>{{ __('platby.simulace.predvolena_metoda') }} <strong>{{ $platba->predvolba->popis() }}</strong> {{ __('platby.simulace.brana_otevre_rovnou_penezenku') }}</p>
    @endif

    @if ($platba->stav->otevrena())
        <div class="tlacitka">
            <form method="post" action="{{ route('platby.simulace.odeslat', $platba) }}">@csrf<input type="hidden" name="vysledek" value="zaplatit"><button class="tlacitko" type="submit">{{ __('platby.simulace.zaplatit') }}</button></form>
            <form method="post" action="{{ route('platby.simulace.odeslat', $platba) }}">@csrf<input type="hidden" name="vysledek" value="zamitnout"><button class="tlacitko cervene" type="submit">{{ __('platby.simulace.zamitnout') }}</button></form>
            <form method="post" action="{{ route('platby.simulace.odeslat', $platba) }}">@csrf<input type="hidden" name="vysledek" value="zrusit"><button class="tlacitko druhe" type="submit">{{ __('platby.simulace.zrusit') }}</button></form>
        </div>
    @else
        <p>{{ __('platby.simulace.platba_uz_je', ['popis' => mb_strtolower($platba->stav->popis())]) }}</p>
        <div class="tlacitka"><a class="tlacitko druhe" href="{{ route('platby.vysledek', $platba) }}">{{ __('platby.simulace.vysledek') }}</a></div>
    @endif
@endsection
