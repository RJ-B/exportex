<?php

namespace App\Fakturace;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Klient fakturace.simren.cz (doplněk Fakturace, docs/fakturace.md).
 *
 * Klíče zapisuje do .env portál (FAKTURACE_URL, FAKTURACE_TOKEN,
 * FAKTURACE_WEBHOOK_SECRET, FAKTURACE_REZIM) – test dostane testovací
 * napojení (doklady TEST-…, mimo účetnictví), produkce ostré. Bez klíčů
 * web běží dál, jen „čeká na napojení“.
 *
 *  - vystav() – doklad (POST /invoices; external_id + idempotency_key drží,
 *    že opakování nevyrobí druhý),
 *  - udajeFirmy() / ulozUdajeFirmy() – bankovní účet a údaje firmy na
 *    dokladech (oprávnění firma:udaje; IBAN a SWIFT dopočítá fakturace,
 *    testovací napojení jen čte),
 *  - stav() – napojeno / testovací režim / čeká (stránka Nastavení → Fakturace).
 *
 * Dokumentace API: ve fakturaci po přihlášení, Automatizace → Napojení → Dokumentace API.
 */
class Fakturace
{
    public const STAV_NAPOJENO = 'napojeno';

    public const STAV_TEST = 'test';

    public const STAV_CEKA = 'ceka';

    public function napojeno(): bool
    {
        return filled(config('fakturace.token')) && filled(config('fakturace.url'));
    }

    public function testovaci(): bool
    {
        return config('fakturace.rezim') === 'test';
    }

    /** @return array{stav: string, nazev: string, popis: string} */
    public function stav(): array
    {
        return match (true) {
            ! $this->napojeno() => ['stav' => self::STAV_CEKA, 'nazev' => __('obecne.fakturace.ceka_na_napojeni'), 'popis' => __('obecne.fakturace.klice_zapise_portal_sam_jakmile_ma')],
            $this->testovaci() => ['stav' => self::STAV_TEST, 'nazev' => __('obecne.fakturace.testovaci_rezim'), 'popis' => __('obecne.fakturace.doklady_maji_radu_test_nejdou_do')],
            default => ['stav' => self::STAV_NAPOJENO, 'nazev' => __('obecne.fakturace.napojeno'), 'popis' => __('obecne.fakturace.ostre_doklady')],
        };
    }

    /** Adresa API: FAKTURACE_URL bez i s /api/v1 (starší .env) vede na /api/v1. */
    public static function api(): string
    {
        $url = rtrim((string) config('fakturace.url'), '/');

        return str_ends_with($url, '/api/v1') ? $url : $url.'/api/v1';
    }

    /**
     * Vystaví doklad (tělo podle API fakturace: customer, items, external_id,
     * idempotency_key, issue, send…).
     *
     * @param  array<string, mixed>  $doklad
     * @return array<string, mixed>
     *
     * @throws FakturaceChyba
     */
    public function vystav(array $doklad): array
    {
        $odpoved = $this->odpoved($this->pozadavek(20)->post('/invoices', $doklad));

        return (array) ($odpoved['document'] ?? $odpoved);
    }

    /**
     * Údaje firmy na dokladech (GET /firma). Pět minut v cache, chyba se
     * neukládá. `firma` null = nejde přečíst (důvod v `chyba`).
     *
     * @return array{firma: ?array<string, mixed>, lze_menit: bool, chyba: ?string}
     */
    public function udajeFirmy(bool $znovu = false): array
    {
        if (! $this->napojeno()) {
            return ['firma' => null, 'lze_menit' => false, 'chyba' => __('obecne.fakturace.web_zatim_neni_na_fakturaci_napojeny')];
        }

        if ($znovu) {
            Cache::forget(self::KLIC_CACHE);
        }

        if (is_array($ulozene = Cache::get(self::KLIC_CACHE))) {
            return $ulozene;
        }

        try {
            $odpoved = $this->pozadavek(8)->get('/firma');
        } catch (ConnectionException) {
            return ['firma' => null, 'lze_menit' => false, 'chyba' => __('obecne.fakturace.fakturace_ted_neodpovida_zkuste_to_za')];
        }

        if (! $odpoved->successful()) {
            return ['firma' => null, 'lze_menit' => false, 'chyba' => match ($odpoved->status()) {
                404 => __('obecne.fakturace.fakturace_zatim_udaje_firmy_z_webu'),
                403 => __('obecne.fakturace.napojeni_nema_opravneni_k_udajum_firmy'),
                default => self::zprava($odpoved),
            }];
        }

        $vysledek = ['firma' => (array) $odpoved->json('firma'), 'lze_menit' => (bool) $odpoved->json('lze_menit'), 'chyba' => null];
        Cache::put(self::KLIC_CACHE, $vysledek, 300);

        return $vysledek;
    }

    /**
     * Uloží bankovní účet (případně e-mail a text na dokladu) ve fakturaci
     * (PATCH /firma). Neplatné číslo účtu → ValidationException s klíčem pole.
     *
     * @param  array{cislo_uctu?: ?string, iban?: ?string, swift?: ?string, email?: ?string, text_na_dokladu?: ?string}  $udaje
     * @return array<string, mixed> firma po uložení
     *
     * @throws FakturaceChyba|ValidationException
     */
    public function ulozUdajeFirmy(array $udaje): array
    {
        try {
            $odpoved = $this->pozadavek(15)->patch('/firma', $udaje);
        } catch (ConnectionException) {
            throw new FakturaceChyba(__('obecne.fakturace.fakturace_ted_neodpovida_zkuste_to_za'));
        }

        if ($odpoved->status() === 422) {
            throw ValidationException::withMessages(array_map(fn ($c) => (array) $c, (array) $odpoved->json('errors')) ?: ['cislo_uctu' => [self::zprava($odpoved)]]);
        }

        $firma = (array) $this->odpoved($odpoved)['firma'];
        $this->udajeFirmy(znovu: true);

        return $firma;
    }

    private const KLIC_CACHE = 'fakturace.udaje_firmy';

    private function pozadavek(int $timeout): PendingRequest
    {
        if (! $this->napojeno()) {
            throw new FakturaceChyba(__('obecne.fakturace.web_zatim_neni_na_fakturaci_napojeny_2'));
        }

        return Http::baseUrl(self::api())->withToken((string) config('fakturace.token'))->acceptJson()->timeout($timeout);
    }

    /** @return array<string, mixed> */
    private function odpoved(Response $odpoved): array
    {
        if (! $odpoved->successful()) {
            throw new FakturaceChyba(self::zprava($odpoved));
        }

        return (array) $odpoved->json();
    }

    private static function zprava(Response $odpoved): string
    {
        $chyby = collect((array) $odpoved->json('errors'))->flatten()->take(3)->implode(' ');

        return Str::limit(trim(($odpoved->json('message') ?? __('obecne.fakturace.fakturace_vratila_chybu', ['status' => $odpoved->status()])).' '.$chyby), 400);
    }
}
