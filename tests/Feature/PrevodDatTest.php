<?php

namespace Tests\Feature;

use App\Console\PrevodDat;
use App\Models\User;
use App\Models\Zprava;
use App\Support\PrevzetiHesla;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Převod dat ze staré aplikace: stará databáze jen pro čtení, nanečisto,
 * kontrola počtů, opakovatelnost a hesla z cizích hashů.
 */
class PrevodDatTest extends TestCase
{
    use RefreshDatabase;

    private string $soubor;

    protected function setUp(): void
    {
        parent::setUp();

        // Stará aplikace: SQLite soubor (jako artkrizova) se staršími tabulkami.
        $this->soubor = tempnam(sys_get_temp_dir(), 'stara').'.sqlite';
        touch($this->soubor);
        config(['database.connections.stara' => ['driver' => 'sqlite', 'database' => $this->soubor, 'prefix' => '', 'foreign_key_constraints' => false]]);

        $stara = DB::connection('stara');
        $stara->statement('create table users (id integer primary key, email text, first_name text, last_name text, password text)');
        $stara->statement('create table messages (id integer primary key, name text, surname text, email text, body text, deleted integer)');

        $bcrypt = password_hash('tajne', PASSWORD_BCRYPT, ['cost' => 4]);
        $stara->table('users')->insert([
            ['id' => 1, 'email' => 'jana@firma.cz', 'first_name' => 'Jana', 'last_name' => 'Nováková', 'password' => $bcrypt],
            ['id' => 2, 'email' => 'petr@firma.cz', 'first_name' => 'Petr', 'last_name' => 'Dvořák', 'password' => '{bcrypt}$2a$'.substr($bcrypt, 4)],
            ['id' => 3, 'email' => 'eva@firma.cz', 'first_name' => 'Eva', 'last_name' => 'Malá', 'password' => '$P$BqYz8Jq0Z0p6wK9b1zW2.Q3mH8Yt0p/'],
        ]);
        $stara->table('messages')->insert([
            ['id' => 1, 'name' => 'Karel', 'surname' => 'Novák', 'email' => 'karel@seznam.cz', 'body' => 'Poptávám klimatizaci do kanceláře 30 m².', 'deleted' => 0],
            ['id' => 2, 'name' => 'Spam', 'surname' => 'Bot', 'email' => 'bot@example.com', 'body' => 'xxx', 'deleted' => 1],
        ]);

        $this->app->make(Kernel::class)->registerCommand($this->app->make(ImportZkouska::class));
    }

    protected function tearDown(): void
    {
        DB::purge('stara');
        @unlink($this->soubor);
        parent::tearDown();
    }

    public function test_prevod_s_hesly_a_kontrolou_poctu(): void
    {
        $this->artisan('zkouska:import-stara')->assertSuccessful()
            ->expectsOutputToContain('Hesla převzatá: 2; nastaví si znovu: WordPress 1');

        $this->assertSame(3, User::query()->count());
        $this->assertTrue(Hash::check('tajne', User::query()->where('email', 'jana@firma.cz')->value('password')));
        // Spring {bcrypt}$2a$ – po převodu ověří i Laravel (jinak by se uložil hash hashe).
        $this->assertTrue(Hash::check('tajne', User::query()->where('email', 'petr@firma.cz')->value('password')));
        $this->assertNull(User::query()->where('email', 'eva@firma.cz')->value('password'));
        $this->assertSame(1, Zprava::query()->count(), 'smazaná zpráva se vědomě přeskočila');

        // Opakovatelné: druhý běh nic nezdvojí.
        $this->artisan('zkouska:import-stara')->assertSuccessful();
        $this->assertSame(3, User::query()->count());
        $this->assertSame(1, Zprava::query()->count());
    }

    public function test_nanecisto_nic_neulozi(): void
    {
        $this->artisan('zkouska:import-stara', ['--nanecisto' => true])->assertSuccessful()
            ->expectsOutputToContain('Nanečisto');

        $this->assertSame(0, User::query()->count());
    }

    public function test_nesedici_pocty_vrati_vsechno(): void
    {
        ImportZkouska::$ztratit = true;

        try {
            $this->artisan('zkouska:import-stara')->assertFailed()->expectsOutputToContain('Počty nesedí');
        } finally {
            ImportZkouska::$ztratit = false;
        }

        $this->assertSame(0, User::query()->count());
    }

    public function test_stara_databaze_jen_pro_cteni(): void
    {
        $this->expectException(QueryException::class);

        (new ImportZkouska)->staraProTest()->table('users')->delete();
    }

    public function test_cizi_hashe(): void
    {
        $bcrypt = password_hash('heslo', PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertSame($bcrypt, PrevzetiHesla::prevezmi($bcrypt));
        $this->assertSame($bcrypt, PrevzetiHesla::prevezmi('{bcrypt}$2a$'.substr($bcrypt, 4)));
        $this->assertSame($bcrypt, PrevzetiHesla::prevezmi('$2b$'.substr($bcrypt, 4)));
        $this->assertNull(PrevzetiHesla::prevezmi('{noop}heslo'));
        $this->assertNull(PrevzetiHesla::prevezmi('$wp$2y$10$abc'));
        $this->assertNull(PrevzetiHesla::prevezmi(password_hash('heslo', PASSWORD_ARGON2ID)));
        $this->assertNull(PrevzetiHesla::prevezmi(md5('heslo')));
        $this->assertNull(PrevzetiHesla::prevezmi(null));
        $this->assertSame('Spring bcrypt', PrevzetiHesla::druh('{bcrypt}$2a$10$x'));
    }
}

/** Ukázkový převod: účty podle e-mailu, zprávy z formuláře bez smazaných. */
class ImportZkouska extends PrevodDat
{
    public static bool $ztratit = false;

    protected $signature = 'zkouska:import-stara {--nanecisto}';

    public function staraProTest(): Connection
    {
        return $this->stara();
    }

    protected function prevod(Connection $stara): void
    {
        $this->tabulka('účty', $stara->table('users')->orderBy('id'), function (object $r) {
            if (self::$ztratit && $r->id === 3) {
                return null;   // „zapsáno“, ale v cíli chybí
            }

            User::query()->updateOrCreate(['email' => $r->email], [
                'jmeno' => $r->first_name,
                'prijmeni' => $r->last_name,
                'password' => $this->heslo($r->password),
            ]);
        }, fn () => User::query()->count());

        $this->tabulka('zprávy z webu', $stara->table('messages')->orderBy('id'), function (object $r) {
            if ($r->deleted) {
                return false;
            }

            Zprava::query()->updateOrCreate(['email' => $r->email, 'zprava' => $r->body], ['jmeno' => $r->name, 'prijmeni' => $r->surname]);
        }, fn () => Zprava::query()->count());
    }
}
