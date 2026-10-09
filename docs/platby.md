# Platby – volitelný doplněk šablony

Platební brána pro e-shop, rezervace, objednávky nebo odkaz k zaplacení. **Ve výchozím stavu
vypnutá** – projekt bez plateb ji nemá vůbec (žádné routy, tabulky, administrace ani plánovač).

## Zapnutí

1. V CRM se doplněk volí v okně „Realizace: projekt a web“ (`.simren/sablona.yml` → `doplnky: [platby]`).
   Šablona o volbě v CRM neví – v projektu se zapíná commitem:
2. `config/sablona.php` → `'doplnky' => ['platby' => true]` (ne v `.env`, je to vlastnost projektu;
   `env('SABLONA_DOPLNEK_PLATBY')` je jen pro testy).
3. Nasazení pustí migraci `database/migrations/platby/` (načítá ji jen zapnutý doplněk).
4. Na testu i produkci musí portál do `.env` doplnit testovací bránu Sim&Ren:
   `PLATBY_TEST_MOONE_CLIENT_ID`, `PLATBY_TEST_MOONE_CLIENT_SECRET` (nikdy do repa).

Vypnutí = zpět `false`; tabulky a data zůstanou (smazat je patří do vlastní migrace).
Kód doplňku je celý v `app/Platby/`, `routes/platby.php`, `resources/views/platby/`,
`resources/views/filament/platby/`, `resources/views/mail/platby/`, `config/platby.php`.

## Test × produkce

| Prostředí | Brána | Peníze |
|---|---|---|
| lokálně (`APP_ENV=local`) a testy | **simulace** – stránka s tlačítky Zaplatit / Zamítnout / Zrušit (`PLATBY_SIMULACE=true`); s `false` testovací brána | žádné |
| testovací web (`APP_ENV=staging`, `*.test.simren.cz`) | **vždy testovací brána Sim&Ren** – Mo.one `https://api-test.znpay.tech`, údaje z `.env` | žádné |
| produkce, režim **Testovací** (výchozí) | testovací brána Sim&Ren | žádné |
| produkce, režim **Ostrý** | **brána klienta** – Comgate nebo Mo.one, údaje z administrace | ostré |

**Pojistky** (`App\Platby\NastaveniPlateb`):

- Ostrý režim jde zapnout jen na produkci a jen s vyplněnými a **ověřenými** údaji (Nastavení →
  Platební brána → *Ověřit spojení* – dotaz bez pohybu peněz: Comgate `method.json`, Mo.one token).
  Změna údajů ověření zruší (otisk údajů s `APP_KEY`). Nové údaje se s ostrým režimem neuloží –
  nejdřív Ověřit spojení.
- Když zvolený režim nejde (ostrý bez ověření, chybí testovací údaje), platby **stojí**
  (`PlatbyNedostupne`) – nikdy se tiše nepřepne jinam. Ostrý web nesmí „prodávat“ přes testovací
  bránu a test nesmí platit ostře.
- Simulace mimo `local`/`testing` neexistuje (konstruktor vyhodí výjimku, stránka 404).
- Rozpracovaná platba se dokončí tou bránou a režimem, kterým vznikla – přepnutí režimu ji nepřehodí.
- Údaje brány klienta jsou šifrované klíčem aplikace (`Crypt`), tajný klíč se do formuláře nikdy
  nevrací (jen „uloženo – vyplň jen pro změnu“) a do Aktivity jde jen „(heslo skryto)“. Po přenosu dat
  z testu do produkce (jiný `APP_KEY`) se údaje musí zadat a ověřit znovu.
- **Štítek „TESTOVACÍ PLATBY“** na stránkách platby (výsledek, simulace; vlastní stránka projektu ho vloží
  `@includeWhen(config('sablona.doplnky.platby'), 'platby.stitek')` hned za `<body>`) a v administraci
  (horní lišta, odznak u Plateb), dokud se neplatí ostře. E-maily testovacích plateb mají v předmětu `[TEST]`.

## Průchod platbou

```
projekt: Platby::zaloz(PozadavekPlatby)  →  Platba (stav Čeká, presmerovani_url)
zákazník: redirect na bránu  →  platí  →  /platby/{verejne_id}/navrat
brána: webhook /platby/webhook/{comgate|moone}
oboje: Platby::overStav() = DOTAZ NA BRÁNU (webhooku ani návratu se nevěří)
       → Platby::prejdi() (zámek řádku, povolený přechod, historie)
       → události PlatbaZmenilaStav / PlatbaZaplacena, Aktivita, e-mail zákazníkovi přes Poštu
pojistka: plánovač každou minutu, jen když něco čeká (platby čekající 2 min – 48 h, každá nejvýš po 5 min)
```

V projektu:

```php
use App\Platby\{Platby, PozadavekPlatby, UzZaplaceno, PlatbyNedostupne, ChybaBrany};

try {
    $platba = app(Platby::class)->zaloz(new PozadavekPlatby(
        castka: Platby::halere($objednavka->celkem),     // Kč → haléře (int), nikdy float
        popis: 'Objednávka '.$objednavka->cislo,
        email: $objednavka->email,
        jmeno: $objednavka->jmeno, prijmeni: $objednavka->prijmeni,
        predmet: $objednavka,                            // klíč idempotence „App\Models\Objednavka:15“
        reference: $objednavka->cislo,
        navratUrl: route('objednavka', $objednavka),     // po platbě sem s ?platba=<verejne_id>
    ));

    return redirect()->away($platba->presmerovani_url);
} catch (UzZaplaceno $e) { … } catch (PlatbyNedostupne|ChybaBrany $e) { … }
```

```php
// Posluchač – objednávka zaplacena (přijde jednou za platbu):
Event::listen(PlatbaZaplacena::class, function (PlatbaZaplacena $e) {
    if ($e->platba->testovaci()) { /* nic se nestrhlo – zboží neodesílat */ }
    $e->platba->predmet?->oznacZaplaceno($e->platba);
});
```

Odkaz k zaplacení bez e-shopu: administrace → Platby → **Nová platba** (popis, částka, zákazník,
volitelně e-mail s odkazem `/platby/{id}/zaplatit`).

## Stavy

`zalozena → ceka → zaplacena → castecne_vracena → vracena`, vedle `zamitnuta`, `zrusena`, `chyba`
(`App\Platby\StavPlatby`). Stav mění jen `Platby::prejdi()`. Zrušenou / zamítnutou platbu, kterou brána
pak potvrdí jako zaplacenou, systém přijme (peníze odešly) a zapíše do Chyb. Zaplaceno s jinou částkou,
než má platba, se nepřijme (historie + Chyby).

## Idempotence a dvojí zaplacení

- Jeden předmět (`klic`) = nejvýš jedna rozpracovaná platba: dvojklik / návrat zpět vrátí stejnou platbu
  (stejná částka, brána, režim, do 30 min). Jiná částka = stará se ověří a zruší u brány, vznikne nová.
- Zaplacený předmět další platbu nedostane (`UzZaplaceno`). Kdyby přesto přišly dvě zaplacené (zákazník
  zaplatil starou i novou), zapíše se to do Chyb – jednu vrať.
- Zámky: `Cache::lock` na klíč při založení a na platbu při vrácení, `lockForUpdate` při změně stavu.
  Opakovaný webhook, návrat i plánovač se potkají bez následků – událost `PlatbaZaplacena` a e-mail jen jednou.

## Brány

Jednotné rozhraní `App\Platby\Brany\Brana`: `zaloz`, `stav`, `zrus`, `vrat`, `overSpojeni`,
`webhookPravy`, `zWebhooku`. Nová brána = nová třída + řádek v `NastaveniPlateb`.

**Comgate** – REST API 2.0 (`https://payments.comgate.cz/v2.0`, Basic merchant:heslo, JSON, haléře):
`POST payment.json` → `transId`, `redirect`; `GET payment/transId/{id}.json` (PENDING/PAID/CANCELLED);
`DELETE` tamtéž = zrušení; `POST refund.json` = vrácení (i částečné); `GET method.json` = Ověřit spojení.
Návratové adresy (`url_paid`, `url_cancelled`, `url_pending`) jdou s každou platbou. **URL pro předání
výsledku platby** se nastavuje v Klientském portálu Comgate (Integrace → Nastavení obchodu) na
`https://<doména>/platby/webhook/comgate` – administrace ji ukazuje. Oznámení se špatným heslem = 403.

**Mo.one** (ZNPay, integrační dokumentace ZS-548): `POST /payment/api/auth/token` (ClientID, ClientSecret
→ JWT na hodinu, cache – limit 10 požadavků za minutu), `POST /payment/api/transactions/initiate`
(Amount v Kč, CurrencyCode, ReturnUrl, CallbackUrl, ExternalTransactionID = naše `verejne_id`) →
`Transaction.PublicID`, `RedirectUrl`; návrat `?status=success|fail|cancel&transactionId=` (jen informace);
webhook v camelCase **bez podpisu** (`transactionPublicID`, `externalID`, `status`) → vždy
`GET /payment/api/transactions/{PublicID}/status` (Created/Initiated/Processing/InvestigationNeeded = čeká,
Success, Fail, Cancelled); `PUT …/cancel`. **Vrácení peněz API nemá** – dělá se v aplikaci Mo.one
(tlačítko Vrátit je u Mo.one zašedlé). REST je PascalCase, webhook camelCase. Webhook musí být veřejná
adresa – na localhost / `*.test` / privátní IP se `CallbackUrl` neposílá (stav dorovná návrat a plánovač).
Test `https://api-test.znpay.tech`, produkce `https://api.znpay.tech` (`PLATBY_MOONE_URL`).
Klient si údaje vygeneruje sám: aplikace Mo.one (produkt OrbitS) → PayPoint typu Platební brána →
Vygenerovat přístupové údaje (Client secret se ukáže jen jednou).

## Administrace

- **Platby** (správce): záložky Vše · Čeká · Zaplacené · Neúspěšné · Vrácené, filtr brány a režimu,
  hledání (popis, číslo, e-mail, id). Detail: údaje, **historie** (každá změna stavu, webhook, návrat,
  ruční ověření – kdo a kdy), akce *Ověřit stav u brány*, *Zrušit platbu*, *Vrátit peníze* (část i celé,
  s potvrzením), *Odkaz k zaplacení*. Nová platba = odkaz k zaplacení.
- **Nastavení → Platební brána**: jak teď platby běží, brána klienta a její údaje, adresa pro oznámení,
  *Ověřit spojení*, režim Testovací / Ostrý (na testu zašedlý).

## Provozní logy

- Chyby brány (nepřijatá platba, neověřitelný stav, nepovedené vrácení, e-mail) → **Logy → Chyby**
  (`ErrorLogger`, portál z nich dělá incidenty).
- Založení, zaplacení, změny stavu, vrácení, ověření údajů a přepnutí režimu → **Logy → Aktivita**
  (`platba.zalozena`, `platba.zaplacena`, `platba.stav`, `platba.vracena`, `platby.overeno`, `platby.rezim`).
- E-mail zákazníkovi (zaplaceno, vrácení, odkaz) přes Poštu → **Logy → E-maily**
  (`config('platby.email_zakaznikovi')` vypne potvrzení, když projekt posílá vlastní).

## Testy

`tests/Feature/Platby/` – simulace (celý průchod, idempotence, dvojí zaplacení, pozdní platba, vrácení,
plánovač), Mo.one a Comgate přes `Http::fake` (tvar požadavků, webhook + ověření stavu, 401 → nový token,
chybné údaje), přepínání test × produkce, tajemství mimo formulář, štítek, administrace a vypnutý doplněk.
Testy se doplňkem zapínají traitem `SPlatbami` (env před vytvořením aplikace).

## Otevřené

- Testovací přístupové údaje Sim&Ren k Mo.one (test) dodá Sim&Ren; portál je pak zakládá do `.env`
  testu i produkce. Bez nich platby na testu nefungují (administrace to řekne).
- CRM zatím doplněk „platby“ v okně Realizace nenabízí (zná jen připravované SSO) a do repa nic
  nezapisuje – zapnutí je commit v projektu.
