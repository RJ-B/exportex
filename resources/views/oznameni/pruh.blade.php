{{-- Pruh přes celý web a administraci (odstávky, výpadky – App\Support\Oznameni\Pruh).
     Patří hned za <body> každého layoutu webu: @include('oznameni.pruh').
     Pruh pro všechny vidí i nepřihlášený; vážný nejde zavřít. --}}
@php($pruhy = \App\Support\Oznameni\Pruh::pro(auth()->user()))
@if ($pruhy->isNotEmpty())
    @include('oznameni._zdroje')
    <div class="ozn-pruhy" wire:ignore>
        @foreach ($pruhy as $o)
            <div class="ozn-pruh ozn-pruh--{{ $o->zavaznost->value }}" data-ozn-pruh="{{ $o->uuid }}" role="{{ $o->zavaznost->value === 'kriticke' ? 'alert' : 'status' }}">
                <div class="ozn-pruh-obsah">
                    <strong>{{ $o->titulek }}</strong>
                    @if (filled($o->text))<span class="ozn-pruh-text">{{ \Illuminate\Support\Str::limit($o->textProsty(), 220) }}</span>@endif
                    @if ($o->udalost_od)
                        <span class="ozn-pruh-kdy">
                            {{ $o->udalost_od->format('j. n. H:i') }}@if ($o->udalost_do)–{{ $o->udalost_do->isSameDay($o->udalost_od) ? $o->udalost_do->format('H:i') : $o->udalost_do->format('j. n. H:i') }}@endif
                            @if ($o->udalost_od->isFuture())<span class="ozn-odpocet" data-ozn-od="{{ $o->udalost_od->toIso8601String() }}"></span>@endif
                        </span>
                    @endif
                    @if (filled($o->odkaz))<a href="{{ $o->odkaz }}">{{ $o->odkaz_text ?: 'Více' }}</a>@endif
                </div>
                @if ($o->zavaznost->zaviratelny())
                    <button type="button" class="ozn-zavrit" data-ozn-zavrit aria-label="Zavřít">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M5 5l10 10M15 5L5 15"/></svg>
                    </button>
                @endif
            </div>
        @endforeach
    </div>
    {{-- Zavřené pruhy schovat hned (ne až po načtení skriptu – jinak by bliknuly). --}}
    <script>
        (function () {
            try {
                document.querySelectorAll('[data-ozn-pruh]').forEach(function (p) {
                    if (p.querySelector('[data-ozn-zavrit]') && localStorage.getItem('ozn-pruh:' + p.dataset.oznPruh)) { p.hidden = true; }
                });
            } catch (e) {}
        })();
    </script>
@endif
