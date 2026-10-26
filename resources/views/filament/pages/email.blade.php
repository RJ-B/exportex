{{-- Nastavení → E-mail: schránky firmy a odkud se k jakému účelu posílá (App\Filament\Pages\Email).
     Soběstačná (vlastní styly .email-*), Tailwind třídy ve vlastních šablonách Filament nekompiluje. --}}
<x-filament-panels::page>
    <style>
        .email-stav { border-radius: .5rem; background: var(--gray-50); padding: .75rem 1rem; font-size: .875rem; }
        .dark .email-stav { background: rgb(255 255 255 / .05); }
        .email-chyba { border-radius: .5rem; background: var(--danger-50); padding: .75rem 1rem; font-size: .875rem; }
        .dark .email-chyba { background: rgb(239 68 68 / .1); }
        .email-radek { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; padding: .5rem 0; border-top: 1px solid var(--gray-200); font-size: .875rem; }
        .dark .email-radek { border-color: rgb(255 255 255 / .1); }
        .email-radek:first-child { border-top: 0; }
        .email-adresa { flex: 1 1 14rem; min-width: 0; }
        .email-slabe { color: var(--gray-500); font-size: .8rem; }
    </style>

    @if ($chyba)
        <div class="email-chyba">{{ $chyba }}</div>
    @elseif ($firma)
        <div class="email-stav">
            @if ($firma['vlastnik'])
                {{ __('administrace.email.firma') }} <strong>{{ $firma['vlastnik']['nazev'] }}</strong>@if ($firma['vlastnik']['ico']) {{ __('administrace.email.ico', ['ico' => $firma['vlastnik']['ico']]) }}@endif
            @endif
            @if (! $firma['smi_menit'] && $firma['proc_ne'])
                <div>{{ $firma['proc_ne'] }}</div>
            @endif
        </div>

        <x-filament::section heading="{{ __('administrace.email.schranky_firmy') }}">
            @forelse ($firma['schranky'] as $s)
                <div class="email-radek" wire:key="schranka-{{ $s['adresa'] }}">
                    <div class="email-adresa">
                        <div>{{ $s['jmeno'] ? $s['jmeno'].' <'.$s['adresa'].'>' : $s['adresa'] }}</div>
                        <div class="email-slabe">
                            {{ $s['stav_popis'] ?? $s['stav'] }}@if (($s['stav'] ?? null) === 'chyba' && ($s['stav_zprava'] ?? null)) – {{ $s['stav_zprava'] }}@endif
                            @php $proUcely = collect($firma['vysledne'] ?? [])->filter(fn ($a) => $a === $s['adresa'])->keys()->map(fn ($u) => collect($firma['ucely'])->firstWhere('klic', $u)['nazev'] ?? $u); @endphp
                            @if ($proUcely->isNotEmpty()) {{ __('administrace.email.posila', ['implode' => $proUcely->implode(', ')]) }} @endif
                        </div>
                    </div>
                    @if ($firma['smi_menit'])
                        {{ ($this->overitAction)(['adresa' => $s['adresa']]) }}
                        {{ ($this->zkouskaAction)(['adresa' => $s['adresa']]) }}
                        @if ($s['smi_menit'] ?? false)
                            {{ ($this->odebratAction)(['adresa' => $s['adresa']]) }}
                        @endif
                    @endif
                </div>
            @empty
                <span class="email-slabe">{{ __('administrace.email.firma_zatim_nema_zadnou_schranku_e', ['hodnota' => ($firma['vysledne']['obecne'] ?? null) ? ' ('.$firma['vysledne']['obecne'].')' : '']) }}</span>
            @endforelse
        </x-filament::section>

        {{ $this->form }}
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
