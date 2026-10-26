<?php

namespace App\Http\Controllers;

use App\Support\MapaWebu;
use Illuminate\Http\Response;

/** /robots.txt a /sitemap.xml z aplikace (App\Support\MapaWebu). */
class MapaWebuController extends Controller
{
    public function robots(): Response
    {
        return response(MapaWebu::robots(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function sitemap(): Response
    {
        abort_unless(MapaWebu::indexovat(), 404);

        return response()->view('seo.sitemap', ['polozky' => MapaWebu::polozky()], 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
