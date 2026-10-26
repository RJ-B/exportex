{{-- Administrace → Pošta: propojení s Poštou (posta.simren.cz). Soběstačná (vlastní styly),
     aby šla přenést do každého projektu. Tailwind třídy ve vlastních šablonách Filament
     nekompiluje, proto třídy .posta-* s existujícími proměnnými Filamentu. --}}
<x-filament-panels::page>
    <style>
        .posta-stav { border-radius: .5rem; background: var(--gray-50); padding: .75rem 1rem; font-size: .875rem; }
        .dark .posta-stav { background: rgb(255 255 255 / .05); }
        .posta-chyba { border-radius: .5rem; background: var(--danger-50); padding: .75rem 1rem; font-size: .875rem; }
        .dark .posta-chyba { background: rgb(239 68 68 / .1); }
        .posta-udaje { display: grid; grid-template-columns: minmax(8rem, 1fr) 3fr; gap: .4rem 1rem; margin: 0; font-size: .875rem; }
        .posta-udaje dt { color: var(--gray-500); }
        .posta-udaje dd { margin: 0; }
        .posta-slabe { color: var(--gray-500); font-size: .8rem; }
        .posta-adresa { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem .5rem; margin-bottom: .25rem; }
    </style>

    @if (! $propojeni['propojeno'])
        <div class="posta-chyba">
            {{ __('posta.posta.aplikace_neni_propojena_s_postou_e') }} <strong>{{ __('posta.posta.propojit_s_postou') }}</strong>{{ __('posta.posta.v_poste_spravce_prideli_adresy_ze_2') }}
            @if ($stara)
                <br>{{ __('posta.posta.aplikace_ma_jeste_starou_schranku') }} <strong>{{ $stara['adresa'] }}</strong> {{ __('posta.posta.pri_propojeni_ji_posta_muze_prevzit', ['smtp_host' => $stara['smtp_host'], 'stara' => $stara['zdroj'] === 'env' ? ', v .env' : '']) }}
            @endif
        </div>
    @else
        @if ($prevzeti && empty($prevzeti['smazano']))
            <div class="{{ ($prevzeti['ok'] ?? false) ? 'posta-stav' : 'posta-chyba' }}" style="margin-bottom: 1rem">
                <strong>{{ __('posta.posta.prevzeti_stare_schranky', ['adresa' => $prevzeti['adresa']]) }}</strong> {{ $prevzeti['zprava'] ?? '' }}
                @if ($prevzeti['ok'] ?? false)
                    @if (! empty($prevzeti['zkouska_id']))
                        <br>{{ __('posta.posta.zkusebni_e_mail_je_v_poste') }}
                    @else
                        <br>{{ __('posta.posta.posli') }} <strong>{{ __('posta.posta.zkusebni_e_mail') }}</strong> {{ __('posta.posta.az_ho_posta_potvrdi_jako_odeslany') }}
                    @endif
                @endif
            </div>
        @elseif ($prevzeti && ! empty($prevzeti['env_zbyva']))
            <div class="posta-stav" style="margin-bottom: 1rem">
                {{ __('posta.posta.stara_schranka_je_prevzata_do_posty', ['adresa' => $prevzeti['adresa']]) }} <code>.env</code> {{ __('posta.posta.na_serveru_mail_host_mail_username') }} <code>MAIL_MAILER=log</code> {{ __('posta.posta.staci_jako_zaloha') }}
            </div>
        @endif

        @if ($stav['ok'] === false)
            <div class="posta-chyba">{{ $stav['zprava'] }}</div>
        @endif

        <x-filament::section heading="{{ __('posta.posta.propojeni') }}">
            <dl class="posta-udaje">
                <dt>{{ __('posta.posta.posta') }}</dt><dd>{{ $propojeni['url'] }}{{ $propojeni['rucne'] ? __('posta.posta.klic_zadany_rucne') : '' }}</dd>
                @if ($propojeni['aplikace'])<dt>{{ __('posta.posta.aplikace_v_poste') }}</dt><dd>{{ $propojeni['aplikace'] }}</dd>@endif
                <dt>{{ __('posta.posta.smi_posilat_z') }}</dt>
                <dd>
                    @forelse ($propojeni['adresy'] as $adresa)
                        <div class="posta-adresa">
                            <span>{{ $adresa['jmeno'] ? $adresa['jmeno'].' <'.$adresa['adresa'].'>' : $adresa['adresa'] }}</span>
                            {{-- Kopie do Odeslaných – stav posílá Pošta (starší Pošta ho nemá, pak se nic neukáže). --}}
                            @isset($adresa['kopie'])
                                <span class="posta-slabe" title="{{ $adresa['kopie_zprava'] ?? '' }}">· {{ match ($adresa['kopie']) {
                                    'zapnuto' => __('posta.posta.kopie_se_ukladaji_do_odeslanych_2'),
                                    'sama' => __('posta.posta.odeslane_uklada_schranka_sama'),
                                    'nelze' => __('posta.posta.kopie_do_odeslanych_nejdou'),
                                    default => __('posta.posta.kopie_se_do_odeslanych_neukladaji'),
                                } }}</span>
                                @if (in_array($adresa['kopie'], ['zapnuto', 'vypnuto'], true))
                                    {{ ($this->kopieAction)(['adresa' => $adresa['adresa'], 'zapnout' => $adresa['kopie'] === 'vypnuto']) }}
                                @endif
                            @endisset
                        </div>
                    @empty
                        <span class="posta-slabe">{{ __('posta.posta.zadne_adresy_pridel_je_v_poste') }}</span>
                    @endforelse
                </dd>
                @if ($propojeni['kdy'])<dt>{{ __('posta.posta.propojeno') }}</dt><dd>{{ \App\Support\CasAplikace::zVenku($propojeni['kdy'])?->format(__('formaty.datum_cas')) }}</dd>@endif
                @if ($propojeni['token_plati_do'])<dt>{{ __('posta.posta.klic_plati_do') }}</dt><dd>{{ \App\Support\CasAplikace::zVenku($propojeni['token_plati_do'])?->format(__('formaty.datum')) }} <span class="posta-slabe">{{ __('posta.posta.obnovuje_se_sam') }}</span></dd>@endif
                <dt>{{ __('posta.posta.odchozi_fronta') }}</dt><dd>{{ $fronta ? __('posta.posta.zprav_ceka_na_postu_predaji_se', ['fronta' => $fronta]) : __('posta.posta.prazdna') }}</dd>
            </dl>
            <p class="posta-slabe" style="margin-top: .75rem;">{{ __('posta.posta.schranky_dns_domeny_spf_dkim_dmarc') }}</p>
        </x-filament::section>
    @endif

    <form wire:submit="uloz">
        {{ $this->form }}

        @if ($propojeni['propojeno'] || static::webovy())
            <div style="margin-top: 1.5rem">
                <x-filament::button type="submit">{{ __('posta.posta.ulozit') }}</x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
