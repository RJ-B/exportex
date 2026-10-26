{{-- Rozvržení webu Exportex (dřív hlavička a patička opsané v index.html, cookies.html
     a soukromi.html). Vzhled a chování beze změny: public/assets/css/style.css
     a public/assets/js/main.js (světlý / tmavý režim, CZ / EN, menu, formulář).

     Texty jsou dvojjazyčné – prvek nese data-cs a data-en (App\Support\Preklad),
     viditelně je čeština. Firma a kontakty ze Základních údajů, menu podle Obsahu
     webu (sekce zapnuté a v pořadí), v šabloně není natvrdo žádný kontakt. --}}
@use('App\Support\Preklad')
@use('App\Support\ObsahWebu')
@php
    $u = \App\Support\ZakladniUdaje::nacti();
    $paticka = ObsahWebu::sekce('paticka');
    $kontakt = ObsahWebu::sekce('kontakt');
    $sekce = ObsahWebu::sekceNaWebu();
    $menu = array_values(array_filter($sekce, fn ($s) => $s['vMenu']));
    $formular = \App\Support\SekceWebu::zapnuta('formular');
    $naUvodu = request()->routeIs('domu');
    // Na úvodní stránce kotvy (plynulý posun), jinde odkaz na úvodní stránku s kotvou.
    $kotva = fn (string $id) => ($naUvodu ? '' : '/').'#'.$id;
    // Verze souboru v adrese – po změně se v prohlížeči neukáže stará z cache.
    $v = fn (string $cesta) => '/'.$cesta.'?v='.(@filemtime(public_path($cesta)) ?: '1');
    // SEO ze šablony (App\Support\Seo): titulek, popis, robots (noindex mimo produkci), canonical,
    // Open Graph, Twitter a JSON-LD – upravuje se v Obsah webu → SEO a měření. Provozovatel
    // ze šablony doplněný o údaje Exportex (App\Support\StrukturovanaData), na úvodu i služba.
    $titulekStranky = trim($__env->yieldContent('titulek'));
    $seo = $naUvodu
        ? \App\Support\Seo\Seo::uvod()
        : \App\Support\Seo\Seo::stranka($titulekStranky ?: null, klic: trim($__env->yieldContent('seo_klic')) ?: null)->popis(trim($__env->yieldContent('popis')) ?: null);
    $graf = \App\Support\StrukturovanaData::web()['@graph'];
    $organizace = $graf[0];
    unset($organizace['@id']);
    $seo->obrazek(\App\Support\Seo\NastaveniSeo::get('obrazek') ? null : rtrim((string) config('web.url'), '/').'/assets/img/og.webp')
        ->subjekt(array_merge($organizace, array_filter(\App\Support\Seo\StrukturovanaData::subjekt(), fn ($h) => $h !== null && $h !== [])));
    if ($naUvodu) {
        $seo->data($graf[2]);   // Service se sortimentem
    }
    $telefon = $u['telefon'];
    $cislice = Preklad::cislice($telefon);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

{{ $seo->html() }}
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="theme-color" content="{{ config('web.barva') }}">
<meta name="color-scheme" content="light dark">
<meta name="format-detection" content="telephone=no">

@yield('hlava')

{{-- Ikony — „e“ vyříznuté z firemního loga --}}
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">

{{-- Písma jsou hostovaná přímo tady, ne u Google Fonts — návštěvníkova
     IP adresa se tak neposílá třetí straně a odpadá externí spojení. --}}
<link rel="preload" as="font" type="font/woff2" href="/assets/fonts/outfit-400-latin-ext.woff2" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="/assets/fonts/outfit-700-latin-ext.woff2" crossorigin>
<link rel="stylesheet" href="{{ $v('assets/css/fonts.css') }}">

<link rel="stylesheet" href="{{ $v('assets/css/style.css') }}">
@stack('preload')

<script src="{{ $v('assets/js/theme-init.js') }}"></script>
@stack('jsonld')
</head>
<body>
{{-- Pruh oznámení (odstávka, výpadek) – docs/oznameni.md. --}}
@include('oznameni.pruh')
@includeWhen(config('sablona.doplnky.platby'), 'platby.stitek')

<a href="#obsah" class="skip" data-cs="{{ __('web.web.preskocit_na_obsah') }}" data-en="{{ __('web.web.preskocit_na_obsah', [], 'en') }}">{{ __('web.web.preskocit_na_obsah') }}</a>

@if ($naUvodu)
{{-- ===== Progress ===== --}}
<div class="progress" id="progress" role="presentation"></div>
@endif

{{-- ===== Header ===== --}}
<header class="hdr" id="hdr">
  <div class="hdr__in">
    <a href="{{ $naUvodu ? '#top' : '/' }}" class="logo" aria-label="{{ __('web.web.uvod', ['nazev' => $u['nazev']]) }}">
      <img class="logo__mark" src="/assets/img/logo.webp" width="532" height="120" alt="{{ $u['nazev'] }}">
    </a>

    <nav class="nav" aria-label="{{ __('web.web.hlavni_navigace') }}">
      @foreach ($menu as $polozka)
      <a class="nav__link" href="{{ $kotva($polozka['kotva']) }}" {{ Preklad::attr($polozka['cs'], $polozka['en']) }}>{{ $polozka['cs'] }}</a>
      @endforeach

      <div class="lang" role="group" aria-label="Jazyk / Language">
        <button type="button" class="lang__btn is-on" data-lang="cs" aria-pressed="true" lang="cs">CZ</button>
        <span class="lang__sep">/</span>
        <button type="button" class="lang__btn" data-lang="en" aria-pressed="false" lang="en">EN</button>
      </div>

      <button type="button" class="themebtn" id="themeBtn" aria-pressed="false"
              data-cs-label="{{ __('web.web.prepnout_na_svetly_rezim') }}" data-en-label="Switch to light mode"
              aria-label="{{ __('web.web.prepnout_na_svetly_rezim') }}" title="{{ __('web.web.prepnout_na_svetly_rezim') }}">
        <svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2.4M12 19.6V22M4.2 4.2l1.7 1.7M18.1 18.1l1.7 1.7M2 12h2.4M19.6 12H22M4.2 19.8l1.7-1.7M18.1 5.9l1.7-1.7"/></svg>
      </button>

      @if ($formular)
      <a href="{{ $kotva('kontakt') }}" class="btn btn--primary btn--sm btn--nav" data-cs="{{ __('web.web.poptavka') }}" data-en="{{ __('web.web.poptavka', [], 'en') }}">{{ __('web.web.poptavka') }}</a>
      @endif

      <button type="button" class="burger" id="burger" aria-label="Menu" aria-expanded="false" aria-controls="drawer">
        <span></span><span></span><span></span>
      </button>
    </nav>
    @include('oznameni.zvonecek')
  </div>
</header>

{{-- ===== Mobilní menu ===== --}}
<div class="drawer" id="drawer" aria-hidden="true">
  <nav class="drawer__list" aria-label="{{ __('web.web.mobilni_navigace') }}">
    @foreach ($menu as $polozka)
    <a class="drawer__link" href="{{ $kotva($polozka['kotva']) }}"><span class="n">{{ $polozka['cislo'] }}</span><span {{ Preklad::attr($polozka['cs'], $polozka['en']) }}>{{ $polozka['cs'] }}</span></a>
    @endforeach
  </nav>
  <div class="drawer__foot">
    @if ($formular)
    <a href="{{ $kotva('kontakt') }}" class="btn btn--primary btn--md" data-cs="{{ __('web.web.poslat_poptavku') }}" data-en="{{ __('web.web.poslat_poptavku', [], 'en') }}">{{ __('web.web.poslat_poptavku') }}</a>
    @endif
    @if ($telefon)
    <a href="tel:{{ Preklad::tel($telefon) }}" class="btn btn--glass btn--md">{{ $telefon }}</a>
    @endif
  </div>
</div>

@yield('obsah')

{{-- ===== Footer ===== --}}
<footer class="ftr">
  <div class="wrap">
    <div class="ftr__cols">

      <div class="ftr__brand">
        <img class="ftr__mark" src="/assets/img/logo.webp" width="532" height="120" alt="{{ $u['nazev'] }}">
        <p {{ Preklad::attr($paticka['text'], $paticka['text_en']) }}>{{ $paticka['text'] }}</p>
      </div>

      <nav class="ftr__col" aria-labelledby="ftr-nav">
        <h2 class="ftr__h" id="ftr-nav" data-cs="{{ __('web.web.rychle_odkazy') }}" data-en="{{ __('web.web.rychle_odkazy', [], 'en') }}">{{ __('web.web.rychle_odkazy') }}</h2>
        <ul>
          @foreach ($menu as $polozka)
          <li><a href="{{ $kotva($polozka['kotva']) }}" {{ Preklad::attr($polozka['cs'], $polozka['en']) }}>{{ $polozka['cs'] }}</a></li>
          @endforeach
        </ul>
      </nav>

      <div class="ftr__col">
        <h2 class="ftr__h" data-cs="Kontakt" data-en="Contact">{{ __('web.web.kontakt') }}</h2>
        <ul>
          @if ($kontakt['osoba'])<li><span class="ftr__name">{{ $kontakt['osoba'] }}</span></li>@endif
          @if ($u['email'])<li><a href="mailto:{{ $u['email'] }}">{{ $u['email'] }}</a></li>@endif
          @if ($telefon)<li><a href="tel:{{ Preklad::tel($telefon) }}">{{ $telefon }}</a></li>@endif
          @if ($telefon && $kontakt['whatsapp'])<li><a href="https://wa.me/{{ $cislice }}" target="_blank" rel="noopener noreferrer">{{ __('web.web.whatsapp') }}</a></li>@endif
          @if ($telefon && $kontakt['telegram'])<li><a href="https://t.me/+{{ $cislice }}" target="_blank" rel="noopener noreferrer">{{ __('web.web.telegram') }}</a></li>@endif
        </ul>
      </div>

      <div class="ftr__col">
        <h2 class="ftr__h" data-cs="Informace" data-en="Information">{{ __('web.web.informace') }}</h2>
        <ul>
          <li><a href="{{ route('cookies') }}" data-cs="{{ __('web.web.cookies_a_uloziste') }}" data-en="{{ __('web.web.cookies_a_uloziste', [], 'en') }}">{{ __('web.web.cookies_a_uloziste') }}</a></li>
          <li><a href="{{ route('ochrana-udaju') }}" data-cs="{{ __('web.web.ochrana_osobnich_udaju') }}" data-en="{{ __('web.web.ochrana_osobnich_udaju', [], 'en') }}">{{ __('web.web.ochrana_osobnich_udaju') }}</a></li>
          @if (\App\Support\NastaveniWebu::meri())
          <li><button type="button" data-cc="open" data-cs="{{ __('web.web.nastaveni_cookies') }}" data-en="{{ __('web.web.nastaveni_cookies', [], 'en') }}">{{ __('web.web.nastaveni_cookies') }}</button></li>
          @endif
        </ul>
      </div>

    </div>

    {{-- Zákonná identifikace podnikatele podle § 435 občanského zákoníku --}}
    <address class="ftr__legal">
      <strong>{{ $u['firma'] ?: $u['nazev'] }}</strong>
      @if ($u['adresa'])
      @php($adresa = str_replace("\n", ', ', $u['adresa']))
      <span {{ Preklad::attr($adresa, $paticka['adresa_en']) }}>{{ $adresa }}</span>
      @endif
      @if ($u['ico'])<span>{{ __('web.web.ico', ['ico' => $u['ico']]) }}</span>@endif
      @if ($u['dic'])<span>{{ __('web.web.dic', ['dic' => $u['dic']]) }}</span>@endif
      @if ($u['rejstrik'])
      <span {{ Preklad::attr($u['rejstrik'], $paticka['rejstrik_en']) }}>{{ $u['rejstrik'] }}</span>
      @endif
    </address>

    <div class="ftr__bar">
      @php($cr = '© '.now()->year.' '.($u['firma'] ?: $u['nazev']).' — ')
      <span class="ftr__cr" {{ Preklad::attr($cr.$paticka['pruh'], $cr.$paticka['pruh_en']) }}>{{ $cr.$paticka['pruh'] }}</span>
      <span class="ftr__by">
        <span data-cs="Web:" data-en="Website by:">{{ __('web.web.web') }}</span>
        <a href="{{ config('web.autor_web') }}" target="_blank" rel="noopener noreferrer">{{ config('web.autor') }}</a>
      </span>
    </div>
  </div>
</footer>

@stack('konec')

<script src="{{ $v('assets/js/main.js') }}"></script>

{{-- Cookie lišta šablony – ukáže se, jen když web měří (Obsah webu → SEO a měření).
     Bez měření žádná lišta: web ukládá jen nezbytné cookies a volby návštěvníka. --}}
@include('pravni.cookie-lista')
</body>
</html>
