{{-- Štítek „TESTOVACÍ PLATBY“ na webu, dokud brána neběží ostře (doplněk Platby).
     Projekt ho vloží do svého layoutu hned za <body>:
     @includeWhen(config('sablona.doplnky.platby'), 'platby.stitek') --}}
@if (\App\Platby\NastaveniPlateb::stitek())
    <div role="status" style="position: fixed; left: 12px; bottom: 12px; z-index: 2147483000; padding: 6px 12px; border-radius: 999px; background: #b45309; color: #fff; font: 700 12px/1.2 system-ui, -apple-system, 'Segoe UI', sans-serif; letter-spacing: .05em; box-shadow: 0 4px 14px rgba(0,0,0,.18); pointer-events: none;">
        TESTOVACÍ PLATBY
    </div>
@endif
