<?php

namespace App\Platby;

use App\Platby\Brany\Comgate;
use App\Platby\Brany\MoOne;
use App\Platby\Brany\StavZBrany;
use App\Platby\Mail\PlatbaPrijata;
use App\Platby\Mail\PlatbaVracena;
use App\Platby\Udalosti\PlatbaZaplacena;
use App\Platby\Udalosti\PlatbaZmenilaStav;
use App\Services\AuditLogger;
use App\Services\ErrorLogger;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request as Pozadavek;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Platby přes bránu – jediné místo, které platbu zakládá a mění její stav.
 *
 * Pravidla:
 *  - Stav mění jen prejdi(): zámek řádku, povolený přechod (StavPlatby),
 *    záznam do historie, události až po commitu. Opakovaný webhook, návrat
 *    a plánovač se tak potkají bez následků (idempotence).
 *  - Stavu z webhooku ani z návratu se nevěří – vždy overStav() dotazem na bránu.
 *  - Jeden předmět (klíč) = nejvýš jedna rozpracovaná platba a žádná druhá
 *    zaplacená (zámek při založení, UzZaplaceno). Kdyby přesto přišly dvě
 *    zaplacené, zapíše se to do Chyb – jedna se vrací.
 *  - Pomalé I/O (brána, e-mail) nikdy uvnitř transakce.
 *  - Chyby brány do Logy → Chyby, platby a změny stavu do Aktivity.
 */
class Platby
{
    /** Kč (i „1 234,50“) → haléře, bez plovoucí čárky. */
    public static function halere(string|int|float $kc): int
    {
        if (is_int($kc)) {
            return $kc * 100;
        }

        $text = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $kc));

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $text)) {
            throw new InvalidArgumentException('Částka „'.$kc.'“ není číslo v korunách.');
        }

        [$cele, $desetiny] = array_pad(explode('.', $text), 2, '0');

        return (int) $cele * 100 + (str_starts_with($cele, '-') ? -1 : 1) * (int) str_pad($desetiny, 2, '0');
    }

    /**
     * Založí platbu u aktivní brány a vrátí ji (presmerovani_url = kam poslat zákazníka).
     *
     * @throws PlatbyNedostupne platby teď nejdou (údaje, režim)
     * @throws UzZaplaceno předmět už je zaplacený
     * @throws ChybaBrany brána platbu nepřijala (zapsáno do Chyb, platba ve stavu Chyba)
     */
    public function zaloz(PozadavekPlatby $pozadavek): Platba
    {
        $brana = NastaveniPlateb::aktivni();
        $klic = $pozadavek->klic();

        return Cache::lock('platby:zalozeni:'.($klic ? hash('sha256', $klic) : Str::uuid()), 60)
            ->block(15, function () use ($pozadavek, $brana, $klic) {
                if ($klic && ($rozpracovana = $this->predchozi($klic, $pozadavek, $brana->kod(), $brana->rezim()))) {
                    return $rozpracovana;
                }

                $platba = new Platba([
                    'popis' => Str::limit($pozadavek->popis, 190, ''),
                    'reference' => $pozadavek->reference,
                    'email' => $pozadavek->email,
                    'jmeno' => $pozadavek->jmeno,
                    'prijmeni' => $pozadavek->prijmeni,
                    'navrat_url' => $pozadavek->navratUrl,
                ]);
                $platba->forceFill([
                    'verejne_id' => (string) Str::uuid(),
                    'klic' => $klic,
                    'brana' => $brana->kod(),
                    'rezim' => $brana->rezim(),
                    'stav' => StavPlatby::Zalozena,
                    'castka' => $pozadavek->castka,
                    'mena' => $pozadavek->mena,
                ]);
                $pozadavek->predmet && $platba->predmet()->associate($pozadavek->predmet);
                $platba->save();

                $this->zapisUdalost($platba, null, StavPlatby::Zalozena, 'zalozeni', 'Platba založena – '.$brana->nazev().' ('.$brana->rezim()->popis().')');
                AuditLogger::record('platba.zalozena', $platba, 'Platba '.$this->oznaceni($platba).' založena – '.$platba->castkaKc().' ('.$brana->nazev().', '.mb_strtolower($brana->rezim()->popis()).')');

                try {
                    $zalozena = $brana->zaloz($platba);
                } catch (Throwable $e) {
                    $platba->forceFill(['chyba' => mb_substr($e->getMessage(), 0, 500)])->save();
                    $this->prejdi($platba, StavPlatby::Chyba, 'zalozeni', $e->getMessage());
                    app(ErrorLogger::class)->capture($e, ['platba' => $platba->verejne_id, 'brana' => $brana->kod()]);

                    throw $e instanceof ChybaBrany ? $e : new ChybaBrany('Platbu se nepodařilo založit.', previous: $e);
                }

                $platba->forceFill(['externi_id' => $zalozena->externiId, 'presmerovani_url' => $zalozena->presmerovani])->save();
                $this->prejdi($platba, StavPlatby::Ceka, 'zalozeni', 'Brána platbu přijala ('.$zalozena->externiId.')', $zalozena->data);

                return $platba->refresh();
            });
    }

    /**
     * Nová platba se stejným obsahem po zamítnuté, zrušené nebo chybné
     * (Zkusit znovu na stránce výsledku, odkaz k zaplacení).
     */
    public function znovu(Platba $platba): Platba
    {
        if (! in_array($platba->stav, [StavPlatby::Zamitnuta, StavPlatby::Zrusena, StavPlatby::Chyba], true)) {
            throw new LogicException('Znovu jde zaplatit jen neúspěšnou platbu.');
        }

        return $this->zaloz(new PozadavekPlatby(
            castka: $platba->castka,
            popis: $platba->popis,
            email: (string) $platba->email,
            jmeno: $platba->jmeno,
            prijmeni: $platba->prijmeni,
            predmet: $platba->predmet,
            reference: $platba->reference,
            klic: $platba->klic,
            navratUrl: $platba->navrat_url,
            mena: $platba->mena,
        ));
    }

    /**
     * Zeptá se brány na stav a podle odpovědi platbu posune. Volá se po návratu
     * zákazníka, po webhooku, plánovačem a z administrace.
     *
     * @param  array  $podnet  co tvrdil webhook / návrat (jen do historie)
     *
     * @throws ChybaBrany|PlatbyNedostupne
     */
    public function overStav(Platba $platba, string $zdroj = 'overeni', array $podnet = []): Platba
    {
        if (blank($platba->externi_id)) {
            return $platba;
        }

        try {
            $stav = NastaveniPlateb::proPlatbu($platba)->stav($platba);
        } catch (Throwable $e) {
            app(ErrorLogger::class)->capture($e, ['platba' => $platba->verejne_id, 'zdroj' => $zdroj]);
            $this->zapisUdalost($platba, $platba->stav, $platba->stav, $zdroj, 'Ověření stavu selhalo: '.$e->getMessage(), $podnet);

            throw $e;
        }

        $platba->forceFill(['overeno_v' => now()])->save();
        $data = array_filter(['brana' => $stav->data, 'podnet' => $podnet]);

        // Zaplaceno, ale jiná částka než naše = nepřijmout, ať rozhodne člověk.
        if ($stav->stav === StavPlatby::Zaplacena && $stav->castka !== null && $stav->castka !== $platba->castka) {
            $zprava = 'Brána hlásí zaplaceno '.Platba::kc($stav->castka, $platba->mena).', platba je na '.$platba->castkaKc().' – nepřijato.';
            $this->zapisUdalost($platba, $platba->stav, $platba->stav, $zdroj, $zprava, $data);
            app(ErrorLogger::class)->capture(new RuntimeException('Platba '.$platba->verejne_id.': '.$zprava), ['platba' => $platba->verejne_id]);

            return $platba->refresh();
        }

        // Brána o vrácení neví (Comgate drží PAID) – vrácená zůstává vrácená.
        if ($stav->stav === StavPlatby::Zaplacena && $platba->stav->zaplaceno()) {
            $stav = new StavZBrany($platba->stav, $stav->castka, $stav->metoda, $stav->poznamka, $stav->data);
        }

        $zmeneno = $this->prejdi($platba, $stav->stav, $zdroj, $stav->poznamka, $data, array_filter(['metoda' => $stav->metoda]));

        // Bez změny se zapisuje jen to, o co se někdo zajímal (webhook, návrat, člověk) –
        // plánovač by historii zahltil.
        if (! $zmeneno && $zdroj !== 'planovac') {
            $this->zapisUdalost($platba, $platba->stav, $platba->stav, $zdroj, $stav->stav === $platba->stav
                ? 'Stav beze změny: '.$platba->stav->popis()
                : 'Brána hlásí „'.$stav->stav->popis().'“ – platba zůstává „'.$platba->stav->popis().'“.', $data);
        }

        return $platba->refresh();
    }

    /** Platba z webhooku brány (jen k vyhledání – stav se ověří dotazem). */
    public function najdiZWebhooku(string $brana, Request $request): ?Platba
    {
        $udaje = match ($brana) {
            'comgate' => Comgate::zWebhooku($request),
            'moone' => MoOne::zWebhooku($request),
            default => ['externi_id' => null, 'verejne_id' => null],
        };

        if (blank($udaje['externi_id']) && blank($udaje['verejne_id'])) {
            return null;
        }

        $nalezene = Platba::query()
            ->where('brana', $brana)
            ->where(fn ($q) => $q
                ->when($udaje['externi_id'], fn ($q, $id) => $q->orWhere('externi_id', $id))
                ->when($udaje['verejne_id'] && Str::isUuid($udaje['verejne_id']), fn ($q) => $q->orWhere('verejne_id', $udaje['verejne_id'])))
            ->get();

        // Stejné číslo u testovací i ostré brány: rozhodne naše verejne_id (Mo.one externalID).
        return $nalezene->firstWhere('verejne_id', $udaje['verejne_id']) ?? ($nalezene->count() === 1 ? $nalezene->first() : null);
    }

    /**
     * Zruší nezaplacenou platbu u brány i u nás. Když ji brána mezitím
     * zaplatila, zůstane zaplacená (overStav) a vyhodí se výjimka.
     */
    public function zrus(Platba $platba, string $zdroj = 'administrace', ?string $duvod = null): void
    {
        if (! $platba->stav->otevrena()) {
            throw new LogicException('Zrušit jde jen platbu, která čeká na zaplacení.');
        }

        if (filled($platba->externi_id)) {
            $brana = NastaveniPlateb::proPlatbu($platba);

            try {
                $brana->zrus($platba);
            } catch (ChybaBrany $e) {
                // Možná už je zaplacená nebo zrušená – rozhodne stav.
                $this->overStav($platba, $zdroj);

                if (! $platba->refresh()->stav->otevrena()) {
                    if ($platba->stav->zaplaceno()) {
                        throw new LogicException('Platbu už zákazník zaplatil – zrušit nejde, jen vrátit.');
                    }

                    return;
                }

                app(ErrorLogger::class)->capture($e, ['platba' => $platba->verejne_id]);

                throw $e;
            }
        }

        $this->prejdi($platba, StavPlatby::Zrusena, $zdroj, $duvod ? 'Zrušeno: '.$duvod : 'Zrušeno');
    }

    /**
     * Vrátí zákazníkovi peníze (celé nebo část), kde to brána umí.
     *
     * @param  int  $castka  haléře
     */
    public function vrat(Platba $platba, int $castka, ?string $duvod = null): void
    {
        Cache::lock('platby:vraceni:'.$platba->id, 60)->block(15, function () use ($platba, $castka, $duvod) {
            $platba->refresh();

            if (! in_array($platba->stav, [StavPlatby::Zaplacena, StavPlatby::CastecneVracena], true)) {
                throw new LogicException('Vrátit jde jen zaplacenou platbu.');
            }

            if ($castka < 1 || $castka > $platba->zbyvaVratit()) {
                throw new InvalidArgumentException('Vrátit jde 0,01 až '.Platba::kc($platba->zbyvaVratit(), $platba->mena).'.');
            }

            $brana = NastaveniPlateb::proPlatbu($platba);

            if (! $brana->umiVratit()) {
                throw new LogicException($brana->nazev().' vrácení přes API neumí – peníze vrať v aplikaci brány.');
            }

            try {
                $brana->vrat($platba, $castka);
            } catch (Throwable $e) {
                app(ErrorLogger::class)->capture($e, ['platba' => $platba->verejne_id, 'vraceni' => $castka]);
                $this->zapisUdalost($platba, $platba->stav, $platba->stav, 'administrace', 'Vrácení '.Platba::kc($castka, $platba->mena).' selhalo: '.$e->getMessage());

                throw $e;
            }

            $vraceno = $platba->vraceno + $castka;
            $novy = $vraceno >= $platba->castka ? StavPlatby::Vracena : StavPlatby::CastecneVracena;
            $poznamka = 'Vráceno '.Platba::kc($castka, $platba->mena).($duvod ? ' – '.$duvod : '');

            if ($novy === $platba->stav) {
                // Další částečné vrácení – stav stejný, mění se jen částka.
                DB::transaction(function () use ($platba, $vraceno, $poznamka) {
                    Platba::query()->whereKey($platba->id)->lockForUpdate()->first()?->forceFill(['vraceno' => $vraceno])->save();
                    $this->zapisUdalost($platba, $platba->stav, $platba->stav, 'administrace', $poznamka);
                });
                $platba->refresh();
            } else {
                $this->prejdi($platba, $novy, 'administrace', $poznamka, [], ['vraceno' => $vraceno]);
            }

            AuditLogger::record('platba.vracena', $platba, 'Platba '.$this->oznaceni($platba).': '.$poznamka, new: ['vraceno' => $vraceno]);
            $this->posli($platba, new PlatbaVracena($platba, $castka));
        });
    }

    /**
     * Změna stavu – jediné místo, kde se stav mění. Zámek řádku, kontrola
     * přechodu, historie; události, Aktivita a e-mail až po commitu.
     *
     * @return bool změnilo se něco?
     */
    public function prejdi(Platba $platba, StavPlatby $novy, string $zdroj, ?string $poznamka = null, array $data = [], array $zmeny = []): bool
    {
        $puvodni = null;

        $zmeneno = DB::transaction(function () use ($platba, $novy, $zdroj, $poznamka, $data, $zmeny, &$puvodni) {
            $zamcena = Platba::query()->whereKey($platba->id)->lockForUpdate()->firstOrFail();
            $puvodni = $zamcena->stav;

            if (! $puvodni->muzePrejitNa($novy)) {
                return false;
            }

            $zamcena->forceFill([
                ...$zmeny,
                'stav' => $novy,
                ...($novy === StavPlatby::Zaplacena && ! $zamcena->zaplaceno_v ? ['zaplaceno_v' => now()] : []),
            ])->save();

            $this->zapisUdalost($zamcena, $puvodni, $novy, $zdroj, $poznamka, $data);

            return true;
        });

        $platba->refresh();

        if ($zmeneno) {
            $this->poZmene($platba, $puvodni, $zdroj);
        }

        return $zmeneno;
    }

    /** Je co ověřovat? (plánovač – jen čte) */
    public static function maCekajici(): bool
    {
        return rescue(fn () => self::cekajici()->exists(), false, false);
    }

    /**
     * Pojistka za webhook: zeptá se brány na čekající platby. Zaseknuté
     * založení (brána neodpověděla, proces spadl) po hodině označí jako Chybu.
     *
     * @return int kolik plateb se ověřilo
     */
    public function overCekajici(): int
    {
        Platba::query()
            ->where('stav', StavPlatby::Zalozena)
            ->whereNull('externi_id')
            ->where('created_at', '<', now()->subHour())
            ->each(fn (Platba $p) => $this->prejdi($p, StavPlatby::Chyba, 'planovac', 'Založení u brány se nedokončilo.'));

        $pocet = 0;

        self::cekajici()->limit(50)->get()->each(function (Platba $platba) use (&$pocet) {
            try {
                $this->overStav($platba, 'planovac');
                $pocet++;
            } catch (Throwable) {
                // Zapsáno v overStav (Chyby + historie); další platba.
            }
        });

        return $pocet;
    }

    private static function cekajici()
    {
        return Platba::query()
            ->whereIn('stav', [StavPlatby::Ceka, StavPlatby::Zalozena])
            ->whereNotNull('externi_id')
            ->where('created_at', '<=', now()->subMinutes((int) config('platby.overovat_po', 2)))
            ->where('created_at', '>=', now()->subHours((int) config('platby.overovat_nejdele', 48)))
            ->where(fn ($q) => $q->whereNull('overeno_v')->orWhere('overeno_v', '<=', now()->subMinutes(5)));
    }

    /**
     * Rozpracovaná platba stejného předmětu: stejná částka, brána a režim
     * a mladší 30 minut = použije se znovu (dvojklik, návrat zpět). Jinak se
     * ověří a zruší. Zaplacená = UzZaplaceno.
     */
    private function predchozi(string $klic, PozadavekPlatby $pozadavek, string $brana, Rezim $rezim): ?Platba
    {
        $predchozi = Platba::query()->where('klic', $klic)->orderByDesc('id')->get();

        if ($zaplacena = $predchozi->first(fn (Platba $p) => $p->stav->zaplaceno())) {
            throw new UzZaplaceno($zaplacena);
        }

        foreach ($predchozi->filter(fn (Platba $p) => $p->stav->otevrena()) as $platba) {
            if ($platba->stav === StavPlatby::Ceka && $platba->castka === $pozadavek->castka && $platba->brana === $brana
                && $platba->rezim === $rezim && filled($platba->presmerovani_url) && $platba->created_at->gt(now()->subMinutes(30))) {
                $this->zapisUdalost($platba, $platba->stav, $platba->stav, 'zalozeni', 'Znovu použita rozpracovaná platba (opakované odeslání).');

                return $platba;
            }

            // Starší nebo jiná rozpracovaná: nejdřív zjistit, jestli ji zákazník mezitím nezaplatil.
            try {
                $this->overStav($platba, 'zalozeni');
            } catch (Throwable) {
                // Neověřená zůstane čekat (plánovač); kdyby ji zaplatil, Chyby ohlásí dvojí platbu.
            }

            if ($platba->refresh()->stav->zaplaceno()) {
                throw new UzZaplaceno($platba);
            }

            if ($platba->stav->otevrena()) {
                try {
                    $this->zrus($platba, 'zalozeni', 'nahrazena novou platbou');
                } catch (Throwable) {
                    // Zrušit nejde (brána nedostupná) – plánovač ji dořeší.
                }

                if ($platba->refresh()->stav->zaplaceno()) {
                    throw new UzZaplaceno($platba);
                }
            }
        }

        return null;
    }

    private function poZmene(Platba $platba, ?StavPlatby $puvodni, string $zdroj): void
    {
        event(new PlatbaZmenilaStav($platba, $puvodni));

        if ($platba->stav === StavPlatby::Zaplacena && ! $puvodni?->zaplaceno()) {
            AuditLogger::record('platba.zaplacena', $platba, 'Platba '.$this->oznaceni($platba).' zaplacena – '.$platba->castkaKc()
                .' ('.$platba->nazevBrany().', '.mb_strtolower($platba->rezim->popis()).')', new: [
                    'castka' => $platba->castka, 'brana' => $platba->brana, 'rezim' => $platba->rezim->value, 'externi_id' => $platba->externi_id, 'zdroj' => $zdroj,
                ]);

            if (in_array($puvodni, [StavPlatby::Zrusena, StavPlatby::Zamitnuta], true)) {
                app(ErrorLogger::class)->capture(new RuntimeException('Platba '.$this->oznaceni($platba).' byla '.mb_strtolower($puvodni->popis()).', ale brána ji potvrdila jako zaplacenou – peníze přišly, zkontroluj objednávku.'), ['platba' => $platba->verejne_id]);
            }

            if ($platba->klic && Platba::query()->where('klic', $platba->klic)->whereKeyNot($platba->id)
                ->whereIn('stav', [StavPlatby::Zaplacena, StavPlatby::CastecneVracena])->exists()) {
                app(ErrorLogger::class)->capture(new RuntimeException('Dvojí zaplacení „'.$platba->klic.'“ – vrať jednu z plateb (Platby v administraci).'), ['platba' => $platba->verejne_id]);
            }

            event(new PlatbaZaplacena($platba));
            $this->posli($platba, new PlatbaPrijata($platba));

            return;
        }

        if (! in_array($platba->stav, [StavPlatby::Ceka, StavPlatby::Vracena, StavPlatby::CastecneVracena], true)) {
            AuditLogger::record('platba.stav', $platba, 'Platba '.$this->oznaceni($platba).': '.($puvodni?->popis() ?? '–').' → '.$platba->stav->popis(), $puvodni ? ['stav' => $puvodni->value] : null, ['stav' => $platba->stav->value, 'zdroj' => $zdroj]);
        }
    }

    private function zapisUdalost(Platba $platba, ?StavPlatby $z, ?StavPlatby $na, string $zdroj, ?string $poznamka = null, array $data = []): void
    {
        PlatbaUdalost::query()->create([
            'platba_id' => $platba->id,
            'stav_z' => $z,
            'stav_na' => $na,
            'zdroj' => $zdroj,
            'poznamka' => $poznamka ? mb_substr($poznamka, 0, 500) : null,
            'data' => $data ?: null,
            'user_id' => Auth::id(),
            'ip_adresa' => app()->runningInConsole() ? null : Pozadavek::ip(),
            'created_at' => now(),
        ]);
    }

    private function posli(Platba $platba, Mailable $mail): void
    {
        if (! config('platby.email_zakaznikovi') || blank($platba->email)) {
            return;
        }

        try {
            Mail::to($platba->email, $platba->celeJmeno() ?: null)->send($mail);
        } catch (Throwable $e) {
            app(ErrorLogger::class)->capture($e, ['platba' => $platba->verejne_id, 'kdy' => 'e-mail zákazníkovi']);
        }
    }

    private function oznaceni(Platba $platba): string
    {
        return $platba->reference ?: Str::limit($platba->popis, 40);
    }
}
