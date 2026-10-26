{{-- Cookies – anglicky (souhrn k českému vzoru pravni/_cookies-obsah + _uloziste-cs). --}}
@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $meri = \App\Support\NastaveniWebu::meri();
@endphp
<p>{{ __('pravni.cookies_en.this_page_explains_which_cookies_the', ['nazev' => $u['firma'] ?: $u['nazev'], 'u' => $u['ico'] ? ', company number '.$u['ico'] : '']) }}</p>

<h2>{{ __('pravni.cookies_en.strictly_necessary_cookies') }}</h2>
<table>
    <tr><th>{{ __('pravni.cookies_en.name') }}</th><th>{{ __('pravni.cookies_en.purpose') }}</th><th>{{ __('pravni.cookies_en.lifetime') }}</th></tr>
    <tr><td>{{ config('session.cookie') }}</td><td>{{ __('pravni.cookies_en.keeps_the_session_state_and_the') }}</td><td>{{ __('pravni.cookies_en.minutes', ['lifetime' => config('session.lifetime')]) }}</td></tr>
    <tr><td>XSRF-TOKEN</td><td>{{ __('pravni.cookies_en.protects_forms_against_cross_site') }}</td><td>{{ __('pravni.cookies_en.minutes', ['lifetime' => config('session.lifetime')]) }}</td></tr>
    @if ($meri)
        <tr><td>souhlas_cookies</td><td>{{ __('pravni.cookies_en.remembers_your_choice_in_the_cookie') }}</td><td>{{ __('pravni.cookies_en.t6_months') }}</td></tr>
    @endif
</table>
<p>{{ __('pravni.cookies_en.these_cookies_are_our_own_are') }}</p>

@if ($meri)
    <h2>{{ __('pravni.cookies_en.analytics_and_marketing') }}</h2>
    <p>{{ __('pravni.cookies_en.analytics_or_marketing_tools_only_run') }} <button type="button" data-cc="open">{{ __('pravni.cookies_en.cookie_settings') }}</button>{{ __('pravni.cookies_en.details_are_in_the_czech_version') }}</p>
@else
    <h2>{{ __('pravni.cookies_en.no_analytics_no_advertising') }}</h2>
    <p>{{ __('pravni.cookies_en.there_is_no_analytics_advertising_or') }}</p>
@endif

<h2>{{ __('pravni.cookies_en.what_is_stored_in_your_browser') }}</h2>
<p>{{ __('pravni.cookies_en.the_site_keeps_three_small_items') }}</p>
<ul>
    <li><strong>{{ __('pravni.cookies_en.exportex_lang_v2') }}</strong> {{ __('pravni.cookies_en.the_language_you_picked_czech_or') }}</li>
    <li><strong>{{ __('pravni.cookies_en.exportex_theme_v2') }}</strong> {{ __('pravni.cookies_en.the_light_or_dark_mode_you') }}</li>
    <li><strong>{{ __('pravni.cookies_en.exportex_scroll') }}</strong> {{ __('pravni.cookies_en.how_far_down_the_page_you') }}</li>
</ul>
<p>{{ __('pravni.cookies_en.all_of_it_stays_in_your') }}</p>

<h2>{{ __('pravni.cookies_en.external_content') }}</h2>
<p>{{ __('pravni.cookies_en.the_site_loads_everything_fonts_included') }}</p>

<h2>{{ __('pravni.cookies_en.your_rights') }}</h2>
<p>{{ __('pravni.cookies_en.how_we_handle_personal_data_and') }} <a href="{{ route('ochrana-udaju') }}">{{ __('pravni.cookies_en.privacy_notice') }}</a>.@if ($u['email']) {{ __('pravni.cookies_en.questions') }} <a href="mailto:{{ $u['email'] }}">{{ $u['email'] }}</a>.@endif</p>
