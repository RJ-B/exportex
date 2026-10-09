<?php

namespace App\Support\Oznameni;

use App\Enums\DruhOznameni;
use App\Enums\KanalOznameni;
use App\Models\OznameniPredvolby;
use App\Models\OznameniSouhlas;
use App\Models\User;
use App\Support\ZakladniUdaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Co chce uživatel dostávat: druh × kanál. Platí přednostně to, co si nastavil;
 * jinak výchozí z DruhOznameni. Zamčené kanály (centrum u provozních a servisních)
 * platí vždy, novinky a nabídky ven (e-mail, push) jen se souhlasem.
 *
 * Každá změna u druhu, který potřebuje souhlas, se zapíše do oznameni_souhlasy
 * i se zněním, se kterým člověk souhlasil (GDPR – doložit souhlas).
 */
class Predvolby
{
    public static function chce(User $user, DruhOznameni $druh, KanalOznameni $kanal): bool
    {
        if (in_array($kanal, $druh->zamceneKanaly(), true)) {
            return true;
        }

        // Marketing vypnutý pro aplikaci = nikomu nikudy, ať si nastavil cokoli.
        if ($druh === DruhOznameni::Marketing && ! NastaveniOznameni::marketing()) {
            return false;
        }

        $ulozeno = $user->relationLoaded('oznameniPredvolby')
            ? $user->oznameniPredvolby?->kanaly
            : OznameniPredvolby::query()->where('user_id', $user->getKey())->value('kanaly');

        if (is_string($ulozeno)) {
            $ulozeno = json_decode($ulozeno, true);
        }

        $hodnota = ((array) $ulozeno)[$druh->value][$kanal->value] ?? null;

        return $hodnota === null ? $druh->vychozi($kanal) : (bool) $hodnota;
    }

    /**
     * Celá tabulka pro stránku předvoleb.
     *
     * @return array<string, array<string, array{zapnuto: bool, zamceno: bool}>>
     */
    public static function matice(User $user): array
    {
        $matice = [];

        foreach (NastaveniOznameni::druhy() as $druh) {
            foreach (KanalOznameni::predvolby() as $kanal) {
                $matice[$druh->value][$kanal->value] = [
                    'zapnuto' => self::chce($user, $druh, $kanal),
                    'zamceno' => in_array($kanal, $druh->zamceneKanaly(), true),
                ];
            }
        }

        return $matice;
    }

    /**
     * Uloží předvolby z formuláře. Chybějící hodnota = vypnuto (formulář posílá
     * jen zapnuté), zamčené se neukládají. U novinek a nabídek se každá změna
     * zapíše jako udělení nebo odvolání souhlasu.
     *
     * @param  array<string, array<string, mixed>>  $odeslane  [druh => [kanal => bool]]
     */
    public static function uloz(User $user, array $odeslane, string $zdroj, ?Request $request = null): void
    {
        $nove = [];

        foreach (NastaveniOznameni::druhy() as $druh) {
            foreach (KanalOznameni::predvolby() as $kanal) {
                if (in_array($kanal, $druh->zamceneKanaly(), true)) {
                    continue;
                }

                $nove[$druh->value][$kanal->value] = filter_var($odeslane[$druh->value][$kanal->value] ?? false, FILTER_VALIDATE_BOOL);
            }
        }

        self::zapis($user, $nove, $zdroj, $request);
    }

    /** Odhlášení odkazem z e-mailu: druh ven nikudy (e-mail, push). */
    public static function odhlas(User $user, DruhOznameni $druh, string $zdroj, ?Request $request = null): void
    {
        $nove = [];

        foreach (KanalOznameni::cases() as $kanal) {
            if ($kanal->vnejsi()) {
                $nove[$druh->value][$kanal->value] = false;
            }
        }

        self::zapis($user, $nove, $zdroj, $request);
    }

    /** Znění souhlasu – uloží se k záznamu, ať jde doložit, s čím člověk souhlasil. */
    public static function textSouhlasu(DruhOznameni $druh, KanalOznameni $kanal): string
    {
        $co = match ($druh) {
            DruhOznameni::Marketing => 'nabídek, akcí a slev',
            default => 'novinek (nové funkce a změny v nabídce)',
        };
        $kudy = match ($kanal) {
            KanalOznameni::Email => 'e-mailem',
            KanalOznameni::WebPush => 'upozorněním v prohlížeči',
            KanalOznameni::MobilPush => 'upozorněním v mobilní aplikaci',
            default => 'v centru oznámení',
        };

        return 'Souhlasím se zasíláním '.$co.' od '.(ZakladniUdaje::get('nazev') ?: config('app.name')).' '.$kudy
            .'. Souhlas můžu kdykoli odvolat v předvolbách nebo odkazem v každé zprávě.';
    }

    /** @param  array<string, array<string, bool>>  $zmeny */
    private static function zapis(User $user, array $zmeny, string $zdroj, ?Request $request): void
    {
        DB::transaction(function () use ($user, $zmeny, $zdroj, $request) {
            $predvolby = OznameniPredvolby::query()->lockForUpdate()->firstOrNew(['user_id' => $user->getKey()]);
            $kanaly = (array) $predvolby->kanaly;

            foreach ($zmeny as $druhKlic => $poKanalech) {
                $druh = DruhOznameni::from($druhKlic);

                foreach ($poKanalech as $kanalKlic => $zapnuto) {
                    $kanal = KanalOznameni::from($kanalKlic);
                    $predtim = self::chce($user, $druh, $kanal);
                    $kanaly[$druhKlic][$kanalKlic] = $zapnuto;

                    if ($druh->vyzadujeSouhlas() && $kanal->vnejsi() && $predtim !== $zapnuto) {
                        OznameniSouhlas::query()->create([
                            'user_id' => $user->getKey(),
                            'druh' => $druh->value,
                            'kanal' => $kanal->value,
                            'udelen' => $zapnuto,
                            'zdroj' => $zdroj,
                            'text' => $zapnuto ? self::textSouhlasu($druh, $kanal) : null,
                            'ip_adresa' => $request?->ip(),
                            'prohlizec' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                        ]);
                    }
                }
            }

            $predvolby->kanaly = $kanaly;
            $predvolby->save();
        });

        $user->unsetRelation('oznameniPredvolby');
    }
}
