{{-- Loga plateb (doplněk Platby, docs/platby.md → Loga plateb). Ukáže se jen se zapnutým doplňkem.
     Visa a Mastercard jsou povinná (Comgate); Apple Pay / Google Pay jen když je předvolba zapnutá
     a brána ji má; Comgate (s odkazem) nebo Mo.one podle brány, která se SKUTEČNĚ používá
     (na testu testovací brána Sim&Ren = Mo.one, lokálně simulace = jen karty).
     Vložení do vlastní patičky: @include('platby._loga')
     Parametry (vše nepovinné):
       pozadi    'auto' (výchozí – světlá/tmavá podle prefers-color-scheme), 'svetle' | 'tmave' (patička má pevné pozadí)
       zarovnani 'flex-start' (výchozí) | 'center' | 'flex-end'
       text      true = přidá „Platbu zajišťuje …“ a oznámení o bráně (stránky plateb)
       brana     kód brány (comgate | moone | simulace), výchozí $platba->brana, jinak brána, která platí teď
     Loga jsou oficiální a neupravují se – mění se jen velikost a varianta (public/img/platby/). --}}
@if (config('sablona.doplnky.platby'))
    @php
        $pozadi = $pozadi ?? 'auto';
        $zarovnani = $zarovnani ?? 'flex-start';
        $text = $text ?? false;
        $kod = $brana ?? (isset($platba) ? $platba->brana : \App\Platby\NastaveniPlateb::kodBrany());
        $loga = $kod ? \App\Platby\NastaveniPlateb::logaPlateb($kod) : null;

        // Logo ve variantě podle pozadí; auto = <picture> s tmavou variantou pro prefers-color-scheme: dark.
        $logo = function (string $svetle, ?string $tmave, string $alt, int $sirka, int $vyska = 24) use ($pozadi) {
            $img = fn (string $soubor) => '<img src="'.e(asset('img/platby/'.$soubor)).'" alt="'.e($alt).'" width="'.$sirka.'" height="'.$vyska.'" loading="lazy" decoding="async">';
            $tmave ??= $svetle;

            return new \Illuminate\Support\HtmlString(match (true) {
                $pozadi === 'tmave' => $img($tmave),
                $pozadi === 'svetle' || $svetle === $tmave => $img($svetle),
                default => '<picture><source media="(prefers-color-scheme: dark)" srcset="'.e(asset('img/platby/'.$tmave)).'">'.$img($svetle).'</picture>',
            });
        };
    @endphp
    @if ($loga)
        @once
            <style>
                .platby-loga { margin: 20px 0 0; }
                .platby-loga ul { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; }
                .platby-loga li { margin: 0; padding: 0; display: flex; align-items: center; }
                .platby-loga a { display: flex; }
                .platby-loga img { display: block; height: 24px; width: auto; max-width: 100%; }
                .platby-loga__penezenka--tmave { background: #fff; border-radius: 5px; padding: 3px 6px; }
                @media (prefers-color-scheme: dark) { .platby-loga--auto .platby-loga__penezenka { background: #fff; border-radius: 5px; padding: 3px 6px; } }
                .platby-loga__text { margin: 0 0 10px; font-size: 14px; }
                .platby-loga__podminka { margin: 12px 0 0; font-size: 12px; line-height: 1.5; }
            </style>
        @endonce
        <div class="platby-loga platby-loga--{{ $pozadi }}" role="group" aria-label="{{ __('platby.loga.platebni_metody_a_platebni_brana') }}" style="text-align: {{ $zarovnani === 'flex-start' ? 'left' : ($zarovnani === 'center' ? 'center' : 'right') }};">
            @if ($text && $loga['brana'])
                <p class="platby-loga__text">{{ __('platby.loga.platbu_zajistuje', ['hodnota' => \App\Platby\NastaveniPlateb::BRANY[$loga['brana']]]) }}</p>
            @endif
            <ul style="justify-content: {{ $zarovnani }};">
                <li>{{ $logo('visa.svg', 'visa-bile.svg', 'Visa', 39) }}</li>
                <li>{{ $logo('mastercard.svg', null, 'Mastercard', 34) }}</li>
                @if ($loga['apple_pay'])
                    <li class="platby-loga__penezenka platby-loga__penezenka--{{ $pozadi }}">{{ $logo('apple-pay.svg', null, 'Apple Pay', 48) }}</li>
                @endif
                @if ($loga['google_pay'])
                    <li class="platby-loga__penezenka platby-loga__penezenka--{{ $pozadi }}">{{ $logo('google-pay.svg', null, 'Google Pay', 48) }}</li>
                @endif
                @if ($loga['brana'] === 'comgate')
                    <li><a href="https://www.comgate.eu" target="_blank" rel="noopener" aria-label="{{ __('platby.loga.comgate_platebni_brana_otevre_se_v') }}">{{ $logo('comgate.svg', 'comgate-tmave-pozadi.svg', 'Comgate', 104) }}</a></li>
                @elseif ($loga['brana'] === 'moone')
                    <li>{{ $logo('moone-logo-tmave.svg', 'moone-logo-bile.svg', 'Mo.one', 37) }}</li>
                @endif
            </ul>
            @if ($text)
                @include('platby._o-brane', ['branaKod' => $kod, 'trida' => 'platby-loga__podminka'])
            @endif
        </div>
    @endif
@endif
