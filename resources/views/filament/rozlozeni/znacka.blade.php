{{-- Logo a název aplikace (App\Filament\Rozlozeni) – nahoře v levém menu, na mobilu
     vlevo v horní liště. Stejné logo jako na webu (LogoWebu): široké logo (nápis) stojí
     samo, užší značka má vedle název. Bez loga čtvercová ikona (IkonaWebu, výchozí) a název. --}}
@php($logo = \App\Support\LogoWebu::url())
@if ($logo && \App\Support\LogoWebu::siroke())
    <span class="simren-znacka">
        <img class="simren-znacka-logo" src="{{ $logo }}" alt="{{ filament()->getBrandName() }}">
    </span>
@else
    <span class="simren-znacka">
        <img src="{{ $logo ?? \App\Support\IkonaWebu::url(192) }}" alt="" width="28" height="28">
        <span class="simren-znacka-nazev">{{ filament()->getBrandName() }}</span>
    </span>
@endif
