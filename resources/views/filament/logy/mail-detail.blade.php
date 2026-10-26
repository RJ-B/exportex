{{-- Detail e-mailu. Chyba zůstává i po úspěšném opakování – jinak by
     z historie zmizelo, že s tímhle mailem byl problém. --}}
<div class="simren-detail">
    <dl class="simren-udaje">
        <dt>{{ count($mail->recipients ?? []) > 1 ? __('administrace.mail_detail.prijemci') : __('administrace.mail_detail.prijemce') }}</dt>
        <dd>
            @forelse ($mail->recipients ?? [] as $adresa)
                <div>{{ $adresa }}</div>
            @empty
                {{ $mail->to_email }}
            @endforelse
        </dd>

        <dt>{{ __('administrace.mail_detail.stav') }}</dt>
        <dd>{{ $mail->statusLabel() }}</dd>

        <dt>{{ __('administrace.mail_detail.vytvoreno') }}</dt>
        <dd>{{ $mail->created_at?->format(__('formaty.datum_cas_sekundy')) }}</dd>

        <dt>{{ $mail->isFailed() ? 'Selhalo' : __('administrace.mail_detail.odeslano') }}</dt>
        <dd>{{ ($mail->sent_at ?? $mail->failed_at)?->format(__('formaty.datum_cas_sekundy')) ?? '—' }}</dd>

        <dt>{{ __('administrace.mail_detail.pokusu') }}</dt>
        <dd>{{ $mail->attempts }}</dd>

        <dt>{{ __('administrace.mail_detail.kdo_poslal') }}</dt>
        <dd>{{ $mail->odesilatelPopis() }}</dd>

        @if ($mail->posta_id)
            <dt>{{ __('administrace.mail_detail.id_v_poste') }}</dt>
            <dd class="simren-mono">{{ $mail->posta_id }}</dd>
        @endif

        <dt>{{ __('administrace.mail_detail.typ') }}</dt>
        <dd class="simren-mono">{{ $mail->mailable ? class_basename($mail->mailable) : '—' }}</dd>
    </dl>

    @if ($mail->error)
        <div>
            <div class="simren-slabe">{{ __('administrace.mail_detail.chyba_pri_odesilani') }}</div>
            <pre class="simren-ramec simren-mono simren-pre">{{ $mail->error }}</pre>
            @if ($mail->wasRetried())
                <p class="simren-slabe">{{ __('administrace.mail_detail.tohle_je_stopa_po_drivejsim_neuspechu') }}</p>
            @endif
        </div>
    @endif
</div>
