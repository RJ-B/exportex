<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Titulek, popis, canonical, Open Graph a JSON-LD (App\Support\Seo\Seo) – i ve vlastním layoutu projektu. --}}
    {{ \App\Support\Seo\Seo::uvod()->html() }}
    {{ \App\Support\IkonaWebu::html() }}
    <style>
        body { margin: 0; min-height: 100vh; display: grid; grid-template-rows: auto 1fr; place-items: center; font-family: system-ui, sans-serif; background: #f5f6f8; color: #172033; }
        body > .ozn-pruhy { justify-self: stretch; align-self: start; }
        .roh { position: fixed; top: 12px; right: 12px; }
        main { text-align: center; padding: 24px; }
        a { color: #2563eb; }
        @media (prefers-color-scheme: dark) { body { background: #10141c; color: #e6e9f0; } a { color: #6ea0ff; } }
    </style>
</head>
<body>
    @include('oznameni.pruh')
    @includeWhen(config('sablona.doplnky.platby'), 'platby.stitek')
    @auth<div class="roh">@include('oznameni.zvonecek')</div>@endauth
    <main style="grid-row: 2;">
        <h1>{{ \App\Support\ZakladniUdaje::get('nazev') }}</h1>
        <p><a href="{{ url('/admin') }}">{{ __('web.welcome.prihlasit_se_do_administrace') }}</a></p>
    </main>
    {{-- Cookie lišta patří na konec každé veřejné stránky (ukáže se, jen když web měří). --}}
    @include('pravni.cookie-lista')
</body>
</html>
