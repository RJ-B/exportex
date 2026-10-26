{{-- Detail naplánovaného / odeslaného oznámení: co, komu, kudy, kdy a čísla (Statistika). --}}
@php($f = fn ($n) => number_format((int) $n, 0, ',', ' '))
<div class="simren-detail">
    @if ($cisla)
        {{-- Problémy nahoře: nedoručené e-maily. --}}
        @if (($cisla['email_nedoruceno'] ?? 0) > 0)
            <div class="simren-chyba"><strong>{{ __('administrace.detail.e_mailu_se_nepodarilo_dorucit', ['f' => $f($cisla['email_nedoruceno'])]) }}</strong> {{ __('administrace.detail.duvody_jsou_v_sprava_aplikace_logy') }}</div>
        @endif
        <div class="simren-pas">
            <div><b>{{ $f($cisla['prijemcu']) }}</b><span class="simren-slabe">{{ __('administrace.detail.prijemcu') }}</span></div>
            <div><b>{{ $f($cisla['precteno']) }}</b><span class="simren-slabe">{{ __('administrace.detail.precetlo', ['cisla' => $cisla['prijemcu'] ? ' ('.round($cisla['precteno'] / $cisla['prijemcu'] * 100).' %)' : '']) }}</span></div>
            @if (filled($oznameni->odkaz))<div><b>{{ $f($cisla['prokliknuto']) }}</b><span class="simren-slabe">{{ __('administrace.detail.prokliklo') }}</span></div>@endif
            @isset($cisla['email_predano'])
                <div><b>{{ $f($cisla['email_doruceno']) }}</b><span class="simren-slabe">{{ __('administrace.detail.e_mailu_doruceno') }}</span></div>
                @if ($cisla['email_ceka'])<div><b>{{ $f($cisla['email_ceka']) }}</b><span class="simren-slabe">{{ __('administrace.detail.e_mailu_ceka_ve_fronte') }}</span></div>@endif
                @if ($cisla['email_bez_souhlasu'])<div><b>{{ $f($cisla['email_bez_souhlasu']) }}</b><span class="simren-slabe">{{ __('administrace.detail.bez_souhlasu_e_mail_nesel') }}</span></div>@endif
                @if ($cisla['email_preskoceno'])<div><b>{{ $f($cisla['email_preskoceno']) }}</b><span class="simren-slabe">{{ __('administrace.detail.e_mail_vypnuty') }}</span></div>@endif
            @endisset
        </div>
    @endif

    <dl class="simren-udaje">
        <dt>{{ __('administrace.detail.stav') }}</dt><dd>{{ $oznameni->stav->nazev() }}@if ($oznameni->stav === \App\Enums\StavOznameni::Naplanovano) {{ __('administrace.detail.na', ['format' => $oznameni->naplanovano_na?->format(__('formaty.datum_cas'))]) }}@endif @if ($oznameni->odeslano_at) {{ $oznameni->odeslano_at->format(__('formaty.datum_cas')) }}@endif</dd>
        <dt>{{ __('administrace.detail.druh') }}</dt><dd>{{ $oznameni->druh->nazev() }}</dd>
        <dt>{{ __('administrace.detail.komu') }}</dt><dd>{{ $komu }}</dd>
        <dt>{{ __('administrace.detail.kudy') }}</dt><dd>@foreach ($oznameni->kanalyEnum() as $kanal){{ $kanal->nazev() }}@if (! $loop->last)<br>@endif @endforeach</dd>
        @if ($oznameni->maKanal(\App\Enums\KanalOznameni::Pruh))
            <dt>{{ __('administrace.detail.pruh') }}</dt><dd>{{ $oznameni->zavaznost->nazev() }}, {{ $oznameni->pruh_od ? 'od '.$oznameni->pruh_od->format(__('formaty.datum_cas')) : __('administrace.detail.od_odeslani') }}{{ $oznameni->pruh_do ? ' do '.$oznameni->pruh_do->format(__('formaty.datum_cas')) : __('administrace.detail.dokud_ho_neukoncite') }}</dd>
        @endif
        @if ($oznameni->udalost_od)
            <dt>{{ __('administrace.detail.odstavka') }}</dt><dd>{{ $oznameni->udalost_od->format(__('formaty.datum_cas')) }}@if ($oznameni->udalost_do) – {{ $oznameni->udalost_do->format(__('formaty.datum_cas')) }}@endif</dd>
        @endif
        <dt>{{ __('administrace.detail.odkud') }}</dt><dd>{{ ['administrace' => 'Administrace', 'aplikace' => __('administrace.detail.z_aplikace_kod'), 'portal' => __('administrace.detail.portal_sim_ren')][$oznameni->zdroj] ?? $oznameni->zdroj }}@if ($oznameni->odeslal) – {{ $oznameni->odeslal->getFilamentName() }}@endif</dd>
    </dl>

    <div class="simren-ramec">
        <strong>{{ $oznameni->titulek }}</strong>
        <div style="margin-top: .25rem;">{{ $oznameni->textHtml() }}</div>
        @if (filled($oznameni->odkaz))<p style="margin-top: .5rem;">{{ $oznameni->odkaz_text ?: 'Zobrazit' }} → {{ $oznameni->odkaz }}</p>@endif
    </div>
</div>
