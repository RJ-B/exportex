<?php

namespace App\Support;

use App\Events\SekceWebuZmeneny;
use App\Models\Nastaveni;
use Filament\Facades\Filament;

/**
 * Zapnuté / vypnuté sekce webu (Obsah webu). Vypnutá sekce na webu není
 * (stránky 404, projekt ji schová z menu), v administraci se dál upravuje.
 * Hlavička a patička vypnout nejde – je na každé stránce.
 *
 * Sekci zavádí stránka nebo resource Obsahu webu metodou klicSekce()
 * (CastObsahuWebu); veřejné routy ji hlídají middlewarem `sekce:<klíč>`,
 * šablony helperem sekce_zapnuta('<klíč>').
 */
class SekceWebu
{
    public static function zapnuta(string $klic): bool
    {
        $trida = self::tridy()[$klic] ?? null;

        // Jádro webu (vypnoutJde() = false) je zapnuté vždycky, ať je uložené cokoli.
        if ($trida && ! $trida::vypnoutJde()) {
            return true;
        }

        return rescue(fn () => self::cti($klic), null, false) !== '0';
    }

    public static function nastav(string $klic, bool $zapnuto): void
    {
        self::zapis($klic, $zapnuto ? '1' : '0');
        self::ozvat();
    }

    /** Projekt reaguje posluchačem události SekceWebuZmeneny (např. srovná vlastní menu). */
    private static function ozvat(): void
    {
        SekceWebuZmeneny::dispatch();
    }

    /**
     * Všechny vypínatelné sekce z Obsahu webu: klíč => název.
     *
     * @return array<string, string>
     */
    public static function vsechny(): array
    {
        return collect(self::tridy())->map(fn (string $trida) => $trida::nazevSekce())->all();
    }

    /**
     * Sekce, které jsou stránkami webu (mají odkaz) – v uloženém pořadí.
     * To platí pro menu webu i pro pořadí v Obsahu webu v administraci.
     *
     * @return array<string, class-string> klíč => třída stránky / resource
     */
    public static function stranky(): array
    {
        $stranky = collect(self::tridy())->filter(fn (string $trida) => $trida::maOdkaz());
        $poradi = self::poradi();

        return $stranky
            ->sortBy(fn ($trida, string $klic) => [array_search($klic, $poradi, true) === false ? PHP_INT_MAX : array_search($klic, $poradi, true), $trida::getNavigationSortVychozi()])
            ->all();
    }

    /** Pozice sekce v pořadí (0, 1, …) nebo null, když v pořadí není. */
    public static function pozice(string $klic): ?int
    {
        $index = array_search($klic, array_keys(self::stranky()), true);

        return $index === false ? null : $index;
    }

    /** @param list<string> $klice */
    public static function nastavPoradi(array $klice): void
    {
        self::zapis('poradi', json_encode(array_values($klice)));
        self::ozvat();
    }

    /** @return list<string> uložené pořadí klíčů (i sekcí, které teď nemají odkaz) */
    public static function ulozenePoradi(): array
    {
        return self::poradi();
    }

    /**
     * Zapnuté sekce v pořadí – jednostránkový web podle toho skládá stránku.
     *
     * @return list<string>
     */
    public static function naWebu(): array
    {
        return collect(self::stranky())
            ->filter(fn ($trida, string $klic) => self::zapnuta($klic))
            ->keys()->all();
    }

    /**
     * Menu webu: zapnuté sekce v pořadí. Projekt ho vykreslí v hlavičce.
     *
     * @return list<array{klic: string, nazev: string, odkaz: string}>
     */
    public static function menu(): array
    {
        return collect(self::stranky())
            ->filter(fn ($trida, string $klic) => self::zapnuta($klic) && $trida::vMenuWebu())
            ->map(fn ($trida, string $klic) => ['klic' => $klic, 'nazev' => $trida::nazevSekce(), 'odkaz' => $trida::odkazSekce()])
            ->values()->all();
    }

    /** @return list<string> */
    private static function poradi(): array
    {
        return (array) json_decode((string) rescue(fn () => self::cti('poradi'), null, false), true);
    }

    /** @return array<string, class-string> */
    private static function tridy(): array
    {
        $panel = Filament::getPanel('admin');
        $tridy = [];

        foreach ([...$panel->getPages(), ...$panel->getResources()] as $trida) {
            if (method_exists($trida, 'klicSekce') && filled($klic = $trida::klicSekce())) {
                $tridy[$klic] = $trida;
            }
        }

        return $tridy;
    }

    // ---- Úložiště. Projekt s vlastním modelem nastavení mění jen tyhle dvě metody. ----

    private static function cti(string $klic): ?string
    {
        return Nastaveni::hodnota('web.sekce.'.$klic);
    }

    private static function zapis(string $klic, string $hodnota): void
    {
        Nastaveni::nastav('web.sekce.'.$klic, $hodnota);
    }
}
