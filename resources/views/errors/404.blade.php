{{-- Stránka nenalezena (dřív 404.html). Samostatná, bez hlavičky a patičky – jako dřív.
     Běží i bez session (neexistující adresa nemá middleware webu). --}}
@php
    $nazev = rescue(fn () => \App\Support\ZakladniUdaje::get('nazev'), config('app.name'), false);
    $v = fn (string $cesta) => '/'.$cesta.'?v='.(@filemtime(public_path($cesta)) ?: '1');
@endphp
<!DOCTYPE html>
<html lang="cs" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stránka nenalezena — {{ $nazev }}</title>
<meta name="robots" content="noindex, follow">
<meta name="theme-color" content="{{ config('web.barva') }}">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="{{ $v('assets/css/fonts.css') }}">
<link rel="stylesheet" href="{{ $v('assets/css/style.css') }}">
<script src="{{ $v('assets/js/theme-init.js') }}"></script>
</head>
<body>
<main id="obsah" class="section section--base" style="min-height:100svh;display:flex;align-items:center">
  <div class="wrap" style="text-align:center;max-width:52ch">
    <div class="eyebrow" style="justify-content:center"><span class="num">404</span>
      <span class="lbl">Stránka nenalezena</span></div>
    <h1 class="h2" style="margin-bottom:16px">Tady nic není.</h1>
    <p class="lead" style="margin:0 auto 28px">Odkaz je nejspíš zastaralý nebo překlepnutý. Zkuste to od začátku.</p>
    <a href="/" class="btn btn--primary btn--lg">Zpět na úvod</a>
  </div>
</main>
</body>
</html>
