@php
    use App\Platby\StavPlatby;
    $nadpis = match (true) {
        $platba->stav->zaplaceno() => __('platby.vysledek.dekujeme_zaplaceno'),
        $platba->stav->otevrena() => __('platby.vysledek.cekame_na_potvrzeni_platby'),
        default => __('platby.vysledek.platba_neprobehla'),
    };
    $obnovit = $platba->stav->otevrena() ? 5 : null;
@endphp
@extends('platby._rozvrzeni')

@section('obsah')
    @if (session('chyba'))<div class="hlaska">{{ session('chyba') }}</div>@endif

    @if ($platba->stav->zaplaceno())
        <div class="ikona" style="background: color-mix(in srgb, var(--ok) 14%, transparent); color: var(--ok);">✓</div>
    @elseif ($platba->stav->otevrena())
        <div class="ikona" style="background: color-mix(in srgb, var(--ceka) 14%, transparent); color: var(--ceka);">…</div>
    @else
        <div class="ikona" style="background: color-mix(in srgb, var(--chyba) 14%, transparent); color: var(--chyba);">!</div>
    @endif

    <p class="nazev">{{ \App\Support\ZakladniUdaje::get('nazev') }}</p>
    <h1>{{ $nadpis }}</h1>
    <p>{{ $platba->popis }}@if ($platba->reference) {{ __('platby.vysledek.c', ['reference' => $platba->reference]) }}@endif</p>
    <p class="castka">{{ $platba->castkaKc() }}</p>

    @if ($platba->stav->zaplaceno())
        <p>{{ __('platby.vysledek.potvrzeni_jsme_poslali_na_vas_e') }}</p>
    @elseif ($platba->stav->otevrena())
        <p>{{ __('platby.vysledek.banka_platbu_jeste_potvrzuje_stranka_se') }}</p>
        @if ($platba->stav === StavPlatby::Ceka && $platba->presmerovani_url)
            <div class="tlacitka"><a class="tlacitko druhe" href="{{ route('platby.zaplatit', $platba) }}">{{ __('platby.vysledek.vratit_se_k_platbe') }}</a></div>
        @endif
    @else
        <p>{{ __('platby.vysledek.penize_se_nestrhly', ['platba' => $platba->stav === StavPlatby::Zamitnuta ? __('platby.vysledek.banka_platbu_zamitla') : __('platby.vysledek.platba_byla_zrusena_nebo_vyprsela')]) }}</p>
        <div class="tlacitka">
            <form method="post" action="{{ route('platby.znovu', $platba) }}">@csrf<button class="tlacitko" type="submit">{{ __('platby.vysledek.zaplatit_znovu') }}</button></form>
        </div>
    @endif

    <div class="tlacitka"><a class="tlacitko druhe" href="{{ url('/') }}">{{ __('platby.vysledek.zpet_na_web') }}</a></div>
@endsection
