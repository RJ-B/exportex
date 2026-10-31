# exportex.cz

Prezentační web **exportex s.r.o.** – textil z Uzbekistánu a Střední Asie do EU
(sortiment, postup, trasa, doklady a clo, o nás, ukázky zakázek a poptávkový formulář).
Jednostránkový, česky a anglicky (přepínač CZ / EN), světlý i tmavý režim.

Postavený na šabloně Sim&Ren (`SimRen-sro/simren-laravel-starter`): Laravel 13,
Filament 5 (administrace na `/admin`), PHP 8.5, MariaDB. Pravidla šablony platí –
viz `CLAUDE.md`. Do 10/2026 to byl statický web na GitHub Pages; vzhled a chování
pro návštěvníka zůstaly stejné.

- Bez Node.js a bez buildu: vzhled je hotové CSS a JS v `public/assets`
  (`css/style.css`, `js/main.js`), stránky skládá Blade.
- Nasazení: commit na `main` → CRM → portál (`.simren/projekt.yml`, kontrola `/zdravi`,
  při chybě návrat). Postup a co je potřeba na serveru: `NASAZENI.md`.

## Lokálně

```bash
composer install
cp .env.example .env && php artisan key:generate   # SQLite stačí
touch database/database.sqlite && php artisan migrate && php artisan storage:link
php artisan db:seed --class=PoptavkySeeder   # nepovinné: dvě vymyšlené poptávky (jen lokálně)
php artisan serve
```

Účet do administrace: `php artisan simren:spravce $(printf '{"email":"ty@firma.cz","jmeno":"Jan","prijmeni":"Novák"}' | base64)`
vrátí odkaz na nastavení hesla. Testy: `php artisan test </dev/null`.

## Administrace

```
Přehled · Zobrazit web · Zprávy z webu (poptávky z formuláře – firma, jazyk webu)
Obsah webu   Hlavička a patička (název, kontakty, provozovatel, patička CZ/EN, sekce na webu – pořadí a vypnutí)
             · Úvod · Sortiment · Jak to funguje · Trasa · Doklady a clo · O nás · Ukázky zakázek
             · Kontakt a formulář (propojení s Poštou, kam chodí poptávky) · Kontakt – texty
             · Ochrana osobních údajů · SEO a měření
Nastavení    Uživatelé
Provoz       Logy · Stav webu (jen superadmin)
Přehled · Zobrazit web · Oznámení (+ Skupiny příjemců)
```

Každý text má pole česky a vedle anglicky. Co se v administraci nemění: popisky
ovládání (filtr, formulář), mapa trasy, SEO pro sdílení a pevná část strukturovaných
dat (`config/web.php`).

## Vzhled

Celý web je postavený na **liquid glass** – průsvitné vrstvy s rozostřeným pozadím
(`backdrop-filter`) a světelným okrajem, který se kreslí gradientem přes
`mask-composite: exclude` v `.glass::before`. Aby mělo sklo co rozostřovat, mají
sekce v pozadí měkké barevné záře (`.section::before`). Prohlížeče bez
`backdrop-filter` dostanou plnou výplň přes `@supports not`.

Firemní červená je odečtená přímo z loga: **`#B0221A`**. Světlejší odstíny pro text
(`--ac-l: #E5544A`) jsou doladěné ručně. Všechny barvy, rádiusy, stíny a časování jsou
tokeny v `:root` na začátku `style.css` – změna vzhledu se dělá tam.

Písma **Outfit** (text) a **JetBrains Mono** (popisky, čísla) jsou hostovaná na webu
(`public/assets/fonts`) – IP návštěvníka se neposílá Googlu.

### Světlý a tmavý režim

Přepíná se tlačítkem v hlavičce. **Výchozí je světlý** – atribut `data-theme="light"`
je rovnou v `<html>`; `theme-init.js` ho v hlavičce jen odebere, pokud si návštěvník
dřív zvolil tmavý. Ve světlém režimu zůstávají tmavé jen **hero** a **hlavička, dokud
není přilepená** (leží na fotce) – lokální přepsání proměnných na `.hero`
a `.hdr:not(.is-stuck)`.

### Jazyk

Prvek nese obě podoby v `data-cs` a `data-en` (`App\Support\Preklad`), viditelně je
čeština; přepínač prohodí `innerHTML`. Opakující se obsah (sortiment, kroky, trasa,
doklady, ukázky, popisky formuláře) dostává `main.js` jako JSON v
`<script type="application/json" id="exportex-data">` (`ObsahWebu::proSkript()`).
Delší texty (ochrana údajů, cookies) mají každý jazyk ve vlastním bloku `data-jazyk`.
Angličtina má vlastní adresu `?lang=en` (hreflang).

**Volby návštěvníka se ukládají, jen když je sám klikne** (`exportex-lang-v2`,
`exportex-theme-v2` v localStorage; pozice na stránce `exportex-scroll` v sessionStorage).

### Mapa trasy

Oblouk **Uzbekistán → Evropa** bez mezizastávek; celá Evropa je zvýrazněná jako oblast
rozvozu, šipka míří do Česka. Podklad z Natural Earth (world-atlas 50m) je vložený
v `resources/views/web/_mapa.blade.php` (~51 kB). Projekce
`x = 100 + 7.0711 · délka`, `y = 800 − 10 · šířka`; oblouk, body a popisky kreslí
`renderRoute()` v `main.js`.

### Sortiment

Detail je **bodový, ne odstavcový**: body jsou krátké věty, parametry dlaždice s čísly.
Body a dlaždice se nesmí opakovat – co je v dlaždici, do bodů nepatří.

## Poptávkový formulář

Odesílá JSON na `/kontakt` (`KontaktController`) – poptávka se uloží do **Zprávy z webu**
a upozornění odejde e-mailem na kontaktní e-mail (mikyska@exportex.cz) přes Poštu
(posta.simren.cz, schránka Forpsi). Dřív šla přes formsubmit.co – to je pryč.
Ochrana proti spamu: skryté pole a podepsaná časová past (`OchranaFormulare`), limit
3/min a 20/den z jedné IP, serverová validace; žádná captcha.
- **Oznámení** ([docs/oznameni.md](docs/oznameni.md)): zpráva žije jednou na serveru a k lidem dojde kanály –
  **centrum** (zvoneček na webu `@include('oznameni.zvonecek')` i v administraci, stránka `/oznameni`, Moje oznámení),
  **pruh** přes web a administraci (`@include('oznameni.pruh')` hned za `<body>` – odstávky s odpočtem, výpadky; pro
  všechny ho vidí i nepřihlášení) a **e-mail přes Poštu**. Administrace → Oznámení: koncept, náhled, zkouška sobě,
  cílení (všem / role / skupiny / víc vybraných lidí, každý jednou, superadmini nikdy hromadně), naplánování,
  odeslání s potvrzením počtu příjemců a limitem, čísla (přečteno, prokliknuto, doručeno). Z kódu
  `Oznam::provozni('…')->komu($user)->klic('…')->posli()`. Předvolby druh × kanál, novinky e-mailem jen se souhlasem
  (záznam v `oznameni_souhlasy`), odhlášení jedním kliknutím (List-Unsubscribe-Post), marketing ve výchozím stavu
  vypnutý (Oznámení → Nastavení, superadmin). E-maily po dávkách ve frontě. Web push a mobil – krok 3.
- **Doplněk Platby** ([docs/platby.md](docs/platby.md)) – volitelná platební brána (Comgate REST 2.0, Mo.one, lokálně simulace), ve výchozím stavu vypnutá (`config/sablona.php` → `doplnky.platby`). Test platí vždy testovací bránou Sim&Ren (Mo.one test), produkce ostře jen s ověřenými údaji klienta; štítek „TESTOVACÍ PLATBY“, stav vždy ověřený dotazem na bránu, idempotence a zámky proti dvojímu zaplacení, historie stavů, vrácení, Platby a Nastavení → Platební brána v administraci.

---

Zbytek tohoto README je popis šablony Sim&Ren (`simren-laravel-starter` v1.0.1).

## Kostra administrace (stejná ve všech projektech)

```
Přehled · Zobrazit web · Oznámení (+ Skupiny příjemců)
<sekce projektu>     s čím se pracuje denně (poptávky, rezervace, objednávky, blog…) – PŘED 'Obsah webu'
Obsah webu           co se nastaví jednou: Hlavička a patička · <sekce webu projektu> · Kontakt a formulář
                     · Ochrana osobních údajů · SEO a měření
Nastavení (sbalené)  Uživatelé · E-mailová schránka (u aplikace bez webu i Pošta – propojení s posta.simren.cz)
──────────────────── (dole v menu, za čarou)
Správa aplikace      Logy · Stav webu – jen superadmin (ostatní skupinu nevidí)
```

**Rozložení administrace** (stejné ve všech aplikacích Sim&Ren, `App\Filament\Rozlozeni`,
`public/css/rozlozeni.css`, `public/js/rozlozeni.js`):

- levé menu na celou výšku – nahoře logo (ikona z loga webu) a název aplikace, dole za čarou
  **Správa aplikace** jen pro superadmina (`Rozlozeni::SPRAVA_APLIKACE` jako `$navigationGroup`),
- horní lišta – vlevo **drobečková navigace** (drobečky stránky, jinak skupina › položka menu),
  vpravo zvoneček oznámení a avatar s uživatelským menu (jméno, Profil, Zabezpečení, Odhlásit),
- **bez globálního vyhledávání** (`globalSearch(false)`) – hledá se v tabulkách,
- mobil: vlevo logo, vpravo zvoneček a ☰ úplně vpravo; menu vyjede zprava a nahoře má účet
  (jméno, Profil, Zabezpečení, Odhlásit). Slovo se neláme – dlouhý název se zkrátí třemi tečkami.

Projekt s vlastním panelem zavolá `Rozlozeni::nastav($panel)`; další položky lišty vpravo (přepínač
firem…) patří do render hooku `USER_MENU_BEFORE` před zvoneček.

**Zabezpečení – druhý krok přihlášení** (`App\Bezpecnost\DruhyKrok`, uživatelské menu → Zabezpečení):
po hesle **passkey** (Touch ID, Face ID, Windows Hello, telefon spárovaný QR kódem) nebo **kód
z Authenticatoru** (TOTP, Filament `AppAuthentication`) s obnovovacími kódy. Každý si zapne jedno,
nebo obojí; výzva nabídne nejdřív passkey a když ho zařízení neumí nebo selže, kód. Přihlášení jen
passkeyem bez hesla aplikace nemá (trasy `laravel/passkeys` jsou vypnuté). Passkey patří doméně
z `APP_URL` (lokálně jen `localhost`, ne 127.0.0.1) – na jiné adrese se nenabídne. Přidání
a odebrání zařízení chce heslo znovu (10 minut); telefon se páruje jednorázovým odkazem
`/ucet/parovani/{token}` (v databázi jen otisk, 15 minut, nikoho nepřihlásí). Kdo přijde o telefon
i obnovovací kódy, tomu správce v Uživatelích dá **Zrušit druhý krok**. Odkaz na nastavení hesla
druhý krok neobejde (přihlásí se znovu). Tajemství Authenticatoru, passkeye ani párování se
přenosem dat z testu nepřenáší (`Casti::NIKDY_*`).

**Podoba Obsahu webu** – `config/sablona.php`, `obsah_webu`:
`'menu'` jednoduchý web (sekce přímo v menu), `'sekce'` web s provozem (jedna položka, uvnitř lišta),
`null` aplikace bez webu. Stránku nebo resource zařadí do Obsahu webu trait `CastObsahuWebu`.

**Sekce webu** (Služby, Realizace…): v traitu `klicSekce()` (vypínatelná) a `odkazSekce()` (adresa
na webu). Pak jde v Hlavičce a patičce zapnout/vypnout a seřadit přetažením – pořadí platí pro menu
webu (`SekceWebu::menu()`) i administraci. Veřejné routy sekce `->middleware('sekce:<klíč>')`
(vypnutá = 404, přihlášený vidí), v šablonách `@sekce('<klíč>') … @endsekce`.
Hlavička a patička a Ochrana osobních údajů vypnout nejde.

**Ochrana osobních údajů** (`/ochrana-osobnich-udaju`, přístupná v každém stavu webu): vzor podle
simren.cz (`pravni/_ochrana-obsah`), skládá se sám ze Základních údajů a z toho, co web dělá
(formulář, měření Google/Seznam, smlouvy); k doplnění jen to, co se liší (Obsah webu → Ochrana
osobních údajů, `OchranaUdaju`). Projekt s vlastním vzhledem vloží `@include('pravni._ochrana-obsah')`.

**Kontaktní formulář** (`/kontakt`, sekce Kontakt; `@include('formulare.kontakt')`): zprávy v menu
Zprávy z webu (odznak nepřečtených), upozornění na příjemce z Kontakt a formulář (odpověď rovnou
odesílateli), validace s českými hláškami (`OdeslaniZpravy`). **Proti botům** pro jakýkoli formulář
`@include('formulare._ochrana')` + `OchranaFormulare::jeBot()`: skryté pole a podepsaná časová past
(< 3 s nebo > 2 h = bot; botovi se tváří odesláno), limit `throttle:formular` 3/min a 20/den z IP.

**Vyhledávače a doména**: testovací web se neindexuje – `X-Robots-Tag`, meta robots i `robots.txt`
podle jednoho pravidla `MapaWebu::indexovat()` (jen produkce ve stavu Online; administrace, nastavení hesla
a platby nikde). `/robots.txt` (s odkazem na sitemap) a `/sitemap.xml` skládá aplikace (`App\Support\MapaWebu`
– úvod, zapnuté sekce, právní stránky s `lastmod` v ISO 8601 s posunem a obrázkem stránky; projekt doplní
svoje stránky v `polozky()`). Statický `public/robots.txt` nepatří (server by ho vrátil dřív). Web má jednu
doménu – tu z `APP_URL`: `www.` a `sablona.dalsi_domeny` jdou trvale na ni (`KanonickaDomena`),
odkazy jsou přes https, když `APP_URL` je https (i za proxy Hestie a v mailech z fronty).

**SEO a strukturovaná data** (`App\Support\Seo`): do `<head>` každého layoutu webu místo `<title>`
`{{ \App\Support\Seo\Seo::stranka('Kontakt', klic: 'formular')->popis($perex)->html() }}`
(úvod `Seo::uvod()`). Vypíše `<title>`, meta description, robots, canonical (doména z `APP_URL`, bez
parametrů), Open Graph (title, description, image s rozměry a typem, url, type, locale `cs_CZ`, site_name),
Twitter Card (`summary_large_image`) a jeden JSON-LD `@graph`: provozovatel (`Organization`, nebo
`LocalBusiness` s podtypem podle druhu firmy – adresa provozovny, otevírací doba, poloha), `WebSite`
(`SearchAction` jen s `sablona.vyhledavani`), `WebPage`, na podstránkách `BreadcrumbList`
(`->drobecky([...])`). Články a produkty: `->typ('article')->data(StrukturovanaData::clanek(…))`,
`StrukturovanaData::produkt(…)` (Product + Offer). Vše z údajů webu (Hlavička a patička, SEO a měření) –
co vyplněné není, ve výstupu chybí. Obsah webu → **SEO a měření**: výchozí popis a **obrázek pro sdílení**
(nahrání → JPEG 1200 × 630, `Fotky::ulozSdileni` – výjimka z WebP, LinkedIn a další sítě WebP nečtou; starší WebP se pro og:image převede na JPEG kopii sám, `Fotky::jpegSdileni`), typ provozovatele (Organizace / Místní firma + druh,
otevírací doba po řádcích, poloha), vlastní titulek / popis / obrázek každé stránky webu a **náhled, jak
to uvidí Google a Facebook**. Obsah projektu (článek, služba) dostane totéž do svého formuláře:
sloupec `seo` (JSON) + `SeoPole::sekce()` a na stránce `Seo::stranka(...)->zModelu($clanek)`.
hreflang šablona nemá (web je jen česky).

**Ikona v záložce** (favicon) vzniká z loga – nebo z nepovinné Ikony v záložce vedle něj, když je logo
široké – při uložení Hlavičky a patičky (`App\Support\IkonaWebu`, jen GD): čtvercová PNG 32, 180
(iPhone, bílé pozadí) a 192 px v `storage/app/public/web/ikona`. Bez loga platí výchozí neutrální ikona
z `public/` (`ikona-*.png`, `favicon.ico`). Do `<head>` každého layoutu webu patří
`{{ \App\Support\IkonaWebu::html() }}` (šablona ho má ve všech svých stránkách i v administraci).
Logo je jedno pro web i administraci (`App\Support\LogoWebu`): administrace ho v levém menu a na přihlášení
ukazuje sama. Web s logem pevně ve vzhledu ho zapíše do `config/sablona.php` → `vzhled` (`logo`, čtvercová
`ikona`) – pak se v administraci nenahrává a krok průvodce „Ikona a logo“ se neukazuje.

**Bezpečnost**: `BezpecnostniHlavicky` na webu i v administraci (X-Frame-Options, nosniff,
Referrer-Policy, Permissions-Policy, HSTS na produkci podle `APP_URL`), session cookie na produkci jen přes HTTPS,
přihlášení do administrace omezené Filamentem (5 pokusů/min), role není hromadně přiřaditelná,
hesla (i zašifrovaná) mimo Aktivitu, logo bez SVG, vlastní HTML v textech čištěné (`sanitizeHtml`).

**Cookie lišta** (`@include('pravni.cookie-lista')` na konec každé veřejné stránky, tlačítko
`data-cc="open"` = Nastavení cookies, vzor podle simren.cz): Consent Mode v2, měřicí kódy do souhlasu
inertní, „Přijmout“ i „Odmítnout“ stejně nápadné, souhlas 6 měsíců. Ukáže se, jen když web měří
(SEO a měření: GA4, Seznam, Google Ads, Sklik); po přidání nástroje se zeptá znovu. **Zásady používání
cookies** (`/zasady-cookies`) se skládají samy podle nástrojů. Barvy: `--cc-vlastni-akcent` a spol.

Stav webu (Online / Připravujeme / Údržba, `App\Enums\StavWebu`) hlídá `StavWebuMiddleware` na veřejných
routách (`routes/web.php` – nové veřejné routy do jeho skupiny); přihlášený vidí web vždy, administrace,
právní stránky a `/zdravi` běží v každém stavu.

## Převod dat ze staré aplikace

Starou databázi čte spojení `stara` (`STARA_DB_*` v `.env`, starý SQLite soubor přes
`STARA_DB_CONNECTION=sqlite`) – jen pro čtení. Příkaz převodu dědí `App\Console\PrevodDat`
(vzor v jeho komentáři) a píše jen kroky `tabulka('co', $stara->table(…), fn ($r) => …, fn () => počet v cíli)`.
Základ pustí všechno v jedné transakci, `--nanecisto` ji na konci vrátí, vypíše souhrn
ve staré × zapsáno × přeskočeno × v nové a když počty nesedí, nic neuloží. Opakovatelnost:
zapisovat `updateOrCreate` podle původního id (`puvodni_id`) nebo přirozeného klíče.

Hesla přes `$this->heslo($hash)` (`App\Support\PrevzetiHesla`): PHP `password_hash`, Spring
`{bcrypt}$2a$…` a `$2b$` převezme (Laravel jinak `$2a$` neověří a cast `hashed` by zahashoval
hash), WordPress, Argon a ostatní dostanou null – heslo si nastaví znovu; souhrn řekne kolik.
Po převodu `STARA_DB_*` z `.env` smazat.

**Další databáze, na které web stojí** (sdílené účty…): spojení z předpony v `config/database.php`
(`'identita' => $dalsi('IDENTITY_')`), proměnné `IDENTITY_DB_*` doplnit do `.env` na serveru
(portál zakládá jen hlavní `DB_*`) a jméno spojení do `sablona.databaze` – `/zdravi` ho pak
kontroluje stejně jako hlavní databázi.

## Přenos dat mezi weby (test → produkce)

V portálu u produkčního webu **Přenést data z testu**: vybrat části a u každé režim, portál
nejdřív ukáže náhled (co přibude, změní se, smaže), po potvrzení přenese. Shim přitom
spouští `php artisan simren:prenos casti|export|import|uklid` (`App\Support\Prenos`).

- **Části** jsou v `config/prenos.php` – šablona má Údaje firmy, Nastavení webu a Uživatele
  (výchozí vypnuto, jen doplnit/aktualizovat). **Nová tabulka s obsahem = nová část nebo
  tabulka v části**, jinak ji přenos nevidí. Řádky se párují podle `klic` (slug, e-mail…), vazby
  na id se přečíslují (`vazby`), fotky a soubory, na které řádky odkazují, jdou s nimi.
  Víc možností klíče (`[['puvodni_id'], ['nazev']]`) se páruje předem proti produkci před importem,
  silnější možnost má přednost a **řádek produkce se spáruje nejvýš s jedním řádkem testu** – druhý je
  konflikt (`konflikty`, `konflikty_popis` v náhledu), přeskočí se a nic nepřepíše.
- **Režimy:** *doplnit* (jen chybějící, nic existujícího nemění), *aktualizovat* (chybějící přidá,
  existující přepíše, co je jen v cíli, nechá), *nahradit* (část v cíli smaže a nahradí kopií).
- Nikdy se nepřenáší logy, fronta, sezení, platby ani klíče Pošty a platební brány a stav webu
  (`Casti::NIKDY_*`).
- **Šifrované hodnoty** (`Crypt`, cast `encrypted` – klíče brány, Authenticator) jsou na webu s jiným
  `APP_KEY` nečitelné a čitelně ze serveru nikdy neodcházejí: export je vynechá a výsledek je vyjmenuje
  (`sifrovane_vynechane` – jen z řádků, které by se opravdu přenesly, každou hodnotu jednou), na cíli
  zůstane jeho hodnota. Po importu celé databáze (Přenést data z testu
  při předání z CRM) shim spustí `simren:prenos po-importu`: klíče a odchozí fronta Pošty zdroje se
  zahodí (portál web propojí znovu) a nečitelné šifrované hodnoty se vymažou a vyjmenují
  (`sifrovane.vymazano`) – zadají se znovu (`App\Support\Prenos\Sifrovane`).
- Databáze v jedné transakci, před importem záloha (shim), záznam do Aktivity `data.prevzata`.

## Sdílené databáze a sdílené přihlášení (SSO)

**Každý projekt má vlastní databázi** – tu zakládá portál (`DB_*`), zálohuje ji podle manifestu
a přenáší z testu do produkce. Další databáze (převod ze staré aplikace, sdílené účty) jsou výjimka:
spojení z předpony (viz výš), proměnné doplnit do `.env` na serveru ručně, jméno spojení do
`sablona.databaze` (hlídá je `/zdravi`). Portál je nezakládá a **přenos dat z testu je nepřenáší** –
přenáší se jen hlavní databáze a `storage/app`. Zálohu sdílené databáze dělá Server Zálohy
(dump všech databází serveru, plán `*-db`): sdílená databáze proto musí ležet na serveru se
zálohami a po založení se v portálu (Zálohy → databáze) ověří, že v posledním snímku je.

**Sdílené přihlášení pro víc projektů jednoho klienta je speciální případ**, ne součást šablony
(v CRM doplněk „Sdílené přihlášení (SSO)“ – zatím připravujeme). Doporučený tvar: **identita jako
samostatná aplikace** (vlastní repo, databáze a zálohy) a ostatní projekty se k ní přihlašují přes
**OIDC/OAuth** – žádný projekt nečte cizí databázi. Sdílené pohledy do databáze účtů (GymPorn,
„varianta B“ s `users` jako pohledem do identity) zůstávají, kde už jsou, ale nové projekty je
nedostávají: přenos dat, obnova ze zálohy i migrace jednoho projektu tam zasahují do ostatních.
Kdo SSO cookie na doméně klienta zapíná, ať počítá s tím, že na `*.test.simren.cz` by platila pro
testy všech klientů – v testu vypnout.

## Co je jinak než v čistém Laravelu

- `.simren/sablona.yml` – popis šablony pro katalog v CRM (název, jedna věta, druhy zakázek, co obsahuje,
  test, balíčky, vzhledy administrace). CRM ho čte z repa šablony; v projektu nic nedělá (`SablonaKatalogTest`
  hlídá, aby šel přečíst).
- **Seedery nikdy na serveru:** `DatabaseSeeder` nezakládá nic (`db:seed --force` na serveru je neškodné),
  testovací data jen v seederu se zábranou `JenLokalne` (vzor `UkazkovaDataSeeder`, mimo local/testing
  vyhodí výjimku dřív, než cokoli zapíše; `SeederyJenLokalneTest` hlídá i nové seedery).
- **Žádné prvky ve vzhledu prohlížeče:** Select, filtry a výběr data ve Filamentu `native(false)` globálně
  (`AppServiceProvider::bezNativnichPrvku`), natvrdo kreslené `<select>` (stránkování, řazení) převleče
  `public/js/vyber.js` + `public/css/vyber.css`; na webu vlastní pole, roletka stejně vysoká jako pole
  a zaškrtávátka i přepínače v cookie liště (`ZadneNativniPrvkyTest`).

- `.simren/projekt.yml` – manifest pro portál (prostředí, kroky nasazení, zálohy).
- `/zdravi` a `simren:zdravi --json` – kontrola pro portál (databáze, cache, storage, plánovač, fronta, chyby).
- `simren:chyby --json [--od=<unix čas>]` – chyby aplikace po skupinách (otisk z `ErrorLogger`) pro portál: každá skupina je v portálu incident (výjimka, zpráva, soubor:řádek, URL, počet, poprvé/naposledy, trace). **Vyřešeno jde oběma směry:** Vyřešeno / Vrátit v portálu zapíše shim (`simren:chyby --vyresit=<otisk>` s kdo a poznámkou na stdin, `--vratit=<otisk>`), Vyřešeno (s poznámkou „Co se udělalo“) / Vrátit zpět v Logy → Chyby si portál přečte; nový výskyt chybu vrátí mezi nevyřešené na obou místech. Shim příkaz pozná podle `app/Support/ChybyAplikace.php` – nepřejmenovávat.
- `simren:udrzba stav|zapnout|vypnout|pripravujeme|spustit --json [--vstup]` – Stav webu „Údržba“ a „Připravujeme“ pro portál a CRM (`App\Support\UdrzbaWebu`): u instalace je jedno tlačítko Vypnout údržbu, které vypne artisan down i Stav webu. `zapnout`/`vypnout` jen Online ↔ Údržba („Připravujeme“ se nemění), `pripravujeme` Online → Připravujeme (nová produkce na doméně klienta před předáním), `spustit` Připravujeme → Online (předání). S `--vstup` čte ze standardního vstupu JSON `{text, konec}` – text pro návštěvníky a předpokládaný konec údržby (ISO 8601) z CRM; stránka Údržba pak ukáže text a „Hotovo přibližně v 14:30“, `Retry-After` do konce. Uloží se jako `web.udrzba.text` / `web.udrzba.konec`, vypnutí nebo ruční přepnutí v administraci je smaže. Změna jde do Aktivity. Portál příkaz pozná podle souboru `app/Support/UdrzbaWebu.php`, text a Připravujeme podle konstanty `S_TEXTEM` v něm. Přihlášený (správce) vidí web v každém stavu, `/zdravi` běží vždy.
- `simren:spravce` – účet správce z CRM (Můj účet správce): nový účet (nebo bez hesla) – heslo nikdo nezná, přijde odkaz na nastavení hesla (`reset_url`); **účet s heslem heslo nikdy nemění** a dostane jednorázové přihlášení rovnou do administrace (`login_url`, `/prihlaseni-spravce/{token}`, `App\Support\PrihlaseniZCrm`: podepsaný, 5 minut, jen jednou, v cache jen otisk; jen superadmin, druhý krok neobejde). Seedery superadminy nezakládají.
- `simren:recenzent --json` – účet pro kontrolu obchodu (App Store, Google Play) `test@<ostrá doména>`: CRM ho u zakázky s mobilní aplikací založí tlačítkem Můj účet správce (portál → shim, JSON `{email, heslo}` jen na stdin). Běžný uživatel s ověřeným e-mailem a výchozím heslem z portálu, sloupec `users.recenzent` – mimo statistiky jako superadmin (`User::bezSuperadminu`, `User::mimoStatistiky`). Data k vyzkoušení si aplikace doplní posluchačem `App\Events\RecenzentPripraven` (idempotentně). Správce se na recenzenta nepředělá.
- Role `users.role`: superadmin (vývojáři Sim&Ren) > admin (majitel) > klient; Administrace → Uživatelé.
- Správa aplikace → Logy (jen superadmin): Aktivita (kdo co změnil, `AuditableObserver`), E-maily (každý odchozí mail; stav a chyby doplňuje Pošta, „Poslat znovu“ zařadí nedoručený mail v Poště znovu), Chyby (agregované podle otisku). Retence v Logy → Aktivita.
- **Pošta** (posta.simren.cz): aplikace neposílá přes vlastní SMTP, ale přes Poštu – mail transport `posta` (`App\Support\Posta`, `PostaServiceProvider`), `Mail::` beze změny. Administrace → Pošta (správce): **Propojit s poštou** (autorizační kód + PKCE, klíče šifrovaně), výchozí odesílatel z adres, které přidělil správce Pošty, zkušební e-mail. Schránky, DNS domén (SPF, DKIM, DMARC), opakování a kopii do Odeslaných řeší Pošta. E-mail jde do Pošty hned při odeslání (jedno volání API). Jen když Pošta neodpovídá, mail čeká v odchozí frontě (`posta_odchozi`, stejný Idempotency-Key – Pošta ho nepošle dvakrát) a předá ho plánovač uvnitř `schedule:run` (`OdchoziFronta`, jen když ve frontě něco je; ručně `posta:fronta`). Výsledek přijde webhookem (`/posta/webhook`, podpis V2), záložně dotazem. `simren:zdravi --json` (`posta`): nepropojeno / Pošta nedostupná / nedoručené → portál otevře incident „Neodchází pošta“. Stará schránka aplikace (nastavení nebo `.env`) se při prvním propojení převezme do Pošty i s heslem (`StaraSchranka`, správce Pošty to povolí na souhlasu) a po zkušebním e-mailu se z aplikace smaže. Web vytvořený portálem propojí s Poštou portál sám (`posta:z-portalu`, klíče na stdin přes shim); `.env` (`POSTA_TOKEN`, `POSTA_WEBHOOK_TAJEMSTVI`, `POSTA_OD`) je záloha, když v nastavení nic není. Převod starší aplikace: [docs/prevod-na-postu.md](docs/prevod-na-postu.md).
- **Účel zprávy** (`UcelZpravy`, `config/posta.php` → `ucely`): web posílá jednou schránkou (Nastavení → E-mailová schránka)
  a s každým mailem pošle Poště účel (formuláře, účty, doklady, objednávky, hromadné). Víc schránek firmy podle účelu
  nastavuje Sim&Ren ručně v portálu / Poště – v administraci webu se nenastavují.
- **Oznámení** ([docs/oznameni.md](docs/oznameni.md)): zpráva žije jednou na serveru a k lidem dojde kanály –
  **centrum** (zvoneček na webu `@include('oznameni.zvonecek')` i v administraci, stránka `/oznameni`, Moje oznámení),
  **pruh** přes web a administraci (`@include('oznameni.pruh')` hned za `<body>` – odstávky s odpočtem, výpadky; pro
  všechny ho vidí i nepřihlášení) a **e-mail přes Poštu**. Administrace → Oznámení: koncept, náhled, zkouška sobě,
  cílení (všem / role / skupiny / víc vybraných lidí, každý jednou, superadmini nikdy hromadně), naplánování,
  odeslání s potvrzením počtu příjemců a limitem, čísla (přečteno, prokliknuto, doručeno). Z kódu
  `Oznam::provozni('…')->komu($user)->klic('…')->posli()`. Předvolby druh × kanál, novinky e-mailem jen se souhlasem
  (záznam v `oznameni_souhlasy`), odhlášení jedním kliknutím (List-Unsubscribe-Post), marketing ve výchozím stavu
  vypnutý (Oznámení → Nastavení, superadmin). E-maily po dávkách ve frontě. Web push a mobil – krok 3.
- `App\Support\Fotky` – nahrané fotky jako zmenšený WebP (`Fotky::pole(FileUpload::make(…))`), obrázek pro sdílení 1200 × 630 jako JPEG (`Fotky::poleSdileni`).
- **Doplněk Fakturace** ([docs/fakturace.md](docs/fakturace.md)) – napojení na fakturace.simren.cz, ve výchozím stavu vypnuté (`config/sablona.php` → `doplnky.fakturace`). Klíče zapíše portál (test testovací napojení, produkce ostré), v administraci Nastavení → Fakturace: stav napojení a bankovní účet pro doklady (uloží se ve fakturaci), webhook s podpisem V2 jako událost `FakturaceUdalost`.
- **Jazyky** ([docs/jazyky.md](docs/jazyky.md)): žádné texty natvrdo – klíče `__('oblast.skupina.klic')`, texty v `lang/cs` a `lang/en`, jazyky v `config/jazyky.php`. Jazyk určí uživatel (Profil) → výchozí; e-maily v jazyce příjemce. `php artisan jazyky:kontrola` najde texty natvrdo a chybějící překlady, `php artisan jazyky:prevod` převede texty na klíče. Nový jazyk = `lang/<kód>` + řádek v configu.
- **Doplněk Platby** ([docs/platby.md](docs/platby.md)) – volitelná platební brána (Comgate REST 2.0, Mo.one, lokálně simulace), ve výchozím stavu vypnutá (`config/sablona.php` → `doplnky.platby`). Test platí vždy testovací bránou Sim&Ren (Mo.one test), produkce ostře jen s ověřenými údaji klienta; štítek „TESTOVACÍ PLATBY“, stav vždy ověřený dotazem na bránu, idempotence a zámky proti dvojímu zaplacení, historie stavů, vrácení, Platby a Nastavení → Platební brána v administraci.
- Plánovač s `withoutOverlapping(…, false)` a obejitím zakázaného `pcntl` na HestiaCP.
- **V cronu jen `schedule:run`** (manifest `fronta_spousti: planovac`): frontu (`queue:work --stop-when-empty`) pouští plánovač na pozadí a jen když v ní něco čeká (`App\Support\FrontaUloh`), poštovní úlohy běží jako closure uvnitř `schedule:run`. Každou minutu tak startuje jen jeden proces PHP; portál řádek cronu posouvá v rámci minuty podle názvu aplikace (`sleep N`), aby aplikace serveru nestartovaly naráz. Nová každominutová úloha patří do `Schedule::call()` s `->when()`, ne `Schedule::command()` (každý příkaz je nový proces PHP).
- 404, 405, 403… nejsou chyba: do `laravel.log` ani do Logy → Chyby se nezapíší a `simren:zdravi` je nepočítá (boti na `/careers` neotevřou incident „Chyby v aplikaci“).
- Žádný `package.json` ani Vite.
- Čeština a pražský čas jako výchozí.
