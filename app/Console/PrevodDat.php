<?php

namespace App\Console;

use App\Support\PrevzetiHesla;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Základ příkazu, který převádí data ze staré aplikace (WordPress, staré PHP,
 * SQLite, Java…) do nové. Projekt napíše jen to, co je jeho:
 *
 *     class ImportStara extends PrevodDat
 *     {
 *         protected $signature = 'web:import-stara {--nanecisto}';
 *
 *         protected function prevod(Connection $stara): void
 *         {
 *             $this->tabulka('účty', $stara->table('users'), function (object $r) {
 *                 User::query()->updateOrCreate(['email' => $r->email], [
 *                     'jmeno' => $r->first_name, 'prijmeni' => $r->last_name,
 *                     'password' => $this->heslo($r->password),
 *                 ]);
 *             }, fn () => User::query()->count());
 *         }
 *     }
 *
 * Co dělá základ:
 * - stará databáze (spojení `stara`, STARA_DB_* v .env) jen pro čtení – zápis do ní spadne,
 * - všechno v jedné transakci; `--nanecisto` na konci vrátí (vypíše, co by se stalo),
 * - souhrn zdroj × zapsáno × přeskočeno × v cíli; když počty nesedí, vrátí se
 *   všechno a příkaz skončí chybou,
 * - hesla přes PrevzetiHesla (Spring, bcrypt, PHP); co Laravel neověří, je v souhrnu.
 *
 * Opakovatelnost je na projektu: zapisovat přes updateOrCreate podle původního
 * id (sloupec `puvodni_id`) nebo přirozeného klíče, ve zdroji nic nemazat.
 * Pomalé I/O (soubory, obrázky) patří mimo – do vlastního kroku po převodu.
 */
abstract class PrevodDat extends Command
{
    /** @var array<string, array{zdroj: int, zapsano: int, preskoceno: int, cil: ?int}> */
    private array $souhrn = [];

    /** @var array<string, int> druh hashe => počet účtů, které si heslo nastaví znovu */
    private array $heslaZnovu = [];

    private int $heslaPrevzata = 0;

    abstract protected function prevod(Connection $stara): void;

    public function handle(): int
    {
        $stara = $this->stara();
        $nanecisto = $this->hasOption('nanecisto') && (bool) $this->option('nanecisto');

        DB::beginTransaction();

        try {
            $this->prevod($stara);
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Převod spadl, nic se neuložilo: '.$e->getMessage());

            return self::FAILURE;
        }

        $sedi = $this->vypisSouhrn();

        if (! $sedi || $nanecisto) {
            DB::rollBack();
            $sedi ? $this->warn('Nanečisto – nic se neuložilo.') : $this->error('Počty nesedí – nic se neuložilo.');

            return $sedi ? self::SUCCESS : self::FAILURE;
        }

        DB::commit();
        $this->info('Převedeno.');

        return self::SUCCESS;
    }

    /** Stará databáze jen pro čtení. */
    protected function stara(): Connection
    {
        $spojeni = DB::connection('stara');

        match ($spojeni->getDriverName()) {
            'sqlite' => $spojeni->statement('PRAGMA query_only = ON'),
            'mysql', 'mariadb' => $spojeni->statement('SET SESSION TRANSACTION READ ONLY'),
            default => null,
        };

        return $spojeni;
    }

    /**
     * Převede řádky dotazu nad starou databází. $zapis(object $radek) vrátí false,
     * když řádek vědomě přeskočil (smazaný, testovací…); cokoli jiného = zapsáno.
     * $vCili() = kolik řádků je po převodu v cíli – musí to být zdroj − přeskočené.
     */
    protected function tabulka(string $nazev, Builder $zdroj, callable $zapis, ?callable $vCili = null): void
    {
        $radek = ['zdroj' => (clone $zdroj)->count(), 'zapsano' => 0, 'preskoceno' => 0, 'cil' => null];

        foreach ($zdroj->cursor() as $zaznam) {
            $zapis($zaznam) === false ? $radek['preskoceno']++ : $radek['zapsano']++;
        }

        $radek['cil'] = $vCili ? (int) $vCili() : null;
        $this->souhrn[$nazev] = $radek;
    }

    /** Hash hesla ze staré aplikace pro sloupec `password` (null = nastaví si ho znovu). */
    protected function heslo(?string $hash): ?string
    {
        $prevzate = PrevzetiHesla::prevezmi($hash);

        if ($prevzate === null) {
            $druh = PrevzetiHesla::druh($hash);
            $this->heslaZnovu[$druh] = ($this->heslaZnovu[$druh] ?? 0) + 1;
        } else {
            $this->heslaPrevzata++;
        }

        return $prevzate;
    }

    /** @return array<string, array{zdroj: int, zapsano: int, preskoceno: int, cil: ?int}> */
    public function souhrn(): array
    {
        return $this->souhrn;
    }

    private function vypisSouhrn(): bool
    {
        $sedi = true;
        $radky = [];

        foreach ($this->souhrn as $nazev => $r) {
            $ok = $r['zdroj'] === $r['zapsano'] + $r['preskoceno']
                && ($r['cil'] === null || $r['cil'] === $r['zdroj'] - $r['preskoceno']);
            $sedi = $sedi && $ok;
            $radky[] = [$nazev, $r['zdroj'], $r['zapsano'], $r['preskoceno'], $r['cil'] ?? '–', $ok ? 'sedí' : 'NESEDÍ'];
        }

        $this->table(['Co', 'Ve staré', 'Zapsáno', 'Přeskočeno', 'V nové', ''], $radky);

        if ($this->heslaPrevzata || $this->heslaZnovu) {
            $this->line('Hesla převzatá: '.$this->heslaPrevzata
                .($this->heslaZnovu ? '; nastaví si znovu: '.collect($this->heslaZnovu)->map(fn ($n, $druh) => "{$druh} {$n}")->implode(', ') : ''));
        }

        return $sedi;
    }
}
