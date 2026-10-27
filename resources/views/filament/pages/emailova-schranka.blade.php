{{-- Nastavení → E-mailová schránka (App\Filament\Pages\EmailovaSchranka): formulář s vlastními tlačítky
     (Uložit a ověřit, Zkušební e-mail, Vrátit na systémovou), DNS domény. --}}
<x-filament-panels::page>
    {{ $this->form }}

    <x-filament-actions::modals />
</x-filament-panels::page>
