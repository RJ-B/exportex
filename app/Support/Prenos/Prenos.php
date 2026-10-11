<?php

namespace App\Support\Prenos;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Přenos vybraných částí dat mezi weby (test → produkce, portál → Přenést
 * data z testu). Shim na zdroji spustí `simren:prenos-export`, balík přes
 * portál převeze a na cíli spustí `simren:prenos-import` – nejdřív
 * nanečisto (náhled pro člověka), po potvrzení doopravdy.
 *
 * Režimy (zvlášť pro každou část):
 *   doplnit       – přidá jen řádky, které v cíli nejsou; nic existujícího nemění,
 *   aktualizovat  – přidá chybějící a existující přepíše verzí ze zdroje;
 *                   co je jen v cíli, zůstane,
 *   nahradit      – řádky části v cíli smaže a nahradí kopií zdroje.
 *
 * Databáze se mění v jedné transakci (nanečisto se na konci vrátí), soubory
 * se zapíšou až po úspěšném uložení. Zálohu celé databáze před importem
 * a návrat při chybě dělá navíc shim.
 */
class Prenos
{
    public const FORMAT = 1;

    /** Sloupce, které se při porovnání „změněno / stejné“ nepočítají. */
    private const CASOVE = ['created_at', 'updated_at'];

    /** @var array<string, array<int|string, int|string>> tabulka => [id ve zdroji => id v cíli] */
    private array $mapy = [];

    /** @var array<string, array{disk: string, cesta: string, prepsat: bool}> */
    private array $soubory = [];

    public static function adresar(string $id): string
    {
        if (! preg_match('/^p[0-9a-f]{32}$/', $id)) {
            throw new RuntimeException('Neplatný identifikátor přenosu.');
        }

        return storage_path('framework/prenos/'.$id);
    }

    /**
     * Všechny části do adresáře: data.json a soubory/<disk>/<cesta>.
     *
     * @return array{casti: array<string, int>, soubory: int, sifrovane_vynechane: array<string, int>}
     */
    public function export(string $adresar): array
    {
        if (File::exists($adresar)) {
            throw new RuntimeException('Adresář přenosu už existuje.');
        }

        File::ensureDirectoryExists($adresar.'/soubory', 0700);
        $data = ['format' => self::FORMAT, 'aplikace' => config('app.name'), 'vytvoreno' => now()->toIso8601String(), 'casti' => []];
        $soubory = [];
        $pocty = [];
        $vynechane = [];

        foreach (Casti::vse() as $klic => $cast) {
            $pocty[$klic] = 0;

            foreach ($cast['tabulky'] as $tabulka => $def) {
                if (! Schema::hasTable($tabulka)) {
                    continue;
                }

                // Šifrované hodnoty (APP_KEY) se neposílají – na cíli by byly nečitelné
                // a čitelně ze serveru ven nesmí. Výsledek řekne, co se vynechalo.
                $radky = DB::table($tabulka)->get()
                    ->map(function ($r) use ($tabulka, &$vynechane) {
                        return Sifrovane::bez($tabulka, Casti::bezTajnych($tabulka, (array) $r), $vynechane);
                    })
                    ->filter(fn (array $r) => Casti::patri($tabulka, $def, $r))
                    ->values()->all();

                foreach ($radky as $r) {
                    foreach (Soubory::zRadku($r) as $s) {
                        $soubory[$s['disk'].':'.$s['cesta']] = $s;
                    }
                }

                $data['casti'][$klic]['tabulky'][$tabulka] = $radky;
                $pocty[$klic] += count($radky);
            }
        }

        foreach ($soubory as $s) {
            $cil = $adresar.'/soubory/'.$s['disk'].'/'.$s['cesta'];
            File::ensureDirectoryExists(dirname($cil), 0700);
            File::put($cil, Storage::disk($s['disk'])->get($s['cesta']));
        }

        $data['sifrovane_vynechane'] = $vynechane;
        File::put($adresar.'/data.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return ['casti' => $pocty, 'soubory' => count($soubory), 'sifrovane_vynechane' => $vynechane];
    }

    /**
     * @param  array<string, string>  $volby  část => režim (Casti::volby)
     * @return array{nanecisto: bool, casti: array<string, array<string, mixed>>, soubory: int, sifrovane_vynechane: array<string, int>}
     */
    public function import(string $adresar, array $volby, bool $nanecisto): array
    {
        $data = json_decode((string) @file_get_contents($adresar.'/data.json'), true);

        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Balík přenosu nemá data, nebo je v neznámém formátu.');
        }

        $casti = Casti::vse();
        $this->mapy = [];
        $this->soubory = [];
        $vysledek = [];

        DB::beginTransaction();

        try {
            // Napřed spárovat všechno, co balík nese (i nevybrané části): vazba
            // z vybrané části může vést na řádek, který se nepřenáší, ale v cíli už je.
            foreach ($casti as $klic => $cast) {
                foreach ($cast['tabulky'] as $tabulka => $def) {
                    if (! Schema::hasTable($tabulka)) {
                        continue;
                    }

                    foreach ($data['casti'][$klic]['tabulky'][$tabulka] ?? [] as $radek) {
                        $this->sparuj($tabulka, $def, $radek);
                    }
                }
            }

            foreach ($volby as $klic => $rezim) {
                $vysledek[$klic] = ['nazev' => $casti[$klic]['nazev'], 'rezim' => $rezim]
                    + $this->cast($casti[$klic]['tabulky'], $data['casti'][$klic]['tabulky'] ?? [], $rezim);
            }

            $soubory = $this->spocitejSoubory($adresar);

            if ($nanecisto) {
                DB::rollBack();

                return ['nanecisto' => true, 'casti' => $vysledek, 'soubory' => $soubory, 'sifrovane_vynechane' => (array) ($data['sifrovane_vynechane'] ?? [])];
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $this->zapisSoubory($adresar);
        Cache::flush();

        AuditLogger::record(
            event: 'data.prevzata',
            summary: 'Převzata data z jiného webu ('.collect($vysledek)->map(fn ($v) => $v['nazev'].' – '.$v['rezim'])->implode(', ').')',
            new: ['zdroj' => $data['aplikace'] ?? null, 'export' => $data['vytvoreno'] ?? null, 'casti' => $vysledek, 'soubory' => $soubory, 'sifrovane_vynechane' => (array) ($data['sifrovane_vynechane'] ?? [])],
        );

        return ['nanecisto' => false, 'casti' => $vysledek, 'soubory' => $soubory, 'sifrovane_vynechane' => (array) ($data['sifrovane_vynechane'] ?? [])];
    }

    /** @return array<string, int> */
    private function cast(array $tabulky, array $zdroj, string $rezim): array
    {
        $p = ['nove' => 0, 'zmenene' => 0, 'stejne' => 0, 'ponechane' => 0, 'smazane' => 0, 'jen_v_cili' => 0, 'preskocene' => 0];

        if ($rezim === 'nahradit') {
            foreach (array_reverse($tabulky, true) as $tabulka => $def) {
                if (Schema::hasTable($tabulka)) {
                    $p['smazane'] += $this->smaz($tabulka, $def);
                }
            }
        }

        foreach ($tabulky as $tabulka => $def) {
            if (! Schema::hasTable($tabulka)) {
                continue;
            }

            $sloupce = Schema::getColumnListing($tabulka);
            $pk = $def['pk'] !== null && in_array($def['pk'], $sloupce, true) ? $def['pk'] : null;
            $nalezene = [];

            foreach ($zdroj[$tabulka] ?? [] as $radek) {
                $radek = $this->premapuj($radek, $def);

                if ($radek === null || ! Casti::patri($tabulka, $def, $radek)) {
                    $p['preskocene']++;

                    continue;
                }

                $radek = Casti::bezTajnych($tabulka, array_intersect_key($radek, array_flip($sloupce)));
                $cil = $this->najdi($tabulka, $def, $radek);
                $hodnoty = $pk ? array_diff_key($radek, [$pk => true]) : $radek;

                if ($cil === null) {
                    $noveId = $this->vloz($tabulka, $pk, $radek, $hodnoty);

                    if ($pk) {
                        $this->mapy[$tabulka][$radek[$pk]] = $noveId;
                    }

                    $nalezene[] = $pk ? (string) $noveId : $this->identita($def, $radek);
                    $this->doFronty($radek, $rezim !== 'doplnit');
                    $p['nove']++;

                    continue;
                }

                if ($pk) {
                    $this->mapy[$tabulka][$radek[$pk]] = $cil[$pk];
                }

                $nalezene[] = $pk ? (string) $cil[$pk] : $this->identita($def, $cil);

                if (! $this->lisi($hodnoty, $cil)) {
                    $p['stejne']++;
                } elseif ($rezim === 'doplnit') {
                    $p['ponechane']++;
                } else {
                    $this->radekCile($tabulka, $def, $pk, $cil)->update($hodnoty);
                    $this->doFronty($radek, true);
                    $p['zmenene']++;
                }
            }

            if ($rezim !== 'nahradit') {
                $p['jen_v_cili'] += DB::table($tabulka)->get()
                    ->map(fn ($r) => (array) $r)
                    ->filter(fn (array $r) => Casti::patri($tabulka, $def, $r)
                        && ! in_array($pk ? (string) $r[$pk] : $this->identita($def, $r), $nalezene, true))
                    ->count();
            }
        }

        return $p;
    }

    /** Spáruje řádek zdroje s cílem podle klíče (bez zápisu) – pro vazby. */
    private function sparuj(string $tabulka, array $def, array $radek): void
    {
        $pk = $def['pk'];

        if ($pk === null || ! array_key_exists($pk, $radek)) {
            return;
        }

        $radek = $this->premapuj($radek, $def);
        $cil = $radek === null ? null : $this->najdi($tabulka, $def, $radek);

        if ($cil !== null && array_key_exists($pk, $cil)) {
            $this->mapy[$tabulka][$radek[$pk]] = $cil[$pk];
        }
    }

    /** Vazby na id ve zdroji → id v cíli; vazba na řádek, který v cíli není, = null (řádek se přeskočí). */
    private function premapuj(array $radek, array $def): ?array
    {
        foreach ($def['vazby'] as $sloupec => $rodic) {
            $hodnota = $radek[$sloupec] ?? null;

            if ($hodnota === null) {
                continue;
            }

            if (! isset($this->mapy[$rodic][$hodnota])) {
                return null;
            }

            $radek[$sloupec] = $this->mapy[$rodic][$hodnota];
        }

        return $radek;
    }

    /**
     * Řádek cíle podle klíče. Víc možností klíče se zkouší popořadě; možnost,
     * ve které má zdroj prázdnou hodnotu, se přeskočí (kromě poslední).
     */
    private function najdi(string $tabulka, array $def, array $radek): ?array
    {
        $posledni = count($def['klic']) - 1;

        foreach ($def['klic'] as $i => $klic) {
            if ($i < $posledni && collect($klic)->contains(fn ($s) => ($radek[$s] ?? null) === null)) {
                continue;
            }

            if ($cil = $this->podle($tabulka, $klic, $radek)->first()) {
                return (array) $cil;
            }
        }

        return null;
    }

    private function podle(string $tabulka, array $sloupce, array $radek)
    {
        $dotaz = DB::table($tabulka);

        foreach ($sloupce as $sloupec) {
            ($radek[$sloupec] ?? null) === null
                ? $dotaz->whereNull($sloupec)
                : $dotaz->where($sloupec, $radek[$sloupec]);
        }

        return $dotaz;
    }

    /** Dotaz na jeden konkrétní řádek cíle: podle primárního klíče, jinak podle (poslední možnosti) klíče. */
    private function radekCile(string $tabulka, array $def, ?string $pk, array $cil)
    {
        return $pk ? DB::table($tabulka)->where($pk, $cil[$pk]) : $this->podle($tabulka, end($def['klic']), $cil);
    }

    /** Totožnost řádku bez primárního klíče (nastavení): hodnoty poslední možnosti klíče. */
    private function identita(array $def, array $radek): string
    {
        return json_encode(array_map(fn ($s) => isset($radek[$s]) ? (string) $radek[$s] : null, end($def['klic'])));
    }

    private function vloz(string $tabulka, ?string $pk, array $radek, array $hodnoty): int|string|null
    {
        // Číselné id dá cíl nové; jiný primární klíč (uuid, ulid) se přenese, jak je.
        if ($pk && is_numeric($radek[$pk] ?? null)) {
            return DB::table($tabulka)->insertGetId($hodnoty);
        }

        DB::table($tabulka)->insert($radek);

        return $pk ? $radek[$pk] : null;
    }

    private function lisi(array $hodnoty, array $cil): bool
    {
        foreach ($hodnoty as $sloupec => $hodnota) {
            if (in_array($sloupec, self::CASOVE, true)) {
                continue;
            }

            $tady = $cil[$sloupec] ?? null;

            if (($hodnota === null) !== ($tady === null) || (string) $hodnota !== (string) $tady) {
                return true;
            }
        }

        return false;
    }

    /** Smaže řádky části v cíli (filtry části platí i tady). */
    private function smaz(string $tabulka, array $def): int
    {
        $smazane = 0;

        $pk = $def['pk'] !== null && Schema::hasColumn($tabulka, $def['pk']) ? $def['pk'] : null;

        foreach (DB::table($tabulka)->get()->map(fn ($r) => (array) $r) as $r) {
            if (Casti::patri($tabulka, $def, $r)) {
                $smazane += $this->radekCile($tabulka, $def, $pk, $r)->delete();
            }
        }

        $this->mapy[$tabulka] = [];

        return $smazane;
    }

    private function doFronty(array $radek, bool $prepsat): void
    {
        foreach (Soubory::zRadku($radek, false) as $s) {
            $klic = $s['disk'].':'.$s['cesta'];
            $this->soubory[$klic] = $s + ['prepsat' => $prepsat || ($this->soubory[$klic]['prepsat'] ?? false)];
        }
    }

    /** Kolik souborů se zapíše – jen ty, které balík nese a cíl je potřebuje (celkem, jeden může patřit víc částem). */
    private function spocitejSoubory(string $adresar): int
    {
        $this->soubory = array_filter($this->soubory, fn (array $s) => is_file($this->zdrojSouboru($adresar, $s))
            && ($s['prepsat'] || ! Storage::disk($s['disk'])->exists($s['cesta'])));

        return count($this->soubory);
    }

    private function zapisSoubory(string $adresar): void
    {
        foreach ($this->soubory as $s) {
            Storage::disk($s['disk'])->put($s['cesta'], File::get($this->zdrojSouboru($adresar, $s)));
        }
    }

    private function zdrojSouboru(string $adresar, array $s): string
    {
        return $adresar.'/soubory/'.$s['disk'].'/'.$s['cesta'];
    }
}
