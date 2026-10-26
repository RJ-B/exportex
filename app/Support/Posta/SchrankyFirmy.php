<?php

namespace App\Support\Posta;

/**
 * E-mail firmy (Nastavení → E-mail): schránky firmy v Poště a odkud se k jakému
 * účelu posílá (formuláře, účty, doklady…) – jedna schránka pro všechno, nebo
 * zvlášť. Firmu webu přiřazuje správce Sim&Ren v portálu; web spravuje jen
 * schránky SVÉ firmy (Pošta to hlídá). Testovací web jen čte – posílá vždy
 * testovací adresou.
 *
 * Heslo schránky jde jen ze serveru webu do Pošty při přidání / změně; web ho
 * NEUKLÁDÁ a do formuláře se nevrací.
 */
class SchrankyFirmy
{
    public function __construct(private Klient $klient) {}

    /** Firma, schránky, účely a výsledná adresa ke každému účelu. @return array<string, mixed> */
    public function popis(): array
    {
        return $this->klient->spravuj('GET', '/vlastnik');
    }

    /**
     * Účely celé firmy: účel → adresa ('' / null = zrušit, platí „pro všechno“).
     *
     * @param  array<string, ?string>  $ucely
     * @return array<string, mixed>
     */
    public function nastav(array $ucely): array
    {
        return $this->klient->spravuj('PUT', '/vlastnik/ucely', ['rozsah' => 'vlastnik', 'ucely' => (object) array_map(fn ($a) => filled($a) ? $a : null, $ucely)]);
    }

    /** Nová schránka firmy heslem (Pošta přihlášení hned ověří). @return array<string, mixed> */
    public function pridej(array $data): array
    {
        return $this->klient->spravuj('POST', '/vlastnik/schranky', array_filter($data, fn ($v) => $v !== null && $v !== ''));
    }

    /** Nové heslo / jméno schránky firmy. @return array<string, mixed> */
    public function uprav(string $adresa, array $data): array
    {
        return $this->klient->spravuj('PATCH', '/vlastnik/schranky/'.rawurlencode($adresa), array_filter($data, fn ($v) => $v !== null && $v !== ''));
    }

    /** @return array<string, mixed> */
    public function odeber(string $adresa): array
    {
        return $this->klient->spravuj('DELETE', '/vlastnik/schranky/'.rawurlencode($adresa));
    }

    /** @return array{ok: bool, zprava: string} */
    public function over(string $adresa): array
    {
        return (array) ($this->klient->spravuj('POST', '/vlastnik/schranky/'.rawurlencode($adresa).'/overit')['overeni'] ?? ['ok' => false, 'zprava' => '?']);
    }

    /** @return array<string, mixed> stav zkušební zprávy */
    public function zkouska(string $adresa, string $komu): array
    {
        return $this->klient->spravuj('POST', '/vlastnik/schranky/'.rawurlencode($adresa).'/zkouska', [
            'komu' => $komu,
            'predmet' => __('posta.schranky_firmy.zkusebni_e_mail_z', ['name' => config('app.name')]),
            'text' => __('posta.schranky_firmy.dobry_den_toto_je_zkusebni_e', ['adresa' => $adresa]),
        ]);
    }

    /** Adresa, kam poslat prohlížeč na přihlášení přes Google / Microsoft (návrat na $navrat). */
    public function oauth(string $poskytovatel, string $adresa, ?string $jmeno, string $navrat, string $stav): string
    {
        return (string) $this->klient->spravuj('POST', '/vlastnik/oauth/'.rawurlencode($poskytovatel), array_filter([
            'adresa' => $adresa, 'jmeno' => $jmeno, 'navrat' => $navrat, 'stav' => $stav,
        ]))['url'];
    }

    /** Poskytovatelé (servery, přihlášení přes Google / Microsoft). @return list<array<string, mixed>> */
    public function predvolby(): array
    {
        return (array) ($this->klient->spravuj('GET', '/predvolby')['predvolby'] ?? []);
    }
}
