# Pokyny k projektu – exportex.cz

Prezentační web exportex s.r.o. (textil z Uzbekistánu a Střední Asie do EU). Do 10/2026
statický web na GitHub Pages; 3. 10. 2026 převeden na šablonu Sim&Ren
(`SimRen-sro/simren-laravel-starter`) stejně jako rootklima.cz. Níž nejdřív to, čím se web liší
od šablony, pak pravidla šablony.

## exportex.cz – co je jinak než v šabloně

### Stack

PHP 8.5 · Laravel 13 · Filament 5 (`/admin`) · MariaDB 11.4. **Bez Node.js a bez buildu**:
vzhled webu je hotové CSS a JS v `public/assets` (dřív `assets/` statického webu, beze
změny vzhledu), stránky jsou Blade. Administrace (Filament) se nebuildí, jako v šabloně.
Při změně `style.css` nebo `main.js` se verze v adrese počítá sama (čas souboru).

### Obsah webu

- Web je jednostránkový (`domu.blade.php`): Úvod (hero, jádro) a pak sekce podle
  *Obsah webu → Hlavička a patička → Sekce na webu* (`ObsahWebu::sekceNaWebu()`): Sortiment,
  Jak to funguje, Trasa, Doklady a clo, O nás, Ukázky zakázek, Kontakt (= sekce šablony
  `formular`). **Čísla 01–07 a střídání pozadí se počítají podle pořadí** – nepsat je natvrdo.
  Menu, mobilní menu i Rychlé odkazy v patičce = zapnuté sekce, štítek sekce = položka menu.
- **Dvojjazyčnost**: každý text má `<pole>` (česky) a `<pole>_en`; v Blade přes
  `Preklad::attr($cs, $en)` (atributy `data-cs`/`data-en`, přepíná `main.js`). Nový text
  bez anglické podoby na webu po přepnutí na EN zůstane česky – vždy obojí.
- Texty a seznamy sekcí jsou v `App\Support\ObsahWebu` (klíč `obsah.<sekce>` v `nastaveni`,
  JSON); `VYCHOZI` je obsah, se kterým web vznikl (dřív `index.html` a `assets/js/data.js`),
  neuložené pole se bere z něj. Stránky v `app/Filament/Pages/Web` (základ `StrankaSekceWebu`
  ukládá jen svůj klíč). Kontakt – texty je část sekce Kontakt (`sekceWebu() = 'formular'`).
- Sortiment, kroky, trasa, doklady a ukázky kreslí `main.js` z JSON
  `<script type="application/json" id="exportex-data">` (`ObsahWebu::proSkript()`, stejný tvar
  jako dřív `data.js`). Popisky ovládání a formuláře jsou v `ObsahWebu::POPISKY`.
- Firemní údaje (název, e-mail, telefon, firma, IČO, sídlo, rejstřík) jsou Základní údaje
  šablony; anglické sídlo a rejstřík a texty patičky v `obsah.paticka` (Hlavička a patička).
  **V šablonách nesmí být natvrdo telefon, e-mail ani adresa** (ani v `main.js` – hlášky
  formuláře mají `{email}`, `{telefon}`). Výchozí hodnoty: `config/nastaveni.php` (dřív migrace `udaje_exportex`).
- `config/web.php`: kanonická adresa (`https://exportex.cz`), popisy pro vyhledávače a sdílení,
  pevná část JSON-LD (`StrukturovanaData`), přesměrování www.
- **Fotky**: původní v `public/assets/img` (WebP, u sortimentu `?v=` – při výměně souboru pod
  stejným jménem datum změnit). Nahrané v administraci → `App\Support\Fotky` (WebP) na disk
  `public`; `ObsahWebu::obrazek()` rozliší cestu. Formulář je nekontroluje na disku
  (`fetchFileInformation(false)`), jinak by původní fotky při uložení zahodil.

### Poptávkový formulář a pošta

Mechanismus šablony (Zprávy z webu, upozornění na příjemce z *Kontakt a formulář*,
výchozí kontaktní e-mail mikyska@exportex.cz). Pole kreslí `main.js` a posílá JSON
(`KontaktController` umí JSON i obyčejné odeslání). Navíc **firma** (povinná, B2B) a **jazyk
webu** (`zpravy.firma`, `zpravy.jazyk`); anglická poptávka má v předmětu `[EN]`. Jméno
a příjmení zvlášť (jsou vedle sebe v jednom řádku, výška formuláře je jako dřív). Ochrana:
skryté pole a podepsaný čas z atributů formuláře (`data-past`, `data-cas`) + `OchranaFormulare`,
`throttle:formular` (JSON 429), `OdeslaniZpravy`; `botCheck()` v `main.js` jen navíc.
Žádnou vrstvu neodstraňovat bez rozhovoru. formsubmit.co se nepoužívá.

**Pošta jde přes Poštu (posta.simren.cz)** – Administrace → Kontakt a formulář → Propojit
s poštou (`App\Support\Posta`, transport `posta`, `Mail::` beze změny). Schránka exportex.cz
zůstává u Forpsi (`smtp.forpsi.com:465` SSL, mikyska@exportex.cz) – v Poště ji zadá správce
Pošty i s heslem (v aplikaci heslo nikdy nebylo, převzít není co). DNS domény (SPF, DKIM,
DMARC) hlídá Pošta. Řádky `posta.host/port/sifrovani/uzivatel` (výchozí v `config/nastaveni.php`)
zůstávají – migrací se nemažou (docs prevodu, smaže je až převzetí schránky).
Web z portálu propojí s Poštou portál sám (`posta:z-portalu`, `app/Support/Posta/PrikazZPortalu.php`, klíče na stdin); `.env` (`POSTA_TOKEN`, `POSTA_WEBHOOK_TAJEMSTVI`, `POSTA_OD`) je jen záloha, když v nastavení nic není.

### Bezpečnost

`BezpecnostniHlavicky` je šablona + **přísná CSP pro veřejný web** (dřív v `<meta>`):
`script-src 'self'`, žádný vložený skript (data webu jsou JSON, ten se nespouští),
`frame-ancestors 'none'`. Až když se v SEO a měření zapne měření, přidá se `'unsafe-inline'`
a domény měřicích nástrojů (cookie lišta šablony). Do veřejných stránek **nepřidávat inline
`<script>`** – na produkci by ho CSP zablokovala. Administrace a nastavení hesla CSP nemají.
`KanonickaDomena` přesměruje www.exportex.cz na exportex.cz.

### Pasti

- Neexistující adresa (404) nemá middleware webu ani session – `errors/404` je samostatná
  stránka bez `csrf_token()` a bez dat z hlavičky webu.
- Texty z administrace jdou do `data-cs`/`data-en` jako HTML (`innerHTML`) – vždy přes
  `Preklad::attr()` (escapuje dvakrát), nikdy rovnou `{!! !!}`.
- Sekce mají shodné výšky na pixel s původním statickým webem – při zásahu do rozvržení
  porovnej screenshoty před a po (desktop i mobil, CZ i EN).
- **Doplněk Platby** (docs/platby.md, `config/sablona.php` → `doplnky.platby`, výchozí vypnuto): stav platby mění
  jen `Platby::prejdi()`, stavu z webhooku ani návratu se nevěří (vždy `overStav()` dotazem), částky v haléřích
  (`Platby::halere`), test nikdy neplatí ostře a ostrý režim bez ověřených údajů platby zastaví – nikdy tiše
  nepřepínat na jinou bránu. Údaje bran do `.env` jen testovací Sim&Ren (portál), klienta jen šifrovaně v administraci.
- **Přenos dat test → produkce** (portál, `App\Support\Prenos`, README): každá nová tabulka s obsahem
  patří do části v `config/prenos.php` (párovací `klic`, `vazby`), jinak ji přenos z testu nepřenese.

Projekt vznikl ze šablony Sim&Ren (`SimRen-sro/simren-laravel-starter`).

## Stack

PHP 8.5 · Laravel 13 · Filament 5 · MariaDB 11.4. **Bez Node.js** – rozhraní je Blade
a Filament, jehož assety se publikují při `composer install` (`filament:upgrade`),
nic se nebuildí. Vlastní CSS a JS patří do `public/` jako hotové soubory.

## Nasazení

- Commit na `main` se sám nasadí na testovací adresu (`.simren/projekt.yml`).
  Nová verze se přepne, až projde kontrolou `/zdravi` (databáze, cache, storage
  a `sablona.databaze`); jinak dál běží stará. Dohled v portálu má hlídat taky
  `/zdravi`, ne text stránky.
- Testovací web se neindexuje (noindex mimo produkci); `robots.txt` a `sitemap.xml`
  skládá aplikace (`App\Support\MapaWebu`) – statický `public/robots.txt` nepatří.
- Do produkce jen tlačítkem v CRM po potvrzení. Nikdy ručně na serveru.
- **Verze se vydávají jen v portálu** (projekt → Verze → Vydat) – nikdy `gh release create`
  ani `git tag vX.Y.Z` + push (ani v agentech). Vydání mimo portál přeskočí přepočet instalací
  a portál ho hlásí jako incident.
- Na server se nic nekopíruje ručně (scp, rsync) – jen přes git.
- `.env` se do repa nikdy nedává. Na serveru ho zakládá portál.
- Testovací seedery nikdy nesmí běžet na serveru (zábrana `JenLokalne`, `SeederyJenLokalneTest`).

## Administrace

Kostra menu je stejná ve všech projektech (README → Kostra administrace). Samostatně v menu jen to,
s čím se pracuje denně; co se nastaví jednou a mění zřídka, patří do **Obsahu webu** (trait
`CastObsahuWebu`, podoba podle `config/sablona.php`). Nastavení je sbalené, **Správa aplikace**
(jen superadmin – logy, stav webu…) dole za čarou. Blog, poptávky ani jiné moduly v šabloně nejsou –
přidává je projekt.

**Rozložení administrace** je jednotné (`App\Filament\Rozlozeni`, README): logo a název v levém
menu, drobečky v liště vlevo, vpravo zvoneček a avatar (Profil, Zabezpečení, Odhlásit), bez
globálního vyhledávání; na mobilu ☰ vpravo, menu zprava s účtem nahoře. Neměnit bez zadání.
**Druhý krok přihlášení** (`App\Bezpecnost\DruhyKrok`): passkey, nebo kód z Authenticatoru
s obnovovacími kódy – volba je na uživateli v Zabezpečení. Žádné přihlášení jen passkeyem; nová
cesta, která přihlašuje (odkaz, vstup…), musí druhý krok vyžadovat taky.

Každý web má **Ochranu osobních údajů** (vzor v šabloně, doplní se jen co se liší) – nemazat ji
a nevypínat; při nové funkci, která zpracovává osobní údaje, doplnit její část do textu.

## Pravidla

- **Statistiky a přehledy počtů nikdy nepočítají superadminy** (vývojáři Sim&Ren testují naostro – prodávají,
  kupují, procházejí): `User::bezSuperadminu()`, u záznamů s uživatelem jako aktérem `whereDoesntHave`; záznamy
  bez uživatele (NULL) v číslech zůstávají.
- Migrace se po nasazení do produkce neupravují, jen se přidává nová.
- **Migrace a seedery nevkládají data** (ani výchozí údaje firmy do `nastaveni`). Čerstvá produkce
  by „měla data“ a portál by odmítl přenos dat z testu. Výchozí hodnoty nastavení patří do
  `config/nastaveni.php` (čtou se, dokud je správce nezmění); test `CistaInstalaceTest` to hlídá.
- Jméno a příjmení vždy jako dvě pole.
- Žádné prvky ve vzhledu prohlížeče (roletka, zaškrtávátko, výběr data, `confirm()`): Filament má
  `native(false)` globálně, vlastní Blade jen vlastní komponenty; roletka stejně vysoká jako pole.
- Testovací data jen v seederu se zábranou `JenLokalne` a nikdy z `DatabaseSeeder`.
- Další databáze (sdílené účty) jen výjimečně a se zálohou – README → Sdílené databáze a SSO.
- **Přenos dat test → produkce** (portál, `App\Support\Prenos`, README): každá nová tabulka s obsahem
  patří do části v `config/prenos.php` (párovací `klic`, `vazby`), jinak ji přenos z testu nepřenese.
  Šifrované hodnoty (`Crypt`, `encrypted`) se nepřenáší a po importu celé databáze se nečitelné vymažou
  (`Sifrovane`) – tajemství nikdy neposílat čitelně mimo server.
- **Na produkci nikdy mailer `log` ani `array`** – e-mail by se tvářil jako odeslaný. `simren:zdravi` to hlásí
  portálu (blok `posta`) a záznam v Logy → E-maily je „neodesláno“.
- `/zdravi` odpovídá i v údržbě (`artisan down`, výjimka v `bootstrap/app.php`) – nasazení během údržby
  ho čte; výjimku nerušit.
- Převod dat ze staré aplikace jen přes `App\Console\PrevodDat` a spojení `stara`
  (jen čtení, nanečisto, kontrola počtů, hesla přes `PrevzetiHesla`) – README.
- Nahrávané fotky se převádějí na WebP. **Výjimka: obrázek pro sdílení** (og:image 1200 × 630, výchozí v SEO a měření
  i vlastní u stránek a obsahu) je **JPEG** (kvalita 85, sRGB, bez metadat, `Fotky::ulozSdileni`) – LinkedIn a další sítě
  WebP nečtou. Starší WebP dostane pro og:image JPEG kopii vedle sebe sám (`Fotky::jpegSdileni`), data se nemění.
- **SEO**: nový layout webu má v `<head>` místo `<title>` `{{ \App\Support\Seo\Seo::stranka($nadpis, klic: …)->html() }}`
  (úvod `Seo::uvod()`) – titulek, popis, canonical, Open Graph, Twitter Card a JSON-LD. Nic ručně do meta
  a JSON-LD nevymýšlet: jen údaje webu (Hlavička a patička, SEO a měření, obsah stránky). Nová veřejná
  stránka patří do `MapaWebu::polozky()` (s `lastmod`), noindex se řídí jen `MapaWebu::indexovat()`.
- Ikona v záložce prohlížeče vzniká z loga (`App\Support\IkonaWebu`, PNG – ne WebP); nový layout webu
  musí mít v `<head>` `{{ \App\Support\IkonaWebu::html() }}`, jinak bude bez ikony.
- Pošta jen přes **Poštu** (posta.simren.cz) – Administrace → Pošta → Propojit s poštou
  (`App\Support\Posta`, transport `posta`). Ne SMTP v `.env` a nikdy přes poštu na serveru
  (ten žádnou nemá). Maily posílat normálně přes `Mail::`. Schránky a DNS domén řeší Pošta;
  vlastní opakování, kontrolu schránky ani `mail:retry-failed` aplikace nemá.
  Testy se ke skutečné Poště nepřipojují (`Http::fake`).
- **E-mail firmy a účely** (Nastavení → E-mail, `App\Filament\Pages\Email`, `SchrankyFirmy`): schránky firmy
  a odkud se k jakému účelu posílá – drží je Pošta, web nic neukládá (heslo jen server → Pošta). Nový mail
  s jiným účelem než „pro všechno“ zapsat do `config/posta.php` → `ucely` (třída → `formulare`, `ucty`,
  `doklady`, `objednavky`, `hromadne`; obnova hesla a ověření e-mailu jsou `ucty` samy, `UcelZpravy`).
  S účelem vybírá odesílatele Pošta. Testovací web jen čte (posílá testovací adresou).
- **Oznámení lidem aplikace jen přes modul Oznámení** (docs/oznameni.md): z kódu `App\Support\Oznameni\Oznam`,
  odesílá jen `Odeslani` (stav ručně neměnit, do `oznameni_*` nezapisovat mimo něj). Novinky a nabídky e-mailem/pushem
  jen se souhlasem (`Predvolby`), každá změna souhlasu do `oznameni_souhlasy`. Nový layout webu: `@include('oznameni.pruh')`
  za `<body>` a `@include('oznameni.zvonecek')` do hlavičky. Superadmini nikdy v hromadném cílení ani v číslech.
- **Doplněk Platby** (docs/platby.md, `config/sablona.php` → `doplnky.platby`): stav platby mění jen
  `Platby::prejdi()`, stavu z webhooku ani návratu se nevěří (vždy `overStav()` dotazem), částky v haléřích
  (`Platby::halere`), test nikdy neplatí ostře a ostrý režim bez ověřených údajů platby zastaví – nikdy tiše
  nepřepínat na jinou bránu. Údaje bran do `.env` jen testovací Sim&Ren (portál), klienta jen šifrovaně v administraci.
  Apple Pay / Google Pay jen jako **předvolba na platební stránce brány** (`MetodaPlatby`, `PozadavekPlatby::metoda`,
  `NastaveniPlateb::nabizi` = přepínač v administraci + `Brana::predvolby()`); nejde-li, platí se s výběrem na bráně, nikdy chyba.
- **Doplněk Fakturace** (docs/fakturace.md, `config/sablona.php` → `doplnky.fakturace`): klíče do `.env` zapisuje jen
  portál (test testovací, produkce ostré), nikdy ručně. Bankovní účet pro doklady se ukládá ve fakturaci přes API
  (`App\Fakturace\Fakturace::ulozUdajeFirmy`, `firma:udaje`), web ho neukládá; testovací napojení jen čte. Webhook
  jen s podpisem V2, události projekt poslouchá přes `FakturaceUdalost`.
- Slovo se v rozhraní neláme uprostřed (`word-break: break-all` ne).
- Chyby aplikace čte portál Sim&Ren (`simren:chyby`, `App\Support\ChybyAplikace`) a vyřešení jde oběma
  směry – `error_logs`, `ErrorLogger` ani tenhle příkaz neměnit tak, aby se rozbil otisk nebo výstup.
- Balíčkovač pro JS, kdyby byl potřeba: npm.
- **Čas:** aplikace i databáze v `Europe/Prague` (`APP_TIMEZONE`), v databázi místní čas bez pásma.
  Ven (API, webhooky, JSON, shim) jen ISO 8601 s posunem (`toIso8601String()`), nikdy holé
  `Y-m-d H:i:s`. Čas zvenku převádí `App\Support\CasAplikace::zVenku()` (zobrazení, dotazy);
  `CasAplikace::zapni()` v `AppServiceProvider` je pojistka, aby Eloquent neuložil UTC jako místní
  čas (GitHub „18:24Z“ by jinak bylo 18:24 místo 20:24). Pozor na Carbon 3: `createFromTimestamp()`
  vrací UTC. V JS a mobilu čas z API vždy převést na místní (`new Date(iso)`, Dart `.toLocal()`).
  Test `CasVPasmuAplikaceTest`.
- **Žádné texty natvrdo** (docs/jazyky.md): každý text pro člověka přes `__('oblast.skupina.klic')`, čeština
  do `lang/cs/<oblast>.php` a angličtina do `lang/en/<oblast>.php`. Filament: metody místo vlastností s textem,
  skupiny menu přes `App\Filament\Navigace`; konstanty s texty jako metody; datum `__('formaty.datum')`; text do JS
  přes `@js(__('…'))`. Kontrola `php artisan jazyky:kontrola` (a `JazykyTest`), převod `php artisan jazyky:prevod`.
  Nový jazyk = složka `lang/<kód>` + řádek v `config/jazyky.php`.
- **Pořadí se nikdy nezadává číslem ve formuláři** (pole Řazení/Pořadí/`sort_order`) – jen přetažením v seznamu (drag and drop), nový záznam jde na konec. Technika – Filament: `->reorderable('<sloupec>')` na tabulce (`->defaultSort('<sloupec>')`) a nový záznam dostane pořadí max+1 automaticky (v modelu `creating` nebo ve stránce Create). Sloupec v DB zůstává, mizí jen z UI.

## Testy a CI/CD – jen lokálně

**Testy, analýza ani kontroly kódu se přes CI/CD (GitHub Actions) nespouštějí** – žádné workflow
s testy do `.github/workflows` nepřidávat. Spouští se **lokálně před commitem** (příkaz v sekci
o kontrole před commitem / README). GitHub Actions jen na to, co lokálně udělat nejde (vydání,
které potřebuje tajemství na GitHubu) – a i to jen při skutečném vydání, ne při každém pushi.
Důvod: účty firmy jsou GitHub Free – 2000 minut měsíčně a rozpočet $0, po vyčerpání se Actions
zastaví do dalšího měsíce. Spotřebu hlídá portál (Limity a služby → GitHub Actions).
