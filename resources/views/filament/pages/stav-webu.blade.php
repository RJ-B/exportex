{{-- Tři karty stavů; aktuální je zvýrazněný a místo tlačítka má štítek. --}}
<x-filament-panels::page>
    <style>
        .stav-mrizka { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem; }
        .stav-karta { border-radius: .75rem; padding: 1rem; display: flex; flex-direction: column; gap: .75rem; justify-content: space-between;
                      box-shadow: inset 0 0 0 1px rgb(3 7 18 / .1); background: var(--gray-50); }
        .dark .stav-karta { box-shadow: inset 0 0 0 1px rgb(255 255 255 / .1); background: rgb(255 255 255 / .03); }
        .stav-nazev { display: flex; align-items: center; gap: .5rem; font-weight: 600; }
        .stav-popis { font-size: .875rem; color: var(--gray-500); }
    </style>

    <x-filament::section>
        <x-slot name="description">Přihlášený člověk vidí web vždycky celý. Administrace a kontrola zdraví (/zdravi) běží v každém stavu.</x-slot>

        <div class="stav-mrizka">
            @foreach ($stavy as $stav)
                @php($je = $stav === $aktualni)
                <div class="stav-karta" @if ($je) style="box-shadow: inset 0 0 0 2px var(--{{ $stav->barva() }}-500);" @endif>
                    <div style="display: flex; flex-direction: column; gap: .35rem;">
                        <span class="stav-nazev">
                            <x-filament::icon :icon="$stav->ikona()" style="width: 1.25rem; height: 1.25rem;" />
                            {{ $stav->nazev() }}
                        </span>
                        <span class="stav-popis">{{ $stav->popis() }}</span>
                    </div>
                    <div>
                        @if ($je)
                            <x-filament::badge :color="$stav->barva()" icon="heroicon-m-check">Právě zapnuto</x-filament::badge>
                        @else
                            {{ ($this->prepnoutAction)(['stav' => $stav->value]) }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($zmena)
            <p class="stav-popis" style="margin-top: 1rem;">Naposledy přepnuto: {{ $zmena }}.</p>
        @endif
    </x-filament::section>

    <form wire:submit="uloz">
        {{ $this->form }}

        <div style="margin-top: 1.5rem">
            <x-filament::button type="submit">Uložit texty</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
