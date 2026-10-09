{{-- Administrace → Pošta: propojení s Poštou (posta.simren.cz). Soběstačná (vlastní styly),
     aby šla přenést do každého projektu. Tailwind třídy ve vlastních šablonách Filament
     nekompiluje, proto třídy .posta-* s existujícími proměnnými Filamentu. --}}
<x-filament-panels::page>
    <style>
        .posta-stav { border-radius: .5rem; background: var(--gray-50); padding: .75rem 1rem; font-size: .875rem; }
        .dark .posta-stav { background: rgb(255 255 255 / .05); }
        .posta-chyba { border-radius: .5rem; background: var(--danger-50); padding: .75rem 1rem; font-size: .875rem; }
        .dark .posta-chyba { background: rgb(239 68 68 / .1); }
        .posta-udaje { display: grid; grid-template-columns: minmax(8rem, 1fr) 3fr; gap: .4rem 1rem; margin: 0; font-size: .875rem; }
        .posta-udaje dt { color: var(--gray-500); }
        .posta-udaje dd { margin: 0; }
        .posta-slabe { color: var(--gray-500); font-size: .8rem; }
    </style>

    @if (! $propojeni['propojeno'])
        <div class="posta-chyba">
            Aplikace není propojená s Poštou – e-maily (formuláře, upozornění, obnova hesla) neodcházejí.
            Klikni na <strong>Propojit s poštou</strong>; v Poště správce přidělí adresy, ze kterých smí aplikace posílat.
            @if ($stara)
                <br>Aplikace má ještě starou schránku <strong>{{ $stara['adresa'] }}</strong> ({{ $stara['smtp_host'] }}{{ $stara['zdroj'] === 'env' ? ', v .env' : '' }}) –
                při propojení ji Pošta může převzít i s heslem (správce to povolí na souhlasu).
            @endif
        </div>
    @else
        @if ($prevzeti && empty($prevzeti['smazano']))
            <div class="{{ ($prevzeti['ok'] ?? false) ? 'posta-stav' : 'posta-chyba' }}" style="margin-bottom: 1rem">
                <strong>Převzetí staré schránky {{ $prevzeti['adresa'] }}:</strong> {{ $prevzeti['zprava'] ?? '' }}
                @if ($prevzeti['ok'] ?? false)
                    @if (! empty($prevzeti['zkouska_id']))
                        <br>Zkušební e-mail je v Poště – jakmile ho Pošta potvrdí jako odeslaný, stará schránka (i heslo) se z aplikace smaže.
                    @else
                        <br>Pošli <strong>zkušební e-mail</strong> – až ho Pošta potvrdí jako odeslaný, stará schránka (i heslo) se z aplikace sama smaže.
                    @endif
                @endif
            </div>
        @elseif ($prevzeti && ! empty($prevzeti['env_zbyva']))
            <div class="posta-stav" style="margin-bottom: 1rem">
                Stará schránka {{ $prevzeti['adresa'] }} je převzatá do Pošty. Zbývá smazat SMTP z <code>.env</code> na serveru
                (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD…) – přes portál, <code>MAIL_MAILER=log</code> stačí jako záloha.
            </div>
        @endif

        @if ($stav['ok'] === false)
            <div class="posta-chyba">{{ $stav['zprava'] }}</div>
        @endif

        <x-filament::section heading="Propojení">
            <dl class="posta-udaje">
                <dt>Pošta</dt><dd>{{ $propojeni['url'] }}{{ $propojeni['rucne'] ? ' (klíč zadaný ručně)' : '' }}</dd>
                @if ($propojeni['aplikace'])<dt>Aplikace v Poště</dt><dd>{{ $propojeni['aplikace'] }}</dd>@endif
                <dt>Smí posílat z</dt>
                <dd>
                    @forelse ($propojeni['adresy'] as $adresa)
                        <div>{{ $adresa['jmeno'] ? $adresa['jmeno'].' <'.$adresa['adresa'].'>' : $adresa['adresa'] }}</div>
                    @empty
                        <span class="posta-slabe">žádné adresy – přiděl je v Poště a dej Ověřit</span>
                    @endforelse
                </dd>
                @if ($propojeni['kdy'])<dt>Propojeno</dt><dd>{{ \App\Support\CasAplikace::zVenku($propojeni['kdy'])?->format('j. n. Y H:i') }}</dd>@endif
                @if ($propojeni['token_plati_do'])<dt>Klíč platí do</dt><dd>{{ \App\Support\CasAplikace::zVenku($propojeni['token_plati_do'])?->format('j. n. Y') }} <span class="posta-slabe">(obnovuje se sám)</span></dd>@endif
                <dt>Odchozí fronta</dt><dd>{{ $fronta ? $fronta.' zpráv čeká na Poštu – předají se samy' : 'prázdná' }}</dd>
            </dl>
            <p class="posta-slabe" style="margin-top: .75rem;">Schránky, DNS domény (SPF, DKIM, DMARC) a opakování při chybě řeší Pošta. Co odešlo a co ne: Provoz → Logy → E-maily.</p>
        </x-filament::section>
    @endif

    <form wire:submit="uloz">
        {{ $this->form }}

        @if ($propojeni['propojeno'] || static::webovy())
            <div style="margin-top: 1.5rem">
                <x-filament::button type="submit">Uložit</x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
