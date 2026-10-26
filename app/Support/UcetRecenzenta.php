<?php

namespace App\Support;

use App\Events\RecenzentPripraven;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Účet pro kontrolu obchodu (recenzent App Storu a Google Play).
 *
 * Google Play (Obsah aplikace → Přístup k aplikaci) i App Store Connect
 * (App Review Information) chtějí přihlašovací údaje, se kterými jde mobilní
 * aplikace vyzkoušet. Ve všech aplikacích Sim&Ren je to stejný účet:
 * `test@<ostrá doména webu API>` – běžný uživatel (ne správce), vyřazený ze
 * statistik (`User::scopeBezSuperadminu`, sloupec `recenzent`).
 *
 * Zakládá ho CRM tlačítkem „Můj účet správce“ (u zakázky s mobilní aplikací)
 * → portál → shim (`portal-deploy <recept> recenzent`) → `php artisan
 * simren:recenzent --json` s JSON na standardním vstupu {email, heslo, jmeno,
 * prijmeni}. Heslo drží portál (výchozí heslo účtu pro kontrolu, šifrovaně)
 * a nikdy nejde v argumentech procesu ani ve výstupu.
 *
 * Idempotentní: chybějící účet vznikne, existující se jen srovná (heslo,
 * ověřený e-mail, příznak recenzenta). Správce se na recenzenta nikdy
 * nepředělá – to je chyba.
 *
 * Data, na kterých jde aplikace vyzkoušet (permanentka, kredit, ukázkový
 * obsah…), si doplní konkrétní aplikace posluchačem události
 * App\Events\RecenzentPripraven (app/Listeners – Laravel ho najde sám).
 * Shim pozná, že aplikace účet umí, podle tohoto souboru.
 */
final class UcetRecenzenta
{
    public const JMENO = 'Kontrola';

    public const PRIJMENI = 'obchodu';

    /**
     * @param  array{email?: mixed, heslo?: mixed, jmeno?: mixed, prijmeni?: mixed}  $vstup
     * @return array{ok: true, created: bool, heslo_zmeneno: bool, email: string}
     */
    public static function zajisti(array $vstup): array
    {
        $email = mb_strtolower(trim((string) ($vstup['email'] ?? '')));
        $heslo = (string) ($vstup['heslo'] ?? '');
        $jmeno = trim((string) ($vstup['jmeno'] ?? '')) ?: self::JMENO;
        $prijmeni = trim((string) ($vstup['prijmeni'] ?? '')) ?: self::PRIJMENI;

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            throw new RuntimeException(__('obecne.ucet_recenzenta.neplatny_e_mail_uctu_pro_kontrolu'));
        }

        if (mb_strlen($heslo) < 12 || mb_strlen($heslo) > 200) {
            throw new RuntimeException(__('obecne.ucet_recenzenta.heslo_uctu_pro_kontrolu_musi_mit'));
        }

        if (mb_strlen($jmeno) > 60 || mb_strlen($prijmeni) > 60) {
            throw new RuntimeException(__('obecne.ucet_recenzenta.prilis_dlouhe_jmeno_uctu_pro_kontrolu'));
        }

        return DB::transaction(function () use ($email, $heslo, $jmeno, $prijmeni) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            $novy = $user === null;

            if (! $novy && $user->jeSpravce()) {
                throw new RuntimeException(__('obecne.ucet_recenzenta.ucet_je_spravce_pro_kontrolu_obchodu', ['email' => $email]));
            }

            $user ??= new User(['email' => $email, 'jmeno' => $jmeno, 'prijmeni' => $prijmeni]);
            $zmenit = $novy || blank($user->password) || ! Hash::check($heslo, (string) $user->password);

            $user->forceFill(array_filter([
                'role' => $novy ? 'klient' : null,
                'recenzent' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'password' => $zmenit ? $heslo : null,
            ], fn ($v) => $v !== null))->save();

            RecenzentPripraven::dispatch($user, $novy);

            return ['ok' => true, 'created' => $novy, 'heslo_zmeneno' => $zmenit && ! $novy, 'email' => $email];
        });
    }
}
