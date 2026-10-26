{{-- Detail platby (ViewPlatba): údaje a historie stavů. Vlastní inline styly – Tailwind třídy
     ve vlastních šablonách Filamentu nefungují. --}}
@php
    $radek = 'display: grid; grid-template-columns: 11rem 1fr; gap: 4px 16px; padding: 6px 0; border-bottom: 1px solid var(--gray-200);';
    $popisek = 'color: var(--gray-500); font-size: .875rem;';
@endphp
<div style="display: grid; gap: 24px;">
    @if ($platba->testovaci())
        <div style="padding: 10px 14px; border-radius: 10px; background: color-mix(in srgb, var(--warning-500) 14%, transparent); color: var(--warning-600); font-weight: 600;">
            @if ($platba->skutecna()){{ __('platby.detail.skutecna_platba_pres_testovaci_branu') }}@else {{ __('platby.detail.testovaci_platba_nic_se_nestrhlo', ['popis' => mb_strtolower($platba->rezim->popis())]) }}@endif
        </div>
    @endif

    <x-filament::section heading="Platba">
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.stav') }}</span><span><x-filament::badge :color="$platba->stav->barva()" style="display: inline-flex;">{{ $platba->stav->popis() }}</x-filament::badge></span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.castka') }}</span><span>{{ $platba->castkaKc() }}@if ($platba->vraceno > 0) {{ __('platby.detail.vraceno', ['kc' => \App\Platby\Platba::kc($platba->vraceno, $platba->mena)]) }}@endif</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.za_co') }}</span><span>{{ $platba->popis }}@if ($platba->reference) {{ __('platby.detail.c', ['reference' => $platba->reference]) }}@endif</span></div>
        @if ($platba->predmet_type)
            <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.objednavka') }}</span><span>{{ class_basename($platba->predmet_type) }} #{{ $platba->predmet_id }}</span></div>
        @endif
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.zakaznik') }}</span><span>{{ $platba->celeJmeno() ?: '–' }}@if ($platba->email) · <a href="mailto:{{ $platba->email }}" style="text-decoration: underline;">{{ $platba->email }}</a>@endif</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.brana') }}</span><span>{{ __('platby.detail.rezim', ['nazev_brany' => $platba->nazevBrany(), 'popis' => mb_strtolower($platba->rezim->popis()), 'platba' => $platba->metoda ? ' · '.$platba->metoda : '']) }}</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.cislo_u_brany') }}</span><span style="font-family: ui-monospace, monospace;">{{ $platba->externi_id ?: '–' }}</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.zalozena') }}</span><span>{{ $platba->created_at->format(__('formaty.datum_cas_sekundy')) }}</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.zaplacena') }}</span><span>{{ $platba->zaplaceno_v?->format(__('formaty.datum_cas_sekundy')) ?? '–' }}</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.naposledy_overena') }}</span><span>{{ $platba->overeno_v?->format(__('formaty.datum_cas_sekundy')) ?? '–' }}</span></div>
        @if ($platba->chyba)
            <div style="{{ $radek }}"><span style="{{ $popisek }}">{{ __('platby.detail.chyba_brany') }}</span><span style="color: var(--danger-600);">{{ $platba->chyba }}</span></div>
        @endif
    </x-filament::section>

    <x-filament::section heading="{{ __('platby.detail.historie') }}" description="{{ __('platby.detail.kazda_zmena_stavu_i_kazde_oznameni') }}">
        <div class="tabulka-obal" style="overflow-x: auto;">
            <table style="width: 100%; min-width: 640px; table-layout: fixed; border-collapse: collapse; font-size: .875rem;">
                <colgroup><col style="width: 9.5rem"><col style="width: 11rem"><col style="width: 13rem"><col></colgroup>
                <thead>
                    <tr style="text-align: left; color: var(--gray-500);">
                        <th style="padding: 6px 8px 6px 0; font-weight: 500;">{{ __('platby.detail.kdy') }}</th>
                        <th style="padding: 6px 8px; font-weight: 500;">{{ __('platby.detail.odkud') }}</th>
                        <th style="padding: 6px 8px; font-weight: 500;">{{ __('platby.detail.stav') }}</th>
                        <th style="padding: 6px 0 6px 8px; font-weight: 500;">{{ __('platby.detail.poznamka') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($platba->udalosti as $u)
                        <tr style="border-top: 1px solid var(--gray-200); vertical-align: top;">
                            <td style="padding: 6px 8px 6px 0; white-space: nowrap;">{{ $u->created_at?->format(__('formaty.datum_cas_sekundy')) }}</td>
                            <td style="padding: 6px 8px;">{{ $u->popisZdroje() }}@if ($u->uzivatel)<br><span style="color: var(--gray-500);">{{ $u->uzivatel->getFilamentName() }}</span>@endif</td>
                            <td style="padding: 6px 8px;">
                                @if ($u->stav_z !== $u->stav_na)
                                    {{ $u->stav_z?->popis() ?? '–' }} → <strong>{{ $u->stav_na?->popis() }}</strong>
                                @else
                                    <span style="color: var(--gray-500);">{{ $u->stav_na?->popis() }}</span>
                                @endif
                            </td>
                            <td style="padding: 6px 0 6px 8px;">{{ $u->poznamka }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</div>
