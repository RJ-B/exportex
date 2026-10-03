{{-- Cookie lišta (vzor podle simren.cz). Vložit na konec <body> každé veřejné stránky:
     @include('pravni.cookie-lista'). Tlačítko „Nastavení cookies“ kdekoli (patička):
     <button type="button" data-cc="open">Nastavení cookies</button>

     Ukáže se, jen když web něco měří (SEO a měření: zapnuté měření, cookie lišta
     a vyplněné ID). Nic se nespustí bez souhlasu; „Přijmout“ a „Odmítnout“ jsou
     stejně nápadné. Barvy: CSS proměnné --cc-* (projekt je přepíše ve svém CSS). --}}
@php
    $nastroje = \App\Support\NastaveniWebu::nastroje();
    $analytika = $nastroje['analytics'];
    $marketing = $nastroje['marketing'];
    // Verze souhlasu podle nástrojů – po přidání nástroje se lišta zeptá znovu.
    $verze = crc32(json_encode([array_keys($analytika), array_keys($marketing)]));
    $zasady = \Illuminate\Support\Facades\Route::has('cookies') ? route('cookies') : null;
    $nazvy = ['ga4' => 'Google Analytics', 'seznam' => 'měření Seznamu', 'google_ads' => 'Google Ads', 'sklik' => 'Sklik od Seznamu'];
@endphp

@if ($analytika || $marketing)
{{-- Consent Mode v2 musí běžet dřív než jakýkoli Google tag – výchozí je odmítnutí. --}}
<script>
(function () {
    var KEY = 'souhlas_cookies', VERSION = {{ $verze }};
    function read() {
        var m = document.cookie.match(/(?:^|;\s*)souhlas_cookies=([^;]*)/);
        if (!m) return null;
        try { var v = JSON.parse(decodeURIComponent(m[1])); return (v && v.v === VERSION) ? v : null; } catch (e) { return null; }
    }
    function write(state) {
        state.v = VERSION; state.t = new Date().toISOString();
        var d = new Date(); d.setMonth(d.getMonth() + 6);   // souhlas platí 6 měsíců
        document.cookie = KEY + '=' + encodeURIComponent(JSON.stringify(state)) + ';expires=' + d.toUTCString()
            + ';path=/;SameSite=Lax' + (location.protocol === 'https:' ? ';secure' : '');
    }
    window.ccSouhlas = { read: read, write: write };
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    window.gtag = gtag;
    gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
        analytics_storage: 'denied', functionality_storage: 'granted', security_storage: 'granted', wait_for_update: 500 });
    var s = read();
    if (s) gtag('consent', 'update', { ad_storage: s.marketing ? 'granted' : 'denied', ad_user_data: s.marketing ? 'granted' : 'denied',
        ad_personalization: s.marketing ? 'granted' : 'denied', analytics_storage: s.analytics ? 'granted' : 'denied' });
})();
</script>

{{-- Měřicí skripty jsou do souhlasu inertní (text/plain) – prohlížeč je nespustí. --}}
@isset ($analytika['ga4'])
    <script type="text/plain" data-cookie="analytics" data-src="https://www.googletagmanager.com/gtag/js?id={{ $analytika['ga4'] }}"></script>
    <script type="text/plain" data-cookie="analytics">gtag('js', new Date()); gtag('config', @js($analytika['ga4']), { anonymize_ip: true });</script>
@endisset
@isset ($analytika['seznam'])
    <script type="text/plain" data-cookie="analytics" data-src="https://c.seznam.cz/js/rc.js"></script>
    <script type="text/plain" data-cookie="analytics">window.sznIVA && window.sznIVA.IS.updateIdentities({ eid: @js($analytika['seznam']) });</script>
@endisset
@isset ($marketing['google_ads'])
    @unless (isset($analytika['ga4']))
        <script type="text/plain" data-cookie="marketing" data-src="https://www.googletagmanager.com/gtag/js?id={{ $marketing['google_ads'] }}"></script>
    @endunless
    <script type="text/plain" data-cookie="marketing">gtag('js', new Date()); gtag('config', @js($marketing['google_ads']));</script>
@endisset
@isset ($marketing['sklik'])
    @unless (isset($analytika['seznam']))
        <script type="text/plain" data-cookie="marketing" data-src="https://c.seznam.cz/js/rc.js"></script>
    @endunless
    <script type="text/plain" data-cookie="marketing">var retargetingConf = { rtgId: {{ (int) $marketing['sklik'] }}, consent: 1 }; window.rc && window.rc.retargetingHit && window.rc.retargetingHit(retargetingConf);</script>
@endisset

<style>
    .cc, .cc-modal { --cc-pozadi: var(--cc-vlastni-pozadi, #ffffff); --cc-text: var(--cc-vlastni-text, #16181c); --cc-slabe: #5f6673;
        --cc-akcent: var(--cc-vlastni-akcent, #2563eb); --cc-na-akcentu: #ffffff; --cc-okraj: #e5e7eb; font: 15px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
    @media (prefers-color-scheme: dark) { .cc, .cc-modal { --cc-pozadi: var(--cc-vlastni-pozadi, #171a21); --cc-text: var(--cc-vlastni-text, #eceef1); --cc-slabe: #a2a9b4; --cc-okraj: #2a2f38; } }
    .cc[hidden], .cc-modal[hidden] { display: none; }
    .cc { position: fixed; left: 0; right: 0; bottom: 0; z-index: 9990; background: var(--cc-pozadi); color: var(--cc-text); border-top: 1px solid var(--cc-okraj); box-shadow: 0 -12px 40px rgba(0,0,0,.18); }
    .cc-in { max-width: 1120px; margin: 0 auto; padding: 18px 16px; display: flex; align-items: center; gap: 28px; }
    .cc-copy { flex: 1; min-width: 0; }
    .cc h2 { font-size: 16px; margin: 0 0 4px; }
    .cc p { margin: 0; font-size: 14px; color: var(--cc-slabe); }
    .cc a, .cc-modal a { color: inherit; text-decoration: underline; }
    .cc-akce { display: flex; gap: 10px; flex: none; flex-wrap: wrap; }
    /* „Přijmout“ a „Odmítnout“ stejně nápadné – schované odmítnutí je nejčastější prohřešek. */
    .cc-tl { min-width: 128px; padding: 10px 16px; border-radius: 10px; font: inherit; font-weight: 600; cursor: pointer; border: 1px solid var(--cc-akcent); }
    .cc-plne { background: var(--cc-akcent); color: var(--cc-na-akcentu); }
    .cc-obrys { background: transparent; color: var(--cc-text); border-color: var(--cc-okraj); }
    .cc-modal { position: fixed; inset: 0; z-index: 9995; display: grid; place-items: center; padding: 16px; background: rgba(0,0,0,.55); }
    .cc-panel { width: min(540px, 100%); max-height: 86vh; overflow-y: auto; background: var(--cc-pozadi); color: var(--cc-text); border-radius: 16px; padding: 26px; }
    .cc-panel h2 { font-size: 20px; margin: 0 0 4px; }
    .cc-kat { display: block; padding: 14px 0; border-top: 1px solid var(--cc-okraj); }
    .cc-kat p { margin: 6px 0 0; font-size: 13px; color: var(--cc-slabe); }
    .cc-hlava { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .cc-kat input { width: 20px; height: 20px; accent-color: var(--cc-akcent); cursor: pointer; }
    .cc-vzdy { font-size: 12px; color: var(--cc-slabe); }
    .cc-modal .cc-akce { justify-content: flex-end; margin-top: 20px; }
    @media (max-width: 760px) { .cc-in { flex-direction: column; align-items: stretch; gap: 14px; } .cc-akce { flex-direction: column; } .cc-tl { width: 100%; } }
</style>

<div class="cc" id="cc-lista" hidden role="dialog" aria-modal="false" aria-labelledby="cc-nadpis">
    <div class="cc-in">
        <div class="cc-copy">
            <h2 id="cc-nadpis">Cookies na tomhle webu</h2>
            <p>Nezbytné cookies potřebujeme, aby web fungoval – ty nejdou vypnout.
                {{ collect([$analytika ? 'Analytické' : null, $marketing ? 'marketingové' : null])->filter()->join(' a ') }} nastavíme jen s vaším souhlasem.
                @if ($zasady) Podrobnosti v <a href="{{ $zasady }}">zásadách používání cookies</a>.@endif</p>
        </div>
        <div class="cc-akce">
            <button type="button" class="cc-tl cc-plne" data-cc="vse">Přijmout vše</button>
            <button type="button" class="cc-tl cc-plne" data-cc="nic">Odmítnout vše</button>
            <button type="button" class="cc-tl cc-obrys" data-cc="open">Nastavení</button>
        </div>
    </div>
</div>

<div class="cc-modal" id="cc-nastaveni" hidden role="dialog" aria-modal="true" aria-labelledby="cc-m-nadpis">
    <div class="cc-panel">
        <h2 id="cc-m-nadpis">Nastavení cookies</h2>
        <p style="margin: 0 0 16px; font-size: 14px; color: var(--cc-slabe);">Vyberte, co nám dovolíte. Volbu můžete kdykoli změnit odkazem Nastavení cookies na webu.</p>
        <div class="cc-kat">
            <div class="cc-hlava"><strong>Nezbytné</strong><span class="cc-vzdy">vždy zapnuto</span></div>
            <p>Stav relace a ochrana formulářů proti zneužití. Bez nich web nefunguje.</p>
        </div>
        @if ($analytika)
            <label class="cc-kat">
                <div class="cc-hlava"><strong>Analytické</strong><input type="checkbox" data-cc-kat="analytics"></div>
                <p>Souhrnná statistika návštěvnosti – kolik lidí web navštíví a které stránky si projdou. {{ collect(array_keys($analytika))->map(fn ($n) => $nazvy[$n])->join(', ') }}.</p>
            </label>
        @endif
        @if ($marketing)
            <label class="cc-kat">
                <div class="cc-hlava"><strong>Marketingové</strong><input type="checkbox" data-cc-kat="marketing"></div>
                <p>Umožňují zobrazit vám naši reklamu na jiných webech a změřit její účinnost. {{ collect(array_keys($marketing))->map(fn ($n) => $nazvy[$n])->join(', ') }}.</p>
            </label>
        @endif
        <div class="cc-akce">
            <button type="button" class="cc-tl cc-obrys" data-cc="ulozit">Uložit volbu</button>
            <button type="button" class="cc-tl cc-plne" data-cc="vse">Přijmout vše</button>
        </div>
    </div>
</div>

<script>
(function () {
    var lista = document.getElementById('cc-lista'), modal = document.getElementById('cc-nastaveni');
    function spustit(stav) {
        document.querySelectorAll('script[type="text/plain"][data-cookie]').forEach(function (uzel) {
            if (!stav[uzel.dataset.cookie]) return;
            var s = document.createElement('script');
            if (uzel.dataset.src) { s.src = uzel.dataset.src; s.async = true; } else { s.textContent = uzel.textContent; }
            document.head.appendChild(s); uzel.remove();
        });
    }
    function pouzit(stav) {
        ccSouhlas.write(stav);
        gtag('consent', 'update', { ad_storage: stav.marketing ? 'granted' : 'denied', ad_user_data: stav.marketing ? 'granted' : 'denied',
            ad_personalization: stav.marketing ? 'granted' : 'denied', analytics_storage: stav.analytics ? 'granted' : 'denied' });
        spustit(stav); lista.hidden = true; modal.hidden = true;
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-cc]'); if (!t) return;
        var a = t.dataset.cc;
        if (a === 'vse') pouzit({ analytics: true, marketing: true });
        else if (a === 'nic') pouzit({ analytics: false, marketing: false });
        else if (a === 'open') {
            var s = ccSouhlas.read() || {};
            modal.querySelectorAll('[data-cc-kat]').forEach(function (i) { i.checked = !!s[i.dataset.ccKat]; });
            modal.hidden = false;
        } else if (a === 'ulozit') {
            var st = { analytics: false, marketing: false };
            modal.querySelectorAll('[data-cc-kat]').forEach(function (i) { st[i.dataset.ccKat] = i.checked; });
            pouzit(st);
        }
    });
    var ulozeny = ccSouhlas.read();
    if (ulozeny) spustit(ulozeny); else lista.hidden = false;
})();
</script>
@endif
