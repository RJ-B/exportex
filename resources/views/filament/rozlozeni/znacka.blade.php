{{-- Logo a název aplikace (App\Filament\Rozlozeni) – nahoře v levém menu, na mobilu
     vlevo v horní liště. Čtvercová ikona z loga webu (IkonaWebu, bez loga výchozí). --}}
<span class="simren-znacka">
    <img src="{{ \App\Support\IkonaWebu::url(192) }}" alt="" width="28" height="28">
    <span class="simren-znacka-nazev">{{ filament()->getBrandName() }}</span>
</span>
