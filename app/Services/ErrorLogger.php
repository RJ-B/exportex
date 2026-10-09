<?php

namespace App\Services;

use App\Models\ErrorLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Psy\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Zapisuje výjimky do `error_logs`.
 *
 * Dvě pravidla, na kterých to celé stojí:
 *
 * 1. NIKDY nesmí vyhodit výjimku. Běží uvnitř exception handleru — chyba tady by
 *    znamenala chybu při zpracování chyby a shodila by odpověď (nebo zacyklila).
 *    Proto je všechno v try/catch a zápis se v nejhorším tiše zahodí.
 *
 * 2. Agreguje podle otisku. Bez toho by jedna chyba v cyklu založila tisíce řádků
 *    a přebila v UI všechno ostatní.
 *
 * Alarm mailem tu schválně NENÍ (na rozdíl od trenéra a pokladny): provoz hlídá
 * portál Sim&Ren. Chyby čte z laravel.log (`simren:zdravi`), u produkce klienta
 * z nich CRM založí závadu. Tahle tabulka je na dohledání a odškrtnutí v administraci.
 */
class ErrorLogger
{
    /**
     * Výjimky, které nejsou chybou aplikace, jen běžný provoz: nevyplněný formulář,
     * vypršelá session, nepřihlášený uživatel, rate limit.
     *
     * Většinu z nich odfiltruje Laravel sám ve svém `$internalDontReport` ještě před
     * reportable callbackem. Seznam se tu drží schválně: v `bootstrap/app.php` se
     * `HttpException` z toho filtru VYJÍMÁ (jinak by `abort(500)` nikdy nedorazil),
     * takže se sem HTTP chyby dostanou a je potřeba je rozlišit samostatně.
     */
    private const IGNORED = [
        ValidationException::class,
        AuthenticationException::class,
        TokenMismatchException::class,
        ModelNotFoundException::class,
        ThrottleRequestsException::class,
        // Překlep v `php artisan tinker` není chyba aplikace, ale člověka u konzole.
        // Bez tohohle se do /admin/chyby zapisovaly syntaktické chyby z REPL a chodil
        // za ně alarm. Chytá se celý Psy\ namespace, ne jen parse
        // error — všechno pod ním pochází ze skořápky tinkeru, ne z aplikace.
        Exception::class,
    ];

    /**
     * Ruční nahlášení odchycené výjimky.
     *
     * Hook v bootstrap/app.php vidí jen výjimky, které probublají ven. Co aplikace
     * odchytí v try/catch (selhání faktury po zaplacení, neodeslaná notifikace),
     * je taky chyba – a bez tohohle by v Logy → Chyby nebyla. Volat všude, kde
     * odchycená chyba znamená škodu.
     *
     * `$context` se přilepí k hlášce, aby šlo dohledat, čeho se to týkalo
     * (např. order_id) — na otisk to nemá vliv, čísla se normalizují.
     */
    public function capture(Throwable $e, array $context = []): void
    {
        $this->report($e, $context);
    }

    public function report(Throwable $e, array $context = []): void
    {
        try {
            if ($this->shouldIgnore($e)) {
                return;
            }

            $fingerprint = $this->fingerprint($e);
            $now = now();
            $message = $e->getMessage();
            $context = [...$this->livewireContext(), ...$context];

            if ($context) {
                $message .= ' | '.json_encode($context, JSON_UNESCAPED_UNICODE);
            }

            // firstOrCreate, ne select+create: mezi dotazem a zápisem se vejde souběžný
            // request se stejnou chybou a druhý insert by spadl na unique indexu.
            // Chyba by tak zmizela — a to zrovna u chyb, které chodí v návalu (výpadek
            // brány, spadlé spojení do DB), tedy tam, kde je log nejvíc potřeba.
            // Laravel si kolizi unique klíče uvnitř ošetří a vrátí existující řádek.
            //
            // Delší hodnoty se ořezávají: `exception`, `file` a `url` jsou VARCHAR(255)
            // a ve strict módu by přetečení vyhodilo výjimku, kterou by spolkl catch níž
            // → chyba by se opět ztratila. Vendor cesty přes 255 znaků nejsou vzácné.
            $log = ErrorLog::firstOrCreate(
                ['fingerprint' => $fingerprint],
                [
                    'level' => 'error',
                    'exception' => mb_substr(get_class($e), 0, 255),
                    'message' => mb_substr($message, 0, 2000),
                    'file' => mb_substr($e->getFile(), 0, 255),
                    'line' => $e->getLine(),
                    'occurrences' => 0,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ],
            );

            // Atomický inkrement i s kontextem posledního výskytu jedním dotazem —
            // `occurrences + 1` v PHP by při souběhu ztrácelo výskyty.
            $log->increment('occurrences', 1, [
                'last_seen_at' => $now,
                // Zpráva se přepisuje i při opakování, ne jen při založení řádku.
                // Nese totiž kontext (u Livewire které tlačítko to bylo), a ten
                // se u druhého výskytu může lišit od prvního — a právě ten
                // poslední člověk hledá, když se dívá do Logů.
                'message' => mb_substr($message, 0, 2000),
                'url' => mb_substr((string) $this->url(), 0, 255) ?: null,
                'method' => $this->console() ? 'CLI' : request()?->method(),
                'user_id' => auth()->id(),
                // V konzoli žádná IP není. `request()->ip()` ji přesto vrátí (127.0.0.1),
                // protože Laravel i pro artisan sestaví Request ze serverových proměnných.
                'ip' => $this->console() ? null : request()?->ip(),
                'trace' => $this->trace($e),
                // Chyba se vrátila → znovu ji vytáhneme mezi nevyřešené.
                'resolved_at' => null,
            ]);
        } catch (Throwable) {
            // Schválně naprázdno: logování chyb nesmí být zdrojem chyb. Výjimka
            // stejně doputuje do laravel.log standardní cestou.
        }
    }

    private function shouldIgnore(Throwable $e): bool
    {
        foreach (self::IGNORED as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        // HTTP výjimky pod 500 (404, 403, 419…) jsou očekávaný provoz, ne chyba kódu.
        // 503 v režimu údržby (artisan down – převod, nasazení) je záměr, ne chyba.
        return $e instanceof HttpExceptionInterface
            && ($e->getStatusCode() < 500 || ($e->getStatusCode() === 503 && app()->isDownForMaintenance()));
    }

    /**
     * Otisk pro agregaci. Z hlášky se vyhazují proměnlivé části — jinak by
     * „Order #12 not found" a „Order #13 not found" byly dvě různé chyby.
     *
     * POŘADÍ ALTERNATIV JE ZÁSADNÍ. Dřív tu bylo `\d+|[0-9a-f]{8}-…` a `\d+` jako
     * první rozsekalo UUID po číslicích dřív, než se UUID větev vůbec zkusila:
     * tři výskyty téže chyby s různým UUID daly tři různé otisky, agregace nefungovala
     * a alarm chodil za každý výskyt. UUID i e-mail proto musí být PŘED `\d+`.
     */
    private function fingerprint(Throwable $e): string
    {
        $normalized = preg_replace(
            [
                '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', // UUID
                '/[\w.+-]+@[\w-]+\.[\w.-]+/',                                       // e-mail (i kvůli PII v hlášce)
                '/\d+/',                                                            // čísla až nakonec
            ],
            ['UUID', 'EMAIL', 'N'],
            $e->getMessage() ?: '',
        );

        return hash('sha256', implode('|', [
            get_class($e),
            $e->getFile(),
            $e->getLine(),
            mb_substr($normalized, 0, 200),
        ]));
    }

    /**
     * Co prohlížeč poslal do Livewire — které vlastnosti se měly nastavit
     * a které metody zavolat.
     *
     * Bez tohohle se u chyb z `/livewire/update` nedá zjistit, které tlačítko
     * je způsobilo: v logu zůstala jen adresa, která je pro celou aplikaci
     * jedna („Public property [$] not found" tak tři týdny visel bez viníka).
     *
     * ZÁMĚRNĚ jen názvy cest a metod, žádné hodnoty ani argumenty: v payloadu
     * jezdí jména, čísla a částky, a ty do logu chyb nepatří.
     *
     * @return array<string, string>
     */
    private function livewireContext(): array
    {
        try {
            // Rozhoduje adresa, ne `runningInConsole()`: v artisanu Laravel
            // sestaví Request ze serverových proměnných a ten na livewire/update
            // nikdy nesedí, takže konzoli není potřeba řešit zvlášť — a hlavně
            // pod PHPUnitem platí „běžím v konzoli" i pro odchycený požadavek,
            // takže by to nešlo pokrýt testem.
            if (! request()?->is('livewire/update')) {
                return [];
            }

            $cesty = [];
            $metody = [];

            // Livewire posílá pole komponent – na jedné stránce jich je víc
            // a spadnout může kterákoli.
            foreach ((array) request()->input('components', []) as $komponenta) {
                foreach (array_keys((array) ($komponenta['updates'] ?? [])) as $cesta) {
                    $cesty[] = (string) $cesta;
                }

                foreach ((array) ($komponenta['calls'] ?? []) as $volani) {
                    $metody[] = (string) ($volani['method'] ?? '?');
                }
            }

            return array_filter([
                'wire_updates' => mb_substr(implode(', ', $cesty), 0, 300),
                'wire_calls' => mb_substr(implode(', ', $metody), 0, 300),
            ]);
        } catch (Throwable) {
            // Kontext je bonus. Když se ho nepodaří sebrat, chyba se musí
            // zapsat i tak — jinak by sběr kontextu ukrajoval z logu chyb.
            return [];
        }
    }

    private function console(): bool
    {
        try {
            return app()->runningInConsole();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Kde chyba vznikla. U cronu a artisanu se ukládá příkaz, ne adresa.
     *
     * Laravel i pro konzoli sestaví Request ze serverových proměnných, takže
     * `request()->url()` vrátí APP_URL a `method()` „GET" – log by pak tvrdil, že se
     * chyba stala na hlavní stránce z IP 127.0.0.1, i když žádný požadavek
     * neexistoval, a poslal člověka hledat stránku, která se nikdy neotevřela.
     */
    private function url(): ?string
    {
        try {
            if ($this->console()) {
                $argv = $_SERVER['argv'] ?? [];

                return isset($argv[1]) ? 'artisan '.$argv[1] : 'artisan';
            }

            // Bez query stringu: mohou v něm být osobní údaje nebo tokeny.
            return request()?->url();
        } catch (Throwable) {
            return null;
        }
    }

    private function trace(Throwable $e): string
    {
        return mb_substr($e->getTraceAsString(), 0, 5000);
    }
}
