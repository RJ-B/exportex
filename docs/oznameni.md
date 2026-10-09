# Oznámení – návrh modulu (šablony Sim&Ren)

Stav: **krok 1 hotový v `simren-laravel-starter`** (centrum, pruh, e-mail přes Poštu, administrace,
předvolby a souhlasy, oznámení z kódu). Kroky 2–4 jsou návrh – níž.

Šablona je jen pro **nové projekty**; do existujících aplikací se modul nepřenáší (rozhodnutí 4. 10. 2026).

---

## 1. O co jde

Oznámení je **jedna zpráva, která žije jednou na serveru aplikace** a k lidem dojde různými kanály.
Přečtení, proklik i archiv se zapisují k jednomu záznamu příjemce – web, administrace i mobil
ukazují totéž a přečtené na telefonu je přečtené i na webu.

### Kdo posílá

| Zdroj (`oznameni.zdroj`) | Kdo | Typicky | Stav |
|---|---|---|---|
| `administrace` | správce aplikace (klient – admin) | novinky, změny, nové funkce, „zítra zavřeno“ | krok 1 |
| `aplikace` | kód projektu (`Oznam::…`) | „objednávka odeslána“, „platba přijata“ | krok 1 |
| `portal` | Sim&Ren přes portal.simren.cz | nová verze („co je nového“), plánovaná odstávka s odpočtem, výpadek a jeho vyřešení | krok 2 |

### Kanály (`KanalOznameni`)

| Kanál | Kde | Kdo vidí | Stav |
|---|---|---|---|
| `centrum` | zvoneček na webu i v administraci, stránka Oznámení, seznam v mobilu | příjemce | krok 1 |
| `pruh` | pruh nahoře přes celý web a administraci | pro všechny i nepřihlášení, cílený jen přihlášený příjemce | krok 1 |
| `email` | e-mail přes Poštu (posta.simren.cz) | příjemce podle předvoleb a souhlasu | krok 1 |
| `web_push` | upozornění prohlížeče (VAPID, bez cizí služby) | jen kdo povolí | krok 3 – v modelu připravené |
| `mobil_push` | push do mobilní aplikace přes Firebase | jen kdo povolí v telefonu | krok 3 – v modelu připravené |

Pruh není předvolba – patří k provozu (odstávka, výpadek) a smí ho jen **servisní** oznámení.

---

## 2. Datový model

Všechno v hlavní databázi aplikace (zálohuje a přenáší ji portál). Migrace
`2026_10_09_100000_oznameni.php` – opakovatelná, bez dat (čerstvá instalace nesmí „mít data“).

### `oznameni` – zpráva

| Sloupec | |
|---|---|
| `uuid` | veřejné id (API, e-mail, metadata v Poště) |
| `zdroj`, `zdroj_klic` | odkud a klíč zdroje; **unikátní dvojice = idempotence** (id oznámení z portálu, `objednavka-104-odeslana` z kódu) |
| `druh` | `provozni` · `servisni` · `novinky` · `marketing` (`DruhOznameni`) |
| `zavaznost` | `info` · `varovani` · `kriticke` · `vyreseno` – barva pruhu |
| `titulek`, `text`, `odkaz`, `odkaz_text` | text v Markdownu (vlastní HTML se escapuje, nebezpečné odkazy pryč); odkaz `/cesta` nebo `https://…` |
| `kanaly` | JSON seznam kanálů |
| `cileni` | JSON `{"komu":"vsichni"}` nebo `{"komu":"vybrani","role":[…],"skupiny":[…],"uzivatele":[…]}` |
| `stav` | `koncept` → `naplanovano` → `odesila` → `odeslano` (`StavOznameni`), mění ho jen `Odeslani` |
| `naplanovano_na`, `odeslano_at` | plán a skutečné odeslání |
| `pruh_od`, `pruh_do` | kdy pruh visí (prázdné do = dokud ho někdo neukončí) |
| `udalost_od`, `udalost_do` | odstávka – pruh ukáže odpočet, e-mail termín |
| `pocet_prijemcu` | snímek při odeslání |
| `vytvoril_id`, `odeslal_id` | kdo (také v Aktivitě – `AuditableObserver`) |

### `oznameni_prijemci` – komu přišlo (centrum)

Jeden řádek na člověka a oznámení (unikát `oznameni_id + user_id` – **každý jednou**, i když ho
zasáhla role, skupina i výběr). `v_centru` (vypnul si druh v centru?), `precteno_at`,
`prokliknuto_at`, `archivovano_at`. Tohle je to, co se synchronizuje mezi webem, administrací a mobilem.

### `oznameni_doruceni` – doručení po příjemci a kanálu

Řádek pro každý **vnější** kanál (e-mail, později push): `kanal`, `stav`
(`ceka` · `odeslano` · `preskoceno` · `chyba`), `duvod` („Bez souhlasu.“, „Vypnuto v předvolbách.“,
„Bez e-mailu.“, text chyby), `mail_log_id` (výsledek doručení z Pošty v Logy → E-maily), `odeslano_at`.
Unikát `prijemce_id + kanal` → opakovaná úloha nepošle dvakrát.

### `oznameni_predvolby` – co chce uživatel dostávat

`user_id` (unikát), `kanaly` JSON `{"novinky": {"email": true, "centrum": false}, …}`.
Co tam není, platí výchozí z `DruhOznameni::vychozi()`. Vzor: jádro účtů GymPorn
(`HasNotificationChannelPreferences`, katalog událostí s výchozími a zamčenými kanály, chybějící
zaškrtávátko = vypnuto) – tady katalog = `DruhOznameni` × `KanalOznameni`.

### `oznameni_souhlasy` – záznam souhlasu (GDPR)

Jen přidávání: `user_id`, `druh`, `kanal`, `udelen` (ano/ne), `zdroj` (`predvolby` ·
`odhlaseni` · `jedno-kliknuti` · později `registrace`, `api`), **`text` = přesné znění**, se kterým
člověk souhlasil, `ip_adresa`, `prohlizec`, `created_at`. Platný stav je v předvolbách, historie tady.

### `oznameni_skupiny` + `oznameni_skupiny_clenove`

Pojmenované skupiny příjemců (Stálí zákazníci…), spravuje správce. Projekt může později přidat
i skupiny z kódu (rozhraní `SkupinaPrijemcu` s dotazem – zatím není potřeba).

### Krok 3: `oznameni_push_odbery`

`user_id`, `typ` (`web` | `fcm`), `endpoint` / token (otisk pro unikát), `klic_p256dh`, `klic_auth`
(web push), `platforma`, `zarizeni`, `verze_aplikace`, `posledni_uspech_at`, `chyba_at`, `created_at`.
Mrtvý odběr (410 Gone / `UNREGISTERED`) se smaže. V `simren-api-starter` už je tabulka `zarizeni`
(FCM) – push do mobilu ji použije, web push dostane vlastní řádky stejné tabulky (sloučit při kroku 3).

### SaaS (`simren-saas-starter`)

Všechny tabulky navíc `firma_id` (NOT NULL, FK, index, unikáty začínají `firma_id`), modely
s `PatriFirme`. Oznámení **platformy** (provozovatel → všem firmám, např. odstávka) má
`firma_id = NULL` jen v samostatné tabulce `platforma_oznameni`, ze které se při odeslání
založí oznámení v každé firmě (`vKontextu`) – žádný dotaz přes všechny firmy z požadavku.

---

## 3. Druhy a pravidla

| Druh | Kdo ho píše | Centrum | E-mail / push | Pruh |
|---|---|---|---|---|
| Provozní | jen kód aplikace | vždy (zamčené) | výchozí zapnuto, jde vypnout | ne |
| Servisní | správce, kód, portál | vždy (zamčené) | výchozí zapnuto, jde vypnout | ano |
| Novinky | správce, kód, portál („co je nového“) | výchozí zapnuto, jde vypnout | **jen s výslovným souhlasem** | ne |
| Nabídky a akce (marketing) | správce – **jen když je zapnutý** | výchozí zapnuto | **jen s výslovným souhlasem** | ne |

- **Souhlas** je potřeba pro obchodní sdělení **mimo aplikaci** (e-mail, push) – GDPR čl. 6/1 a
  a zákon 480/2004 Sb. Novinka v centru (uvnitř aplikace, kam si člověk přišel) souhlas nepotřebuje,
  ale jde vypnout. Viz otevřené otázky.
- **Marketing je ve výchozím stavu vypnutý** pro celou aplikaci (`config/oznameni.php`,
  přepíná superadmin v Oznámení → Nastavení). Vypnutý = nikomu nikudy, ani se souhlasem.
- **Odhlášení jedním kliknutím**: každý e-mail novinek/nabídek má hlavičky `List-Unsubscribe`
  a `List-Unsubscribe-Post: List-Unsubscribe=One-Click` (RFC 8058 – Gmail, Apple Mail a další ukážou
  vlastní tlačítko, POST bez cookies) a v patičce podepsaný odkaz. Otevření odkazu samo nic neodhlásí
  (skenery odkazů v poště by odhlašovaly) – stránka má jedno tlačítko. Odhlášení se zapíše do souhlasů.
- Provozní a servisní e-mail má v patičce „proč to dostávám“ a odkaz na předvolby.
- **Statistika bez sledování napříč weby**: přečteno = otevřené v centru / proklik, proklik =
  podepsaný odkaz přes vlastní doménu (`/oznameni/proklik/{prijemce}`), doručení = výsledek z Pošty.
  Žádné měřicí pixely, žádné cizí služby. Superadmini (vývojáři) se do čísel nepočítají.

---

## 4. Cílení

- **Všem** = správci (admin) a klienti. **Superadmini nejsou ve „všech“ ani v rolích** – do hromadných
  zpráv klienta nepatří a nepočítají se; dostanou oznámení jen jmenovitě.
- **Vybraným**: role + skupiny + **víc vybraných lidí najednou** (vyhledat jménem, příjmením nebo
  e-mailem a přidávat – `Select::multiple()->searchable()`); výběry se sčítají, **každý dostane jednou**
  (`Cileni::dotaz()` + unikát v `oznameni_prijemci`).
- SaaS: navíc „firmy“ (provozovatel platformy) a v rámci firmy role firmy.
- Kód: `->komu($user | $users | ids)`, `->roli('klient')`, `->skupine($id)`, `->vsem()`.

## 5. Oprávnění – kdo smí posílat co

| Kdo | Co |
|---|---|
| superadmin | vše jako admin + Oznámení → Nastavení (marketing, oznámení z portálu koncovým uživatelům) |
| admin (majitel) | psát, plánovat, odesílat servisní a novinky (nabídky jen zapnuté), skupiny příjemců, statistika; adminovi se v hledání lidí neukazují superadmini |
| klient | jen svoje centrum a předvolby (web `/oznameni`), do administrace nesmí |
| kód aplikace | provozní, servisní, novinky; bez limitu a potvrzení počtu (odpovídá kód), předvolby a souhlasy platí |
| portál Sim&Ren | servisní a novinky (verze), podpis V2; komu podle `oznameni.portal_koncovym` |

Krok 4: volitelné **schválení** – nastavení „hromadné oznámení musí schválit jiný správce“
(stav `ke_schvaleni`, kdo a kdy schválil, autor si sám neschválí).

## 6. Odesílání, fronty a limity

1. Administrace: **koncept** → Náhled (centrum, pruh, e-mail v `iframe sandbox`) → **Poslat zkoušku
   sobě** (jen e-mail přihlášenému, nic se nezapíše) → **Odeslat / Naplánovat**: okno s počty
   (příjemců, v centru, e-mailem, kolik nedostane e-mail bez souhlasu / s vypnutým e-mailem, odhad
   doby rozesílky). Od `limity.potvrzeni_od` (25) příjemců se **počet musí opsat**. Nad
   `limity.max_prijemcu` (5000) odeslat nejde – domluvit se Sim&Ren.
2. `Odeslani::odeslat()` – kontrola (`chyby()`), naplánované jen změní stav. Odeslané se už neupravuje
   (jde jen **Ukončit pruh** a **Použít znovu** jako nový koncept); naplánované se vrátí **Zrušit plán**.
3. `Odeslani::rozeslat()` – příjemci a doručení (`insertOrIgnore`, opakovatelné). Do `limity.hned_do`
   (300) příjemců hned v požadavku, víc ve frontě (`RozeslatOznameni`). Centrum je tím doručené.
4. E-maily ve frontě po dávkách `limity.emailu_za_minutu` (60), každá dávka o minutu později
   (`PoslatEmailyOznameni`), každý e-mail zvlášť `Mail::` → Pošta. Souhlas se před každým e-mailem
   ověří znovu (mohl být mezitím odvolán). Doručení doplní Pošta webhookem do Logy → E-maily.
5. Plánovač (v cronu jen `schedule:run`): `Schedule::call` každou minutu jen když `Odeslani::maPraci()`
   (jen čte) – naplánovaná odešle, zaseknutá (`odesila` déle než 10 min) rozešle znovu.
   Frontu pouští plánovač (`FrontaUloh`), když v ní něco je.

## 7. Pruh (odstávky, výpadky)

- `@include('oznameni.pruh')` hned za `<body>` každého layoutu webu (šablona ho má na úvodu, ve veřejném
  layoutu, ve stavech webu Údržba/Připravujeme) a v administraci (render hook `BODY_START`).
- Pruh pro všechny: dotaz v mezipaměti na minutu (jen hodnoty sloupců – modely Laravel z mezipaměti
  nerozbalí), vidí ho i nepřihlášený. Cílený pruh: jen přihlášený příjemce.
- Vážné nahoře, pak upozornění, informace, vyřešené. Vážný (`kriticke`) nejde zavřít; ostatní zavře
  křížek a pamatuje si to prohlížeč (localStorage, schová se ještě před vykreslením).
- Odstávka: `udalost_od/do`, odpočet „začne za 2 d 20 h“ počítá prohlížeč z ISO 8601 s posunem.
- Výpadek z portálu (krok 2): `kriticke`, po vyřešení se týž pruh přepne na `vyreseno` a za hodinu
  zmizí (`pruh_do`).

## 8. Centrum a stránky

- **Zvoneček** `@include('oznameni.zvonecek')` (web) a render hook `USER_MENU_BEFORE` (administrace):
  počet nepřečtených hned, seznam (`/oznameni/centrum`, JSON) až při otevření; klik = přečteno
  (s odkazem přes podepsaný proklik), křížek = archiv, „Označit vše jako přečtené“, „Zobrazit všechna
  a předvolby“. Bez knihoven (`public/js/oznameni.js`, `public/css/oznameni.css`), světlý i tmavý vzhled.
- **Web `/oznameni`** (přihlášený): filtr Vše · Nepřečtené · Archiv, celé texty, předvolby
  (druh × kanál, zamčené „vždy“, u novinek znění souhlasu). Nepřihlášeného pošle na přihlášení projektu
  (route `login`), jinak do administrace.
- **Administrace → Moje oznámení** (uživatelské menu i zvoneček): totéž ve Filamentu.

## 9. Oznámení z kódu (API pro vývojáře)

```php
use App\Support\Oznameni\Oznam;

Oznam::provozni('Objednávka 2026-104 je na cestě')
    ->text('Balík jsme předali **PPL**, doručení zítra.')
    ->odkaz(route('objednavky.detail', $objednavka, false), 'Sledovat zásilku')
    ->komu($objednavka->user)                       // User, kolekce, id
    ->klic('objednavka-'.$objednavka->id.'-odeslana') // podruhé nic nepošle
    ->posli();

Oznam::servisni('V noci na neděli bude web 30 minut nedostupný')
    ->vsem()->kanaly(KanalOznameni::Centrum, KanalOznameni::Pruh)
    ->zavaznost(ZavaznostOznameni::Varovani)
    ->odstavka($od, $do)->pruh(now(), $do)->posli();
```

Výchozí kanály centrum + e-mail. Chybné oznámení (pruh u novinek, push, nikdo) vyhodí
`OznameniNejdeOdeslat` a **neuloží se**. Bez transakce kolem odeslání (pomalé I/O do transakce nepatří);
souběh se stejným klíčem zastaví unikátní klíč.

## 10. API pro mobil (krok 3, `simren-api-starter` → `/api/v1`)

Autentizace jako zbytek API: **Sanctum `Authorization: Bearer`**, `X-Aplikace-Platforma`,
`X-Aplikace-Verze` (426 pod minimem), App Check. Chyby `{message, errors}` česky, nepřihlášený 401.
Čas vždy **ISO 8601 s posunem** (`toIso8601String()`), aplikace převádí na místní (`casZApi()`, `.toLocal()`).
Tvar položky = `App\Support\Oznameni\Centrum::polozka()` (stejný jako zvoneček na webu).

| Trasa | Co dělá |
|---|---|
| `GET /centrum?stav=vse\|neprectene\|archiv&stranka=` | seznam (stránkovaný, 20) + `neprectenych` |
| `GET /centrum/pocet` | jen počet nepřečtených (odznak na ikoně, levné) |
| `POST /centrum/{id}/precteno` | přečteno (otevření v aplikaci) |
| `POST /centrum/{id}/proklik` | proklik (aplikace otevřela odkaz – deep link) |
| `POST /centrum/precteno-vse` | vše přečteno |
| `POST /centrum/{id}/archiv`, `DELETE /centrum/{id}/archiv` | do archivu a zpět |
| `GET /ja/predvolby-oznameni`, `PUT /ja/predvolby-oznameni` | matice druh × kanál (s `zamceno`, u souhlasu `text_souhlasu`); PUT zapíše souhlas se `zdroj = api` |
| `POST /zarizeni`, `DELETE /zarizeni` | registrace / odhlášení push tokenu (FCM) – už v šabloně |
| `GET /oznameni?platforma=&verze=&jazyk=` | pruhy a okna v aplikaci – **dnešní trasa zůstává**, jen ji začne plnit tabulka `oznameni` (kanál `pruh` + nový `okno`, platforma a rozsah verzí jako dnes) |

- Dnešní `GET/PUT /ja/notifikace` (kategorie pushů z `config/aplikace.php`) se nahradí
  `/ja/predvolby-oznameni`; kategorie = druhy. Stará trasa jedno vydání vrací totéž v starém tvaru.
- **Push** (`Fcm::posli`) nese jen `data`: `oznameni` (uuid), `prijemce` (id), `odkaz` (deep link)
  a `notification` s titulkem a začátkem textu. Klepnutí → aplikace `POST /centrum/{id}/proklik`
  a otevře odkaz.
- **Deep link**: odkaz `/cesta` → `https://<doména>/aplikace/cesta` (App Links / Universal Links už
  šablona umí, `aplikace.odkazy.cesty`); bez aplikace se otevře web na téže cestě. Web i mobil tak
  vedou na totéž místo.
- Přenos dat z testu do produkce: `api:po-prenosu` smaže i push odběry (jako dnes tokeny a zařízení).

## 11. Příjem oznámení z portálu (krok 2)

`POST /oznameni/portal` (mimo session a CSRF, `throttle`), podepsané **V2 s časem** stejně jako webhooky
Pošty, Fakturace a CRM:

- `X-Portal-Signature-V2: t=<unix>,v1=<hex HMAC-SHA256("t.tělo")>`, stáří nejvýš 5 minut, víc `v1=`
  při výměně tajemství (předchozí platí dočasně),
- **idempotence podle `id` z podepsaného těla** (`zdroj = portal`, `zdroj_klic = id`) – stejné id
  podruhé jen vrátí 200; hlavička `X-Portal-Delivery` jen pro log (podepsaná není),
- tajemství předá portál při propojení přes shim (`oznameni:z-portalu`, JSON na stdin, šifrovaně
  v `nastaveni` – stejně jako `posta:z-portalu`), `.env` jen záloha,
- tělo: `{id, udalost: verze|odstavka|vypadek|vyreseno, souvisi_s?, titulek, text, odkaz?, zavaznost,
  od?, do?, komu: spravci|vsichni, kanaly}` – časy ISO 8601 s posunem → `CasAplikace::zVenku()`,
- **komu**: s `oznameni.portal_koncovym = false` (výchozí) jde všechno jen roli `admin`, i když portál
  pošle `vsichni`; zapnout může superadmin,
- `vyreseno` se `souvisi_s` přepne pruh výpadku na `vyreseno` (+ `pruh_do` za hodinu) a do centra přidá
  krátké „vyřešeno“,
- `verze` = druh novinky („Co je nového ve verzi 2.4“), správcům v centru; koncovým uživatelům jen se
  zapnutým nastavením a e-mailem jen se souhlasem,
- odpověď `{oznameni: uuid, prijemcu: n}`; portál ukáže u projektu, komu odešlo.

Záloha bez HTTP: tentýž JSON přes shim (`simren:oznameni --json` na stdin), kdyby web neodpovídal
(výpadek) – portál zkusí webhook a při chybě shim.

## 12. Web push (krok 3)

VAPID klíče vygeneruje aplikace sama při prvním zapnutí (`oznameni:vapid`, soukromý šifrovaně
v `nastaveni`) – **žádná cizí služba** (OneSignal ap.), posílá se rovnou do push služby prohlížeče
(`minishlink/web-push`, Chrome/Firefox/Safari). Service worker `public/sw-oznameni.js`,
povolení jen na klik („Zapnout upozornění v prohlížeči“ v předvolbách – nikdy hned po načtení).
Klik na upozornění → podepsaný proklik. Na iOS jen pro web přidaný na plochu (Safari 16.4+).

## 13. GDPR a Ochrana osobních údajů

- Ochrana osobních údajů má část **Oznámení a novinky**, jakmile aplikace nějaké oznámení nebo
  souhlas má (údaje, účel, právní základ: smlouva / oprávněný zájem, u novinek souhlas, doba uložení).
- Souhlas: znění, čas, IP, prohlížeč; odvolání stejně snadné jako udělení (odkaz v každém e-mailu,
  jedno kliknutí v poštovním programu, předvolby).
- Smazání účtu smaže příjemce, doručení, předvolby i souhlasy (cizí klíče `cascade`).
- Retence (krok 4): příjemci a doručení starší 2 let smazat (`logs:prune`), oznámení zůstane s čísly.

## 14. Čas

Aplikace i databáze v `Europe/Prague`, v databázi místní čas bez pásma. Ven (centrum JSON, API,
portál) jen **ISO 8601 s posunem**; co přijde zvenku (portál), převede `CasAplikace::zVenku()`.
Odpočet v pruhu počítá prohlížeč z ISO času (`new Date(iso)`), v mobilu `casZApi()`.

## 15. Přenos do dalších šablon

| Šablona | Co |
|---|---|
| `simren-saas-starter` | celé jádro + `firma_id`/`PatriFirme` na všech tabulkách, cílení podle firmy, správce firmy píše své firmě, provozovatel platformy všem firmám (`platforma_oznameni`), pruh platformy na všech doménách |
| `simren-moonshine-starter` | jádro beze změny (`app/Support/Oznameni`, modely, migrace, e-mail, web); administrace jako MoonShine resource, zvoneček jako komponenta layoutu MoonShine (stejné `oznameni.js`) |
| `simren-app-starter` | jádro bez webových stránek: centrum a předvolby jen v administraci, pruh v administraci |
| `simren-api-starter` | jádro + API `/centrum`, `/ja/predvolby-oznameni`, push přes `Fcm` (kanál `mobil_push`), `/oznameni` (pruhy a okna) z téže tabulky |
| `simren-mobil-starter` | obrazovka Oznámení (seznam, archiv, předvolby), odznak na ikoně z `/centrum/pocet`, příjem pushe a otevření deep linku, pruh/okno z `/oznameni` |

Přenáší se jako celek (migrace, `app/Support/Oznameni`, enumy, modely, úlohy, e-mail, `public/js|css/oznameni.*`,
testy `OznameniTest`) – ne kusem. Šablony se nedorovnávají zpětně do existujících projektů.

## 16. Kroky

1. **Hotovo** – centrum (web + administrace), pruh, e-mail přes Poštu, administrace Oznámení
   (koncept, plán, náhled, zkouška sobě, cílení včetně víc vybraných lidí a skupin, potvrzení počtu,
   limit), předvolby a souhlasy, odhlášení jedním kliknutím, `Oznam` z kódu, fronta z plánovače,
   základní čísla v detailu.
2. **Portál** – příjem V2 (`/oznameni/portal`), `oznameni:z-portalu`, nastavení „koncovým uživatelům“,
   v portálu: u projektu Oznámit (verze, odstávka s termínem, výpadek → vyřešeno), u vydání verze
   předvyplněné „co je nového“ z release notes, seznam komu odešlo; incident v portálu může nabídnout
   „Oznámit výpadek“ a po vyřešení „Oznámit vyřešení“.
3. **Push a mobil** – `oznameni_push_odbery`, web push (VAPID, service worker), mobil push přes `Fcm`,
   API `/centrum` a předvolby v `simren-api-starter`, obrazovka v `simren-mobil-starter`, deep linky,
   převod `/oznameni` (pruhy a okna) na tabulku `oznameni`.
4. **Statistika a schvalování** – přehled po kanálech v čase (doručeno / přečteno / prokliknuto,
   odhlášení po oznámení), schválení druhým správcem, opakované odeslání nedoručeným, retence,
   export souhlasů (GDPR – doložit, kdo kdy souhlasil).

## 17. Otevřené otázky (výchozí je konzervativní)

1. **Mají klienti posílat i marketing (nabídky, slevy), nebo jen novinky?** Teď: marketing **vypnutý**
   (`oznameni.marketing = false`), zapne superadmin u konkrétní aplikace. Novinky jdou e-mailem jen se
   souhlasem.
2. **Mají oznámení z portálu u klientských produkcí jít i koncovým uživatelům, nebo jen správcům?**
   Teď: **jen správcům** (`oznameni.portal_koncovym = false`), zapne superadmin u aplikace.
3. Novinky **v centru** bez souhlasu (uvnitř aplikace) – stačí, nebo i to chtít jen se souhlasem?
   Teď: v centru ano (jde vypnout), e-mailem/pushem jen se souhlasem.
4. Souhlas s novinkami při **registraci** (zaškrtávátko, výchozí nezaškrtnuté) – v šabloně registrace
   není; projekty s registrací ho mají přidat jako `Predvolby::uloz(…, 'registrace')`?
5. Schvalování (krok 4) – stačí „jiný správce“, nebo má hromadný marketing schvalovat Sim&Ren?
6. Limity – 25 pro opsání počtu, 5000 na oznámení, 60 e-mailů za minutu: sedí s limity Pošty
   a reputací domén klientů?
