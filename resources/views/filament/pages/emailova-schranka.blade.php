{{-- Nastavení → E-mailová schránka (App\Filament\Pages\EmailovaSchranka): formulář s vlastními tlačítky
     (Uložit a ověřit, Zkušební e-mail, Vrátit na systémovou), DNS domény. --}}
<x-filament-panels::page>
    {{ $this->form }}

    @if (\App\Filament\Pages\Email::canAccess())
        <p style="font-size:.875rem;color:var(--gray-500)">
            {{ __('pruvodce.emailova_schranka.zvlast_podle_ucelu') }}
            <x-filament::link :href="\App\Filament\Pages\Email::getUrl()" size="sm">{{ __('pruvodce.emailova_schranka.e_mail_podle_ucelu') }}</x-filament::link>
        </p>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
