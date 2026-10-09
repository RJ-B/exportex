{{-- Kostra stránek platby (výsledek, simulace). Soběstačná – projekt si ji může přepsat
     ve vlastním vzhledu; štítek TESTOVACÍ PLATBY a noindex musí zůstat. --}}
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $nadpis }} – {{ \App\Support\ZakladniUdaje::get('nazev') }}</title>
    <meta name="robots" content="noindex, nofollow">
    @if (class_exists(\App\Support\IkonaWebu::class)){{ \App\Support\IkonaWebu::html() }}@endif
    @isset($obnovit)<meta http-equiv="refresh" content="{{ $obnovit }}">@endisset
    <style>
        :root { --pozadi: #f5f6f8; --karta: #ffffff; --text: #16181c; --slabe: #6b7280; --akcent: #2563eb; --okraj: #e5e7eb; --ok: #16a34a; --chyba: #dc2626; --ceka: #d97706; }
        @media (prefers-color-scheme: dark) { :root { --pozadi: #0f1115; --karta: #171a21; --text: #eceef1; --slabe: #9aa1ab; --akcent: #60a5fa; --okraj: #2a2f38; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--pozadi); color: var(--text); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 520px; background: var(--karta); border: 1px solid var(--okraj); border-radius: 16px; padding: 36px 28px; text-align: center; }
        .ikona { width: 56px; height: 56px; margin: 0 auto 18px; border-radius: 50%; display: grid; place-items: center; font-size: 26px; font-weight: 700; }
        .nazev { font-size: 13px; letter-spacing: .06em; text-transform: uppercase; color: var(--slabe); margin: 0 0 6px; }
        h1 { font-size: 24px; line-height: 1.3; margin: 0 0 10px; }
        p { margin: 0 0 8px; color: var(--slabe); }
        .castka { font-size: 28px; font-weight: 700; color: var(--text); margin: 14px 0 4px; }
        .tlacitka { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 22px; }
        .tlacitko { display: inline-block; padding: 11px 20px; border: 0; border-radius: 10px; background: var(--akcent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .tlacitko.druhe { background: transparent; color: var(--text); border: 1px solid var(--okraj); }
        .tlacitko.cervene { background: var(--chyba); }
        form { display: inline; margin: 0; }
        .hlaska { padding: 10px 12px; border-radius: 10px; background: color-mix(in srgb, var(--chyba) 12%, transparent); color: var(--chyba); margin-bottom: 14px; }
    </style>
</head>
<body>
    @include('platby.stitek')
    <main>
        @yield('obsah')
    </main>
</body>
</html>
