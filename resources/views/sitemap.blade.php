<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>{{ $web }}/</loc>
    <lastmod>{{ $datum }}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>1.0</priority>
    <xhtml:link rel="alternate" hreflang="cs" href="{{ $web }}/"/>
    <xhtml:link rel="alternate" hreflang="en" href="{{ $web }}/?lang=en"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="{{ $web }}/"/>
  </url>
  <url>
    <loc>{{ $web }}/ochrana-osobnich-udaju</loc>
    <changefreq>yearly</changefreq>
    <priority>0.3</priority>
  </url>
</urlset>
