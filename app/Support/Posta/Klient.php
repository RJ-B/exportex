<?php

namespace App\Support\Posta;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Volání API Pošty (/api/v1). Chyby jsou dvou druhů:
 *
 *  - PostaNedostupna: spojení, 5xx, 429, 401/403 (token, IP) – zpráva je
 *    v pořádku, jen ji teď nejde předat; ovladač ji uloží do odchozí fronty,
 *  - PostaOdmitla: 4xx (neplatná adresa, nepřidělený odesílatel…) – zpráva
 *    je špatně a opakování nepomůže; chyba jde do logu e-mailů.
 */
class Klient
{
    /** Předá zprávu Poště. @return array<string, mixed> stav zprávy (id, stav…) */
    public function posli(array $zprava, string $idempotencyKey): array
    {
        return $this->vysledek(fn () => $this->api()->withHeaders(['Idempotency-Key' => $idempotencyKey])->post('/zpravy', $zprava));
    }

    /** @return array<string, mixed> */
    public function stav(string $id): array
    {
        return $this->vysledek(fn () => $this->api()->get('/zpravy/'.rawurlencode($id)));
    }

    /** Nedoručenou zprávu zařadí v Poště znovu (Poslat znovu v logu). @return array<string, mixed> */
    public function znovu(string $id): array
    {
        return $this->vysledek(fn () => $this->api()->post('/zpravy/'.rawurlencode($id).'/znovu'));
    }

    /** @return array<string, mixed> aplikace: název, přidělené adresy, platnost tokenu */
    public function ja(): array
    {
        return (array) ($this->vysledek(fn () => $this->api()->get('/ja'))['aplikace'] ?? []);
    }

    /** @return array{token: string, token_plati_do: ?string} */
    public function obnovToken(): array
    {
        return $this->vysledek(fn () => $this->api()->post('/token/obnovit'));
    }

    /**
     * Převzetí staré schránky aplikace (jednou, do 10 minut po propojení).
     * Heslo jde jen tímhle voláním ze serveru aplikace do Pošty.
     *
     * @return array{vysledek: string, schranka: array, aplikace: array}
     */
    public function prevezmi(array $schranka): array
    {
        return $this->vysledek(fn () => $this->api()->post('/prevzeti-schranky', $schranka));
    }

    public function odpoj(): void
    {
        $this->vysledek(fn () => $this->api()->delete('/propojeni'));
    }

    private function api(): PendingRequest
    {
        $token = Propojeni::token() ?? throw new PostaNedostupna('Aplikace není propojená s Poštou.');

        return Http::baseUrl(Propojeni::url().'/api/v1')->withToken($token)->acceptJson()->asJson()->timeout((int) config('posta.timeout', 10));
    }

    /** @param  callable(): Response  $volani */
    private function vysledek(callable $volani): array
    {
        try {
            $odpoved = $volani();
        } catch (ConnectionException $e) {
            throw new PostaNedostupna('Pošta neodpovídá: '.mb_substr($e->getMessage(), 0, 200), 0, $e);
        }

        if ($odpoved->successful()) {
            return (array) $odpoved->json();
        }

        $zprava = (string) ($odpoved->json('message') ?: 'HTTP '.$odpoved->status());
        $chyby = collect((array) $odpoved->json('errors'))->flatten()->implode(' ');

        if ($odpoved->serverError() || in_array($odpoved->status(), [401, 403, 408, 425, 429], true)) {
            throw new PostaNedostupna('Pošta zprávu teď nepřijala ('.$odpoved->status().': '.mb_substr($zprava, 0, 200).').');
        }

        throw new PostaOdmitla('Pošta zprávu odmítla: '.mb_substr($chyby ?: $zprava, 0, 1000), $odpoved->status());
    }
}
