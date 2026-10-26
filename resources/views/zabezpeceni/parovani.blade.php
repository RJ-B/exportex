{{-- Spárování telefonu z QR kódu (ZarizeniController::parovani). Bez přihlášení –
     odkaz sám je důkaz; umí jen přidat passkey k účtu, nikoho nepřihlásí. --}}
@php($aplikace = \App\Support\ZakladniUdaje::get('nazev') ?: config('app.name'))
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ __('web.parovani.sparovani_telefonu', ['aplikace' => $aplikace]) }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 26rem; margin: 0 auto; padding: 2rem 1rem; display: flex; flex-direction: column; gap: 1rem; }
        h1 { font-size: 1.35rem; margin: 0; }
        p { margin: 0; line-height: 1.5; color: #334155; }
        button { font: inherit; font-weight: 600; padding: .85rem 1rem; border: 0; border-radius: .6rem; background: #2563eb; color: white; cursor: pointer; }
        button:disabled { opacity: .6; cursor: default; }
        .ok { color: #15803d; font-weight: 600; }
        .chyba { color: #b91c1c; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #f1f5f9; }
            p { color: #cbd5e1; }
            .ok { color: #4ade80; }
            .chyba { color: #f87171; }
        }
    </style>
</head>
<body>
<main>
    <h1>{{ __('web.parovani.sparovani_telefonu_2') }}</h1>

    @if (! $parovani)
        <p class="chyba">{{ __('web.parovani.odkaz_uz_neplati_byl_pouzity_zruseny') }}</p>
    @else
        <p>{{ __('web.parovani.telefon_se_pripoji_k_uctu') }} <strong>{{ $parovani->user->email }}</strong> {{ __('web.parovani.v_aplikaci_potom_jim_budete_potvrzovat', ['aplikace' => $aplikace]) }}</p>
        <p>{{ __('web.parovani.klepnete_na_tlacitko_a_potvrdte_face') }}</p>
        <button id="sparovat" type="button">{{ __('web.parovani.sparovat_tento_telefon') }}</button>
        <p id="stav" role="status"></p>
    @endif
</main>

@if ($parovani)
    @include('jazyky.texty-js')
    <script src="{{ asset('js/passkeys.js') }}?v={{ @filemtime(public_path('js/passkeys.js')) }}"></script>
    <script>
        (() => {
            const tlacitko = document.getElementById('sparovat');
            const stav = document.getElementById('stav');

            if (!window.simrenPasskeys.supported()) {
                tlacitko.disabled = true;
                stav.className = 'chyba';
                stav.textContent = @js(__('web.parovani.prohlizec_passkey_neumi'));
                return;
            }

            tlacitko.addEventListener('click', async () => {
                tlacitko.disabled = true;
                stav.className = '';
                stav.textContent = @js(__('web.parovani.cekam_na_potvrzeni'));

                try {
                    const nazev = /iPhone/.test(navigator.userAgent) ? 'iPhone' : (/Android/.test(navigator.userAgent) ? 'Android' : 'Telefon');
                    await window.simrenPasskeys.pair(@json($token), nazev);
                    tlacitko.remove();
                    stav.className = 'ok';
                    stav.textContent = @js(__('web.parovani.hotovo_telefon_je_sparovany'));
                } catch (e) {
                    tlacitko.disabled = false;
                    stav.className = 'chyba';
                    stav.textContent = window.simrenPasskeys.zprava(e);
                }
            });
        })();
    </script>
@endif
</body>
</html>
