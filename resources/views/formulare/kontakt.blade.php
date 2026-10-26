{{-- Kontaktní formulář (KontaktController). Projekt s vlastním vzhledem ho vloží:
     @include('formulare.kontakt'). Ochrana proti botům: formulare._ochrana. --}}
@if (session('odeslano'))
    <div class="hlaska" role="status">{{ __('web.kontakt.dekujeme_zprava_je_odeslana_ozveme_se') }}</div>
@endif

<form method="POST" action="{{ route('kontakt.odeslat') }}" novalidate>
    @csrf
    @include('formulare._ochrana')

    <div class="dvojice">
        <div>
            <label for="jmeno">{{ __('web.kontakt.jmeno') }}</label>
            <input id="jmeno" name="jmeno" value="{{ old('jmeno') }}" required maxlength="80" autocomplete="given-name">
            @error('jmeno')<p class="chyba">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="prijmeni">{{ __('web.kontakt.prijmeni') }}</label>
            <input id="prijmeni" name="prijmeni" value="{{ old('prijmeni') }}" required maxlength="80" autocomplete="family-name">
            @error('prijmeni')<p class="chyba">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email">{{ __('web.kontakt.e_mail') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email">
            @error('email')<p class="chyba">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="telefon">{{ __('web.kontakt.telefon') }} <span style="font-weight: 400; color: var(--slabe);">{{ __('web.kontakt.nepovinne') }}</span></label>
            <input id="telefon" name="telefon" type="tel" value="{{ old('telefon') }}" maxlength="40" autocomplete="tel">
            @error('telefon')<p class="chyba">{{ $message }}</p>@enderror
        </div>
    </div>

    <label for="zprava">{{ __('web.kontakt.zprava') }}</label>
    <textarea id="zprava" name="zprava" rows="6" required maxlength="5000">{{ old('zprava') }}</textarea>
    @error('zprava')<p class="chyba">{{ $message }}</p>@enderror

    @error('formular')<p class="chyba">{{ $message }}</p>@enderror

    <button type="submit" class="tlacitko">{{ __('web.kontakt.odeslat_zpravu') }}</button>
    <p class="poznamka">{{ __('web.kontakt.udaje_pouzijeme_jen_k_vyrizeni_vasi') }} <a href="{{ route('ochrana-udaju') }}">{{ __('web.kontakt.ochrane_osobnich_udaju') }}</a>.</p>
</form>
