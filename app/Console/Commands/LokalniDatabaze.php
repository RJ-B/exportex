<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Throwable;

/**
 * Lokální vývoj: doplní do .env jméno databáze podle složky projektu
 * (`composer setup`). Databázi pak založí `migrate --force` sám.
 *
 * Když se MariaDB nepustí účet z .env (root bez hesla), zkusí přihlášení
 * socketem pod uživatelem systému – tak je MariaDB z Homebrew nastavená
 * na Macu – a povedené údaje zapíše.
 *
 * Na serveru nic nedělá – tam .env zakládá portál s vlastní databází
 * a náhodným heslem. Vyplněné hodnoty se nikdy nepřepisují.
 */
class LokalniDatabaze extends Command
{
    protected $signature = 'simren:lokalni-databaze
        {--soubor= : jiný soubor .env (testy)}
        {--bez-pripojeni : nezkoušet připojení k MariaDB}';

    protected $description = 'Doplní do lokálního .env jméno databáze podle složky projektu';

    /** Kde bývá socket MariaDB (Homebrew na Apple Silicon i Intel, Linux). */
    private const SOKETY = ['/tmp/mysql.sock', '/opt/homebrew/var/mysql/mysql.sock', '/usr/local/var/mysql/mysql.sock', '/run/mysqld/mysqld.sock'];

    public function handle(): int
    {
        if ($this->laravel->environment('production', 'staging')) {
            $this->warn('Na serveru .env zakládá portál – nic se nemění.');

            return self::SUCCESS;
        }

        $soubor = (string) ($this->option('soubor') ?: base_path('.env'));

        if (! is_file($soubor)) {
            $this->error("Soubor {$soubor} není.");

            return self::FAILURE;
        }

        $obsah = (string) file_get_contents($soubor);

        if (preg_match('/^DB_DATABASE=[ \t]*$/m', $obsah)) {
            $jmeno = self::jmeno(basename(base_path()));
            $obsah = (string) preg_replace('/^DB_DATABASE=[ \t]*$/m', 'DB_DATABASE='.$jmeno, $obsah, 1);
            $this->info("DB_DATABASE={$jmeno}");
        } else {
            $this->line('DB_DATABASE už je vyplněné – beze změny.');
        }

        if (! $this->option('bez-pripojeni')) {
            $obsah = $this->prihlaseni($obsah);
        }

        file_put_contents($soubor, $obsah);

        return self::SUCCESS;
    }

    /** Jméno databáze ze složky: malá písmena, číslice a podtržítka, nejvýš 64 znaků. */
    public static function jmeno(string $slozka): string
    {
        $jmeno = trim((string) preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower($slozka)), '_');

        return substr($jmeno !== '' ? $jmeno : 'projekt', 0, 64);
    }

    private function prihlaseni(string $obsah): string
    {
        $p = static fn (string $klic) => preg_match("/^{$klic}=(.*)$/m", $obsah, $m) ? trim($m[1], " \t\"'") : '';

        if ($p('DB_CONNECTION') !== 'mariadb' && $p('DB_CONNECTION') !== 'mysql') {
            return $obsah;
        }

        $zkus = static function (string $dsn, string $uzivatel, string $heslo): bool {
            try {
                new PDO($dsn, $uzivatel, $heslo, [PDO::ATTR_TIMEOUT => 3]);

                return true;
            } catch (Throwable) {
                return false;
            }
        };

        $soket = $p('DB_SOCKET');
        $dsn = $soket !== '' ? "mysql:unix_socket={$soket}" : 'mysql:host='.($p('DB_HOST') ?: '127.0.0.1').';port='.($p('DB_PORT') ?: '3306');

        if ($zkus($dsn, $p('DB_USERNAME'), $p('DB_PASSWORD'))) {
            return $obsah;
        }

        $uzivatel = (string) (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : get_current_user());

        foreach (self::SOKETY as $soket) {
            if ($uzivatel !== '' && file_exists($soket) && $zkus("mysql:unix_socket={$soket}", $uzivatel, '')) {
                foreach (['DB_SOCKET' => $soket, 'DB_USERNAME' => $uzivatel] as $klic => $hodnota) {
                    $obsah = preg_match("/^#? *{$klic}=.*$/m", $obsah)
                        ? (string) preg_replace("/^#? *{$klic}=.*$/m", "{$klic}={$hodnota}", $obsah, 1)
                        : rtrim($obsah)."\n{$klic}={$hodnota}\n";
                }

                $this->info("Přihlášení k MariaDB socketem {$soket} jako {$uzivatel}.");

                return $obsah;
            }
        }

        $this->warn('K MariaDB se nepodařilo přihlásit – doplň DB_USERNAME, DB_PASSWORD (případně DB_SOCKET) v .env.');

        return $obsah;
    }
}
