{{-- Jak teď platby běží (Nastavení → Platební brána). --}}
@php
    $ostre = $prehled['rezim'] === \App\Platby\Rezim::Ostry;
    $barva = $prehled['chyba'] ? 'danger' : ($ostre ? 'success' : 'warning');
@endphp
<div style="padding: 14px 16px; border-radius: 12px; border: 1px solid color-mix(in srgb, var(--{{ $barva }}-500) 40%, transparent); background: color-mix(in srgb, var(--{{ $barva }}-500) 10%, transparent);">
    <p style="margin: 0; font-weight: 700; color: var(--{{ $barva }}-600);">
        @if ($prehled['chyba'])
            Platby teď nefungují
        @elseif ($ostre)
            Ostré platby – {{ $prehled['brana'] }}
        @else
            TESTOVACÍ PLATBY – {{ $prehled['brana'] }}
        @endif
    </p>
    <p style="margin: 4px 0 0; font-size: .875rem; color: inherit; opacity: .85;">{{ $prehled['chyba'] ?? $prehled['popis'] }}</p>
</div>
