{{-- Detail auditní události: co se stalo a co přesně se změnilo.
     Diff před/po je jádro – bez něj je audit jen seznam nadpisů. --}}
<div class="simren-detail">
    <dl class="simren-udaje">
        <dt>{{ __('administrace.audit_detail.kdy') }}</dt>
        <dd>{{ $zaznam->created_at?->format(__('formaty.datum_cas_sekundy')) }}</dd>

        <dt>{{ __('administrace.audit_detail.kdo') }}</dt>
        <dd>{{ $zaznam->user?->getFilamentName() ?? ($zaznam->user_id ? '#'.$zaznam->user_id : __('administrace.audit_detail.system_smazany_ucet')) }}</dd>

        <dt>{{ __('administrace.audit_detail.role_v_tu_chvili') }}</dt>
        <dd>{{ \App\Models\User::popiskyRoli()[$zaznam->user_role] ?? ($zaznam->user_role ?: '—') }}</dd>

        <dt>{{ __('administrace.audit_detail.odkud') }}</dt>
        <dd>{{ $zaznam->ip_address ?: '—' }}</dd>

        <dt>{{ __('administrace.audit_detail.ceho_se_to_tyka') }}</dt>
        <dd>{{ $zaznam->auditable_type ? class_basename($zaznam->auditable_type).' #'.$zaznam->auditable_id : '—' }}</dd>
    </dl>

    @if ($zaznam->summary)
        <div class="simren-ramec">{{ $zaznam->summary }}</div>
    @endif

    @php
        // Sloučené klíče z obou stran: přidané pole je jen v „po", smazané jen
        // v „před" – kdyby se bralo jen jedno, půlka změny by v diffu chyběla.
        $klice = array_unique(array_merge(
            array_keys($zaznam->old_values ?? []),
            array_keys($zaznam->new_values ?? []),
        ));
        $naText = fn ($h) => is_scalar($h) || $h === null
            ? (string) ($h ?? '—')
            : json_encode($h, JSON_UNESCAPED_UNICODE);
    @endphp

    @if ($klice)
        <div>
            <div style="font-weight: 500; margin-bottom: .5rem">{{ __('administrace.audit_detail.co_se_zmenilo') }}</div>
            <table class="simren-tabulka">
                <thead>
                    <tr><th>{{ __('administrace.audit_detail.pole') }}</th><th>{{ __('administrace.audit_detail.pred') }}</th><th>{{ __('administrace.audit_detail.po') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($klice as $klic)
                        <tr>
                            <td class="simren-mono">{{ $klic }}</td>
                            <td class="simren-pred">{{ \Illuminate\Support\Str::limit($naText(data_get($zaznam->old_values, $klic)), 60) }}</td>
                            <td style="font-weight: 500">{{ \Illuminate\Support\Str::limit($naText(data_get($zaznam->new_values, $klic)), 60) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
