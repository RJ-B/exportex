{{-- Rozvržení veřejných stránek šablony (Kontakt, Ochrana osobních údajů, Zásady cookies).
     Projekt s vlastním vzhledem webu vloží jen obsah (@include('pravni._…-obsah'),
     @include('formulare.kontakt')) a cookie lištu do svého layoutu. --}}
@php
    $udaje = \App\Support\ZakladniUdaje::nacti();
@endphp
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $nadpis }} – {{ $udaje['nazev'] }}</title>
    <style>
        :root { --pozadi: #f5f6f8; --karta: #ffffff; --text: #16181c; --slabe: #6b7280; --akcent: #2563eb; --okraj: #e5e7eb; }
        @media (prefers-color-scheme: dark) { :root { --pozadi: #0f1115; --karta: #171a21; --text: #eceef1; --slabe: #9aa1ab; --akcent: #60a5fa; --okraj: #2a2f38; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--pozadi); color: var(--text); font: 16px/1.65 system-ui, -apple-system, "Segoe UI", sans-serif; padding: 32px 16px; }
        main { max-width: 760px; margin: 0 auto; background: var(--karta); border: 1px solid var(--okraj); border-radius: 16px; padding: 36px 32px; }
        .nazev { font-size: 13px; letter-spacing: .06em; text-transform: uppercase; color: var(--slabe); margin: 0 0 6px; }
        h1 { font-size: 28px; line-height: 1.25; margin: 0 0 20px; }
        h2 { font-size: 19px; margin: 28px 0 8px; }
        h3 { font-size: 16px; margin: 18px 0 6px; }
        p, li { color: var(--text); }
        ul { padding-left: 1.2rem; }
        a { color: var(--akcent); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 8px 0 16px; }
        th, td { text-align: left; padding: 8px 10px 8px 0; border-bottom: 1px solid var(--okraj); vertical-align: top; }
        th { color: var(--slabe); font-weight: 500; }
        label { display: block; font-size: 14px; font-weight: 600; margin: 14px 0 4px; }
        /* Žádné prvky ve vzhledu prohlížeče: pole, roletka i zaškrtávátko mají vlastní vzhled
           a roletka je stejně vysoká jako pole (appearance: none, jinak si prohlížeč výšku přepíše). */
        input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea { width: 100%; height: 46px; font: inherit; color: var(--text); background-color: var(--pozadi); border: 1px solid var(--okraj); border-radius: 10px; padding: 0 12px; appearance: none; -webkit-appearance: none; }
        textarea { height: auto; min-height: 120px; padding: 10px 12px; }
        select { padding-right: 40px; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%236b7280' stroke-width='1.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m6 8 4 4 4-4'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; background-size: 18px; }
        input[type=checkbox], input[type=radio] { appearance: none; -webkit-appearance: none; flex: none; width: 20px; height: 20px; margin: 0; border: 1px solid var(--okraj); border-radius: 6px; background: var(--karta); display: inline-grid; place-content: center; cursor: pointer; vertical-align: middle; }
        input[type=radio] { border-radius: 50%; }
        input[type=checkbox]:checked, input[type=radio]:checked { background: var(--akcent); border-color: var(--akcent); }
        input[type=checkbox]:checked::after { content: ""; width: 10px; height: 6px; border: solid #fff; border-width: 0 0 2px 2px; transform: translate(0, -1px) rotate(-45deg); }
        input[type=radio]:checked::after { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #fff; }
        input:focus, select:focus, textarea:focus { outline: 2px solid var(--akcent); outline-offset: 1px; }
        .dvojice { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
        @media (max-width: 560px) { .dvojice { grid-template-columns: 1fr; } }
        .chyba { color: #dc2626; font-size: 13px; margin: 4px 0 0; }
        .hlaska { padding: 12px 14px; border-radius: 10px; background: color-mix(in srgb, #16a34a 14%, transparent); margin-bottom: 16px; }
        .tlacitko { margin-top: 18px; padding: 11px 20px; border: 0; border-radius: 10px; background: var(--akcent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .poznamka { font-size: 13px; color: var(--slabe); margin-top: 12px; }
        .zpet { display: inline-block; font-size: 14px; text-decoration: none; }
        .hlavicka { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; min-height: 38px; }
        body > .ozn-pruhy { margin: -32px -16px 24px; }
    </style>
</head>
<body>
    @include('oznameni.pruh')
    <main>
        <div class="hlavicka">
            <a class="zpet" href="/">← {{ $udaje['nazev'] }}</a>
            @include('oznameni.zvonecek')
        </div>
        <p class="nazev">{{ $stitek ?? 'Právní informace' }}</p>
        <h1>{{ $nadpis }}</h1>
        @include($obsah)
        @if (\Illuminate\Support\Facades\Route::has('ochrana-udaju'))
        <p style="margin-top: 32px; font-size: 14px;">
            <a href="{{ route('ochrana-udaju') }}">Ochrana osobních údajů</a> ·
            <a href="{{ route('cookies') }}">Zásady používání cookies</a>
            @if (\App\Support\NastaveniWebu::meri()) · <button type="button" data-cc="open" style="background: none; border: 0; padding: 0; font: inherit; color: var(--akcent); text-decoration: underline; cursor: pointer;">Nastavení cookies</button>@endif
        </p>
        @endif
    </main>
    @includeIf('pravni.cookie-lista')
</body>
</html>
