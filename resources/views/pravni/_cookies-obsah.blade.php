{{-- Zásady používání cookies – obsah (vzor podle simren.cz). Skládá se sám podle
     nástrojů v SEO a měření; projekt ho vloží do svého vzhledu:
     @include('pravni._cookies-obsah'). --}}
@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $nastroje = \App\Support\NastaveniWebu::nastroje();
    $analytika = $nastroje['analytics'];
    $marketing = $nastroje['marketing'];
    $google = isset($analytika['ga4']) || isset($marketing['google_ads']);
    $sprava = collect([$u['firma'] ?: $u['nazev'], $u['adresa'] ? str_replace("\n", ', ', $u['adresa']) : null, $u['ico'] ? __('pravni.cookies_obsah.ico', ['ico' => $u['ico']]) : null])->filter()->join(', ');
    $popis = [
        'ga4' => ['Google Analytics 4', 'Google Ireland Limited', __('pravni.cookies_obsah.statistika_navstevnosti_a_chovani_na')],
        'seznam' => [__('pravni.cookies_obsah.mereni_seznam'), 'Seznam.cz, a.s.', __('pravni.cookies_obsah.statistika_navstevnosti_a_mereni')],
        'google_ads' => ['Google Ads', 'Google Ireland Limited', __('pravni.cookies_obsah.mereni_konverzi_a_remarketing_v_reklamni')],
        'sklik' => ['Sklik', 'Seznam.cz, a.s.', __('pravni.cookies_obsah.retargeting_a_mereni_konverzi_v_reklamni')],
    ];
@endphp

<p>{{ __('pravni.cookies_obsah.tato_stranka_popisuje_jake_cookies_web', ['sprava' => $sprava]) }}</p>

<h2>{{ __('pravni.cookies_obsah.co_jsou_cookies') }}</h2>
<p>{{ __('pravni.cookies_obsah.cookies_jsou_male_soubory_ktere_web') }}</p>

<h2>{{ __('pravni.cookies_obsah.kdy_se_ptame_na_souhlas') }}</h2>
<p>{{ __('pravni.cookies_obsah.ukladani_cookies_upravuje_89_odst_3') }}</p>

<h2>{{ __('pravni.cookies_obsah.nezbytne_cookies') }}</h2>
<table>
    <tr><th>{{ __('pravni.cookies_obsah.nazev') }}</th><th>{{ __('pravni.cookies_obsah.ucel') }}</th><th>{{ __('pravni.cookies_obsah.platnost') }}</th></tr>
    <tr><td>{{ config('session.cookie') }}</td><td>{{ __('pravni.cookies_obsah.udrzuje_stav_relace_a_prihlaseni_do') }}</td><td>{{ __('pravni.cookies_obsah.minut', ['lifetime' => config('session.lifetime')]) }}</td></tr>
    <tr><td>XSRF-TOKEN</td><td>{{ __('pravni.cookies_obsah.chrani_formulare_pred_zneuzitim_utokem') }}</td><td>{{ __('pravni.cookies_obsah.minut', ['lifetime' => config('session.lifetime')]) }}</td></tr>
    @if ($analytika || $marketing)
        <tr><td>souhlas_cookies</td><td>{{ __('pravni.cookies_obsah.pamatuje_si_vasi_volbu_v_nastaveni') }}</td><td>{{ __('pravni.cookies_obsah.t6_mesicu') }}</td></tr>
    @endif
</table>
<p>{{ __('pravni.cookies_obsah.nezbytne_cookies_jsou_nase_vlastni_a') }}</p>

@if ($analytika)
    <h2>{{ __('pravni.cookies_obsah.analyticke_cookies') }}</h2>
    <p>{{ __('pravni.cookies_obsah.ukazuji_nam_kolik_lidi_web_navstivi') }}</p>
    <table>
        <tr><th>{{ __('pravni.cookies_obsah.nastroj') }}</th><th>{{ __('pravni.cookies_obsah.poskytovatel') }}</th><th>{{ __('pravni.cookies_obsah.ucel') }}</th></tr>
        @foreach (array_keys($analytika) as $n)
            <tr><td>{{ $popis[$n][0] }}</td><td>{{ $popis[$n][1] }}</td><td>{{ $popis[$n][2] }}</td></tr>
        @endforeach
    </table>
@endif

@if ($marketing)
    <h2>{{ __('pravni.cookies_obsah.marketingove_cookies') }}</h2>
    <p>{{ __('pravni.cookies_obsah.umoznuji_zobrazit_vam_nasi_reklamu_na') }}</p>
    <table>
        <tr><th>{{ __('pravni.cookies_obsah.nastroj') }}</th><th>{{ __('pravni.cookies_obsah.poskytovatel') }}</th><th>{{ __('pravni.cookies_obsah.ucel') }}</th></tr>
        @foreach (array_keys($marketing) as $n)
            <tr><td>{{ $popis[$n][0] }}</td><td>{{ $popis[$n][1] }}</td><td>{{ $popis[$n][2] }}</td></tr>
        @endforeach
    </table>
@endif

@if (! $analytika && ! $marketing)
    <p>{{ __('pravni.cookies_obsah.zadne_analyticke_ani_marketingove') }}</p>
@endif

@if ($google)
    <h2>{{ __('pravni.cookies_obsah.predavani_mimo_evropsky_hospodarsky') }}</h2>
    <p>{{ __('pravni.cookies_obsah.aktivujete_li_nastroje_google_mohou_byt') }}</p>
@endif

@if ($analytika || $marketing)
    <h2>{{ __('pravni.cookies_obsah.jak_volbu_zmenit_nebo_odvolat') }}</h2>
    <p>{{ __('pravni.cookies_obsah.souhlas_muzete_kdykoli_zmenit_nebo') }} <button type="button" data-cc="open" style="background: none; border: 0; padding: 0; font: inherit; color: inherit; text-decoration: underline; cursor: pointer;">{{ __('pravni.cookies_obsah.nastaveni_cookies') }}</button>{{ __('pravni.cookies_obsah.odvolani_je_stejne_snadne_jako_udeleni') }}</p>
@endif

<h2>{{ __('pravni.cookies_obsah.vase_prava_a_dalsi_informace') }}</h2>
<p>{{ __('pravni.cookies_obsah.jak_nakladame_s_osobnimi_udaji_a') }} <a href="{{ route('ochrana-udaju') }}">{{ __('pravni.cookies_obsah.ochrana_osobnich_udaju') }}</a>.@if ($u['email']) {{ __('pravni.cookies_obsah.s_dotazem_se_obratte_na') }} <a href="mailto:{{ $u['email'] }}">{{ $u['email'] }}</a>.@endif</p>
