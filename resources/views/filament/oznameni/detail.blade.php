{{-- Detail naplánovaného / odeslaného oznámení: co, komu, kudy, kdy a čísla (Statistika). --}}
@php($f = fn ($n) => number_format((int) $n, 0, ',', ' '))
<div class="simren-detail">
    @if ($cisla)
        {{-- Problémy nahoře: nedoručené e-maily. --}}
        @if (($cisla['email_nedoruceno'] ?? 0) > 0)
            <div class="simren-chyba"><strong>{{ $f($cisla['email_nedoruceno']) }} e-mailů se nepodařilo doručit.</strong> Důvody jsou v Provoz → Logy → E-maily.</div>
        @endif
        <div class="simren-pas">
            <div><b>{{ $f($cisla['prijemcu']) }}</b><span class="simren-slabe">příjemců</span></div>
            <div><b>{{ $f($cisla['precteno']) }}</b><span class="simren-slabe">přečetlo{{ $cisla['prijemcu'] ? ' ('.round($cisla['precteno'] / $cisla['prijemcu'] * 100).' %)' : '' }}</span></div>
            @if (filled($oznameni->odkaz))<div><b>{{ $f($cisla['prokliknuto']) }}</b><span class="simren-slabe">prokliklo</span></div>@endif
            @isset($cisla['email_predano'])
                <div><b>{{ $f($cisla['email_doruceno']) }}</b><span class="simren-slabe">e-mailů doručeno</span></div>
                @if ($cisla['email_ceka'])<div><b>{{ $f($cisla['email_ceka']) }}</b><span class="simren-slabe">e-mailů čeká ve frontě</span></div>@endif
                @if ($cisla['email_bez_souhlasu'])<div><b>{{ $f($cisla['email_bez_souhlasu']) }}</b><span class="simren-slabe">bez souhlasu (e-mail nešel)</span></div>@endif
                @if ($cisla['email_preskoceno'])<div><b>{{ $f($cisla['email_preskoceno']) }}</b><span class="simren-slabe">e-mail vypnutý</span></div>@endif
            @endisset
        </div>
    @endif

    <dl class="simren-udaje">
        <dt>Stav</dt><dd>{{ $oznameni->stav->nazev() }}@if ($oznameni->stav === \App\Enums\StavOznameni::Naplanovano) na {{ $oznameni->naplanovano_na?->format('j. n. Y H:i') }}@endif @if ($oznameni->odeslano_at) {{ $oznameni->odeslano_at->format('j. n. Y H:i') }}@endif</dd>
        <dt>Druh</dt><dd>{{ $oznameni->druh->nazev() }}</dd>
        <dt>Komu</dt><dd>{{ $komu }}</dd>
        <dt>Kudy</dt><dd>@foreach ($oznameni->kanalyEnum() as $kanal){{ $kanal->nazev() }}@if (! $loop->last)<br>@endif @endforeach</dd>
        @if ($oznameni->maKanal(\App\Enums\KanalOznameni::Pruh))
            <dt>Pruh</dt><dd>{{ $oznameni->zavaznost->nazev() }}, {{ $oznameni->pruh_od ? 'od '.$oznameni->pruh_od->format('j. n. Y H:i') : 'od odeslání' }}{{ $oznameni->pruh_do ? ' do '.$oznameni->pruh_do->format('j. n. Y H:i') : ', dokud ho neukončíte' }}</dd>
        @endif
        @if ($oznameni->udalost_od)
            <dt>Odstávka</dt><dd>{{ $oznameni->udalost_od->format('j. n. Y H:i') }}@if ($oznameni->udalost_do) – {{ $oznameni->udalost_do->format('j. n. Y H:i') }}@endif</dd>
        @endif
        <dt>Odkud</dt><dd>{{ ['administrace' => 'Administrace', 'aplikace' => 'Z aplikace (kód)', 'portal' => 'Portál Sim&Ren'][$oznameni->zdroj] ?? $oznameni->zdroj }}@if ($oznameni->odeslal) – {{ $oznameni->odeslal->getFilamentName() }}@endif</dd>
    </dl>

    <div class="simren-ramec">
        <strong>{{ $oznameni->titulek }}</strong>
        <div style="margin-top: .25rem;">{{ $oznameni->textHtml() }}</div>
        @if (filled($oznameni->odkaz))<p style="margin-top: .5rem;">{{ $oznameni->odkaz_text ?: 'Zobrazit' }} → {{ $oznameni->odkaz }}</p>@endif
    </div>
</div>
