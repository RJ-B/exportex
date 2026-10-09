@php
    use App\Platby\StavPlatby;
    $nadpis = match (true) {
        $platba->stav->zaplaceno() => 'Děkujeme, zaplaceno',
        $platba->stav->otevrena() => 'Čekáme na potvrzení platby',
        default => 'Platba neproběhla',
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
    <p>{{ $platba->popis }}@if ($platba->reference) · č. {{ $platba->reference }}@endif</p>
    <p class="castka">{{ $platba->castkaKc() }}</p>

    @if ($platba->stav->zaplaceno())
        <p>Potvrzení jsme poslali na váš e-mail.</p>
    @elseif ($platba->stav->otevrena())
        <p>Banka platbu ještě potvrzuje. Stránka se sama obnoví – nemusíte nic dělat.</p>
        @if ($platba->stav === StavPlatby::Ceka && $platba->presmerovani_url)
            <div class="tlacitka"><a class="tlacitko druhe" href="{{ route('platby.zaplatit', $platba) }}">Vrátit se k platbě</a></div>
        @endif
    @else
        <p>{{ $platba->stav === StavPlatby::Zamitnuta ? 'Banka platbu zamítla.' : 'Platba byla zrušena nebo vypršela.' }} Peníze se nestrhly.</p>
        <div class="tlacitka">
            <form method="post" action="{{ route('platby.znovu', $platba) }}">@csrf<button class="tlacitko" type="submit">Zaplatit znovu</button></form>
        </div>
    @endif

    <div class="tlacitka"><a class="tlacitko druhe" href="{{ url('/') }}">Zpět</a></div>
@endsection
