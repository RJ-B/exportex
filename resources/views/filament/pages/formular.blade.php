{{-- Obecná stránka s formulářem a tlačítkem Uložit (metoda uloz()). --}}
<x-filament-panels::page>
    <form wire:submit="uloz">
        {{ $this->form }}

        <div style="margin-top: 1.5rem">
            <x-filament::button type="submit">Uložit</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
