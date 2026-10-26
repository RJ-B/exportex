{{-- Ochrana osobních údajů – anglicky. Souhrn k českému vzoru šablony
     (pravni/_ochrana-obsah) se stejnými údaji ze Základních údajů; závazné je
     české znění. Při změně zpracování (nový formulář, měření) upravit obojí. --}}
@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $en = \App\Support\ObsahWebu::sekce('paticka');
    $email = $u['email'];
    $meri = \App\Support\NastaveniWebu::meri();
    $ucinnost = \App\Support\OchranaUdaju::nacti()['ucinnost_od'];
@endphp
<p>{{ __('pravni.ochrana_en.this_notice_explains_what_personal_data') }} <em>{{ __('pravni.ochrana_en.this_is_a_summary_the_czech') }}</em></p>
@if ($ucinnost)
    <p><em>{{ __('pravni.ochrana_en.effective_from', ['parse' => \Illuminate\Support\Carbon::parse($ucinnost)->format('j F Y')]) }}</em></p>
@endif

<h2>{{ __('pravni.ochrana_en.t1_who_is_the_controller') }}</h2>
<p>
    {{ $u['firma'] ?: $u['nazev'] }}<br>
    @if ($en['adresa_en'] || $u['adresa']){{ $en['adresa_en'] ?: str_replace("\n", ', ', $u['adresa']) }}<br>@endif
    @if ($u['ico']){{ __('pravni.ochrana_en.company_number', ['ico' => $u['ico']]) }}<br>@endif
    @if ($en['rejstrik_en'] || $u['rejstrik']){{ $en['rejstrik_en'] ?: $u['rejstrik'] }}<br>@endif
    @if ($email){{ __('pravni.ochrana_en.e_mail') }} <a href="mailto:{{ $email }}">{{ $email }}</a><br>@endif
    @if ($u['telefon']){{ __('pravni.ochrana_en.phone', ['telefon' => $u['telefon']]) }}@endif
</p>

<h2>{{ __('pravni.ochrana_en.t2_what_data_we_process_and') }}</h2>
<h3>{{ __('pravni.ochrana_en.t2_1_enquiries_and_communication') }}</h3>
<p>{{ __('pravni.ochrana_en.if_you_contact_us_through_the') }}</p>
<h3>{{ __('pravni.ochrana_en.t2_2_contracts') }}</h3>
<p>{{ __('pravni.ochrana_en.if_a_transaction_takes_place_we') }}</p>
<h3>{{ __('pravni.ochrana_en.t2_3_website_operation_and_security') }}</h3>
<p>{{ __('pravni.ochrana_en.our_server_keeps_technical_logs_ip') }}</p>
@unless ($meri)
<p>{{ __('pravni.ochrana_en.the_website_itself_collects_nothing') }}</p>
@endunless

<h2>{{ __('pravni.ochrana_en.t3_how_long_we_keep_it') }}</h2>
<p>{{ __('pravni.ochrana_en.we_keep_enquiries_for_the_duration') }}</p>

<h2>{{ __('pravni.ochrana_en.t4_who_has_access') }}</h2>
<p>{{ __('pravni.ochrana_en.we_do_not_pass_data_to') }}</p>

<h2>{{ __('pravni.ochrana_en.t5_your_rights') }}</h2>
<p>{{ __('pravni.ochrana_en.you_have_the_right_to_access') }}@if ($email) {{ __('pravni.ochrana_en.just_write_to') }} <a href="mailto:{{ $email }}">{{ $email }}</a>.@endif {{ __('pravni.ochrana_en.if_you_believe_we_are_not') }} <a href="https://uoou.gov.cz/" target="_blank" rel="noopener">uoou.gov.cz</a>).</p>

<h2>{{ __('pravni.ochrana_en.t6_cookies') }}</h2>
<p>{{ __('pravni.ochrana_en.see') }} <a href="{{ route('cookies') }}">{{ __('pravni.ochrana_en.cookies_and_local_storage') }}</a>.</p>
