<?php

namespace App\Support\Oznameni;

use App\Enums\KanalOznameni;
use App\Enums\StavOznameni;
use App\Jobs\PoslatEmailyOznameni;
use App\Jobs\RozeslatOznameni;
use App\Mail\OznameniMail;
use App\Models\MailLog;
use App\Models\Oznameni;
use App\Models\OznameniDoruceni;
use App\Models\OznameniPrijemce;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Jediná cesta, jak oznámení odejde – z administrace, z plánovače i z kódu
 * (Oznam). Stav oznámení mění jen tahle třída.
 *
 *  1. odeslat(): kontrola (chyby()), naplánované jen přepne stav,
 *  2. rozeslat(): příjemci (každý jednou) + řádky doručení – malé oznámení
 *     hned, velké ve frontě (RozeslatOznameni). Centrum je tím doručené,
 *  3. e-maily po dávkách ve frontě (PoslatEmailyOznameni, limit za minutu),
 *     každý zvlášť přes Poštu (Mail::), výsledek doručení doplní Pošta
 *     do Logy → E-maily (mail_log_id).
 *
 * Opakovatelné: příjemci a doručení mají unikátní klíče (insertOrIgnore),
 * e-mail jde jen z doručení ve stavu „čeká“.
 */
class Odeslani
{
    /** Odesílá se déle – asi spadla úloha; plánovač rozeslání zopakuje. */
    public const ZASEKNUTE_PO_MINUTACH = 10;

    /**
     * Proč oznámení nejde odeslat (prázdné = jde).
     *
     * @return list<string>
     */
    public function chyby(Oznameni $oznameni, bool $zAdministrace = true): array
    {
        $chyby = [];
        $druh = $oznameni->druh;
        $kanaly = $oznameni->kanalyEnum();

        if (blank($oznameni->titulek)) {
            $chyby[] = 'Chybí titulek.';
        }

        if (! $druh || ! in_array($druh, $zAdministrace ? NastaveniOznameni::druhyZAdministrace() : NastaveniOznameni::druhy(), true)) {
            $chyby[] = 'Tenhle druh oznámení se v aplikaci neposílá.';
        }

        if ($kanaly === []) {
            $chyby[] = 'Vyberte, kudy oznámení půjde.';
        }

        foreach ($kanaly as $kanal) {
            if (! $kanal->dostupny()) {
                $chyby[] = $kanal->nazev().' zatím aplikace neumí.';
            }
        }

        if ($oznameni->maKanal(KanalOznameni::Pruh)) {
            if ($druh && ! $druh->smiPruh()) {
                $chyby[] = 'Pruh přes web je jen pro servisní oznámení (odstávky, výpadky).';
            }
            if ($oznameni->pruh_od && $oznameni->pruh_do && $oznameni->pruh_do->lte($oznameni->pruh_od)) {
                $chyby[] = 'Pruh musí končit až po začátku.';
            }
        }

        if ($oznameni->udalost_od && $oznameni->udalost_do && $oznameni->udalost_do->lte($oznameni->udalost_od)) {
            $chyby[] = 'Odstávka musí končit až po začátku.';
        }

        if (Cileni::prazdne((array) $oznameni->cileni)) {
            $chyby[] = 'Vyberte, komu oznámení půjde.';
        } elseif ($zAdministrace && $chyby === []) {
            $pocet = Cileni::dotaz((array) $oznameni->cileni)->count();
            $max = NastaveniOznameni::limit('max_prijemcu');

            if ($pocet === 0 && ! ($oznameni->jeProVsechny() && $oznameni->maKanal(KanalOznameni::Pruh))) {
                $chyby[] = 'Výběru neodpovídá nikdo.';
            } elseif ($pocet > $max) {
                $chyby[] = 'Jedno oznámení smí mít nejvýš '.number_format($max, 0, ',', ' ').' příjemců (vybráno '.number_format($pocet, 0, ',', ' ').'). Větší rozesílku domluvte se Sim&Ren.';
            }
        }

        return $chyby;
    }

    /**
     * Odeslat hned, nebo naplánovat (naplanovano_na v budoucnu).
     *
     * @throws OznameniNejdeOdeslat
     */
    public function odeslat(Oznameni $oznameni, ?User $kdo = null, bool $zAdministrace = true): void
    {
        if (! $oznameni->stav->upravitelne()) {
            throw new OznameniNejdeOdeslat(['Oznámení už je odeslané.']);
        }

        if ($chyby = $this->chyby($oznameni, $zAdministrace)) {
            throw new OznameniNejdeOdeslat($chyby);
        }

        $oznameni->odeslal_id = $kdo?->getKey() ?? $oznameni->odeslal_id;

        if ($oznameni->naplanovano_na?->isFuture()) {
            $oznameni->stav = StavOznameni::Naplanovano;
            $oznameni->save();

            return;
        }

        $this->spust($oznameni);
    }

    /** Naplánované zpátky do konceptu. */
    public function zrusitPlan(Oznameni $oznameni): void
    {
        if ($oznameni->stav === StavOznameni::Naplanovano) {
            $oznameni->stav = StavOznameni::Koncept;
            $oznameni->save();
        }
    }

    /** Je v plánu něco k odeslání? Jen čte – plánovač na čisté instalaci nic nezapíše. */
    public static function maPraci(): bool
    {
        return (bool) rescue(fn () => Oznameni::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('stav', StavOznameni::Naplanovano->value)->where('naplanovano_na', '<=', now()))
                ->orWhere(fn ($query) => $query->where('stav', StavOznameni::Odesila->value)->where('updated_at', '<=', now()->subMinutes(self::ZASEKNUTE_PO_MINUTACH))))
            ->exists(), false, false);
    }

    /** Plánovač (každou minutu, jen když maPraci()): naplánovaná a zaseknutá. */
    public function naplanovana(): int
    {
        $pocet = 0;

        Oznameni::query()
            ->where('stav', StavOznameni::Naplanovano->value)->where('naplanovano_na', '<=', now())
            ->orderBy('naplanovano_na')
            ->each(function (Oznameni $oznameni) use (&$pocet) {
                // Mezitím se mohlo změnit (vypnutý marketing, smazaný příjemce) – kontrola znovu.
                if ($this->chyby($oznameni, $oznameni->zdroj === 'administrace') !== []) {
                    $oznameni->stav = StavOznameni::Koncept;
                    $oznameni->save();

                    return;
                }

                $this->spust($oznameni);
                $pocet++;
            });

        Oznameni::query()
            ->where('stav', StavOznameni::Odesila->value)->where('updated_at', '<=', now()->subMinutes(self::ZASEKNUTE_PO_MINUTACH))
            ->each(function (Oznameni $oznameni) {
                $oznameni->touch();
                RozeslatOznameni::dispatch($oznameni->getKey());
            });

        return $pocet;
    }

    private function spust(Oznameni $oznameni): void
    {
        $oznameni->stav = StavOznameni::Odesila;
        $oznameni->save();

        $pocet = $this->potrebujePrijemce($oznameni) ? Cileni::dotaz((array) $oznameni->cileni)->count() : 0;

        if ($pocet <= NastaveniOznameni::limit('hned_do')) {
            $this->rozeslat($oznameni);
        } else {
            RozeslatOznameni::dispatch($oznameni->getKey());
        }
    }

    /** Příjemci a doručení; pak e-maily do fronty. Opakovatelné. */
    public function rozeslat(Oznameni $oznameni): void
    {
        $oznameni->refresh();

        if ($oznameni->stav !== StavOznameni::Odesila) {
            return;
        }

        if ($this->potrebujePrijemce($oznameni)) {
            $druh = $oznameni->druh;
            $centrum = $oznameni->maKanal(KanalOznameni::Centrum);
            $email = $oznameni->maKanal(KanalOznameni::Email);

            Cileni::dotaz((array) $oznameni->cileni)->with('oznameniPredvolby')
                ->chunkById(500, function ($uzivatele) use ($oznameni, $druh, $centrum, $email) {
                    $ted = now();

                    OznameniPrijemce::query()->insertOrIgnore($uzivatele->map(fn (User $user) => [
                        'oznameni_id' => $oznameni->getKey(),
                        'user_id' => $user->getKey(),
                        'v_centru' => $centrum && Predvolby::chce($user, $druh, KanalOznameni::Centrum),
                        'created_at' => $ted,
                        'updated_at' => $ted,
                    ])->all());

                    if (! $email) {
                        return;
                    }

                    $prijemci = OznameniPrijemce::query()->where('oznameni_id', $oznameni->getKey())
                        ->whereIn('user_id', $uzivatele->modelKeys())->pluck('id', 'user_id');

                    OznameniDoruceni::query()->insertOrIgnore($uzivatele->map(function (User $user) use ($prijemci, $druh, $ted) {
                        [$stav, $duvod] = match (true) {
                            blank($user->email) => [OznameniDoruceni::PRESKOCENO, 'Bez e-mailu.'],
                            Predvolby::chce($user, $druh, KanalOznameni::Email) => [OznameniDoruceni::CEKA, null],
                            $druh->vyzadujeSouhlas() => [OznameniDoruceni::PRESKOCENO, 'Bez souhlasu.'],
                            default => [OznameniDoruceni::PRESKOCENO, 'Vypnuto v předvolbách.'],
                        };

                        return [
                            'prijemce_id' => $prijemci[$user->getKey()],
                            'kanal' => KanalOznameni::Email->value,
                            'stav' => $stav,
                            'duvod' => $duvod,
                            'created_at' => $ted,
                            'updated_at' => $ted,
                        ];
                    })->all());
                });
        }

        $oznameni->forceFill([
            'stav' => StavOznameni::Odeslano,
            'odeslano_at' => now(),
            'pocet_prijemcu' => $this->potrebujePrijemce($oznameni) ? $oznameni->prijemci()->count() : null,
        ])->save();

        Pruh::zapomen();

        if ($oznameni->maKanal(KanalOznameni::Email)) {
            $this->emailyDoFronty($oznameni);
        }
    }

    /** Čekající e-maily po dávkách – každá dávka o minutu později (limit za minutu). */
    private function emailyDoFronty(Oznameni $oznameni): void
    {
        $davka = max(1, NastaveniOznameni::limit('emailu_za_minutu'));
        $minuta = 0;

        OznameniDoruceni::query()
            ->where('kanal', KanalOznameni::Email->value)->where('stav', OznameniDoruceni::CEKA)
            ->whereHas('prijemce', fn ($query) => $query->where('oznameni_id', $oznameni->getKey()))
            ->select('id')
            ->chunkById($davka, function ($radky) use (&$minuta) {
                $uloha = PoslatEmailyOznameni::dispatch($radky->modelKeys());

                if ($minuta > 0) {
                    $uloha->delay(now()->addMinutes($minuta));
                }

                $minuta++;
            });
    }

    /** Jeden e-mail (z úlohy). Jen z doručení „čeká“ – opakování úlohy nepošle dvakrát. */
    public function poslatEmail(OznameniDoruceni $doruceni): void
    {
        if ($doruceni->stav !== OznameniDoruceni::CEKA) {
            return;
        }

        $prijemce = $doruceni->prijemce()->with(['oznameni', 'user.oznameniPredvolby'])->first();
        $user = $prijemce?->user;

        if (! $user || blank($user->email)) {
            $doruceni->update(['stav' => OznameniDoruceni::PRESKOCENO, 'duvod' => 'Bez e-mailu.']);

            return;
        }

        // Souhlas mohl být mezitím odvolán (dávky jdou po minutách).
        if (! Predvolby::chce($user, $prijemce->oznameni->druh, KanalOznameni::Email)) {
            $doruceni->update(['stav' => OznameniDoruceni::PRESKOCENO, 'duvod' => 'Mezitím vypnuto.']);

            return;
        }

        $predtim = (int) MailLog::query()->max('id');

        try {
            Mail::to($user->email, $user->getFilamentName())->send(new OznameniMail($prijemce));

            $doruceni->update([
                'stav' => OznameniDoruceni::ODESLANO,
                'odeslano_at' => now(),
                'mail_log_id' => MailLog::query()->where('id', '>', $predtim)->where('to_email', $user->email)->value('id'),
            ]);
        } catch (Throwable $e) {
            report($e);
            $doruceni->update(['stav' => OznameniDoruceni::CHYBA, 'duvod' => mb_substr($e->getMessage(), 0, 255)]);
        }
    }

    /** Zkouška: e-mail jen přihlášenému, nikam se nezapíše jako doručení. */
    public function testSobe(Oznameni $oznameni, User $ja): void
    {
        $prijemce = (new OznameniPrijemce)->setRelation('oznameni', $oznameni)->setRelation('user', $ja);

        Mail::to($ja->email, $ja->getFilamentName())->send(new OznameniMail($prijemce, zkouska: true));
    }

    /** Potřebuje řádky příjemců? Pruh pro všechny ne – vidí ho každý. */
    private function potrebujePrijemce(Oznameni $oznameni): bool
    {
        return $oznameni->maKanal(KanalOznameni::Centrum)
            || $oznameni->maKanal(KanalOznameni::Email)
            || ($oznameni->maKanal(KanalOznameni::Pruh) && ! $oznameni->jeProVsechny());
    }
}
