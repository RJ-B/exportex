# Platby – volitelný doplněk šablony

Platební brána pro e-shop, rezervace, objednávky nebo odkaz k zaplacení. **Ve výchozím stavu
vypnutá** – projekt bez plateb ji nemá vůbec (žádné routy, tabulky, administrace ani plánovač).

## Zapnutí

1. V CRM se doplněk volí v okně „Realizace: projekt a web“ (`.simren/sablona.yml` → `doplnky: [platby]`).
   Šablona o volbě v CRM neví – v projektu se zapíná commitem, který po vzniku repa udělá CRM samo
   („Zapnout doplněk platební brána (CRM)“; dodatečně tlačítko Doplňky u zakázky):
2. `config/sablona.php` → `'doplnky' => ['platby' => …]` na `true` – CRM mění výchozí hodnotu na `env('SABLONA_DOPLNEK_PLATBY', true)` (ne v `.env`, je to vlastnost projektu;
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
| testovací web (`APP_ENV=staging`, `*.test.simren.cz`) | **vždy testovací brána Sim&Ren** – Mo.one (`PLATBY_TEST_MOONE_URL`, výchozí `https://api-test.znpay.tech`), údaje z `.env` | žádné; s ostrou URL `https://api.znpay.tech` **skutečné** |
| produkce, režim **Testovací** (výchozí) | testovací brána Sim&Ren | žádné; s ostrou URL **skutečné** |
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
- **Testovací brána Sim&Ren může být ostrá.** URL, `client_id` a `client_secret` zapisuje do `.env` portál
  (`PLATBY_TEST_MOONE_URL`, `_CLIENT_ID`, `_CLIENT_SECRET`). Testovací weby s platbami platí přes ostrý PayPoint
  Sim&Ren (`https://api.znpay.tech`) – peníze se **skutečně strhnou** a vracejí se ručně v aplikaci Mo.one.
  Pak místo „TESTOVACÍ PLATBY“ svítí **„SKUTEČNÁ PLATBA – TESTOVACÍ BRÁNA“** (`NastaveniPlateb::testovaciBranaOstra()`,
  host `api.znpay.tech`). Host `api-test.znpay.tech` zůstává bez pohybu peněz. Pojistky výše se tím nemění
  (ostrý režim jen na produkci s ověřenými údaji klienta, test nikdy přes bránu klienta).
- **Štítek „TESTOVACÍ PLATBY“** na webu (`@includeWhen(config('sablona.doplnky.platby'), 'platby.stitek')`
  hned za `<body>` – šablona ho má v úvodu, Kontaktu a právních stránkách) a v administraci (horní lišta,
  odznak u Plateb), dokud se neplatí ostře. E-maily testovacích plateb mají v předmětu `[TEST]`.

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
    if ($e->platba->testovaci()) { /* zboží neodesílat; $e->platba->skutecna() = ostrá testovací brána, peníze se strhly */ }
    $e->platba->predmet?->oznacZaplaceno($e->platba);
});
```

Odkaz k zaplacení bez e-shopu: administrace → Platby → **Nová platba** (popis, částka, zákazník,
volitelně e-mail s odkazem `/platby/{id}/zaplatit`).

## Apple Pay a Google Pay (přes platební stránku brány)

Aplikace (a kdo chce, i web) může platbu založit s **předvolenou peněženkou**: platí se pořád na
platební stránce brány, jen ta rovnou otevře Apple Pay / Google Pay místo výběru metod. Žádné
nativní tlačítko ani platební token v aplikaci – to je úroveň 2 níž.

- `App\Platby\MetodaPlatby` (`apple_pay`, `google_pay`) → `new PozadavekPlatby(..., metoda: MetodaPlatby::zVolby($request->input('metoda_platby')))`.
  Platba si ji drží v `predvolba` (`metoda` je to, čím zákazník podle brány opravdu zaplatil).
- **Kdy jde předvolit** (`NastaveniPlateb::nabizi`): přepínač v administraci (Nastavení → Platební
  brána → Apple Pay a Google Pay, výchozí zapnuto) **a** brána ji má (`Brana::predvolby()`):
  - **Comgate** – Apple Pay i Google Pay jsou součást platby kartou: brána je ukáže, když má obchod
    zapnutou kartu `CARD_CZ_COMGATE` nebo `CARD_CZ_CSOB_2` (nebo je ve výpisu přímo kód peněženky);
    zjišťuje se výpisem `method.json` (mezipaměť 10 min). Předvolba = parametr `method` při založení:
    `APPLEPAY_REDIRECT` / `GOOGLEPAY_REDIRECT` (jinak `ALL`). Dokumentace:
    https://apidoc.comgate.cz/en/applepay-a-googlepay, kódy https://apidoc.comgate.cz/en/metody-platebni-brany.
  - **Mo.one** – předvolit neumí (`transactions/initiate` má jen částku, měnu a adresy) → nikdy.
  - **Simulace** – obě (stránka simulace ukáže „Předvolená metoda“).
- Metoda, která teď nejde (vypnutá, brána ji nehlásí), se tiše vynechá a platí se s výběrem na bráně –
  starší aplikace nic nerozbije. Historie platby to zapíše. Jiná předvolba téhož předmětu = nová platba.
- **Co jde teď nabídnout:** `NastaveniPlateb::metody()` → `{karta, apple_pay, google_pay}`. Šablona
  pro mobilní aplikaci (`simren-api-starter`) to vrací v `GET /api/v1/platby/metody`; projekt s vlastním
  API to přidá ke svému katalogu (aplikace pak ukáže tlačítko a do zápisu pošle `metoda_platby`).
- **Co musí udělat klient:** v Comgate mít zapnutou platbu kartou (`CARD_CZ_COMGATE` / `CARD_CZ_CSOB_2`);
  Apple Pay a Google Pay pak brána ukazuje sama (Apple Pay jen v Safari / na zařízení Apple). V Klientském
  portálu Comgate ověřit, že jsou peněženky u obchodu aktivní – jinak je administrace nechá vypnout.

## Nativní Apple Pay a Google Pay v aplikaci (úroveň 2) – zatím ne

Průzkum 12. 10. 2026: nativní tlačítko (Flutter `pay`, token `PKPaymentToken` / `paymentMethodToken`
poslaný přes náš server bráně) **žádná z bran neumí přijmout přes API**, proto se nestaví:

- **Comgate** REST API platbu s tokenem peněženky nemá. Nativní Apple Pay / Google Pay umí jen
  **Comgate Mobile Checkout SDK** – nativní knihovna pro Swift (iOS 15+, SPM) a Kotlin, která token
  posílá Comgate sama ze zařízení (Checkout API, `checkoutId` z Klientského portálu). Flutter verzi
  nemá – šlo by jen přes platform channels. Apple Pay vyžaduje vlastní Merchant ID
  (`merchant.cz.…`) a certifikát pro zpracování plateb, jehož **CSR vystavuje Comgate** (podpora),
  obchodník ho nahraje do Apple Developer a certifikát vrátí Comgate; aktivace není okamžitá a obnovuje
  se ročně. Google Pay potřebuje Merchant ID z Google Pay & Wallet Console a schválení aplikace.
  https://apidoc.comgate.cz/en/mobilni-sdk/uvod, https://apidoc.comgate.cz/en/mobilni-sdk/ios/apple-pay,
  https://apidoc.comgate.cz/en/mobilni-sdk/android/google-pay
- **Mo.one** (Public Payment API, `https://api-test.znpay.tech/swagger/index.html`) – e-shop jde jen
  přesměrováním na platební stránku; token peněženky API nepřijímá. Apple Pay / Google Pay (`XPAY`)
  zná jen Card API pro terminály Mo.one (zápis hotové transakce z terminálu, token zařízení).

## Loga plateb a oznámení o bráně

Comgate vyžaduje v patičce e-shopu **loga Visa a Mastercard** (povinné), doporučuje logo Comgate s odkazem na
https://www.comgate.eu a v obchodních podmínkách / na stránce o platbě text o bráně. Řeší to doplněk sám,
jen když je zapnutý, a **podle brány, která se skutečně používá** (`NastaveniPlateb::kodBrany()`): na testu a
v testovacím režimu je to Mo.one (testovací brána Sim&Ren), v ostrém režimu brána klienta, lokálně simulace
(jen karty a peněženky, bez loga brány). Když platby stojí (`PlatbyNedostupne`), se nezobrazí nic.

- `resources/views/platby/_loga.blade.php` – Visa, Mastercard, Apple Pay / Google Pay (jen při zapnuté
  předvolbě v administraci a bráně Comgate; Mo.one peněženky nepředvolí), logo Comgate s odkazem nebo logo Mo.one.
  Výška 24 px, `alt` u každého loga, `role="group"` + `aria-label`, bez `title`, zalamuje se (`flex-wrap`).
  Oficiální loga jsou v `public/img/platby/` (bez buildu) a **neupravují se** – mění se jen velikost a varianta
  podle pozadí (Visa bílá, Comgate pro tmavé pozadí, Mo.one bílé / tmavé; Apple Pay a Google Pay mají na tmavém
  pozadí bílou podložku). Parametry: `pozadi` (`auto` = podle `prefers-color-scheme`, `svetle`, `tmave` pro
  patičku s pevným pozadím), `zarovnani`, `text`, `brana`.
- `resources/views/platby/_o-brane.blade.php` – oznámení o bráně. Comgate: text, který žádá slovo od slova
  („Online platby kartou (Visa, Mastercard), Apple Pay a Google Pay zajišťuje platební brána Comgate. …“);
  Mo.one: jen „Platby zajišťuje platební brána Mo.one.“ (o licenci Mo.one se nic neuvádí).
- **Stránky plateb** (`resources/views/platby/_rozvrzeni.blade.php` – výsledek, simulace, návrat do aplikace) mají
  loga karet, „Platbu zajišťuje Comgate / Mo.one“ s logem a oznámení o bráně automaticky; loga se řídí bránou
  dané platby (`$platba->brana`).
- **Ochrana osobních údajů** (`pravni/_ochrana-obsah`) má oznámení o bráně v části Platby. Šablona zatím žádný
  vzor obchodních podmínek nemá – projekt s vlastními VOP vloží `@include('platby._o-brane')`.

**Patička webu** (šablony s veřejným webem): `resources/views/layouts/verejna.blade.php` loga vkládá sama.
Vlastní layout projektu je vloží jedním řádkem do své patičky:

```blade
@include('platby._loga')                         {{-- pozadí podle prefers-color-scheme --}}
@include('platby._loga', ['pozadi' => 'tmave'])  {{-- patička s pevným tmavým pozadím --}}
```

(partial se sám hlídá na zapnutý doplněk, `@includeWhen` není nutný).

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

**Víc bran naráz** (administrace → Platební brána → *Nabízet zákazníkům*): klient může nabízet
Comgate i Mo.one zároveň (`NastaveniPlateb::nabizeneBrany`, uloženo `platby.brany`; bez volby jen
zvolená `brana`). Projekt nabídne zákazníkovi volbu (Comgate jako „Kartou online“, Mo.one zvlášť) a
pošle ji v `PozadavekPlatby::brana` (`comgate` / `moone`); `NastaveniPlateb::aktivni($brana)` ostře
vrátí jen nabízenou a ověřenou bránu, jinak `PlatbyNedostupne`. Ostrý režim jde uložit, jen když je
každá nabízená brána ověřená. Mimo ostrý režim platí každá volba testovací bránou.

**Comgate** – REST API 2.0 (`https://payments.comgate.cz/v2.0`, Basic merchant:heslo, JSON, haléře):
`POST payment.json` → `transId`, `redirect`; `GET payment/transId/{id}.json` (PENDING/PAID/CANCELLED);
`DELETE` tamtéž = zrušení; `POST refund.json` = vrácení (i částečné); `GET method.json` = Ověřit spojení.
Návratové adresy (`url_paid`, `url_cancelled`, `url_pending`) jdou s každou platbou. **URL pro předání
výsledku platby** se nastavuje v Klientském portálu Comgate (Integrace → Nastavení obchodu) na
`https://<doména>/platby/webhook/comgate` – administrace ji ukazuje. Oznámení se špatným heslem = 403.
`method.json` vrací jen `methods` (bez `code`) – chybějící `code` u úspěšné odpovědi je v pořádku.

**Způsoby platby u Comgate** (administrace → Platební brána): seznam metod, které má obchod v Comgate
povolené (`method.json`: `id`, `name`, `groupLabel`), jako přepínače. Portál Comgate metody jen
vypisuje, vypnout je tam nejde (jen přes podporu) – proto se vypnuté odeberou při každé platbě
výrazem `method=ALL-KOD-KOD` (https://apidoc.comgate.cz/en/metody-platebni-brany). Ukládá se seznam
**vypnutých** (`platby.comgate.vypnute`), takže nová metoda od Comgate se nabízí, dokud ji klient
nevypne. Výchozí vypnuté (`NastaveniPlateb::COMGATE_VYPNUTE_VYCHOZI`): `BANK_CZ_OTHER` (QR platba /
převod pro klienty jiných bank) a `BANK_CZ_AB_CVAK` (Cvak – Air Bank).

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
  *Ověřit spojení*, Apple Pay a Google Pay (přepínače + co hlásí brána), režim Testovací / Ostrý (na testu zašedlý).

## Provozní logy

- Chyby brány (nepřijatá platba, neověřitelný stav, nepovedené vrácení, e-mail) → **Logy → Chyby**
  (`ErrorLogger`, portál z nich dělá incidenty).
- Založení, zaplacení, změny stavu, vrácení, ověření údajů a přepnutí režimu → **Logy → Aktivita**
  (`platba.zalozena`, `platba.zaplacena`, `platba.stav`, `platba.vracena`, `platby.overeno`, `platby.rezim`).
- E-mail zákazníkovi (zaplaceno, vrácení, odkaz) přes Poštu → **Logy → E-maily**
  (`config('platby.email_zakaznikovi')` vypne potvrzení, když projekt posílá vlastní).

## Stav pro portál a CRM

`simren:zdravi --json` má blok **`platby`** (`App\Platby\StavPlateb`), který portál čte přes shim:
`zapnuto` (doplněk), `brana` (comgate / moone / null = žádná), `klice` a `overeno` (jen ano/ne),
`rezim_volba` (test / ostry), `rezim` (jak platby opravdu běží: ostry / test / simulace, null = stojí,
důvod v `duvod`), `posledni_platba` (`cas`, `rezim`) a `posledni_chyba` (`cas`, `zprava` – zkrácená,
hodnoty klíčů nahrazené `***`). **Nikdy hodnoty klíčů.** Vypnutý doplněk = jen `zapnuto: false`.
Portál ukáže „Platby: ostré, v pořádku“ / „Platební brána v testovacím režimu“ / „Chybí klíče brány“
a u produkce otevře upozornění (nízká závažnost); CRM ho ukáže v boxu Produkce.

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
