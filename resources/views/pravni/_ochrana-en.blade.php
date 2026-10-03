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
<p>This notice explains what personal data we process, why, and what rights you have. We follow the EU General Data Protection Regulation (GDPR) and Czech Act No. 110/2019 Coll. <em>This is a summary; the Czech version is binding.</em></p>
@if ($ucinnost)
    <p><em>Effective from {{ \Illuminate\Support\Carbon::parse($ucinnost)->format('j F Y') }}.</em></p>
@endif

<h2>1. Who is the controller</h2>
<p>
    {{ $u['firma'] ?: $u['nazev'] }}<br>
    @if ($en['adresa_en'] || $u['adresa']){{ $en['adresa_en'] ?: str_replace("\n", ', ', $u['adresa']) }}<br>@endif
    @if ($u['ico'])Company number: {{ $u['ico'] }}<br>@endif
    @if ($en['rejstrik_en'] || $u['rejstrik']){{ $en['rejstrik_en'] ?: $u['rejstrik'] }}<br>@endif
    @if ($email)E-mail: <a href="mailto:{{ $email }}">{{ $email }}</a><br>@endif
    @if ($u['telefon'])Phone: {{ $u['telefon'] }}@endif
</p>

<h2>2. What data we process and why</h2>
<h3>2.1. Enquiries and communication</h3>
<p>If you contact us through the enquiry form, by e-mail, phone, WhatsApp or Telegram, we process the data you give us — typically your name, company, e-mail, phone number and the content of your enquiry; for the form also your IP address and the time it was sent (to protect the form against abuse and spam). We use it to handle your enquiry and for pre-contract negotiation (Art. 6(1)(b) GDPR) and follow-up business communication (our legitimate interest, Art. 6(1)(f) GDPR).</p>
<h3>2.2. Contracts</h3>
<p>If a transaction takes place, we process the data needed to perform the contract and to meet our legal obligations, such as accounting and tax duties (Art. 6(1)(b) and (c) GDPR).</p>
<h3>2.3. Website operation and security</h3>
<p>Our server keeps technical logs (IP address, browser, time and pages visited) to run the website securely and detect abuse — our legitimate interest, usually for no more than 6 months.</p>
@unless ($meri)
<p>The website itself collects nothing further: there is no analytics or tracking script.</p>
@endunless

<h2>3. How long we keep it</h2>
<p>We keep enquiries for the duration of the negotiation and for at most three years afterwards; documents relating to a concluded transaction for the period required by law.</p>

<h2>4. Who has access</h2>
<p>We do not pass data to third parties for their own purposes and we do not sell it. Our e-mail and server hosting providers and the company that maintains the website may process it as processors, within the EU. Accounting providers may access it if a transaction takes place.</p>

<h2>5. Your rights</h2>
<p>You have the right to access your data, to have it corrected or erased, to restrict processing, to data portability, and to object to processing based on legitimate interest.@if ($email) Just write to <a href="mailto:{{ $email }}">{{ $email }}</a>.@endif If you believe we are not handling your data properly, you may lodge a complaint with the Czech Data Protection Authority (Úřad pro ochranu osobních údajů, <a href="https://uoou.gov.cz/" target="_blank" rel="noopener">uoou.gov.cz</a>).</p>

<h2>6. Cookies</h2>
<p>See <a href="{{ route('cookies') }}">Cookies and local storage</a>.</p>
