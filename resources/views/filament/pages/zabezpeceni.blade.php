{{-- Zabezpečení účtu – druhý krok přihlášení (App\Filament\Pages\Zabezpeceni).
     Tailwind ve vlastních šablonách nefunguje, proto třídy simren-* a inline styly
     s proměnnými Filamentu. --}}
<x-filament-panels::page>
    <div class="simren-zabezpeceni">
        <x-filament::section>
            <x-slot name="heading">{{ __('administrace.zabezpeceni.druhy_krok_prihlaseni') }}</x-slot>
            <x-slot name="description">
                {{ __('administrace.zabezpeceni.po_hesle_se_prihlaseni_potvrdi_passkeyem') }}
            </x-slot>

            @if ($zarizeni->isEmpty() && ! $maAuthenticator)
                <div style="color: var(--warning-600);">{{ __('administrace.zabezpeceni.prihlaseni_ted_chrani_jen_heslo_pridejte') }}</div>
            @elseif (! $maAuthenticator)
                <div style="color: var(--warning-600);">{{ __('administrace.zabezpeceni.zapnete_i_authenticator_kdyz_passkey_na') }}</div>
            @else
                <div style="color: var(--success-600);">
                    {{ __('administrace.zabezpeceni.zapnuto_authenticator', ['zarizeni' => $zarizeni->isNotEmpty() ? 'passkey ('.$zarizeni->count().') a ' : '']) }}
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('administrace.zabezpeceni.passkey') }}</x-slot>
            <x-slot name="description">{{ __('administrace.zabezpeceni.passkey_je_ulozeny_v_zarizeni_klicenka') }}</x-slot>

            @if (! $passkeyTady)
                <div style="color: var(--warning-600); margin-bottom: 1rem;">
                    {{ __('administrace.zabezpeceni.na_teto_adrese_passkey_nejde_patri', ['domena' => $domena]) }}
                </div>
            @endif

            @if ($zarizeni->isEmpty())
                <div class="simren-slabe" style="font-size: .875rem;">{{ __('administrace.zabezpeceni.zadne_zarizeni') }}</div>
            @else
                <ul class="simren-zarizeni">
                    @foreach ($zarizeni as $z)
                        <li>
                            <div>
                                <strong>{{ $z->name }}</strong>
                                @if ($z->authenticator)<span class="simren-slabe"> · {{ $z->authenticator }}</span>@endif
                                <div class="simren-slabe">
                                    {{ __('administrace.zabezpeceni.pridano_naposledy', ['format' => $z->created_at?->format(__('formaty.datum')), 'format2' => $z->last_used_at?->format(__('formaty.datum_cas')) ?? __('administrace.zabezpeceni.jeste_nepouzite')]) }}
                                </div>
                            </div>
                            <div>{{ ($this->odebratAction)(['id' => $z->id]) }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Heslo a název zařízení se zadají v okně po kliknutí; po potvrzení okna
                 stránka pošle událost a passkey zaregistruje prohlížeč. --}}
            @if ($passkeyTady)
                <div style="display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 1rem;"
                     x-data="{ chyba: null, bezi: false }"
                     x-on:simren-pridat-passkey.window="bezi = true; chyba = null;
                        window.simrenPasskeys.register($event.detail.nazev)
                            .then(() => $wire.$refresh())
                            .catch(e => chyba = window.simrenPasskeys.zprava(e))
                            .finally(() => bezi = false)">
                    {{ $this->pridatZarizeniAction }}
                    {{ $this->sparovatTelefonAction }}
                    <div x-show="bezi" x-cloak class="simren-slabe" style="width: 100%; font-size: .875rem;">{{ __('administrace.zabezpeceni.cekam_na_touch_id_face_id') }}</div>
                    <div x-show="chyba" x-cloak x-text="chyba" style="color: var(--danger-600); width: 100%; font-size: .875rem;"></div>
                </div>
            @endif

            @if ($parovaniOdkaz)
                <div wire:poll.4s="obnovit" style="margin-top: 1rem; display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap;">
                    <div style="background: white; padding: .5rem; border-radius: .5rem; line-height: 0; width: 220px; flex: none;">{!! $parovaniQr !!}</div>
                    <div style="flex: 1; min-width: 14rem; display: flex; flex-direction: column; gap: .5rem; font-size: .875rem;">
                        <div>{{ __('administrace.zabezpeceni.naskenujte_kod_fotoaparatem_telefonu_a', ['platnost_minut' => \App\Bezpecnost\ParovaniZarizeni::PLATNOST_MINUT]) }}</div>
                        <div class="simren-slabe" style="font-size: .875rem;">{{ __('administrace.zabezpeceni.na_pocitaci_pak_pri_prihlaseni_vyberte') }}</div>
                        <div><x-filament::link tag="button" color="gray" wire:click="zrusitParovani">{{ __('administrace.zabezpeceni.zrusit_odkaz') }}</x-filament::link></div>
                    </div>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('administrace.zabezpeceni.kod_z_authenticatoru') }}</x-slot>
            <x-slot name="description">{{ __('administrace.zabezpeceni.sestimistny_kod_z_aplikace_google') }}</x-slot>

            {{ $this->authenticator }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
