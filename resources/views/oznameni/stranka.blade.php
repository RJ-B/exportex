{{-- Stránka Oznámení (web, /oznameni): všechna oznámení přihlášeného, archiv a předvolby.
     Obsah pro layouts.verejna – projekt s vlastním vzhledem vloží @include('oznameni.stranka')
     a předá stejné proměnné (OznameniController::stranka). --}}
@include('oznameni._zdroje')
<style>
    .ozn-filtry { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 18px; }
    .ozn-filtry a { padding: 6px 14px; border: 1px solid var(--okraj); border-radius: 999px; color: var(--text); text-decoration: none; font-size: 14px; }
    .ozn-filtry a[aria-current="page"] { background: var(--akcent); border-color: var(--akcent); color: #fff; }
    .ozn-akce-hlava { display: flex; justify-content: flex-end; margin: -6px 0 10px; }
    .ozn-karta { padding: 16px 0; border-top: 1px solid var(--okraj); }
    .ozn-karta-meta { font-size: 13px; color: var(--slabe); }
    .ozn-karta h2 { margin: 2px 0 6px; font-size: 18px; }
    .ozn-karta--neprectene h2::before { content: ""; display: inline-block; width: 8px; height: 8px; margin: 0 8px 2px 0; border-radius: 50%; background: var(--akcent); }
    .ozn-karta-text p { margin: 0 0 8px; }
    .ozn-karta-akce { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin-top: 8px; }
    .ozn-karta-akce form { margin: 0; }
    .ozn-tlacitko-odkaz { padding: 0; border: 0; background: none; color: var(--akcent); font: inherit; font-size: 14px; cursor: pointer; }
    .ozn-tlacitko-odkaz:hover { text-decoration: underline; }
    .ozn-hlavni { display: inline-block; padding: 8px 16px; border-radius: 10px; background: var(--akcent); color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; }
    .ozn-stranky { display: flex; justify-content: space-between; margin-top: 12px; font-size: 14px; }
    .ozn-predvolby { margin-top: 36px; padding-top: 8px; border-top: 1px solid var(--okraj); }
    .ozn-druh { padding: 14px 0; border-bottom: 1px solid var(--okraj); }
    .ozn-druh-popis { margin: 2px 0 8px; font-size: 14px; color: var(--slabe); }
    .ozn-volby { display: flex; flex-wrap: wrap; gap: 8px 24px; }
    .ozn-volba { display: inline-flex; align-items: center; gap: 8px; margin: 0; font-size: 15px; font-weight: 400; cursor: pointer; }
    .ozn-volba input:disabled { opacity: .6; cursor: default; }
    .ozn-volba small { color: var(--slabe); }
    .ozn-souhlas { margin: 6px 0 0; font-size: 12px; color: var(--slabe); }
    .ozn-toast { position: fixed; top: 16px; right: 16px; z-index: 100; display: flex; gap: 10px; align-items: center; max-width: calc(100vw - 32px); padding: 12px 14px; border: 1px solid var(--okraj); border-left: 4px solid #16a34a; border-radius: 10px; background: var(--karta); box-shadow: 0 10px 24px rgb(0 0 0 / .12); font-size: 14px; }
</style>

@if (session('predvolby_ulozeny'))
    <div class="ozn-toast" role="status" data-ozn-toast>Předvolby jsou uložené.</div>
    <script>setTimeout(function () { var t = document.querySelector('[data-ozn-toast]'); if (t) { t.remove(); } }, 5000);</script>
@endif

<nav class="ozn-filtry" aria-label="Zobrazit">
    @foreach (['' => 'Vše', 'neprectene' => 'Nepřečtené', 'archiv' => 'Archiv'] as $klic => $popis)
        <a href="{{ route('oznameni.stranka', array_filter(['zobrazit' => $klic])) }}" @if ($zobrazit === $klic) aria-current="page" @endif>{{ $popis }}</a>
    @endforeach
</nav>

@if ($neprectenych > 0)
    <div class="ozn-akce-hlava">
        <form method="post" action="{{ route('oznameni.precteno-vse') }}">@csrf
            <button type="submit" class="ozn-tlacitko-odkaz">Označit vše jako přečtené</button>
        </form>
    </div>
@endif

@forelse ($polozky as $p)
    @php($o = $p->oznameni)
    <article class="ozn-karta {{ $p->precteno_at ? '' : 'ozn-karta--neprectene' }}">
        <div class="ozn-karta-meta">{{ $o->druh->nazev() }} · {{ $o->odeslano_at?->format('j. n. Y H:i') }}{{ $p->archivovano_at ? ' · v archivu' : '' }}</div>
        <h2>{{ $o->titulek }}</h2>
        @if ($o->udalost_od)
            <p class="ozn-karta-meta"><strong>Kdy:</strong> {{ $o->udalost_od->format('j. n. Y H:i') }}@if ($o->udalost_do) – {{ $o->udalost_do->format('j. n. Y H:i') }}@endif</p>
        @endif
        <div class="ozn-karta-text">{{ $o->textHtml() }}</div>
        <div class="ozn-karta-akce">
            @if ($odkaz = $p->odkazProkliku())
                <a class="ozn-hlavni" href="{{ $odkaz }}">{{ $o->odkaz_text ?: 'Zobrazit' }}</a>
            @endif
            @unless ($p->precteno_at)
                <form method="post" action="{{ route('oznameni.precteno', $p) }}">@csrf
                    <button type="submit" class="ozn-tlacitko-odkaz">Označit jako přečtené</button>
                </form>
            @endunless
            <form method="post" action="{{ route('oznameni.archiv', $p) }}">@csrf
                <button type="submit" class="ozn-tlacitko-odkaz">{{ $p->archivovano_at ? 'Vrátit z archivu' : 'Do archivu' }}</button>
            </form>
        </div>
    </article>
@empty
    <p class="poznamka">{{ match ($zobrazit) { 'archiv' => 'Archiv je prázdný.', 'neprectene' => 'Všechno máte přečtené.', default => 'Zatím vám nepřišlo žádné oznámení.' } }}</p>
@endforelse

@if ($polozky->hasPages())
    <div class="ozn-stranky">
        <span>@if ($polozky->previousPageUrl())<a href="{{ $polozky->previousPageUrl() }}">← Novější</a>@endif</span>
        <span>@if ($polozky->nextPageUrl())<a href="{{ $polozky->nextPageUrl() }}">Starší →</a>@endif</span>
    </div>
@endif

<section class="ozn-predvolby" id="predvolby">
    <h2>Předvolby</h2>
    <p class="poznamka" style="margin-top: 0;">Co a kudy vám chodí. Provozní a servisní oznámení (vaše objednávky, odstávky) uvidíte v oznámeních vždy; e-mailem si je můžete vypnout. Novinky a nabídky e-mailem jen s vaším souhlasem – odvolat ho jde tady nebo odkazem v každém e-mailu.</p>
    <form method="post" action="{{ route('oznameni.predvolby') }}">
        @csrf
        @foreach ($druhy as $druh)
            <div class="ozn-druh">
                <strong>{{ $druh->nazev() }}</strong>
                <p class="ozn-druh-popis">{{ $druh->popis() }}</p>
                <div class="ozn-volby">
                    @foreach ($kanaly as $kanal)
                        @php($v = $matice[$druh->value][$kanal->value])
                        <label class="ozn-volba">
                            <input type="checkbox" name="predvolby[{{ $druh->value }}][{{ $kanal->value }}]" value="1" @checked($v['zapnuto']) @disabled($v['zamceno'])>
                            {{ $kanal === \App\Enums\KanalOznameni::Centrum ? 'V oznámeních' : $kanal->nazev() }}
                            @if ($v['zamceno'])<small>(vždy)</small>@endif
                        </label>
                    @endforeach
                </div>
                @if ($druh->vyzadujeSouhlas())
                    <p class="ozn-souhlas">Zaškrtnutím e-mailu: „{{ \App\Support\Oznameni\Predvolby::textSouhlasu($druh, \App\Enums\KanalOznameni::Email) }}“</p>
                @endif
            </div>
        @endforeach
        <button type="submit" class="tlacitko">Uložit předvolby</button>
    </form>
</section>
