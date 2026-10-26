{{-- Výzva k passkeyi ve druhém kroku přihlášení (App\Filament\Auth\VyzvaPasskey).
     Odpověď prohlížeče jde do stavu pole a přihlášení se odešle; ověří ji server.
     Bez podpory passkeye (nebo po selhání) nabídne kód z Authenticatoru – přepne
     výběr způsobu (data.multiFactor.provider), když ho účet má. --}}
@php
    $moznosti = $getMoznosti();
    $maKod = $field->maKod();
@endphp

<x-filament-forms::field-wrapper :field="$field">
    <div
        x-data="{
            bezi: false,
            chyba: null,
            podpora: Boolean(window.PublicKeyCredential && navigator.credentials && PublicKeyCredential.parseRequestOptionsFromJSON),
            init() {
                if (! this.podpora) {
                    this.chyba = @js(__('administrace.vyzva_passkey.zarizeni_passkey_neumi'));
                    @if ($maKod) this.naKod(); @endif
                }
            },
            async potvrdit() {
                const moznosti = this.$el.closest('[data-moznosti]')?.dataset.moznosti;
                if (! moznosti) { this.chyba = @js(__('administrace.vyzva_passkey.vyzva_vyprsela')); return; }
                this.bezi = true;
                this.chyba = null;
                try {
                    const credential = await navigator.credentials.get({ publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(JSON.parse(moznosti)) });
                    this.$wire.$set(@js($getStatePath()), JSON.stringify(credential.toJSON()), false);
                    await this.$wire.authenticate();
                } catch (e) {
                    this.chyba = e?.name === 'NotAllowedError' ? @js(__('administrace.vyzva_passkey.potvrzeni_zruseno')) : (e?.message || @js(__('administrace.vyzva_passkey.passkey_se_nepovedl')));
                } finally {
                    this.bezi = false;
                }
            },
            naKod() {
                this.$wire.$set('data.multiFactor.provider', 'app');
            },
        }"
        class="simren-vyzva-passkey"
        data-moznosti="{{ $moznosti ? json_encode($moznosti) : '' }}"
        style="display: flex; flex-direction: column; gap: .75rem;"
    >
        <p style="margin: 0; color: var(--gray-600);" class="simren-druhy-krok-text">
            {{ __('administrace.vyzva_passkey.potvrdte_prihlaseni_passkeyem_touch_id') }}
        </p>

        <x-filament::button type="button" icon="heroicon-o-finger-print" x-on:click="potvrdit()" x-bind:disabled="bezi || ! podpora">
            <span x-show="! bezi">{{ __('administrace.vyzva_passkey.pouzit_passkey') }}</span>
            <span x-show="bezi" x-cloak>{{ __('administrace.vyzva_passkey.cekam_na_potvrzeni') }}</span>
        </x-filament::button>

        <div x-show="chyba" x-cloak x-text="chyba" style="color: var(--danger-600); font-size: .875rem;"></div>

        @if ($maKod)
            <div style="font-size: .875rem;">
                <x-filament::link tag="button" type="button" x-on:click="naKod()">
                    {{ __('administrace.vyzva_passkey.passkey_nejde_pouzit_kod_z') }}
                </x-filament::link>
            </div>
        @endif
    </div>
</x-filament-forms::field-wrapper>
