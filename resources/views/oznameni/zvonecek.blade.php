{{-- Zvoneček – centrum oznámení přihlášeného (web i administrace). Seznam se načte
     až při otevření (/oznameni/centrum), počet nepřečtených je hned.
     Na webu: @include('oznameni.zvonecek') kamkoli do hlavičky (nepřihlášenému nic nevykreslí).
     $vse = kam vede „Zobrazit všechna“ (výchozí stránka /oznameni). --}}
@auth
    @php($neprectenych = \App\Support\Oznameni\Centrum::neprectenych(auth()->user()))
    @include('oznameni._zdroje')
    <div class="ozn-zvonecek" wire:ignore
         data-ozn-centrum="{{ route('oznameni.centrum') }}"
         data-ozn-precteno-vse="{{ route('oznameni.precteno-vse') }}"
         data-ozn-csrf="{{ csrf_token() }}">
        <button type="button" class="ozn-tlacitko" aria-haspopup="dialog" aria-expanded="false"
                aria-label="Oznámení{{ $neprectenych ? ', nepřečtených '.$neprectenych : '' }}" title="Oznámení">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
            <span class="ozn-pocet" @if (! $neprectenych) hidden @endif>{{ $neprectenych > 99 ? '99+' : $neprectenych }}</span>
        </button>
        <div class="ozn-panel" role="dialog" aria-label="Oznámení" hidden>
            <div class="ozn-hlava">
                <strong>Oznámení</strong>
                <button type="button" class="ozn-odkaz" data-ozn-vse-precteno @if (! $neprectenych) hidden @endif>Označit vše jako přečtené</button>
            </div>
            <div class="ozn-seznam" aria-live="polite"><p class="ozn-prazdno">Načítám…</p></div>
            <a class="ozn-pata" href="{{ $vse ?? route('oznameni.stranka') }}">Zobrazit všechna a předvolby</a>
        </div>
    </div>
@endauth
