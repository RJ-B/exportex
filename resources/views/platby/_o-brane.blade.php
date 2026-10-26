{{-- Oznámení o platební bráně do obchodních podmínek, právních textů a stránek plateb (doplněk Platby).
     Comgate žádá tento text slovo od slova; u Mo.one jen obecná věta – o licenci Mo.one nic nevymýšlet.
     Do vlastních obchodních podmínek projektu: @include('platby._o-brane')
     Parametry: branaKod (comgate | moone), výchozí brána, která se skutečně používá; trida = CSS třída odstavce. --}}
@if (config('sablona.doplnky.platby'))
    @php($branaKod = $branaKod ?? \App\Platby\NastaveniPlateb::kodBrany())
    @if ($branaKod === 'comgate')
        <p @class([$trida ?? null])>{{ __('platby.o_brane.online_platby_kartou_visa_mastercard') }} <a href="https://www.comgate.eu" target="_blank" rel="noopener">www.comgate.eu</a>.</p>
    @elseif ($branaKod === 'moone')
        <p @class([$trida ?? null])>{{ __('platby.o_brane.platby_zajistuje_platebni_brana_mo_one') }}</p>
    @endif
@endif
