# Změny šablony – říjen 2026

Podklad: co si projekty po založení ze šablony nebo po převodu na ni musely samy doplnit
a co se ukázalo jako chyba šablony. Prošlé projekty (stav 4. 10. 2026):

- SimRen-sro: karty, radio, nabidky, licencni-server, simren-web, gymporn-web, gymporn-academy,
  trener-gymporn, trenuj, gym-cards-pos (pokladna), dochazka, trenink, gdpr-web
  (kngym-web na šabloně není – Laravel 13 bez manifestu a zdraví)
- RJ-B: rootklima-web, rootklima-nabidky, tatana-krizova (artkrizova.cz), exportex, reart, zapisnik

Ze šablony přes GitHub vznikly jen `trenink` a `gdpr-web` (oba 4. 10.), `zapisnik` je kopie
(RJ-B nevidí soukromou šablonu). Ostatní byly **převedené** – soubory šablony do nich přešly
ručně. Porovnání proto bere soubory šablony proti stavu v projektu a commity po převodu.

**Platí jen pro nové projekty.** Do existujících projektů se nic nepřenáší (rozhodnutí
vlastníka 4. 10. 2026); u každé položky je, kterých projektů by se týkala, kdyby se to
někdy rozhodlo jinak.

## Priorita 1 – chyby šablony

| # | Co | Proč | Odkud |
|---|---|---|---|
| 1 | `.env.example` na **MariaDB** (ne SQLite), `composer setup` založí lokální databázi podle složky | Na serveru je MariaDB a `php8.5` na simren-serveru nemá ani `pdo_sqlite`. Všude, kde `.env` vzniklo kopií `.env.example` bez přepsání databáze (starý deploy, převzetí `prod-adopt`, ruční instalace), skončil web na SQLite – rootklima i artkrizova běžely na produkci se SQLite a musely se převádět. Lokálně tak taky nikdo nezkusí MariaDB (generované sloupce, collation). | rootklima-web, tatana-krizova, všechny `.env.example` (21×) |
| 2 | **Výchozí hodnoty nastavení v `config/nastaveni.php`, ne v migracích** | Projekty plnily údaje firmy migrací (`insertOrIgnore` do `nastaveni`). Čerstvá produkce tak měla data a shim odmítl přenos dat z testu („web už má data (nastaveni)“). Výchozí hodnota z configu se čte, dokud ji správce nezmění – v databázi nic není. | exportex, rootklima-web (migrace `udaje_*`), rootklima-nabidky |
| 3 | `posta:kontrola` nezapisuje, když schránka není nastavená | Plánovač ji pouští každých 6 hodin a zapisoval `posta.kontrola` i bez schránky – stejný důsledek jako bod 2: produkce po prvním běhu plánovače „měla data“ a přenos z testu odmítla. | všechny projekty se šablonovou Poštou |
| 4 | Pošta: **heslo zašifrované jiným klíčem** = pošta nefunguje (incident), ne tiché přepnutí na `.env` | Po přenosu dat z testu, obnově zálohy nebo přesunu bez `APP_KEY` se heslo nedá rozšifrovat. Šablona to brala jako „schránka není nastavená“, takže maily šly do logu a portál nic nehlásil. Teď `simren:zdravi` hlásí `posta.ok = false` s návodem zadat heslo znovu. | reart (APP_KEY ze staré produkce kvůli heslu pošty, v testu pošta vynulovaná), přenos dat test → produkce |
| 5 | **Testovací web se neindexuje** (`X-Robots-Tag: noindex` a `robots.txt` `Disallow: /` mimo produkci), `robots.txt` a `sitemap.xml` z aplikace | Statický `public/robots.txt` všechno povoloval i na `*.test.simren.cz` – klientské weby měly duplicitní obsah ve vyhledávačích. Šest projektů si `sitemap.xml` a `robots.txt` psalo samo. | karty (noindex), exportex, rootklima-web, tatana-krizova, gymporn-web, gymporn-academy, trener-gymporn (sitemap) |
| 6 | Dokumentace: kontrola nasazení je `/zdravi` (CLAUDE.md tvrdilo `/up`), krok `filament` je součástí `cache` | Tři projekty přidaly do manifestu krok `filament`, který shim od 1.8.1 dělá v kroku `cache` sám. | gym-cards-pos, gymporn-web, reart |

## Priorita 2 – opakuje se ve 2+ projektech

| # | Co | Proč | Odkud |
|---|---|---|---|
| 7 | Odkazy přes **https** podle `APP_URL` (`URL::forceScheme`), HSTS podle `APP_URL` | Čtyři projekty dopsaly `forceScheme('https')` – za proxy Hestie vedly odkazy v e-mailech na http. HSTS šablona posílala jen při `isSecure()`, což za proxy nemusí platit. | exportex, rootklima-web, rootklima-nabidky, simren-web, trener-gymporn |
| 8 | **Kanonická doména**: `www.` a další domény webu trvale na doménu z `APP_URL` (`sablona.dalsi_domeny`) | Dva projekty napsaly totožný middleware `KanonickaDomena`. Hestia zakládá alias `www.` ke každé doméně – bez přesměrování je web dvakrát. | exportex, rootklima-web |
| 9 | **Čeština Laravelu** (`lang/cs`: validace, přihlášení, hesla, stránkování) | Šablona má `APP_LOCALE=cs`, ale překlady Laravelu nemá – hlášky validace mimo Filament se ukazovaly jako `validation.required`. | gym-cards-pos, nabidky, rootklima-nabidky, trener-gymporn, simren-moonshine-starter |
| 10 | **Převod dat ze staré aplikace**: spojení `stara` jen pro čtení, základ importního příkazu (`--nanecisto`, opakovatelný podle původních id, tabulka počtů zdroj × cíl) a převzetí hesel z cizích hashů (Spring `{bcrypt}`, `$2a$`/`$2b$`, PHP `password_hash`) | Skoro každý převod psal import znovu: WordPress (gdpr-web `wordpress`), staré PHP (trenink `trenink_stare`), SQLite → MariaDB (artkrizova skriptem), Java/Spring (CRM, zapisnik). Opakovaly se stejné věci – jen čtení, nanečisto, kontrola počtů, hesla. | gdpr-web, trenink, tatana-krizova, licencni-server, radio, CRM |
| 11 | **Další databáze aplikace** jednotně: `config/database.php` skládá spojení z předpony (`<PŘEDPONA>_DB_*`), `sablona.databaze` = spojení, která hlídá `/zdravi` | Sdílené účty GymPorn (`IDENTITY_DB_*`) měl každý projekt jinak (výchozí SQLite × MySQL, jiné collation), `/zdravi` o nich nevědělo a portál `.env` pro ně nezakládá. | gymporn-web, gymporn-academy, trener-gymporn |
| 12 | `phpunit.xml`: `memory_limit` 512M | Celá sada s Filamentem v jednom procesu přeleze 128 MB. | zapisnik, nabidky, rootklima-nabidky |
| 13 | Test, že `config.platform.php` v `composer.json` odpovídá `php` v manifestu | Lock vyřešený pro jiné PHP než server shodí nasazení až na serveru (dřív php8.3 vs. lokální 8.5). Dnes všechno sedí (8.5.0) – test hlídá, aby se to při změně jednoho nerozjelo. | paměť `laravel-composer-platforma-php` |

## Vědomě beze změny

- **Pošta přes libovolný SMTP (Forpsi)** – exportex si Poštu rozšířil o výběr poskytovatele.
  Pravidlo Sim&Ren je Seznam Email Profi; šablona host a port už ukládá, takže Forpsi jde
  nastavit i bez toho. Zvednout až u druhého projektu (rootklima má poštu taky u Forpsi).
- **Aplikace celá za přihlášením** (karty, rootklima-nabidky, trenink) – každý projekt to řeší
  jinak (vlastní `/prihlaseni`, přihlášení Filamentu, API pro mobil). Šablona má podobu
  „aplikace bez webu“ (`sablona.obsah_webu = null`); jednotný vzor až se ustálí.
- **Kompaktní boční menu** – rootklima-nabidky ho na přání klienta vrátila na výchozí
  velikost Filamentu; jeden případ, šablona zůstává jako CRM.
- **Přehled bez dlaždice Pošta** (rootklima-nabidky) – přání jednoho klienta.
- **Testy na MariaDB místo SQLite v paměti** – chytily by rozdíly typu generovaných sloupců,
  ale zpomalí sadu a potřebují databázi; zatím ne.
- **Česká collation** (`utf8mb4_uca1400_czech_ai_ci`) – CRM ji má, žádný projekt šablony ji
  nepotřeboval; změna výchozí collation by se projevila až na řazení, nechávám na rozhodnutí.
- **Hlídání `/zdravi` v portálu** – rádio hlídalo text titulní stránky, protože adresa dohledu
  v portálu se předvyplňuje adresou webu. To je věc portálu (adresa dohledu má vzít `kontrola`
  z manifestu), v šabloně je `/zdravi` správně.
- **Seznam šablon v CRM** – lokální CRM má v `github.sablony` i `SimRen-sro/simren-filament-starter`,
  které neexistuje; `simren-moonshine-starter` (sourozenec s MoonShine místo Filamentu, 1. 10.)
  nepoužívá žádné repo.

## Provedeno (4. 10. 2026)

| Body | Commit |
|---|---|
| 1, 13 (+ test manifestu bez kroku filament) | 2f468d7 MariaDB i lokálně |
| 2, 3 | e218b17 výchozí nastavení v config/nastaveni.php, `CistaInstalaceTest` |
| 4 | 6de2525 nečitelné heslo pošty = incident |
| 5, 7, 8 | d12cb1a noindex, robots.txt, sitemap.xml, https, `KanonickaDomena` |
| 9 | b35732f lang/cs |
| 10, 11 | 47d3033 `PrevodDat`, `PrevzetiHesla`, spojení `stara`, `sablona.databaze` |
| 6, 12 | dokumentace (`/zdravi`, krok `filament`), `memory_limit` v phpunit.xml |

## Vícejazyčnost (25. 10. 2026)

Rozhodnutí vlastníka 25. 10.: šablony bez textů natvrdo, nový jazyk jen přidáním překladu a řádku
v configu. **Upravené jsou jen šablony** – do projektů se zatím nic nepřenáší (sloučení naplánuje
hlídání šablon). Stejné řešení jako SaaS šablona (simren-saas-starter 34e7146), jádro má stejné klíče.
Návod: `docs/jazyky.md`.

| Co | Kde |
|---|---|
| Texty jen přes klíče `__('oblast.skupina.klic')`, čeština v `lang/cs/*.php`, angličtina celá v `lang/en/*.php`, formáty data `lang/*/formaty.php` | app, resources/views, lang |
| `config/jazyky.php` (`zapnute`, `vychozi`, `podle_prohlizece`), záložní jazyk čeština | config |
| Jazyk požadavku: uživatel (`users.jazyk`, Profil) → prohlížeč (vypnuto) → výchozí | `App\Support\Jazyky\Jazyky`, middleware `NastavJazyk` (web i panel) |
| E-maily v jazyce příjemce (`User` implementuje `HasLocalePreference`), `<html lang>`, `og:locale`, měna, data | `User`, views, `Platba` |
| Skupiny menu přes `App\Filament\Navigace`, vlastnosti Filamentu s textem → metody (vč. `getNavigationParentItem()` u Skupin příjemců) | Filament |
| Konstanty s texty → metody: `User::popiskyRoli()`, `Cileni::role()`, `NastaveniSeo::kategorie()`/`dny()`, `OchranaUdaju::vychozi()`, `TextyStavuWebu::vychozi()` | modely, Support |
| `php artisan jazyky:kontrola`, `php artisan jazyky:prevod`, test `JazykyTest` | `App\Support\Jazyky`, `app/Console/Commands/Jazyky*` |

### Co z toho převezmou projekty

Projekty na laravel šabloně (karty, radio, satny, st-car, korejsky-sen, exportex… – tabulka v portálu):
sloučit šablonu, převzít beze změny jazykovou část (`config/jazyky.php`, `App\Support\Jazyky\*`,
`NastavJazyk`, migraci `2026_10_25_100000_jazyk_uzivatele`, `App\Filament\Navigace`, `lang/cs|en` šablony,
`docs/jazyky.md`, `JazykyTest`), vlastní kód převést `php artisan jazyky:prevod` a vypsaná místa opravit ručně
(konstanty, porovnání, JS), volání změněných konstant (`User::ROLE_POPISKY` → `User::popiskyRoli()`…) přepsat.
Kdo angličtinu nepotřebuje, nechá v `config/jazyky.php` jen `cs` – `JazykyTest` pak kontroluje jen češtinu.

## Testovací schránka test@simren.cz a pojistka pošty na testu (26. 10. 2026)

Testovací weby posílají z test@simren.cz (přiděluje Pošta, heslo jen v Poště – portál) a nic z nich
neodejde ven. **Upravená je jen šablona** – do projektů se zatím nic nepřenáší (doslučování podle
hlídání šablon). Návod: `docs/testovaci-schranka.md`, kontrakt s portálem: žádné nové proměnné `.env`.

| Co | Kde |
|---|---|
| Na testu (`staging`; lokálně jen s testovacím odesílatelem) každý e-mail jen na test@simren.cz od test@simren.cz, bez kopií a Reply-To; původní příjemci v předmětu `[TEST → …]` a hlavičkách `X-Simren-Puvodni-*`. Platí pro každý mailer, frontu, notifikace i Poštu. Produkce nikdy. | `App\Support\Posta\TestovaciSchranka`, `PresmerovaniNaTest`, `App\Mail\LoggingMailManager` |
| `/zdravi` v produkci: odesílatel test@simren.cz (Pošta, `MAIL_FROM_ADDRESS`, `POSTA_OD`) = tvrdá chyba `posta_test` (503) | `App\Support\Zdravi` |
| `simren:zdravi --json` → `posta.od` a `posta.smtp_uzivatel` (jen adresy) pro pojistku portálu | `App\Support\Posta\StavPosty` |
| Přenos částí nepřenese nastavení s hodnotou test@simren.cz; po importu celé DB se stará schránka test@simren.cz a takové nastavení smaže (`testovaci_schranka`: `zapomenuta` / `zadna`) | `App\Support\Prenos\Casti`, `PoImportu` |
| Stará schránka test@simren.cz se Poště k převzetí nenabízí | `App\Support\Posta\StaraSchranka` |
| Texty `lang/*/posta.php` → `test_schranka`, test `TestovaciSchrankaTest` | lang, tests |

### Co z toho převezmou projekty

Sloučit šablonu: dvě nové třídy v `app/Support/Posta`, úpravy `LoggingMailManager`, `Zdravi`, `StavPosty`,
`Casti`, `PoImportu`, `StaraSchranka`, texty `posta.test_schranka`, `docs/testovaci-schranka.md`, test.
Projekt s vlastním `LoggingMailManager` nebo vlastním transportem musí obal `TestovaciSchranka::obal()`
zachovat navrchu. Nic se nemigruje, `.env` se nemění.

## E-mailová schránka a průvodce nastavením na Přehledu (27. 10. 2026)

Klient si sám nastaví schránku, ze které web posílá (jakoukoli – nemusí být na doméně webu), a na Přehledu
vidí, co ještě zbývá nastavit. **Upravená je jen šablona** – do projektů přenese MAIN samostatně.

| Co | Kde |
|---|---|
| Nastavení → **E-mailová schránka**: poskytovatel (Forpsi, Seznam / Email Profi, Gmail / Google Workspace, Microsoft 365, jiný SMTP) s krátkým návodem a předvyplněným serverem, adresa, jméno odesílatele, heslo, Uložit a ověřit, Přihlásit přes Google / Microsoft (když to Pošta umí), zkušební e-mail, DNS domény (SPF / DKIM / DMARC – co doplnit; u gmail.com, seznam.cz… jen informace), **Vrátit na systémovou schránku**. Jen správce. | `App\Filament\Pages\EmailovaSchranka`, `resources/views/filament/pruvodce/dns.blade.php` |
| API Pošty `/api/v1/schranka` (kontrakt „E-mailová schránka z webu“, side-session-notes `schranka-z-webu-kontrakt.md`): PUT nejdřív ověří přihlášení, nepovedené nic neuloží; DELETE = zpět na systémovou; testovací web jen čte (409 / `smi_menit: false`). Heslo jen server → Pošta, web ho neukládá (DB, log ani cache), v cache 10 min jen stav. Pole `kdo` = jméno a příjmení správce. | `App\Support\Posta\SchrankaWebu` |
| Poskytovatelé v configu (klíče = předvolby Pošty), texty v `lang/*/pruvodce.php` → `poskytovatele` (`<klic>_nazev`, `<klic>_navod`) | `config/posta.php` → `poskytovatele`, `App\Support\Posta\Poskytovatele` |
| Přehled → **Dokončete nastavení**: kroky se zaškrtnutím (údaje firmy, e-mailová schránka, právní texty, ikona a logo, SEO / Google, druhý krok přihlášení, platby jen s doplňkem). Zmizí sám, když je vše hotovo; Skrýt (pro celý web, `pruvodce.skryty`), na Přehledu zase tlačítko Průvodce nastavením. Jen správce. Krok, který nejde zjistit, není hotový. | `config/pruvodce.php`, `App\Support\Pruvodce\{Pruvodce,Krok,Kroky\*}`, `App\Filament\Widgets\DokonceteNastaveni` |
| Testy | `EmailovaSchrankaTest`, `PruvodceNastaveniTest` |

### Převzetí do projektů

1. Sloučit šablonu (nové soubory výše, `config/posta.php` → `poskytovatele`, `lang/cs|en/pruvodce.php`, `Prehled` s tlačítkem průvodce).
2. Kroky průvodce projekt upraví jen v `config/pruvodce.php` (vlastní krok = třída nad `Krok`: `nazev`, `popis`, `url`, `hotovo`, případně `zobrazit`).
   Projekt bez některé stránky (např. bez SEO) krok jen vyškrtne.
3. API `/api/v1/schranka` musí být v Poště nasazené (kontrakt → „NASAZENO“), jinak stránka ukáže, že nastavení teď nejde načíst.
4. Nic se nemigruje, `.env` se nemění.

## Oprava: DruhyKrokTest nezávisle na místním .env (27. 10. 2026)

Testy druhého kroku padaly na počítači, kde má `.env` adresu s IP (`APP_URL=http://127.0.0.1:8360`): požadavek testu
vznikl s touto adresou a passkey na IP adrese nejde (`DruhyKrok::passkeyNaTetoAdrese`) – passkey se nenabídl a ověření
skončilo 422. Kód je v pořádku (na IP adrese passkey opravdu nejde), chyba byla v testech: braly adresu z `.env`.

| Co | Kde |
|---|---|
| `<env name="APP_URL" value="http://localhost" force="true"/>` – testy mají vždy stejnou adresu | `phpunit.xml` |

**Převzetí do projektů:** stejný řádek do `phpunit.xml` (projekty ho zdědily). Kód se nemění.

## Odebrána stránka „E-mail podle účelu“ (27. 10. 2026)

Web má jen jednu schránku (Nastavení → E-mailová schránka). Víc schránek firmy a co z které chodí nastaví Sim&Ren
ručně v portálu nebo v Poště – účely v Poště zůstávají a web je s maily dál posílá (`UcelZpravy`, `config/posta.php` → `ucely`).

| Co | Kde |
|---|---|
| Smazaná stránka Nastavení → E-mail (účely, víc schránek firmy) a její texty (`administrace.email`) | `App\Filament\Pages\Email`, její pohled, `App\Support\Posta\SchrankyFirmy` |
| Z E-mailové schránky zmizel odkaz „E-mail podle účelu“ (texty `pruvodce.emailova_schranka.zvlast_podle_ucelu`, `e_mail_podle_ucelu`) | pohled E-mailové schránky |
| Test jen na účel zprávy | `EmailFirmyTest` |

**Převzetí do projektů:** smazat stejné soubory a texty, odebrat odkaz. Projekt, který stránku sám upravoval, ji smaže
taky – nastavení schránek firmy zůstává v Poště. Nic se nemigruje, `.env` se nemění.

## Logo a ikona: jedno pro web i administraci (31. 10. 2026)

Krok průvodce „Ikona a logo“ vedl na Hlavičku a patičku, ale na webech s logem pevně ve vzhledu (exportex) tam
nahrávání nebylo, a administrace ukazovala výchozí ikonu místo loga webu. Teď je logo jedno (`App\Support\LogoWebu`)
a administrace ho bere sama – nic se pro ni nenastavuje zvlášť.

| Co | Kde |
|---|---|
| Logo webu odkudkoli: pevné ve vzhledu webu (`config/sablona.php` → `vzhled.logo`, `vzhled.ikona`, soubory v `public/`), jinak nahrané v Hlavičce a patičce | `App\Support\LogoWebu`, `config/sablona.php` |
| Administrace (levé menu, přihlášení): stejné logo jako web – široké (nápis) samo, užší značka s názvem vedle; bez loga výchozí ikona a název | `resources/views/filament/rozlozeni/znacka.blade.php`, `public/css/rozlozeni.css` |
| Favicon webu i administrace z ikony, jinak z loga (pevná ikona se neořezává – je to hotový čtverec); z pevného loga vznikne sám při prvním zobrazení a znovu, když je soubor novější (SVG GD nepřečte → další zdroj) | `App\Support\IkonaWebu` |
| Hlavička a patička: samostatná sekce **Logo a ikona** s kotvou `#logo` (krok průvodce vede přesně sem), nahrané logo a ikona se ukládají jako WebP (`Fotky::pole`); s pevným logem se sekce neukazuje | `App\Filament\Pages\HlavickaPaticka` |
| Krok „Ikona a logo“: odkaz na `#logo`, hotovo = nahrané logo, s pevným logem se neukazuje (`zobrazit()`) | `App\Support\Pruvodce\Kroky\IkonaALogo` |
| Testy | `IkonaWebuTest` |

**Převzetí do projektů:** soubory výše a texty `administrace.hlavicka_paticka.logo_a_ikona`, `logo_plati_pro_web_i_administraci`.
Projekt s logem pevně ve vzhledu webu vyplní `vzhled.logo` (a čtvercovou `vzhled.ikona`, má-li ji); jeho layout webu může
dál mít vlastní ikony. Nic se nemigruje, `.env` se nemění.
