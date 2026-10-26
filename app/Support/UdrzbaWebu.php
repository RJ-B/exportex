<?php

namespace App\Support;

use App\Enums\StavWebu;
use App\Models\Nastaveni;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stav webu „Údržba“ a „Připravujeme“ pro portál Sim&Ren (`simren:udrzba`).
 *
 * Portál u instalace ukazuje jedno tlačítko podle skutečného stavu: běží-li
 * údržba (artisan down nebo Stav webu „Údržba“), nabídne Vypnout údržbu
 * a vypne obě. Stav webu je nastavení aplikace (`nastaveni.web.stav`), proto
 * ho přepíná aplikace sama, ne skript na serveru – shim jen zavolá
 * `artisan simren:udrzba <akce> --json` pod uživatelem webu.
 * Podle tohohle souboru shim pozná, že aplikace příkaz má; podle konstanty
 * S_TEXTEM, že umí i text, předpokládaný konec a Připravujeme (`--vstup`).
 *
 *  - zapnout: Online → Údržba (s textem a koncem z CRM, `--vstup`); z Údržby
 *    jen změní text a konec. Z „Připravujeme“ nejde – vypnutí by web otevřelo.
 *  - vypnout: Údržba → Online, text a konec se smažou. Ostatní beze změny.
 *  - pripravujeme: Online → Připravujeme (nová produkce na doméně klienta,
 *    dokud se nepředá). Z Údržby nejde.
 *  - spustit: Připravujeme → Online (předání produkce). Ostatní beze změny.
 *
 * Text a konec jsou `web.udrzba.text` a `web.udrzba.konec` – jako všechno
 * pod `web.` je portál při přenosu dat z testu nepočítá za data webu.
 * Změna se zapíše do Aktivity jako z administrace (kdo = systém) a do
 * „Naposledy změnil“ na stránce Stav webu.
 */
final class UdrzbaWebu
{
    /** Shim podle téhle konstanty pozná `--vstup`, pripravujeme a spustit. */
    public const S_TEXTEM = true;

    public const AKCE = ['stav', 'zapnout', 'vypnout', 'pripravujeme', 'spustit'];

    public const KLIC_TEXT = 'web.udrzba.text';

    public const KLIC_KONEC = 'web.udrzba.konec';

    /**
     * @param  array{text?: ?string, konec?: ?string}|null  $vstup  text a předpokládaný konec údržby (ISO 8601); null = beze změny
     * @return array{ok: true, zmeneno: bool, stav_webu: array<string, mixed>|null}
     */
    public static function proved(string $akce, ?array $vstup = null): array
    {
        $pred = StavWebu::aktualni();

        $novy = match ($akce) {
            'stav' => $pred,
            'zapnout' => $pred === StavWebu::Pripravujeme
                ? throw new RuntimeException(__('obecne.udrzba_webu.web_je_ve_stavu_pripravujeme_udrzbu'))
                : StavWebu::Udrzba,
            'vypnout' => $pred === StavWebu::Udrzba ? StavWebu::Online : $pred,
            'pripravujeme' => $pred === StavWebu::Udrzba
                ? throw new RuntimeException(__('obecne.udrzba_webu.web_je_v_udrzbe_pripravujeme_se'))
                : StavWebu::Pripravujeme,
            'spustit' => $pred === StavWebu::Pripravujeme ? StavWebu::Online : $pred,
            default => throw new InvalidArgumentException('Neznámá akce „'.$akce.'“ – jen '.implode(', ', self::AKCE).'.'),
        };

        if ($akce === 'zapnout' && $vstup !== null) {
            self::ulozPlan($vstup);
        }

        if ($novy !== $pred) {
            Nastaveni::nastav(StavWebu::KLIC, $novy->value);
            Nastaveni::nastav(StavWebu::KLIC.'.zmena', __('obecne.udrzba_webu.portal_sim_ren', ['format' => now()->format(__('formaty.datum_cas_g'))]));
        }

        if ($novy !== StavWebu::Udrzba && $akce !== 'stav') {
            self::smazPlan();
        }

        return [
            'ok' => true,
            'zmeneno' => $novy !== $pred,
            'stav_webu' => Zdravi::udrzba()['stav_webu'],
        ];
    }

    /**
     * Text a předpokládaný konec údržby pro stránku Údržba – jen když web
     * v údržbě opravdu je (jinak null i se starými hodnotami).
     *
     * @return array{text: ?string, konec: ?Carbon}
     */
    public static function plan(): array
    {
        if (StavWebu::aktualni() !== StavWebu::Udrzba) {
            return ['text' => null, 'konec' => null];
        }

        $text = trim((string) rescue(fn () => Nastaveni::hodnota(self::KLIC_TEXT), null, false));
        $konec = rescue(fn () => CasAplikace::zVenku(Nastaveni::hodnota(self::KLIC_KONEC)), null, false);

        return ['text' => $text !== '' ? $text : null, 'konec' => $konec];
    }

    /** Smaže text a konec (vypnutí údržby, ruční změna stavu v administraci). */
    public static function smazPlan(): void
    {
        rescue(function () {
            Nastaveni::smaz(self::KLIC_TEXT);
            Nastaveni::smaz(self::KLIC_KONEC);
        }, null, false);
    }

    /**
     * Věta pro návštěvníka: „Hotovo přibližně v 14:30.“ (jiný den s datem),
     * null bez konce nebo když konec už minul – pak by sliboval nepravdu.
     */
    public static function vetaKonce(?Carbon $konec): ?string
    {
        if ($konec === null || $konec->isPast()) {
            return null;
        }

        return __('obecne.udrzba_webu.hotovo_priblizne', ['kdy' => ($konec->isToday() ? __('obecne.udrzba_webu.v_cas', ['cas' => $konec->format('G:i')]) : __('obecne.udrzba_webu.dne_v_cas', ['den' => $konec->format(__('formaty.den_mesic')), 'cas' => $konec->format('G:i')]))]);
    }

    /** @param  array{text?: ?string, konec?: ?string}  $vstup */
    private static function ulozPlan(array $vstup): void
    {
        $text = trim(strip_tags((string) ($vstup['text'] ?? '')));
        $text = mb_substr(preg_replace('/\s+/u', ' ', $text) ?? '', 0, 500);

        $konec = null;
        if (filled($vstup['konec'] ?? null)) {
            $konec = CasAplikace::zVenku((string) $vstup['konec'])
                ?? throw new InvalidArgumentException('Neplatný čas konce údržby (čeká se ISO 8601).');
        }

        Nastaveni::nastav(self::KLIC_TEXT, $text !== '' ? $text : null);
        Nastaveni::nastav(self::KLIC_KONEC, $konec?->toIso8601String());
    }
}
