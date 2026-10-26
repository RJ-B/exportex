<?php

namespace App\Fakturace\Http;

use App\Fakturace\FakturaceUdalost;
use App\Fakturace\PodpisWebhooku;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Události z fakturace (`POST /webhooky/fakturace`, doplněk Fakturace).
 *
 * Podpis V2 s časem (X-Fakturace-Signature-V2) se ověřuje VŽDY a dřív, než
 * se cokoli přečte. Každé doručení se zpracuje jednou (id z podepsaného
 * těla, jinak hlavička X-Fakturace-Delivery). Ověřená událost jde dál jako
 * FakturaceUdalost – co s ní, řeší projekt posluchačem. Odpověď 200 i na
 * události, které nikoho nezajímají (fakturace by je jinak opakovala).
 */
class FakturaceWebhook extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tajemstvi = (string) config('fakturace.webhook_tajemstvi');

        if ($tajemstvi === '') {
            Log::warning('Webhook fakturace přišel, ale FAKTURACE_WEBHOOK_SECRET není nastavené.');

            return response('', 503);
        }

        if (! PodpisWebhooku::sedi($request->getContent(), (string) $request->header('X-Fakturace-Signature-V2'), $tajemstvi)) {
            return response('', 401);
        }

        $doruceni = (string) ($request->input('id') ?: $request->header('X-Fakturace-Delivery'));

        // Jednou za doručení (týden v cache – fakturace opakuje nejvýš dny).
        if ($doruceni !== '' && ! Cache::add('fakturace.doruceni.'.sha1($doruceni), true, now()->addWeek())) {
            return response('', 200);
        }

        FakturaceUdalost::dispatch((string) $request->input('event'), (array) $request->input('data', []), $doruceni ?: null);

        return response('', 200);
    }
}
