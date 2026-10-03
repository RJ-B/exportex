<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ochrana veřejných formulářů proti botům – pro jakýkoli formulář:
 * do formuláře @include('formulare._ochrana'), v kontroleru
 * OchranaFormulare::jeBot($request).
 *
 *  - skryté pole (honeypot): člověk ho nevidí, bot vyplní všechno,
 *  - časová past: podepsaný čas zobrazení formuláře; odesláno za méně než
 *    MIN_SEKUND (bot) nebo po MAX_SEKUND (starý formulář, opakované odeslání)
 *    = bot. Podpis klíčem aplikace – čas nejde podvrhnout.
 *
 * Omezení počtu odeslání z jedné IP je zvlášť (RateLimiter 'formular').
 * Botovi se formulář tváří jako odeslaný – ať se nic nenaučí.
 */
class OchranaFormulare
{
    public const HONEYPOT = 'web_adresa';

    public const CAS = '_zobrazeno';

    public const MIN_SEKUND = 3;

    public const MAX_SEKUND = 7200;

    public static function podepsanyCas(): string
    {
        return Crypt::encryptString((string) time());
    }

    public static function jeBot(Request $request): bool
    {
        $duvod = self::duvod($request);

        if ($duvod !== null) {
            Log::info('Formulář: odesláno botem ('.$duvod.')', ['ip' => $request->ip(), 'cesta' => $request->path()]);
        }

        return $duvod !== null;
    }

    private static function duvod(Request $request): ?string
    {
        if (filled($request->input(self::HONEYPOT))) {
            return 'vyplněné skryté pole';
        }

        try {
            $zobrazeno = (int) Crypt::decryptString((string) $request->input(self::CAS));
        } catch (Throwable) {
            return 'chybí nebo je podvržený čas zobrazení';
        }

        $stari = time() - $zobrazeno;

        return match (true) {
            $stari < self::MIN_SEKUND => 'odesláno za '.$stari.' s',
            $stari > self::MAX_SEKUND => 'formulář starší než 2 hodiny',
            default => null,
        };
    }
}
