{{-- Účet nahoře v menu na mobilu (App\Filament\Rozlozeni): avatar s uživatelským
     menu je na mobilu tady, ne v horní liště. Na počítači skryté (rozlozeni.css). --}}
@php
    $uzivatel = filament()->auth()->user();
@endphp

@if ($uzivatel)
    <div class="simren-ucet-mobil">
        <div class="simren-ucet-mobil-hlava">
            <x-filament-panels::avatar.user :user="$uzivatel" />
            <div class="simren-ucet-mobil-jmeno">
                <strong>{{ filament()->getUserName($uzivatel) }}</strong>
                <span>{{ $uzivatel->email }}</span>
            </div>
            <button type="button" class="simren-ucet-mobil-zavrit" x-data="{}" x-on:click="$store.sidebar.close()" aria-label="{{ __('administrace.ucet_mobil.zavrit_menu') }}">
                <x-filament::icon icon="heroicon-o-x-mark" />
            </button>
        </div>
        <nav class="simren-ucet-mobil-odkazy" aria-label="{{ __('administrace.ucet_mobil.ucet') }}">
            @if (filament()->hasProfile())
                <a href="{{ filament()->getProfileUrl() }}">
                    <x-filament::icon icon="heroicon-o-user-circle" /> {{ __('administrace.ucet_mobil.profil') }}
                </a>
            @endif
            <a href="{{ \App\Filament\Pages\Zabezpeceni::getUrl() }}">
                <x-filament::icon icon="heroicon-o-finger-print" /> {{ __('administrace.ucet_mobil.zabezpeceni') }}
            </a>
            <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                @csrf
                <button type="submit">
                    <x-filament::icon icon="heroicon-o-arrow-left-on-rectangle" /> {{ __('administrace.ucet_mobil.odhlasit') }}
                </button>
            </form>
        </nav>
    </div>
@endif
