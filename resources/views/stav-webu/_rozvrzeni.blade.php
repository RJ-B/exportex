{{-- Společná kostra stránek „Připravujeme“ a „Údržba“ (StavWebuMiddleware).
     Soběstačná – bez CSS frameworku, aby fungovala v každém projektu.
     Vzhled si projekt může přepsat ve vlastní šabloně. --}}
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $udaje['nazev'] }} – {{ $nadpis }}</title>
    <style>
        :root { --pozadi: #f5f6f8; --karta: #ffffff; --text: #16181c; --slabe: #6b7280; --akcent: #2563eb; --okraj: #e5e7eb; }
        @media (prefers-color-scheme: dark) { :root { --pozadi: #0f1115; --karta: #171a21; --text: #eceef1; --slabe: #9aa1ab; --akcent: #60a5fa; --okraj: #2a2f38; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; grid-template-rows: auto 1fr; place-items: center; padding: 24px 16px;
               background: var(--pozadi); color: var(--text); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .karta { width: 100%; max-width: 520px; background: var(--karta); border: 1px solid var(--okraj); border-radius: 16px; padding: 40px 32px; text-align: center; }
        .ikona { width: 56px; height: 56px; margin: 0 auto 20px; border-radius: 50%; display: grid; place-items: center; background: color-mix(in srgb, var(--akcent) 12%, transparent); color: var(--akcent); font-size: 26px; }
        .nazev { font-size: 14px; letter-spacing: .06em; text-transform: uppercase; color: var(--slabe); margin: 0 0 6px; }
        h1 { font-size: 26px; line-height: 1.25; margin: 0 0 12px; }
        p { margin: 0; color: var(--slabe); }
        .kontakt { margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--okraj); font-size: 15px; display: flex; flex-direction: column; gap: 4px; }
        .kontakt a { color: var(--akcent); text-decoration: none; }
        .provozovatel { margin-top: 16px; font-size: 13px; color: var(--slabe); }
        body > .ozn-pruhy { justify-self: stretch; align-self: start; margin: -24px -16px 16px; }
    </style>
</head>
<body>
    {{-- Pruh oznámení (odstávka, výpadek) – i v Údržbě a Připravujeme. --}}
    @include('oznameni.pruh')
    <main class="karta" style="grid-row: 2;">
        <div class="ikona" aria-hidden="true">{{ $ikona }}</div>
        <p class="nazev">{{ $udaje['nazev'] }}</p>
        <h1>{{ $nadpis }}</h1>
        <p>{{ $text }}</p>

        @if ($udaje['email'] || $udaje['telefon'])
            <div class="kontakt">
                @if ($udaje['email'])<a href="mailto:{{ $udaje['email'] }}">{{ $udaje['email'] }}</a>@endif
                @if ($udaje['telefon'])<a href="tel:{{ preg_replace('/\s+/', '', $udaje['telefon']) }}">{{ $udaje['telefon'] }}</a>@endif
            </div>
        @endif

        <p class="provozovatel"><a href="{{ route('ochrana-udaju') }}" style="color: inherit;">Ochrana osobních údajů</a></p>

        @if ($udaje['firma'])
            <p class="provozovatel">{{ $udaje['firma'] }}@if ($udaje['ico']) · IČO {{ $udaje['ico'] }}@endif</p>
        @endif
    </main>
</body>
</html>
