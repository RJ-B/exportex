{{-- Administrace → Pošta. Soběstačná (vlastní styly), aby šla přenést do každého projektu.
     Tailwind třídy ve vlastních šablonách Filament nekompiluje, proto třídy .posta-*
     s proměnnými Filamentu (jen existující: --gray-500, --success-600…). --}}
<x-filament-panels::page>
    <style>
        .posta-stav { border-radius: .5rem; background: var(--gray-50); padding: .75rem 1rem; font-size: .875rem; }
        .dark .posta-stav { background: rgb(255 255 255 / .05); }
        .posta-tab { width: 100%; font-size: .8rem; border-collapse: collapse; }
        .posta-tab th { text-align: left; color: var(--gray-500); font-weight: 500; padding: .25rem .75rem .25rem 0; }
        .posta-tab td { padding: .4rem .75rem .4rem 0; border-top: 1px solid var(--gray-200); vertical-align: top; }
        .dark .posta-tab td { border-color: rgb(255 255 255 / .1); }
        .posta-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; overflow-wrap: anywhere; }
        .posta-slabe { color: var(--gray-500); font-size: .8rem; }
        .posta-kroky { margin: 1rem 0 0; padding-left: 1.25rem; line-height: 1.7; list-style: decimal; }
        .posta-kroky a { text-decoration: underline; }
    </style>

    <div class="posta-stav">{{ $stav }}</div>

    <form wire:submit="uloz">
        {{ $this->form }}

        <div style="margin-top: 1.5rem">
            <x-filament::button type="submit">Uložit</x-filament::button>
        </div>
    </form>

    {{-- Návod pro klienta: co zapsat u registrátora domény, aby pošta nepadala do spamu. --}}
    <x-filament::section
        heading="DNS domény{{ $domena ? ' '.$domena : '' }}"
        description="Aby pošta z domény chodila a nekončila ve spamu, musí mít doména u registrátora (Forpsi, Wedos, Active24…) tyhle záznamy. Zapisuje je majitel domény v administraci registrátora.">

        @if (! $domena)
            <p class="posta-slabe">Nejdřív vyplň schránku – návod se připraví pro její doménu.</p>
        @elseif (! $poskytovatel)
            <p class="posta-slabe">Schránka je u jiného poskytovatele ({{ $server }}). Záznamy MX, SPF, DKIM a DMARC pro něj zná jen on –
                nastavují se podle jeho návodu a tady se nekontrolují. SPF musí povolit jeho servery a nesmí obsahovat „a“
                (A domény míří na náš server, a ten poštu neposílá).</p>
        @elseif ($bezplatna)
            <p class="posta-slabe">Bezplatná schránka Seznamu – doména není vaše, DNS se nenastavuje. Pro firemní poštu
                (a lepší doručitelnost) je lepší schránka na vlastní doméně v <a href="https://emailprofi.seznam.cz" target="_blank" rel="noopener" style="text-decoration: underline;">Email Profi</a>.</p>
        @else
            <table class="posta-tab">
                <thead>
                    <tr><th style="width: 4.5rem;">Typ</th><th style="width: 9rem;">Název</th><th>Hodnota</th><th style="width: 30%;">Pozn.</th></tr>
                </thead>
                <tbody>
                    @foreach ($navod as $radek)
                        <tr>
                            <td>{{ $radek['typ'] }}</td>
                            <td class="posta-mono">{{ $radek['nazev'] }}</td>
                            <td class="posta-mono">{{ $radek['hodnota'] }}</td>
                            <td class="posta-slabe">{{ $radek['poznamka'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <ol class="posta-kroky posta-slabe">
                @if ($poskytovatel === 'seznam')
                    <li>Doménu zaregistruj v <a href="https://emailprofi.seznam.cz" target="_blank" rel="noopener">Email Profi</a> a založ schránku (heslo bez diakritiky).</li>
                    <li>U registrátora zapiš záznamy z tabulky. <strong>Název</strong> „@“ znamená samotnou doménu (u některých registrátorů se nechává prázdný).</li>
                    <li>Počkej aspoň hodinu – Seznam si nové záznamy načte až po čase – a pak dej <em>Zkontrolovat DNS</em>.</li>
                @else
                    <li>Pošta i DNS jsou u {{ $nazevPoskytovatele }} – MX a SPF tam obvykle už jsou. DKIM zapni v administraci {{ $nazevPoskytovatele }} u e-mailu domény.</li>
                    <li>Chybějící záznamy doplň u {{ $nazevPoskytovatele }} podle tabulky. <strong>Název</strong> „@“ znamená samotnou doménu.</li>
                    <li>Pak dej <em>Zkontrolovat DNS</em> (MX, SPF a DMARC; DKIM má selektor od {{ $nazevPoskytovatele }} a tady se nekontroluje).</li>
                @endif
                <li>Nakonec <em>Poslat zkušební e-mail</em> a ověř, že nepřišel do spamu.</li>
            </ol>

            <div style="margin-top: 1rem; display: flex; align-items: center; gap: .75rem;">
                <x-filament::button color="gray" wire:click="zkontrolujDns" icon="heroicon-o-magnifying-glass">Zkontrolovat DNS</x-filament::button>
                <span wire:loading wire:target="zkontrolujDns" class="posta-slabe">Ptám se DNS…</span>
            </div>

            @if ($dns)
                <table class="posta-tab" style="margin-top: 1rem;">
                    <tbody>
                        @foreach ($dns as $v)
                            <tr>
                                <td style="width: 6rem;"><strong>{{ $v['zaznam'] }}</strong></td>
                                <td style="width: 6.5rem; color: var(--{{ ['ok' => 'success', 'varovani' => 'warning', 'chyba' => 'danger'][$v['stav']] }}-600);">
                                    {{ ['ok' => '✓ v pořádku', 'varovani' => '! pozor', 'chyba' => '✕ chybí'][$v['stav']] }}
                                </td>
                                <td style="overflow-wrap: anywhere;">{{ $v['zprava'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
