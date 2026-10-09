{{-- Potvrzení před odesláním: kolik lidí co dostane (Cileni::pocty). --}}
@php
    $f = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $email = $oznameni->maKanal(\App\Enums\KanalOznameni::Email);
    $centrum = $oznameni->maKanal(\App\Enums\KanalOznameni::Centrum);
    $pruh = $oznameni->maKanal(\App\Enums\KanalOznameni::Pruh);
@endphp
<div class="simren-detail">
    <div class="simren-pas">
        <div><b>{{ $f($pocty['prijemcu'] ?? 0) }}</b><span class="simren-slabe">příjemců</span></div>
        @if ($centrum)<div><b>{{ $f($pocty['centrum'] ?? 0) }}</b><span class="simren-slabe">v centru oznámení</span></div>@endif
        @if ($email)<div><b>{{ $f($pocty['email'] ?? 0) }}</b><span class="simren-slabe">e-mailem</span></div>@endif
    </div>
    @if ($email && (($pocty['email_bez_souhlasu'] ?? 0) + ($pocty['email_vypnuto'] ?? 0)) > 0)
        @php
            $nebude = array_filter([
                ($pocty['email_bez_souhlasu'] ?? 0) ? $f($pocty['email_bez_souhlasu']).' bez souhlasu s '.($oznameni->druh === \App\Enums\DruhOznameni::Marketing ? 'nabídkami' : 'novinkami') : null,
                ($pocty['email_vypnuto'] ?? 0) ? $f($pocty['email_vypnuto']).' má e-mail vypnutý v předvolbách' : null,
            ]);
        @endphp
        <p class="simren-slabe" style="font-size: .875rem;">E-mail nedostane: {{ implode(', ', $nebude) }}.</p>
    @endif
    @if ($pruh)
        <p style="font-size: .875rem;">Pruh přes web uvidí {{ $oznameni->jeProVsechny() ? 'všichni návštěvníci webu i administrace, i nepřihlášení' : 'jen přihlášení příjemci' }}{{ $oznameni->pruh_do ? ' do '.$oznameni->pruh_do->format('j. n. Y H:i') : ', dokud ho neukončíte' }}.</p>
    @endif
    @if ($email && ($pocty['email'] ?? 0) > \App\Support\Oznameni\NastaveniOznameni::limit('emailu_za_minutu'))
        <p class="simren-slabe" style="font-size: .875rem;">E-maily odcházejí po {{ \App\Support\Oznameni\NastaveniOznameni::limit('emailu_za_minutu') }} za minutu – všechny odejdou asi za {{ (int) ceil(($pocty['email'] ?? 0) / max(1, \App\Support\Oznameni\NastaveniOznameni::limit('emailu_za_minutu'))) }} min.</p>
    @endif
</div>
