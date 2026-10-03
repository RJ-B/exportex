<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Účet správce pro člověka, který si ho vyžádal v CRM („Můj účet správce“).
 *
 * Jednotné ve všech aplikacích Sim&Ren: CRM → portál → shim spustí
 * `php artisan simren:spravce <base64 JSON>`. Nový účet vznikne jako
 * superadmin s náhodným heslem, které nikdo nezná, a vrátí se jednorázový
 * odkaz na nastavení hesla – CRM ho ukáže jen tomu, kdo o účet požádal,
 * a nikam ho neukládá. Existující účet se jen povýší, heslo mu zůstane.
 *
 * Proto superadminy nezakládá žádný seeder a hesla nejsou v kódu.
 * Liší se jen metody pod čarou: jak se tu hledá účet, jak se jmenuje role
 * a kudy vede obnova hesla.
 *
 * Vstup je JSON v base64 (jméno má mezery a diakritiku a jde přes SSH jako
 * jeden argument), výstup JSON.
 */
class ZalozSpravce extends Command
{
    protected $signature = 'simren:spravce {data : base64 JSON s email, jmeno, prijmeni}';

    protected $description = 'Založí (nebo povýší) superadmina a vrátí odkaz na nastavení hesla';

    public function handle(): int
    {
        $data = json_decode((string) base64_decode(strtr((string) $this->argument('data'), '-_', '+/'), true), true);

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $jmeno = trim((string) ($data['jmeno'] ?? ''));
        $prijmeni = trim((string) ($data['prijmeni'] ?? ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($jmeno) > 60 || mb_strlen($prijmeni) > 60) {
            $this->line(json_encode(['error' => 'Neplatný e-mail nebo jméno.']));

            return self::FAILURE;
        }

        $user = $this->najdi($email);
        $novy = $user === null;

        if ($novy) {
            $user = $this->novy($email, $jmeno, $prijmeni);
            // Heslo, které nikdo nezná – přihlásit se jde až po nastavení odkazem.
            $user->forceFill(['password' => Hash::make(Str::password(64)), 'email_verified_at' => now()]);
        }

        $this->povys($user);
        $user->save();

        $token = Password::broker(null)->createToken($user);

        $this->line(json_encode([
            'created' => $novy,
            'email' => $email,
            'reset_url' => $this->odkaz($token, $user),
            'expires_minutes' => (int) config('auth.passwords.'.(null ?? config('auth.defaults.passwords')).'.expire', 60),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    // ── Co se liší podle aplikace ─────────────────────────────────────────

    private function najdi(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    private function novy(string $email, string $jmeno, string $prijmeni): User
    {
        return new User(['email' => $email, 'jmeno' => $jmeno, 'prijmeni' => $prijmeni]);
    }

    private function povys(User $user): void
    {
        $user->forceFill(['role' => 'superadmin']);
    }

    private function odkaz(string $token, User $user): string
    {
        // Vlastní stránka (NastaveniHeslaController): po uložení rovnou přihlásí.
        return route('nastaveni-hesla', ['token' => $token, 'email' => $user->email]);
    }

}
