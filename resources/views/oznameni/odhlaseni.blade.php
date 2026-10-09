{{-- Odhlášení z novinek / nabídek odkazem z e-mailu (podepsaný odkaz, bez přihlášení).
     Jedno tlačítko – skener odkazů v poště, který odkaz jen otevře, nic neodhlásí. --}}
@if ($hotovo)
    <p class="hlaska">Hotovo. Na {{ $email }} už {{ $druh === \App\Enums\DruhOznameni::Marketing ? 'nabídky a akce' : 'novinky' }} e-mailem posílat nebudeme.</p>
    <p class="poznamka">Provozní zprávy o vašem účtu a objednávkách chodí dál. Kdybyste si to rozmysleli, souhlas znovu udělíte v předvolbách oznámení po přihlášení.</p>
@else
    <p>Chcete přestat dostávat {{ $druh === \App\Enums\DruhOznameni::Marketing ? 'nabídky a akce' : 'novinky' }} na <strong>{{ $email }}</strong>?</p>
    <form method="post" action="{{ request()->fullUrl() }}">
        <button type="submit" class="tlacitko">Odhlásit</button>
    </form>
    <p class="poznamka">Provozní zprávy o vašem účtu a objednávkách chodí dál.</p>
@endif
