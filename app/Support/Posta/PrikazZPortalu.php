<?php

namespace App\Support\Posta;

use Illuminate\Console\Command;
use Throwable;

/**
 * posta:z-portalu – propojení s Poštou od portálu, bez klikání.
 *
 * Portál ho spouští přes shim (`<projekt> posta`) pod uživatelem webu a JSON
 * posílá na STDIN – token ani tajemství nikdy nejsou v argumentech procesu
 * ani v logu. Výstup je jeden řádek JSON bez tajemství.
 *
 *   {"akce": "zjisti"}  → {"podporovano": true, "propojeno": bool, "zdroj": …, "stara_schranka": {adresa, smtp_host, zdroj}|null}
 *   {"akce": "propoj", "url", "token", "webhook_tajemstvi", "aplikace", "prevzeti"}
 *                       → {"ok": true, "aplikace": slug, "prevzeti": {ok, zprava}|null}
 *   {"akce": "odpoj"}   → {"ok": true} (portál aplikaci v Poště už odpojil)
 *
 * Shim pozná, že aplikace příkaz má, podle souboru app/Support/Posta/PrikazZPortalu.php.
 */
class PrikazZPortalu extends Command
{
    public const VERZE = 1;

    /** Víc portál neposílá – delší vstup je chyba. */
    private const MAX_VSTUP = 65536;

    /** Náhrada stdin v testech (jinak se čte STDIN). */
    public static ?string $vstup = null;

    protected $signature = 'posta:z-portalu';

    protected $description = 'Propojení s Poštou od portálu (JSON na stdin)';

    public function handle(Propojeni $propojeni): int
    {
        $vstup = json_decode(self::$vstup ?? (string) stream_get_contents(STDIN, self::MAX_VSTUP), true);

        if (! is_array($vstup)) {
            return $this->vystup(['ok' => false, 'chyba' => 'Vstup není JSON.'], self::FAILURE);
        }

        try {
            return match ($vstup['akce'] ?? null) {
                'zjisti' => $this->vystup([
                    'podporovano' => true,
                    'verze' => self::VERZE,
                    'propojeno' => Propojeni::propojeno(),
                    'zdroj' => Propojeni::zdroj(),
                    'stara_schranka' => StaraSchranka::popis(),
                ]),
                'propoj' => $this->vystup($propojeni->zPortalu($vstup)),
                'odpoj' => $this->vystup(tap(['ok' => true], fn () => $propojeni->zapomen())),
                default => $this->vystup(['ok' => false, 'chyba' => 'Neznámá akce.'], self::FAILURE),
            };
        } catch (Throwable $e) {
            report($e);

            return $this->vystup(['ok' => false, 'chyba' => mb_substr($e->getMessage(), 0, 300)], self::FAILURE);
        }
    }

    private function vystup(array $data, int $kod = self::SUCCESS): int
    {
        $this->line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $kod;
    }
}
