{{-- Detail platby (ViewPlatba): údaje a historie stavů. Vlastní inline styly – Tailwind třídy
     ve vlastních šablonách Filamentu nefungují. --}}
@php
    $radek = 'display: grid; grid-template-columns: 11rem 1fr; gap: 4px 16px; padding: 6px 0; border-bottom: 1px solid var(--gray-200);';
    $popisek = 'color: var(--gray-500); font-size: .875rem;';
@endphp
<div style="display: grid; gap: 24px;">
    @if ($platba->testovaci())
        <div style="padding: 10px 14px; border-radius: 10px; background: color-mix(in srgb, var(--warning-500) 14%, transparent); color: var(--warning-600); font-weight: 600;">
            TESTOVACÍ PLATBA ({{ mb_strtolower($platba->rezim->popis()) }}) – nic se nestrhlo.
        </div>
    @endif

    <x-filament::section heading="Platba">
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Stav</span><span><x-filament::badge :color="$platba->stav->barva()" style="display: inline-flex;">{{ $platba->stav->popis() }}</x-filament::badge></span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Částka</span><span>{{ $platba->castkaKc() }}@if ($platba->vraceno > 0) · vráceno {{ \App\Platby\Platba::kc($platba->vraceno, $platba->mena) }}@endif</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Za co</span><span>{{ $platba->popis }}@if ($platba->reference) · č. {{ $platba->reference }}@endif</span></div>
        @if ($platba->predmet_type)
            <div style="{{ $radek }}"><span style="{{ $popisek }}">Objednávka</span><span>{{ class_basename($platba->predmet_type) }} #{{ $platba->predmet_id }}</span></div>
        @endif
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Zákazník</span><span>{{ $platba->celeJmeno() ?: '–' }}@if ($platba->email) · <a href="mailto:{{ $platba->email }}" style="text-decoration: underline;">{{ $platba->email }}</a>@endif</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Brána</span><span>{{ $platba->nazevBrany() }} · {{ mb_strtolower($platba->rezim->popis()) }} režim{{ $platba->metoda ? ' · '.$platba->metoda : '' }}</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Číslo u brány</span><span style="font-family: ui-monospace, monospace;">{{ $platba->externi_id ?: '–' }}</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Založena</span><span>{{ $platba->created_at->format('j. n. Y H:i:s') }}</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Zaplacena</span><span>{{ $platba->zaplaceno_v?->format('j. n. Y H:i:s') ?? '–' }}</span></div>
        <div style="{{ $radek }}"><span style="{{ $popisek }}">Naposledy ověřena</span><span>{{ $platba->overeno_v?->format('j. n. Y H:i:s') ?? '–' }}</span></div>
        @if ($platba->chyba)
            <div style="{{ $radek }}"><span style="{{ $popisek }}">Chyba brány</span><span style="color: var(--danger-600);">{{ $platba->chyba }}</span></div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Historie" description="Každá změna stavu i každé oznámení brány, návrat zákazníka a ruční ověření.">
        <div class="tabulka-obal" style="overflow-x: auto;">
            <table style="width: 100%; min-width: 640px; table-layout: fixed; border-collapse: collapse; font-size: .875rem;">
                <colgroup><col style="width: 9.5rem"><col style="width: 11rem"><col style="width: 13rem"><col></colgroup>
                <thead>
                    <tr style="text-align: left; color: var(--gray-500);">
                        <th style="padding: 6px 8px 6px 0; font-weight: 500;">Kdy</th>
                        <th style="padding: 6px 8px; font-weight: 500;">Odkud</th>
                        <th style="padding: 6px 8px; font-weight: 500;">Stav</th>
                        <th style="padding: 6px 0 6px 8px; font-weight: 500;">Poznámka</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($platba->udalosti as $u)
                        <tr style="border-top: 1px solid var(--gray-200); vertical-align: top;">
                            <td style="padding: 6px 8px 6px 0; white-space: nowrap;">{{ $u->created_at?->format('j. n. Y H:i:s') }}</td>
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
