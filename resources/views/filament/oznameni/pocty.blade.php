{{-- Potvrzení před odesláním: kolik lidí co dostane (Cileni::pocty). --}}
@php
    $f = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $email = $oznameni->maKanal(\App\Enums\KanalOznameni::Email);
    $centrum = $oznameni->maKanal(\App\Enums\KanalOznameni::Centrum);
    $pruh = $oznameni->maKanal(\App\Enums\KanalOznameni::Pruh);
@endphp
<div class="simren-detail">
    <div class="simren-pas">
        <div><b>{{ $f($pocty['prijemcu'] ?? 0) }}</b><span class="simren-slabe">{{ __('administrace.pocty.prijemcu') }}</span></div>
        @if ($centrum)<div><b>{{ $f($pocty['centrum'] ?? 0) }}</b><span class="simren-slabe">{{ __('administrace.pocty.v_centru_oznameni') }}</span></div>@endif
        @if ($email)<div><b>{{ $f($pocty['email'] ?? 0) }}</b><span class="simren-slabe">{{ __('administrace.pocty.e_mailem') }}</span></div>@endif
    </div>
    @if ($email && (($pocty['email_bez_souhlasu'] ?? 0) + ($pocty['email_vypnuto'] ?? 0)) > 0)
        @php
            $nebude = array_filter([
                ($pocty['email_bez_souhlasu'] ?? 0) ? $f($pocty['email_bez_souhlasu']).' bez souhlasu s '.($oznameni->druh === \App\Enums\DruhOznameni::Marketing ? __('administrace.pocty.nabidkami') : 'novinkami') : null,
                ($pocty['email_vypnuto'] ?? 0) ? __('administrace.pocty.ma_e_mail_vypnuty_v_predvolbach', ['f' => $f($pocty['email_vypnuto'])]) : null,
            ]);
        @endphp
        <p class="simren-slabe" style="font-size: .875rem;">{{ __('administrace.pocty.e_mail_nedostane', ['implode' => implode(', ', $nebude)]) }}</p>
    @endif
    @if ($pruh)
        <p style="font-size: .875rem;">{{ __('administrace.pocty.pruh_pres_web_uvidi', ['je_pro_vsechny' => $oznameni->jeProVsechny() ? __('administrace.pocty.vsichni_navstevnici_webu_i_administrace') : __('administrace.pocty.jen_prihlaseni_prijemci'), 'format' => $oznameni->pruh_do ? ' do '.$oznameni->pruh_do->format(__('formaty.datum_cas')) : __('administrace.pocty.dokud_ho_neukoncite')]) }}</p>
    @endif
    @if ($email && ($pocty['email'] ?? 0) > \App\Support\Oznameni\NastaveniOznameni::limit('emailu_za_minutu'))
        <p class="simren-slabe" style="font-size: .875rem;">{{ __('administrace.pocty.e_maily_odchazeji_po_za_minutu', ['limit' => \App\Support\Oznameni\NastaveniOznameni::limit('emailu_za_minutu'), 'limit2' => (int) ceil(($pocty['email'] ?? 0) / max(1, \App\Support\Oznameni\NastaveniOznameni::limit('emailu_za_minutu')))]) }}</p>
    @endif
</div>
