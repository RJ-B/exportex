{{-- Detail e-mailu. Chyba zůstává i po úspěšném opakování – jinak by
     z historie zmizelo, že s tímhle mailem byl problém. --}}
<div class="simren-detail">
    <dl class="simren-udaje">
        <dt>{{ count($mail->recipients ?? []) > 1 ? 'Příjemci' : 'Příjemce' }}</dt>
        <dd>
            @forelse ($mail->recipients ?? [] as $adresa)
                <div>{{ $adresa }}</div>
            @empty
                {{ $mail->to_email }}
            @endforelse
        </dd>

        <dt>Stav</dt>
        <dd>{{ $mail->statusLabel() }}</dd>

        <dt>Vytvořeno</dt>
        <dd>{{ $mail->created_at?->format('j. n. Y H:i:s') }}</dd>

        <dt>{{ $mail->isFailed() ? 'Selhalo' : 'Odesláno' }}</dt>
        <dd>{{ ($mail->sent_at ?? $mail->failed_at)?->format('j. n. Y H:i:s') ?? '—' }}</dd>

        <dt>Pokusů</dt>
        <dd>{{ $mail->attempts }}</dd>

        <dt>Kdo poslal</dt>
        <dd>{{ $mail->odesilatelPopis() }}</dd>

        @if ($mail->posta_id)
            <dt>Id v Poště</dt>
            <dd class="simren-mono">{{ $mail->posta_id }}</dd>
        @endif

        <dt>Typ</dt>
        <dd class="simren-mono">{{ $mail->mailable ? class_basename($mail->mailable) : '—' }}</dd>
    </dl>

    @if ($mail->error)
        <div>
            <div class="simren-slabe">Chyba při odesílání</div>
            <pre class="simren-ramec simren-mono simren-pre">{{ $mail->error }}</pre>
            @if ($mail->wasRetried())
                <p class="simren-slabe">Tohle je stopa po dřívějším neúspěchu – mail nakonec odešel.</p>
            @endif
        </div>
    @endif
</div>
