{{-- Přehled → Dokončete nastavení (App\Filament\Widgets\DokonceteNastaveni, kroky z config/pruvodce.php).
     Vlastní styly .pruvodce-*, Tailwind třídy ve vlastních šablonách Filament nekompiluje. --}}
@php $hotovo = $kroky->where('hotovo', true)->count(); @endphp
<x-filament-widgets::widget>
    <style>
        .pruvodce-krok { display: flex; gap: .75rem; align-items: flex-start; padding: .6rem 0; border-top: 1px solid var(--gray-200); }
        .dark .pruvodce-krok { border-color: rgb(255 255 255 / .1); }
        .pruvodce-krok:first-child { border-top: 0; }
        .pruvodce-znak { flex: none; width: 1.25rem; height: 1.25rem; margin-top: .1rem; }
        .pruvodce-text { flex: 1 1 auto; min-width: 0; font-size: .875rem; }
        .pruvodce-popis { color: var(--gray-500); font-size: .8rem; }
        .pruvodce-hotovo .pruvodce-nazev { color: var(--gray-500); text-decoration: line-through; }
        .pruvodce-pruh { height: .375rem; border-radius: 9999px; background: var(--gray-200); overflow: hidden; margin-bottom: .75rem; }
        .dark .pruvodce-pruh { background: rgb(255 255 255 / .1); }
        .pruvodce-pruh > span { display: block; height: 100%; background: var(--primary-600); }
    </style>
    <x-filament::section :heading="__('pruvodce.pruvodce_nastaveni.dokoncete_nastaveni')"
        :description="__('pruvodce.pruvodce_nastaveni.hotovo_z', ['hotovo' => $hotovo, 'celkem' => $kroky->count()])">
        <x-slot name="afterHeader">
            <x-filament::link tag="button" wire:click="skryt" size="sm" color="gray">{{ __('pruvodce.pruvodce_nastaveni.skryt') }}</x-filament::link>
        </x-slot>

        <div class="pruvodce-pruh"><span style="width: {{ $kroky->count() ? round($hotovo / $kroky->count() * 100) : 100 }}%"></span></div>

        @foreach ($kroky as $krok)
            <div @class(['pruvodce-krok', 'pruvodce-hotovo' => $krok['hotovo']]) wire:key="krok-{{ $krok['klic'] }}">
                @if ($krok['hotovo'])
                    <x-filament::icon icon="heroicon-s-check-circle" class="pruvodce-znak" style="color: var(--success-600)" />
                @else
                    <x-filament::icon icon="heroicon-o-stop-circle" class="pruvodce-znak" style="color: var(--gray-400)" />
                @endif
                <div class="pruvodce-text">
                    <div class="pruvodce-nazev">
                        @if (! $krok['hotovo'] && $krok['url'])
                            <x-filament::link :href="$krok['url']">{{ $krok['nazev'] }}</x-filament::link>
                        @else
                            {{ $krok['nazev'] }}
                        @endif
                    </div>
                    @unless ($krok['hotovo'])
                        <div class="pruvodce-popis">{{ $krok['popis'] }}</div>
                    @endunless
                </div>
            </div>
        @endforeach
    </x-filament::section>
</x-filament-widgets::widget>
