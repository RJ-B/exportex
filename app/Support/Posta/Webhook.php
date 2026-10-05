<?php

namespace App\Support\Posta;

use App\Models\MailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * POST /posta/webhook – výsledek zprávy z Pošty (odeslána / nedoručena).
 *
 * Ověří podpis V2 jako u Fakturace: X-Posta-Signature-V2 = `t=…,v1=…`,
 * HMAC-SHA256 z „t.tělo“ tajemstvím z propojení (po výměně platí dočasně
 * i předchozí), stáří nejvýš 5 minut, každé X-Posta-Delivery jen jednou.
 * Pak podle id zprávy v Poště aktualizuje záznam v Logy → E-maily.
 */
class Webhook
{
    public const MAX_STARI_S = 300;

    public function __invoke(Request $request): JsonResponse
    {
        $telo = $request->getContent();

        if (! self::podpisSedi((string) $request->header('X-Posta-Signature-V2'), $telo, Propojeni::webhookTajemstvi())) {
            return response()->json(['message' => 'Neplatný podpis.'], 401);
        }

        $doruceni = (string) $request->header('X-Posta-Delivery');

        // Každé doručení jednou (Pošta může stejné poslat znovu, když neodpovíme včas).
        if ($doruceni === '' || ! Cache::add('posta-webhook:'.sha1($doruceni), 1, now()->addDay())) {
            return response()->json(['message' => 'Už zpracováno.']);
        }

        $data = json_decode($telo, true);
        $zprava = (array) ($data['zprava'] ?? []);

        // Výsledek může dorazit dřív, než si aplikace k záznamu zapsala id zprávy
        // (Pošta poslala hned) – 409 = Pošta webhook zopakuje za minutu.
        if (filled($zprava['id'] ?? null) && ! self::aktualizuj($zprava)) {
            Cache::forget('posta-webhook:'.sha1($doruceni));

            return response()->json(['message' => 'Zprávu zatím neznáme – zkus to později.'], 409);
        }

        return response()->json(['message' => 'OK']);
    }

    /** Zapíše stav zprávy z Pošty (webhook nebo dotaz na stav) k záznamu v logu. false = záznam není. */
    public static function aktualizuj(array $zprava): bool
    {
        $log = MailLog::query()->where('posta_id', $zprava['id'])->first();

        if (! $log) {
            return false;
        }

        match ($zprava['stav'] ?? null) {
            'odeslano' => $log->update([
                'status' => MailLog::STATUS_SENT,
                'sent_at' => isset($zprava['odeslano']) ? now()->parse($zprava['odeslano']) : now(),
                'attempts' => max(1, (int) ($zprava['pokusu'] ?? 1)),
                'posta_kontrola_at' => now(),
            ]),
            'nedoruceno' => $log->update([
                'status' => MailLog::STATUS_FAILED,
                'failed_at' => now(),
                'attempts' => max(1, (int) ($zprava['pokusu'] ?? 1)),
                'error' => mb_substr((string) ($zprava['chyba'] ?? 'Nedoručeno.'), 0, 2000),
                'posta_kontrola_at' => now(),
            ]),
            default => $log->update(['posta_kontrola_at' => now(), 'attempts' => max(1, (int) ($zprava['pokusu'] ?? 1))]),
        };

        // Zkušební e-mail po převzetí staré schránky prošel – starou schránku smazat.
        if (($zprava['stav'] ?? null) === 'odeslano') {
            StaraSchranka::poDoruceni((string) $zprava['id']);
        }

        return true;
    }

    /** Podpis V2: aspoň jedno v1= sedí s některým tajemstvím a t není starší než 5 minut. */
    public static function podpisSedi(string $hlavicka, string $telo, array $tajemstvi): bool
    {
        if ($tajemstvi === [] || ! preg_match('/(?:^|,)t=(\d+)/', $hlavicka, $t)) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $t[1]) > self::MAX_STARI_S) {
            return false;
        }

        preg_match_all('/(?:^|,)v1=([a-f0-9]{64})/', $hlavicka, $podpisy);

        foreach ($tajemstvi as $tajne) {
            $ocekavany = hash_hmac('sha256', $t[1].'.'.$telo, $tajne);

            foreach ($podpisy[1] as $podpis) {
                if (hash_equals($ocekavany, $podpis)) {
                    return true;
                }
            }
        }

        return false;
    }
}
