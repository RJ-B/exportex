<?php

namespace App\Support;

use Illuminate\Console\Scheduling\Event;

/**
 * Rozložení úloh plánovače v hodině. Každá aplikace serveru má vlastní posun
 * (0–59 min) a úlohy s ->rozlozit() se o něj posunou – údržba tak nestartuje
 * ve stejnou minutu ve všech aplikacích serveru (dřív ve 3:30 desítky naráz).
 *
 * Posun přiděluje portál po serverech (SIMREN_ROZVRH_POSUN – nový projekt do
 * největší mezery). Bez něj se spočítá z adresy webu; stejný výpočet má portál.
 */
final class Rozvrh
{
    public static function zapni(): void
    {
        // ->dailyAt('01:00')->rozlozit(): minuta + posun v téže hodině.
        Event::macro('rozlozit', function () {
            /** @var Event $this */
            $this->expression = Rozvrh::posunVyraz($this->expression, Rozvrh::posun());

            return $this;
        });
    }

    public static function posun(): int
    {
        $nastaveny = config('sablona.rozvrh_posun');

        if (is_numeric($nastaveny)) {
            return (((int) $nastaveny) % 60 + 60) % 60;
        }

        return self::posunZAdresy((string) config('app.url'));
    }

    /** Posun z hostitele webu (crc32 % 60) – instalace, které portál posun ještě nepřidělil. */
    public static function posunZAdresy(string $url): int
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: $url));

        return crc32($host) % 60;
    }

    /**
     * Posune minutu výrazu cronu: pevná minuta v rámci téže hodiny (denní úloha
     * nepřeskočí do jiného dne), krok „každých k minut“ na posun % k. Každou minutu, seznamy
     * a rozsahy zůstávají.
     */
    public static function posunVyraz(string $vyraz, int $posun): string
    {
        $casti = preg_split('/\s+/', trim($vyraz)) ?: [];

        if (count($casti) < 5 || $posun % 60 === 0) {
            return $vyraz;
        }

        if (ctype_digit($casti[0])) {
            $casti[0] = (string) (((int) $casti[0] + $posun) % 60);
        } elseif (preg_match('#^\*/(\d+)$#', $casti[0], $m) && (int) $m[1] > 1 && 60 % (int) $m[1] === 0) {
            $krok = (int) $m[1];
            $casti[0] = ($posun % $krok).'-59/'.$krok;
        } else {
            return $vyraz;
        }

        return implode(' ', $casti);
    }
}
