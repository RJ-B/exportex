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
        <div><div class="simren-slabe">Výskytů</div><b>{{ $chyba->occurrences }}</b></div>
        <div><div class="simren-slabe">Poprvé</div>{{ $chyba->first_seen_at?->format('j. n. Y H:i') ?? '—' }}</div>
        <div><div class="simren-slabe">Naposledy</div>{{ $chyba->last_seen_at?->format('j. n. Y H:i') ?? '—' }}</div>
        <div>
            <div class="simren-slabe">Vyřešeno</div>
            {{ $chyba->resolved_at?->format('j. n. Y H:i') ?? '—' }}
            @if ($chyba->resolved_at)
                <div class="simren-slabe">{{ $chyba->resolvedBy?->getFilamentName() ?? '—' }}</div>
            @endif
        </div>
    </div>

    <dl class="simren-udaje">
        <dt>URL</dt>
        <dd class="simren-zlom">{{ $chyba->url ?: '— (mimo request, např. cron)' }}</dd>
        <dt>Metoda</dt>
        <dd>{{ $chyba->method ?: '—' }}</dd>
        <dt>Uživatel</dt>
        <dd>{{ $chyba->user?->getFilamentName() ?? ($chyba->user_id ? '#'.$chyba->user_id : '— (nepřihlášený)') }}</dd>
        <dt>Odkud</dt>
        <dd>{{ $chyba->ip ?: '—' }}</dd>
    </dl>

    @if ($chyba->trace)
        <details>
            <summary class="simren-slabe" style="cursor: pointer">Zásobník volání</summary>
            <pre class="simren-trace">{{ $chyba->trace }}</pre>
        </details>
    @endif
</div>
