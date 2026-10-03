{{-- Zásady používání cookies – obsah (vzor podle simren.cz). Skládá se sám podle
     nástrojů v SEO a měření; projekt ho vloží do svého vzhledu:
     @include('pravni._cookies-obsah'). --}}
@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $nastroje = \App\Support\NastaveniWebu::nastroje();
    $analytika = $nastroje['analytics'];
    $marketing = $nastroje['marketing'];
    $google = isset($analytika['ga4']) || isset($marketing['google_ads']);
    $sprava = collect([$u['firma'] ?: $u['nazev'], $u['adresa'] ? str_replace("\n", ', ', $u['adresa']) : null, $u['ico'] ? 'IČO '.$u['ico'] : null])->filter()->join(', ');
    $popis = [
        'ga4' => ['Google Analytics 4', 'Google Ireland Limited', 'Statistika návštěvnosti a chování na webu, IP adresa se zkracuje.'],
        'seznam' => ['Měření Seznam', 'Seznam.cz, a.s.', 'Statistika návštěvnosti a měření účinnosti kampaní na Seznamu.'],
        'google_ads' => ['Google Ads', 'Google Ireland Limited', 'Měření konverzí a remarketing v reklamní síti Google.'],
        'sklik' => ['Sklik', 'Seznam.cz, a.s.', 'Retargeting a měření konverzí v reklamní síti Seznamu.'],
    ];
@endphp

<p>Tato stránka popisuje, jaké cookies web používá a jak nad nimi máte kontrolu. Správcem je {{ $sprava }}.</p>

<h2>Co jsou cookies</h2>
<p>Cookies jsou malé soubory, které web uloží do vašeho prohlížeče. Slouží k tomu, aby si stránka pamatovala váš stav, nebo aby provozovatel věděl, kolik lidí web navštívilo.</p>

<h2>Kdy se ptáme na souhlas</h2>
<p>Ukládání cookies upravuje § 89 odst. 3 zákona č. 127/2005 Sb., o elektronických komunikacích. Bez souhlasu lze ukládat jen cookies technicky nezbytné pro fungování webu; pro všechny ostatní je potřeba předchozí souhlas. Dokud nerozhodnete, žádné analytické ani marketingové skripty se nespustí. Předvyplněné souhlasy nepoužíváme.</p>

<h2>Nezbytné cookies</h2>
<table>
    <tr><th>Název</th><th>Účel</th><th>Platnost</th></tr>
    <tr><td>{{ config('session.cookie') }}</td><td>Udržuje stav relace (a přihlášení do administrace).</td><td>{{ config('session.lifetime') }} minut</td></tr>
    <tr><td>XSRF-TOKEN</td><td>Chrání formuláře před zneužitím útokem typu CSRF.</td><td>{{ config('session.lifetime') }} minut</td></tr>
    @if ($analytika || $marketing)
        <tr><td>souhlas_cookies</td><td>Pamatuje si vaši volbu v nastavení cookies.</td><td>6 měsíců</td></tr>
    @endif
</table>
<p>Nezbytné cookies jsou naše vlastní a nepředávají se nikomu dalšímu.</p>

@if ($analytika)
    <h2>Analytické cookies</h2>
    <p>Ukazují nám, kolik lidí web navštíví a které stránky si prohlédnou. Data jsou souhrnná a neslouží k identifikaci konkrétního člověka. Nastavíme je jen s vaším souhlasem.</p>
    <table>
        <tr><th>Nástroj</th><th>Poskytovatel</th><th>Účel</th></tr>
        @foreach (array_keys($analytika) as $n)
            <tr><td>{{ $popis[$n][0] }}</td><td>{{ $popis[$n][1] }}</td><td>{{ $popis[$n][2] }}</td></tr>
        @endforeach
    </table>
@endif

@if ($marketing)
    <h2>Marketingové cookies</h2>
    <p>Umožňují zobrazit vám naši reklamu na jiných webech a změřit, jestli byla účinná. Nastavíme je jen s vaším souhlasem.</p>
    <table>
        <tr><th>Nástroj</th><th>Poskytovatel</th><th>Účel</th></tr>
        @foreach (array_keys($marketing) as $n)
            <tr><td>{{ $popis[$n][0] }}</td><td>{{ $popis[$n][1] }}</td><td>{{ $popis[$n][2] }}</td></tr>
        @endforeach
    </table>
@endif

@if (! $analytika && ! $marketing)
    <p>Žádné analytické ani marketingové cookies web nepoužívá, a proto se na souhlas neptá.</p>
@endif

@if ($google)
    <h2>Předávání mimo Evropský hospodářský prostor</h2>
    <p>Aktivujete-li nástroje Google, mohou být údaje zpracovány i ve Spojených státech – jen za podmínek kapitoly V GDPR (rámec EU–US Data Privacy Framework, standardní smluvní doložky). Tyto nástroje spouštíme až po vašem souhlasu.</p>
@endif

@if ($analytika || $marketing)
    <h2>Jak volbu změnit nebo odvolat</h2>
    <p>Souhlas můžete kdykoli změnit nebo odvolat odkazem <button type="button" data-cc="open" style="background: none; border: 0; padding: 0; font: inherit; color: inherit; text-decoration: underline; cursor: pointer;">Nastavení cookies</button>. Odvolání je stejně snadné jako udělení a nemá vliv na zákonnost zpracování před ním. Volbu si pamatujeme 6 měsíců, pak se zeptáme znovu. Cookies můžete smazat nebo zakázat i v prohlížeči.</p>
@endif

<h2>Vaše práva a další informace</h2>
<p>Jak nakládáme s osobními údaji a jaká máte práva, popisuje <a href="{{ route('ochrana-udaju') }}">Ochrana osobních údajů</a>.@if ($u['email']) S dotazem se obraťte na <a href="mailto:{{ $u['email'] }}">{{ $u['email'] }}</a>.@endif</p>
