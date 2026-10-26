{{-- Detail chyby. Nahoře co a kde, pak kolikrát a odkdy, dole kontext
     posledního výskytu a zásobník volání. --}}
<div class="simren-detail">
    <div class="simren-chyba">
        <strong>{{ $chyba->shortException() }}</strong>
        <div style="margin-top: .25rem">{{ $chyba->message }}</div>
        @if ($chyba->shortFile())
            <div class="simren-mono simren-slabe simren-zlom" style="margin-top: .25rem">{{ $chyba->shortFile() }}:{{ $chyba->line }}</div>
        @endif
    </div>

    <div class="simren-pas">
        <div><div class="simren-slabe">{{ __('administrace.chyba_detail.vyskytu') }}</div><b>{{ $chyba->occurrences }}</b></div>
        <div><div class="simren-slabe">{{ __('administrace.chyba_detail.poprve') }}</div>{{ $chyba->first_seen_at?->format(__('formaty.datum_cas')) ?? '—' }}</div>
        <div><div class="simren-slabe">{{ __('administrace.chyba_detail.naposledy') }}</div>{{ $chyba->last_seen_at?->format(__('formaty.datum_cas')) ?? '—' }}</div>
        <div>
            <div class="simren-slabe">{{ __('administrace.chyba_detail.vyreseno') }}</div>
            {{ $chyba->resolved_at?->format(__('formaty.datum_cas')) ?? '—' }}
            @if ($chyba->resolved_at)
                <div class="simren-slabe">{{ $chyba->resolvedByLabel() ?? '—' }}</div>
            @endif
        </div>
    </div>

    @if ($chyba->resolved_at && $chyba->resolution_note)
        <div class="simren-chyba" style="margin-bottom: .75rem">
            <div class="simren-slabe">{{ __('administrace.chyba_detail.co_se_udelalo') }}</div>
            <div style="white-space: pre-line">{{ $chyba->resolution_note }}</div>
        </div>
    @endif

    <dl class="simren-udaje">
        <dt>URL</dt>
        <dd class="simren-zlom">{{ $chyba->url ?: __('administrace.chyba_detail.mimo_request_napr_cron') }}</dd>
        <dt>{{ __('administrace.chyba_detail.metoda') }}</dt>
        <dd>{{ $chyba->method ?: '—' }}</dd>
        <dt>{{ __('administrace.chyba_detail.uzivatel') }}</dt>
        <dd>{{ $chyba->user?->getFilamentName() ?? ($chyba->user_id ? '#'.$chyba->user_id : __('administrace.chyba_detail.neprihlaseny')) }}</dd>
        <dt>{{ __('administrace.chyba_detail.odkud') }}</dt>
        <dd>{{ $chyba->ip ?: '—' }}</dd>
    </dl>

    @if ($chyba->trace)
        <details>
            <summary class="simren-slabe" style="cursor: pointer">{{ __('administrace.chyba_detail.zasobnik_volani') }}</summary>
            <pre class="simren-trace">{{ $chyba->trace }}</pre>
        </details>
    @endif
</div>
