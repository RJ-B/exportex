{{-- Stav DNS domény schránky (SPF, DKIM, DMARC) srozumitelně: co je v pořádku a co doplnit u správce domény.
     U schránky na cizí doméně (gmail.com, seznam.cz…) jen informace – u ní se nic nenastavuje.
     $dns = odpověď Pošty (…/dns), $adresa = adresa schránky. Vlastní styly, Tailwind se tu nekompiluje. --}}
@php
    $domena = $dns['domena'] ?? \Illuminate\Support\Str::after($adresa, '@');
    $stavy = collect($dns['kontrola'] ?? [])->keyBy(fn ($k) => strtolower((string) ($k['zaznam'] ?? '')));
    $zaznamy = collect($dns['zaznamy'] ?? [])->groupBy(fn ($z) => strtolower((string) ($z['ucel'] ?? '')));
    $barva = fn (?string $s) => match ($s) { 'ok' => 'var(--success-600)', 'varovani' => 'var(--warning-600)', 'chyba' => 'var(--danger-600)', default => 'var(--gray-500)' };
    $text = fn (?string $s) => match ($s) {
        'ok' => __('pruvodce.dns.v_poradku'),
        'varovani' => __('pruvodce.dns.pozor'),
        'chyba' => __('pruvodce.dns.chybi'),
        default => __('pruvodce.dns.nezjisteno'),
    };
@endphp
<div style="font-size:.875rem">
    @if ($dns === [])
        <p style="margin:0">{{ __('pruvodce.dns.ted_nejde_zjistit') }}</p>
    @elseif ($dns['bezplatna'] ?? false)
        <p style="margin:0">{{ __('pruvodce.dns.cizi_domena', ['domena' => $domena]) }}</p>
    @else
        <p style="margin:0 0 .75rem">
            @if (($dns['stav'] ?? null) === 'ok')
                <span style="color:var(--success-600)">{{ __('pruvodce.dns.vse_v_poradku', ['domena' => $domena]) }}</span>
            @else
                {{ __('pruvodce.dns.u_spravce_domeny_doplnte', ['domena' => $domena]) }}
            @endif
        </p>
        @foreach (['spf' => __('pruvodce.dns.spf'), 'dkim' => __('pruvodce.dns.dkim'), 'dmarc' => __('pruvodce.dns.dmarc')] as $ucel => $vysvetleni)
            @php $stav = $stavy->get($ucel)['stav'] ?? null; @endphp
            <div style="border-top:1px solid var(--gray-200);padding:.5rem 0">
                <b>{{ strtoupper($ucel) }}</b>
                – <span style="color:{{ $barva($stav) }}">{{ $text($stav) }}</span>
                <span style="color:var(--gray-500)"> · {{ $vysvetleni }}</span>
                @if ($stav !== 'ok' && filled($stavy->get($ucel)['zprava'] ?? null))
                    <div style="color:var(--gray-500)">{{ $stavy->get($ucel)['zprava'] }}</div>
                @endif
                @if ($stav !== 'ok')
                    @foreach ($zaznamy->get($ucel, []) as $z)
                        <div style="margin-top:.25rem;overflow-x:auto">
                            {{ __('pruvodce.dns.zaznam', ['typ' => $z['typ'] ?? '']) }}
                            <code>{{ $z['jmeno_cele'] ?? $z['jmeno'] ?? '' }}</code> →
                            <code>{{ $z['hodnota'] ?? '' }}</code>
                            @if (filled($z['poznamka'] ?? null))<div style="color:var(--gray-500)">{{ $z['poznamka'] }}</div>@endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach
    @endif
</div>
