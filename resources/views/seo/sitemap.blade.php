{{-- XML deklarace poskládaná z kousků: se zapnutým short_open_tag (PHP na SimRen-serveru) by Blade
     „<?xml … ?>“ ve zdroji šablony vzal jako PHP blok a sitemapa by spadla na syntax error. --}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($polozky as $polozka)
    <url>
        <loc>{{ $polozka['loc'] }}</loc>
@if ($polozka['lastmod'])
        <lastmod>{{ $polozka['lastmod']->toIso8601String() }}</lastmod>
@endif
@foreach ($polozka['obrazky'] as $obrazek)
        <image:image><image:loc>{{ $obrazek }}</image:loc></image:image>
@endforeach
    </url>
@endforeach
</urlset>
