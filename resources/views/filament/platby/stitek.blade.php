{{-- Štítek v horní liště administrace, dokud platby neběží ostře (doplněk Platby). --}}
@if (\App\Platby\NastaveniPlateb::stitek())
    <a href="{{ \App\Platby\Filament\Stranky\PlatebniBrana::getUrl() }}" title="{{ \App\Platby\NastaveniPlateb::prehled()['popis'] }}"
       style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; margin-inline-end: 8px; border-radius: 999px; font-size: .75rem; font-weight: 700; letter-spacing: .04em; white-space: nowrap; background: color-mix(in srgb, var(--warning-500) 18%, transparent); color: var(--warning-600); text-decoration: none;">
        TESTOVACÍ PLATBY
    </a>
@endif
