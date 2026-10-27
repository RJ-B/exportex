<?php

namespace App\Support\Posta;

use App\Models\Nastaveni;
use RuntimeException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Throwable;

/**
 * Testovací schránka Sim&Ren (test@simren.cz) – pošta testovacích webů.
 *
 * Schránku testovacím aplikacím přiděluje Pošta (portál, rezim test); heslo
 * leží jen v Poště, .env testů žádné údaje nemá. Aplikace posílá dál přes
 * Poštu, jen na testu (APP_ENV ≠ production) každý e-mail přesměruje
 * (PresmerovaniNaTest, obal v LoggingMailManager):
 *
 *  - odesílatel i jediný příjemce test@simren.cz, kopie a Reply-To pryč,
 *  - komu by šel: předmět „[TEST → …]“ a hlavičky X-Simren-Puvodni-*,
 *  - platí pro každý mailer, frontu, notifikace i Poštu (obaluje se transport).
 *
 * Zapnuto na testovacím webu (APP_ENV=staging) vždy, jinde mimo produkci,
 * když je testovací adresa odesílatelem (přidělená Poštou, MAIL_FROM_ADDRESS,
 * POSTA_OD). Produkce nepřesměruje nikdy – a odesílatel produkce = testovací
 * adresa je tvrdá chyba v /zdravi (overProdukci), portál ji hlásí.
 *
 * Do databáze se nic nezapisuje. Přenos dat testovací adresu v nastavení
 * nepřenese (Casti::patri) a po importu celé databáze ji zapomene (poImportu).
 */
final class TestovaciSchranka
{
    /** Stejná jako posta.testovaci_schranka v Poště a TestovaciSchranka::ADRESA v portálu. */
    public const ADRESA = 'test@simren.cz';

    /** Přesměrovat poštu téhle instalace? Na produkci nikdy. */
    public static function zapnuta(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        return app()->environment('staging') || self::odesilaZTestovaci();
    }

    public static function jeTestovaci(?string $adresa): bool
    {
        return strtolower(trim((string) $adresa)) === self::ADRESA;
    }

    /** Je testovací adresa odesílatelem (Pošta, .env)? */
    public static function odesilaZTestovaci(): bool
    {
        return collect(self::odesilatele())->contains(fn ($a) => self::jeTestovaci($a));
    }

    /** Transport pro test (LoggingMailManager): přesměrování navrch, pod ním log a skutečný transport. */
    public static function obal(TransportInterface $transport): TransportInterface
    {
        return self::zapnuta() ? new PresmerovaniNaTest($transport, self::ADRESA, self::jmeno()) : $transport;
    }

    public static function jmeno(): string
    {
        return __('posta.test_schranka.jmeno', ['aplikace' => (string) config('app.name')]);
    }

    /**
     * Tvrdá kontrola produkce (Zdravi::tvrde): odesílatel nesmí být testovací
     * adresa – ani přidělená Poštou, ani z .env (MAIL_FROM_ADDRESS, POSTA_OD).
     */
    public static function overProdukci(): string
    {
        if (self::odesilaZTestovaci()) {
            throw new RuntimeException(__('posta.test_schranka.produkce_posila_z_testovaci', ['adresa' => self::ADRESA]));
        }

        return __('posta.test_schranka.odesilatel_neni_testovaci');
    }

    /**
     * Po importu celé databáze (PoImportu): stará schránka test@simren.cz
     * (posta.uzivatel… z doby před Poštou) a nastavení s hodnotou test@simren.cz
     * (kontaktní e-mail, příjemce formulářů…) pryč – produkce je nesmí převzít.
     *
     * @return array{schranka: string, nastaveni: int}
     */
    public static function poImportu(): array
    {
        $schranka = 'zadna';

        if (self::jeTestovaci((string) Nastaveni::query()->where('klic', 'posta.uzivatel')->value('hodnota'))) {
            foreach (StaraSchranka::KLICE as $klic) {
                Nastaveni::smaz('posta.'.$klic);
            }
            $schranka = 'zapomenuta';
        }

        $klice = Nastaveni::query()->get(['klic', 'hodnota'])
            ->filter(fn ($radek) => self::jeTestovaci(is_string($radek->hodnota) ? $radek->hodnota : null))
            ->pluck('klic');

        foreach ($klice as $klic) {
            Nastaveni::smaz($klic);
        }

        return ['schranka' => $schranka, 'nastaveni' => $klice->count()];
    }

    /** @return list<?string> adresy, ze kterých aplikace posílá nebo smí posílat */
    private static function odesilatele(): array
    {
        $adresy = [config('mail.from.address'), config('posta.od')];

        try {
            if (Propojeni::propojeno()) {
                $adresy[] = Propojeni::odesilatel()['adresa'] ?? null;
                array_push($adresy, ...array_column(Propojeni::adresy(), 'adresa'));
            }
        } catch (Throwable) {
            // bez databáze (instalace, první migrace) – jen .env
        }

        return $adresy;
    }
}
