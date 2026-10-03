{{-- Cookies – anglicky (souhrn k českému vzoru pravni/_cookies-obsah + _uloziste-cs). --}}
@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $meri = \App\Support\NastaveniWebu::meri();
@endphp
<p>This page explains which cookies the website uses and what it keeps in your browser. It is operated by {{ $u['firma'] ?: $u['nazev'] }}{{ $u['ico'] ? ', company number '.$u['ico'] : '' }}.</p>

<h2>Strictly necessary cookies</h2>
<table>
    <tr><th>Name</th><th>Purpose</th><th>Lifetime</th></tr>
    <tr><td>{{ config('session.cookie') }}</td><td>Keeps the session state (and the login to the site administration).</td><td>{{ config('session.lifetime') }} minutes</td></tr>
    <tr><td>XSRF-TOKEN</td><td>Protects forms against cross-site request forgery.</td><td>{{ config('session.lifetime') }} minutes</td></tr>
    @if ($meri)
        <tr><td>souhlas_cookies</td><td>Remembers your choice in the cookie settings.</td><td>6 months</td></tr>
    @endif
</table>
<p>These cookies are our own, are not shared with anyone and do not require consent under Czech law (§ 89(3) of Act No. 127/2005 Coll.).</p>

@if ($meri)
    <h2>Analytics and marketing</h2>
    <p>Analytics or marketing tools only run after you give consent in the cookie banner. You can change or withdraw your consent at any time via <button type="button" data-cc="open">Cookie settings</button>. Details are in the Czech version of this page.</p>
@else
    <h2>No analytics, no advertising</h2>
    <p>There is no analytics, advertising or tracking script running here, which is why there is no cookie banner – there would be nothing to ask about.</p>
@endif

<h2>What is stored in your browser</h2>
<p>The site keeps three small items in your browser's own storage so it can remember how you set it up – and only once you choose so yourself:</p>
<ul>
    <li><strong>exportex-lang-v2</strong> — the language you picked (Czech or English), once you switch it.</li>
    <li><strong>exportex-theme-v2</strong> — the light or dark mode you picked, once you switch it.</li>
    <li><strong>exportex-scroll</strong> — how far down the page you were, so a refresh puts you back in the same place. It only lasts until you close the tab (sessionStorage).</li>
</ul>
<p>All of it stays in your browser only, is never transmitted anywhere and reveals nothing about you personally. Clearing site data in your browser removes it.</p>

<h2>External content</h2>
<p>The site loads everything, fonts included, from its own address. It opens no connections to third-party servers, so your IP address is not passed on. The WhatsApp and Telegram links do lead outside, but only open when you click them.</p>

<h2>Your rights</h2>
<p>How we handle personal data and what rights you have is described in the <a href="{{ route('ochrana-udaju') }}">privacy notice</a>.@if ($u['email']) Questions: <a href="mailto:{{ $u['email'] }}">{{ $u['email'] }}</a>.@endif</p>
