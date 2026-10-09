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
        ->map(fn ($n) => ['ga4' => 'Google Analytics', 'seznam' => 'měření Seznam.cz', 'google_ads' => 'Google Ads', 'sklik' => 'Sklik'][$n])->join(', ');
    $smlouvy = $g['smlouvy'] === '1';
    $cookies = \Illuminate\Support\Facades\Route::has('cookies') ? route('cookies') : null;
    $ucinnost = $g['ucinnost_od'] ? \Illuminate\Support\Carbon::parse($g['ucinnost_od'])->format('j. n. Y') : null;
    $kontakt = fn () => $email ? '<a href="mailto:'.e($email).'">'.e($email).'</a>' : 'kontaktech uvedených výše';
    // Oznámení (docs/oznameni.md) – část jen, když je aplikace opravdu posílá.
    $oznameni = (bool) rescue(fn () => \App\Models\Oznameni::query()->exists() || \App\Models\OznameniSouhlas::query()->exists(), false, false);
    $n = 0;
@endphp

<p>Tento dokument popisuje, jaké osobní údaje zpracováváme, za jakým účelem a jaká práva vám v souvislosti se zpracováním osobních údajů náleží. Při zpracování osobních údajů se řídíme zejména nařízením Evropského parlamentu a Rady (EU) 2016/679 (GDPR) a zákonem č. 110/2019 Sb., o zpracování osobních údajů.</p>
@if ($ucinnost)
    <p><em>Účinné od {{ $ucinnost }}.</em></p>
@endif

<h2>{{ ++$n }}. Kdo je správce</h2>
<p>
    {{ $u['firma'] ?: $u['nazev'] }}<br>
    @if ($u['adresa']){!! nl2br(e($u['adresa'])) !!}<br>@endif
    @if ($u['ico'])IČO: {{ $u['ico'] }}<br>@endif
    @if ($u['rejstrik']){{ $u['rejstrik'] }}<br>@endif
    @if ($email)E-mail: <a href="mailto:{{ $email }}">{{ $email }}</a><br>@endif
    @if ($u['telefon'])Telefon: {{ $u['telefon'] }}@endif
</p>
@if ($g['poverenec'])
    <p>Pověřencem pro ochranu osobních údajů je {{ $g['poverenec'] }}.</p>
@else
    <p>Pověřence pro ochranu osobních údajů jsme nejmenovali. Ve věcech ochrany osobních údajů nás můžete kontaktovat na {!! $kontakt() !!}.</p>
@endif

<h2>{{ ++$n }}. Jaké údaje zpracováváme a proč</h2>
@php($m = 0)

@if ($formular)
    <h3>{{ $n }}.{{ ++$m }}. Zpráva z kontaktního formuláře</h3>
    <p><strong>Zpracovávané údaje:</strong> {{ $g['formular_udaje'] }}, dále IP adresa a čas odeslání formuláře.</p>
    <p><strong>Účel:</strong> vyřízení vaší zprávy nebo poptávky a komunikace s vámi.</p>
    <p><strong>Právní základ:</strong> provedení opatření před uzavřením smlouvy na vaši žádost podle čl. 6 odst. 1 písm. b) GDPR, případně náš oprávněný zájem odpovědět na váš dotaz podle čl. 6 odst. 1 písm. f) GDPR. IP adresu a čas odeslání zpracováváme na základě oprávněného zájmu na ochraně formuláře před zneužitím a spamem.</p>
    <p><strong>Doba uložení:</strong> {{ $g['formular_doba'] }}, není-li další uchování nezbytné z jiného zákonného důvodu nebo pro ochranu našich práv.</p>
@endif

@if ($smlouvy)
    <h3>{{ $n }}.{{ ++$m }}. Uzavření a plnění smlouvy</h3>
    <p><strong>Zpracovávané údaje:</strong> identifikační, fakturační a kontaktní údaje, údaje kontaktních osob a komunikace související se smlouvou.</p>
    <p><strong>Účel:</strong> uzavření a plnění smlouvy, komunikace se zákazníkem, fakturace a plnění souvisejících právních povinností.</p>
    <p><strong>Právní základ:</strong> plnění smlouvy podle čl. 6 odst. 1 písm. b) GDPR, plnění zákonných povinností (zejména účetních a daňových) podle čl. 6 odst. 1 písm. c) GDPR a u kontaktních osob obchodních partnerů náš oprávněný zájem podle čl. 6 odst. 1 písm. f) GDPR.</p>
    <p><strong>Doba uložení:</strong> {{ $g['smlouvy_doba'] }}. Účetní a daňové doklady uchováváme po dobu stanovenou právními předpisy.</p>
@endif

<h3>{{ $n }}.{{ ++$m }}. Provoz a zabezpečení webu</h3>
<p><strong>Zpracovávané údaje:</strong> IP adresa, typ prohlížeče, čas přístupu, navštívené adresy a další technické informace v serverových protokolech.</p>
<p><strong>Účel:</strong> bezpečný a spolehlivý provoz webu, diagnostika problémů a odhalování útoků a zneužití.</p>
<p><strong>Právní základ:</strong> náš oprávněný zájem podle čl. 6 odst. 1 písm. f) GDPR.</p>
<p><strong>Doba uložení:</strong> zpravidla nejdéle 6 měsíců, pokud není delší uchování nezbytné k řešení bezpečnostního incidentu nebo ochraně našich práv.</p>

@if ($oznameni)
    <h3>{{ $n }}.{{ ++$m }}. Oznámení a novinky</h3>
    <p><strong>Zpracovávané údaje:</strong> jméno a příjmení, e-mailová adresa, která oznámení jsme vám poslali, zda jste je přečetli nebo otevřeli odkaz v nich (zjišťujeme to jen v naší aplikaci – bez měřicích pixelů a bez sledování na jiných webech), vaše předvolby oznámení a u souhlasu s novinkami záznam o jeho udělení či odvolání (čas, znění souhlasu, IP adresa a prohlížeč).</p>
    <p><strong>Účel:</strong> informovat vás o vašem účtu, objednávkách a provozu služby (odstávky, výpadky, důležité změny) a s vaším souhlasem také o novinkách@if (\App\Support\Oznameni\NastaveniOznameni::marketing()) a nabídkách@endif.</p>
    <p><strong>Právní základ:</strong> provozní a servisní oznámení zasíláme na základě plnění smlouvy podle čl. 6 odst. 1 písm. b) GDPR a našeho oprávněného zájmu na řádném provozu služby podle čl. 6 odst. 1 písm. f) GDPR. Novinky@if (\App\Support\Oznameni\NastaveniOznameni::marketing()) a nabídky@endif e-mailem posíláme jen s vaším souhlasem podle čl. 6 odst. 1 písm. a) GDPR a zákona č. 480/2004 Sb.; souhlas můžete kdykoli odvolat odkazem v každém e-mailu nebo v předvolbách oznámení, odvolání nemá vliv na zákonnost zasílání před ním.</p>
    <p><strong>Doba uložení:</strong> po dobu trvání vašeho účtu.</p>
@endif

@if ($analytika)
    <h3>{{ $n }}.{{ ++$m }}. Analytika a marketing</h3>
    <p><strong>Zpracovávané údaje:</strong> údaje o vašem chování na webu v rozsahu nastavení použitých nástrojů ({{ $nazvyNastroju }}), například navštívené stránky, zařízení a zdroj návštěvy.</p>
    <p><strong>Účel:</strong> analýza návštěvnosti a používání webu@if ($nastroje['marketing']), vyhodnocování reklamy a zobrazování relevantní reklamy@endif.</p>
    <p><strong>Právní základ:</strong> váš souhlas podle čl. 6 odst. 1 písm. a) GDPR. Bez souhlasu se nástroje, které ho vyžadují, nespustí. Souhlas můžete kdykoli změnit nebo odvolat v nastavení cookies na webu; odvolání nemá vliv na zákonnost zpracování před ním.</p>
    @if ($cookies)
        <p>Podrobnosti o cookies jsou v <a href="{{ $cookies }}">Zásadách používání cookies</a>.</p>
    @endif
@endif

@if ($g['doplnek'])
    {!! str($g['doplnek'])->sanitizeHtml() !!}
@endif

<h2>{{ ++$n }}. Odkud osobní údaje získáváme</h2>
<p>Osobní údaje získáváme především přímo od vás – z formuláře na webu, e-mailem, telefonicky nebo při jednání. Kontaktní údaje můžeme výjimečně získat od vašeho zaměstnavatele, obchodního partnera nebo z veřejných rejstříků. Databáze osobních údajů pro marketing nekupujeme.</p>

<h2>{{ ++$n }}. Komu osobní údaje předáváme</h2>
<p>Vaše osobní údaje neprodáváme. V nezbytném rozsahu je mohou zpracovávat poskytovatelé služeb, kteří nám pomáhají s provozem, zejména:</p>
<ul>
    <li>poskytovatelé serverů a hostingu;</li>
    <li>poskytovatelé e-mailových služeb;</li>
    <li>poskytovatelé IT služeb a správy webu;</li>
    @if ($smlouvy)<li>účetní, daňoví a právní poradci;</li>@endif
    @if ($google)<li>Google Ireland Limited (analytika a reklama), pokud jste udělili souhlas;</li>@endif
    @if ($seznam)<li>Seznam.cz, a.s. (měření a reklama), pokud jste udělili souhlas;</li>@endif
    @foreach (\App\Support\OchranaUdaju::dalsiPrijemci() as $prijemce)
        <li>{{ $prijemce }};</li>
    @endforeach
</ul>
<p>S poskytovateli, kteří pro nás vystupují jako zpracovatelé, máme zajištěny smluvní podmínky podle čl. 28 GDPR. Údaje můžeme předat také orgánům veřejné moci, pokud nám to ukládá právní předpis nebo je to nezbytné k ochraně našich práv.</p>

@if ($google)
    <h2>{{ ++$n }}. Předávání mimo Evropský hospodářský prostor</h2>
    <p>U služeb společnosti Google může docházet k předávání údajů do Spojených států amerických. Děje se tak jen za podmínek kapitoly V GDPR – na základě rámce EU–US Data Privacy Framework, standardních smluvních doložek nebo jiného mechanismu podle GDPR – a jen s vaším souhlasem s měřením návštěvnosti.</p>
@endif

<h2>{{ ++$n }}. Automatizované rozhodování a profilování</h2>
<p>Nepoužíváme automatizované individuální rozhodování ani profilování, které by pro vás mělo právní účinky nebo se vás obdobně významně dotýkalo ve smyslu čl. 22 GDPR.</p>

<h2>{{ ++$n }}. Jak osobní údaje chráníme</h2>
<p>Web používá šifrované spojení HTTPS. K údajům mají přístup jen osoby, které je potřebují ke své práci a jsou vázány mlčenlivostí. Používáme přiměřená technická a organizační opatření proti neoprávněnému přístupu, ztrátě nebo zneužití, systémy pravidelně aktualizujeme a data zálohujeme.</p>

<h2>{{ ++$n }}. Vaše práva</h2>
<ul>
    <li>právo na přístup k osobním údajům a na jejich kopii;</li>
    <li>právo na opravu nepřesných nebo doplnění neúplných údajů;</li>
    <li>právo na výmaz za podmínek stanovených GDPR;</li>
    <li>právo na omezení zpracování;</li>
    <li>právo na přenositelnost údajů za podmínek čl. 20 GDPR;</li>
    <li>právo vznést námitku proti zpracování založenému na oprávněném zájmu;</li>
    <li>právo kdykoli odvolat souhlas, je-li zpracování založeno na souhlasu.</li>
</ul>
<p>Žádost můžete poslat na {!! $kontakt() !!}. Vyřídíme ji bez zbytečného odkladu, zpravidla do jednoho měsíce; ve složitějších případech můžeme lhůtu prodloužit až o dva měsíce, o čemž vás budeme informovat. Vyřízení je zpravidla bezplatné. Při důvodných pochybnostech o totožnosti žadatele můžeme požádat o její ověření.</p>

<h2>{{ ++$n }}. Stížnost u dozorového úřadu</h2>
<p>Pokud se domníváte, že při zpracování vašich osobních údajů porušujeme právní předpisy, můžete podat stížnost u Úřadu pro ochranu osobních údajů, Pplk. Sochora 27, 170 00 Praha 7, <a href="https://uoou.gov.cz/" target="_blank" rel="noopener">uoou.gov.cz</a>. Budeme rádi, když se nejdřív obrátíte na nás – vaše právo obrátit se přímo na úřad tím není dotčeno.</p>

<h2>{{ ++$n }}. Změny tohoto dokumentu</h2>
<p>Dokument průběžně aktualizujeme, zejména když se změní způsob zpracování, používané služby nebo právní předpisy. Aktuální verze je vždy na této stránce@if ($ucinnost) spolu s datem účinnosti@endif.</p>
