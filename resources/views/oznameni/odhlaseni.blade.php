{{-- Odhlášení z novinek / nabídek odkazem z e-mailu (podepsaný odkaz, bez přihlášení).
     Jedno tlačítko – skener odkazů v poště, který odkaz jen otevře, nic neodhlásí. --}}
@if ($hotovo)
    <p class="hlaska">{{ __('web.odhlaseni.hotovo_na_uz_e_mailem_posilat', ['email' => $email, 'druh' => $druh === \App\Enums\DruhOznameni::Marketing ? __('web.odhlaseni.nabidky_a_akce') : 'novinky']) }}</p>
    <p class="poznamka">{{ __('web.odhlaseni.provozni_zpravy_o_vasem_uctu_a') }}</p>
@else
    <p>{{ __('web.odhlaseni.chcete_prestat_dostavat_na', ['druh' => $druh === \App\Enums\DruhOznameni::Marketing ? __('web.odhlaseni.nabidky_a_akce') : 'novinky']) }} <strong>{{ $email }}</strong>?</p>
    <form method="post" action="{{ request()->fullUrl() }}">
        <button type="submit" class="tlacitko">{{ __('web.odhlaseni.odhlasit') }}</button>
    </form>
    <p class="poznamka">{{ __('web.odhlaseni.provozni_zpravy_o_vasem_uctu_a_2') }}</p>
@endif
