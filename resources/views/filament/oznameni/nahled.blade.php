{{-- Náhled oznámení: jak vypadá v centru, v pruhu a v e-mailu. --}}
<div class="simren-detail">
    @if ($oznameni->maKanal(\App\Enums\KanalOznameni::Pruh))
        <div>
            <p class="simren-slabe">Pruh přes web</p>
            @include('oznameni._zdroje')
            <div class="ozn-pruhy" style="border-radius: .5rem; overflow: hidden;">
                <div class="ozn-pruh ozn-pruh--{{ $oznameni->zavaznost->value }}">
                    <div class="ozn-pruh-obsah">
                        <strong>{{ $oznameni->titulek }}</strong>
                        @if (filled($oznameni->text))<span class="ozn-pruh-text">{{ \Illuminate\Support\Str::limit($oznameni->textProsty(), 220) }}</span>@endif
                        @if ($oznameni->udalost_od)<span class="ozn-pruh-kdy">{{ $oznameni->udalost_od->format('j. n. H:i') }}@if ($oznameni->udalost_do)–{{ $oznameni->udalost_do->format('H:i') }}@endif</span>@endif
                        @if (filled($oznameni->odkaz))<a href="#" onclick="return false">{{ $oznameni->odkaz_text ?: 'Více' }}</a>@endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($oznameni->maKanal(\App\Enums\KanalOznameni::Centrum))
        <div>
            <p class="simren-slabe">Centrum oznámení (zvoneček)</p>
            <div class="simren-ramec">
                <span class="simren-slabe">{{ $oznameni->druh->nazev() }} · právě teď</span><br>
                <strong>{{ $oznameni->titulek }}</strong>
                <div style="margin-top: .25rem;">{{ $oznameni->textHtml() }}</div>
                @if (filled($oznameni->odkaz))<p style="margin-top: .5rem;"><u>{{ $oznameni->odkaz_text ?: 'Zobrazit' }}</u> → {{ $oznameni->odkaz }}</p>@endif
            </div>
        </div>
    @endif

    @if ($email)
        <div>
            <p class="simren-slabe">E-mail</p>
            <iframe sandbox="" srcdoc="{{ $email }}" title="Náhled e-mailu" style="width: 100%; height: 480px; border: 1px solid var(--gray-200); border-radius: .5rem; background: #fff;"></iframe>
        </div>
    @endif
</div>
