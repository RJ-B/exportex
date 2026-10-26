{{-- Ochrana osobních údajů – obsah (vzor podle simren.cz). Skládá se sám:
     správce ze Základních údajů, části podle toho, co web dělá. Projekt ho vloží
     do svého vzhledu webu: @include('pravni._ochrana-obsah'). Údaje k doplnění:
     Obsah webu → Ochrana osobních údajů (App\Support\OchranaUdaju). --}}
@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $g = \App\Support\OchranaUdaju::nacti();
    $w = \App\Support\NastaveniWebu::nacti();
    $email = $u['email'];
    $formular = \App\Support\SekceWebu::zapnuta('formular');
    // Jen nástroje, které se na webu opravdu spustí (měření zapnuté, lišta zapnutá, ID vyplněné).
    $nastroje = \App\Support\NastaveniWebu::nastroje();
    $google = isset($nastroje['analytics']['ga4']) || isset($nastroje['marketing']['google_ads']);
    $seznam = isset($nastroje['analytics']['seznam']) || isset($nastroje['marketing']['sklik']);
    $analytika = $nastroje['analytics'] !== [] || $nastroje['marketing'] !== [];
    $nazvyNastroju = collect([...array_keys($nastroje['analytics']), ...array_keys($nastroje['marketing'])])
        ->map(fn ($n) => ['ga4' => 'Google Analytics', 'seznam' => __('pravni.ochrana_obsah.mereni_seznam_cz'), 'google_ads' => 'Google Ads', 'sklik' => 'Sklik'][$n])->join(', ');
    $smlouvy = $g['smlouvy'] === '1';
    $cookies = \Illuminate\Support\Facades\Route::has('cookies') ? route('cookies') : null;
    $ucinnost = $g['ucinnost_od'] ? \Illuminate\Support\Carbon::parse($g['ucinnost_od'])->format(__('formaty.datum')) : null;
    $kontakt = fn () => $email ? '<a href="mailto:'.e($email).'">'.e($email).'</a>' : __('pravni.ochrana_obsah.kontaktech_uvedenych_vyse');
    // Oznámení (docs/oznameni.md) – část jen, když je aplikace opravdu posílá.
    $oznameni = (bool) rescue(fn () => \App\Models\Oznameni::query()->exists() || \App\Models\OznameniSouhlas::query()->exists(), false, false);
    // Doplněk Platby (docs/platby.md) – část jen se zapnutým doplňkem; brána podle Nastavení → Platební brána.
    $platby = (bool) config('sablona.doplnky.platby');
    $branaPlateb = $platby ? (\App\Platby\NastaveniPlateb::BRANY[\App\Platby\NastaveniPlateb::brana() ?? ''] ?? null) : null;
    $n = 0;
@endphp

<p>{{ __('pravni.ochrana_obsah.tento_dokument_popisuje_jake_osobni') }}</p>
@if ($ucinnost)
    <p><em>{{ __('pravni.ochrana_obsah.ucinne_od', ['ucinnost' => $ucinnost]) }}</em></p>
@endif

<h2>{{ __('pravni.ochrana_obsah.kdo_je_spravce', ['hodnota' => ++$n]) }}</h2>
<p>
    {{ $u['firma'] ?: $u['nazev'] }}<br>
    @if ($u['adresa']){!! nl2br(e($u['adresa'])) !!}<br>@endif
    @if ($u['ico']){{ __('pravni.ochrana_obsah.ico', ['ico' => $u['ico']]) }}<br>@endif
    @if ($u['rejstrik']){{ $u['rejstrik'] }}<br>@endif
    @if ($email){{ __('pravni.ochrana_obsah.e_mail') }} <a href="mailto:{{ $email }}">{{ $email }}</a><br>@endif
    @if ($u['telefon']){{ __('pravni.ochrana_obsah.telefon', ['telefon' => $u['telefon']]) }}@endif
</p>
@if ($g['poverenec'])
    <p>{{ __('pravni.ochrana_obsah.poverencem_pro_ochranu_osobnich_udaju_je', ['poverenec' => $g['poverenec']]) }}</p>
@else
    <p>{{ __('pravni.ochrana_obsah.poverence_pro_ochranu_osobnich_udaju') }} {!! $kontakt() !!}.</p>
@endif

<h2>{{ __('pravni.ochrana_obsah.jake_udaje_zpracovavame_a_proc', ['hodnota' => ++$n]) }}</h2>
@php($m = 0)

@if ($formular)
    <h3>{{ __('pravni.ochrana_obsah.zprava_z_kontaktniho_formulare', ['n' => $n, 'hodnota' => ++$m]) }}</h3>
    <p><strong>{{ __('pravni.ochrana_obsah.zpracovavane_udaje') }}</strong> {{ __('pravni.ochrana_obsah.dale_ip_adresa_a_cas_odeslani', ['formular_udaje' => $g['formular_udaje']]) }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.ucel') }}</strong> {{ __('pravni.ochrana_obsah.vyrizeni_vasi_zpravy_nebo_poptavky_a') }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.pravni_zaklad') }}</strong> {{ __('pravni.ochrana_obsah.provedeni_opatreni_pred_uzavrenim') }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.doba_ulozeni') }}</strong> {{ __('pravni.ochrana_obsah.neni_li_dalsi_uchovani_nezbytne_z', ['formular_doba' => $g['formular_doba']]) }}</p>
@endif

@if ($smlouvy)
    <h3>{{ __('pravni.ochrana_obsah.uzavreni_a_plneni_smlouvy', ['n' => $n, 'hodnota' => ++$m]) }}</h3>
    <p><strong>{{ __('pravni.ochrana_obsah.zpracovavane_udaje') }}</strong> {{ __('pravni.ochrana_obsah.identifikacni_fakturacni_a_kontaktni') }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.ucel') }}</strong> {{ __('pravni.ochrana_obsah.uzavreni_a_plneni_smlouvy_komunikace_se') }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.pravni_zaklad') }}</strong> {{ __('pravni.ochrana_obsah.plneni_smlouvy_podle_cl_6_odst') }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.doba_ulozeni') }}</strong> {{ __('pravni.ochrana_obsah.ucetni_a_danove_doklady_uchovavame_po', ['smlouvy_doba' => $g['smlouvy_doba']]) }}</p>
@endif

@if ($platby)
    <h3>{{ __('pravni.ochrana_obsah.platby', ['n' => $n, 'hodnota' => ++$m]) }}</h3>
    <p><strong>{{ __('pravni.ochrana_obsah.zpracovavane_udaje') }}</strong> {{ __('pravni.ochrana_obsah.jmeno_a_prijmeni_e_mailova_adresa', ['brana_plateb' => $branaPlateb ? ' '.$branaPlateb : '']) }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.ucel') }}</strong> {{ __('pravni.ochrana_obsah.prijeti_platby_potvrzeni_o_zaplaceni') }}</p>
    @include('platby._o-brane')
    <p><strong>{{ __('pravni.ochrana_obsah.pravni_zaklad') }}</strong> {{ __('pravni.ochrana_obsah.plneni_smlouvy_podle_cl_6_odst_2') }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.doba_ulozeni') }}</strong> {{ __('pravni.ochrana_obsah.po_dobu_stanovenou_ucetnimi_a_danovymi') }}</p>
@endif

<h3>{{ __('pravni.ochrana_obsah.provoz_a_zabezpeceni_webu', ['n' => $n, 'hodnota' => ++$m]) }}</h3>
<p><strong>{{ __('pravni.ochrana_obsah.zpracovavane_udaje') }}</strong> {{ __('pravni.ochrana_obsah.ip_adresa_typ_prohlizece_cas_pristupu') }}</p>
<p><strong>{{ __('pravni.ochrana_obsah.ucel') }}</strong> {{ __('pravni.ochrana_obsah.bezpecny_a_spolehlivy_provoz_webu') }}</p>
<p><strong>{{ __('pravni.ochrana_obsah.pravni_zaklad') }}</strong> {{ __('pravni.ochrana_obsah.nas_opravneny_zajem_podle_cl_6') }}</p>
<p><strong>{{ __('pravni.ochrana_obsah.doba_ulozeni') }}</strong> {{ __('pravni.ochrana_obsah.zpravidla_nejdele_6_mesicu_pokud_neni') }}</p>

@if ($oznameni)
    <h3>{{ __('pravni.ochrana_obsah.oznameni_a_novinky', ['n' => $n, 'hodnota' => ++$m]) }}</h3>
    <p><strong>{{ __('pravni.ochrana_obsah.zpracovavane_udaje') }}</strong> {{ __('pravni.ochrana_obsah.jmeno_a_prijmeni_e_mailova_adresa_2') }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.ucel') }}</strong> {{ __('pravni.ochrana_obsah.informovat_vas_o_vasem_uctu_objednavkach') }}@if (\App\Support\Oznameni\NastaveniOznameni::marketing()) {{ __('pravni.ochrana_obsah.a_nabidkach') }}@endif.</p>
    <p><strong>{{ __('pravni.ochrana_obsah.pravni_zaklad') }}</strong> {{ __('pravni.ochrana_obsah.provozni_a_servisni_oznameni_zasilame_na') }}@if (\App\Support\Oznameni\NastaveniOznameni::marketing()) {{ __('pravni.ochrana_obsah.a_nabidky') }}@endif {{ __('pravni.ochrana_obsah.e_mailem_posilame_jen_s_vasim') }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.doba_ulozeni') }}</strong> {{ __('pravni.ochrana_obsah.po_dobu_trvani_vaseho_uctu') }}</p>
@endif

@if ($analytika)
    <h3>{{ __('pravni.ochrana_obsah.analytika_a_marketing', ['n' => $n, 'hodnota' => ++$m]) }}</h3>
    <p><strong>{{ __('pravni.ochrana_obsah.zpracovavane_udaje') }}</strong> {{ __('pravni.ochrana_obsah.udaje_o_vasem_chovani_na_webu', ['nazvy_nastroju' => $nazvyNastroju]) }}</p>
    <p><strong>{{ __('pravni.ochrana_obsah.ucel') }}</strong> {{ __('pravni.ochrana_obsah.analyza_navstevnosti_a_pouzivani_webu') }}@if ($nastroje['marketing']){{ __('pravni.ochrana_obsah.vyhodnocovani_reklamy_a_zobrazovani') }}@endif.</p>
    <p><strong>{{ __('pravni.ochrana_obsah.pravni_zaklad') }}</strong> {{ __('pravni.ochrana_obsah.vas_souhlas_podle_cl_6_odst') }}</p>
    @if ($cookies)
        <p>{{ __('pravni.ochrana_obsah.podrobnosti_o_cookies_jsou_v') }} <a href="{{ $cookies }}">{{ __('pravni.ochrana_obsah.zasadach_pouzivani_cookies') }}</a>.</p>
    @endif
@endif

@if ($g['doplnek'])
    {!! str($g['doplnek'])->sanitizeHtml() !!}
@endif

<h2>{{ __('pravni.ochrana_obsah.odkud_osobni_udaje_ziskavame', ['hodnota' => ++$n]) }}</h2>
<p>{{ __('pravni.ochrana_obsah.osobni_udaje_ziskavame_predevsim_primo') }}</p>

<h2>{{ __('pravni.ochrana_obsah.komu_osobni_udaje_predavame', ['hodnota' => ++$n]) }}</h2>
<p>{{ __('pravni.ochrana_obsah.vase_osobni_udaje_neprodavame_v') }}</p>
<ul>
    <li>{{ __('pravni.ochrana_obsah.poskytovatele_serveru_a_hostingu') }}</li>
    <li>{{ __('pravni.ochrana_obsah.poskytovatele_e_mailovych_sluzeb') }}</li>
    <li>{{ __('pravni.ochrana_obsah.poskytovatele_it_sluzeb_a_spravy_webu') }}</li>
    @if ($smlouvy)<li>{{ __('pravni.ochrana_obsah.ucetni_danovi_a_pravni_poradci') }}</li>@endif
    @if ($platby)<li>{{ __('pravni.ochrana_obsah.provozovatel_platebni_brany_zpracovani', ['brana_plateb' => $branaPlateb ? ' '.$branaPlateb : '']) }}</li>@endif
    @if ($google)<li>{{ __('pravni.ochrana_obsah.google_ireland_limited_analytika_a') }}</li>@endif
    @if ($seznam)<li>{{ __('pravni.ochrana_obsah.seznam_cz_a_s_mereni_a') }}</li>@endif
    @foreach (\App\Support\OchranaUdaju::dalsiPrijemci() as $prijemce)
        <li>{{ $prijemce }};</li>
    @endforeach
</ul>
<p>{{ __('pravni.ochrana_obsah.s_poskytovateli_kteri_pro_nas_vystupuji') }}</p>

@if ($google)
    <h2>{{ __('pravni.ochrana_obsah.predavani_mimo_evropsky_hospodarsky', ['hodnota' => ++$n]) }}</h2>
    <p>{{ __('pravni.ochrana_obsah.u_sluzeb_spolecnosti_google_muze') }}</p>
@endif

<h2>{{ __('pravni.ochrana_obsah.automatizovane_rozhodovani_a_profilovani', ['hodnota' => ++$n]) }}</h2>
<p>{{ __('pravni.ochrana_obsah.nepouzivame_automatizovane_individualni') }}</p>

<h2>{{ __('pravni.ochrana_obsah.jak_osobni_udaje_chranime', ['hodnota' => ++$n]) }}</h2>
<p>{{ __('pravni.ochrana_obsah.web_pouziva_sifrovane_spojeni_https_k') }}</p>

<h2>{{ __('pravni.ochrana_obsah.vase_prava', ['hodnota' => ++$n]) }}</h2>
<ul>
    <li>{{ __('pravni.ochrana_obsah.pravo_na_pristup_k_osobnim_udajum') }}</li>
    <li>{{ __('pravni.ochrana_obsah.pravo_na_opravu_nepresnych_nebo_doplneni') }}</li>
    <li>{{ __('pravni.ochrana_obsah.pravo_na_vymaz_za_podminek_stanovenych') }}</li>
    <li>{{ __('pravni.ochrana_obsah.pravo_na_omezeni_zpracovani') }}</li>
    <li>{{ __('pravni.ochrana_obsah.pravo_na_prenositelnost_udaju_za') }}</li>
    <li>{{ __('pravni.ochrana_obsah.pravo_vznest_namitku_proti_zpracovani') }}</li>
    <li>{{ __('pravni.ochrana_obsah.pravo_kdykoli_odvolat_souhlas_je_li') }}</li>
</ul>
<p>{{ __('pravni.ochrana_obsah.zadost_muzete_poslat_na') }} {!! $kontakt() !!}{{ __('pravni.ochrana_obsah.vyridime_ji_bez_zbytecneho_odkladu') }}</p>

<h2>{{ __('pravni.ochrana_obsah.stiznost_u_dozoroveho_uradu', ['hodnota' => ++$n]) }}</h2>
<p>{{ __('pravni.ochrana_obsah.pokud_se_domnivate_ze_pri_zpracovani') }} <a href="https://uoou.gov.cz/" target="_blank" rel="noopener">uoou.gov.cz</a>{{ __('pravni.ochrana_obsah.budeme_radi_kdyz_se_nejdriv_obratite') }}</p>

<h2>{{ __('pravni.ochrana_obsah.zmeny_tohoto_dokumentu', ['hodnota' => ++$n]) }}</h2>
<p>{{ __('pravni.ochrana_obsah.dokument_prubezne_aktualizujeme_zejmena_2') }}@if ($ucinnost) {{ __('pravni.ochrana_obsah.spolu_s_datem_ucinnosti') }}@endif.</p>
