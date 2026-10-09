# Pokyny k projektu – exportex.cz

Prezentační web exportex s.r.o. (textil z Uzbekistánu a Střední Asie do EU). Do 10/2026
statický web na GitHub Pages; 3. 10. 2026 převeden na šablonu Sim&Ren
(`SimRen-sro/simren-laravel-starter`) stejně jako rootklima.cz. **Pravidla šablony platí** –
README a CLAUDE.md šablony; tady je jen to, čím se web liší.

## Stack

PHP 8.5 · Laravel 13 · Filament 5 (`/admin`) · MariaDB 11.4. **Bez Node.js a bez buildu**:
vzhled webu je hotové CSS a JS v `public/assets` (dřív `assets/` statického webu, beze
změny vzhledu), stránky jsou Blade. Administrace (Filament) se nebuildí, jako v šabloně.
Při změně `style.css` nebo `main.js` se verze v adrese počítá sama (čas souboru).

## Nasazení

- Commit na `main` se sám nasadí na testovací web (`.simren/projekt.yml`, kořen `public/`).
  Do produkce jen přes CRM/portál. Nic na server ručně – jen git. Postup: `NASAZENI.md`.
- `.env` se do repa nedává. Testovací seeder (`DatabaseSeeder`) na serveru odmítne běžet
  a nikdy se tam nespouští.
- **Oznámení lidem aplikace jen přes modul Oznámení** (docs/oznameni.md): z kódu `App\Support\Oznameni\Oznam`,
  odesílá jen `Odeslani` (stav ručně neměnit, do `oznameni_*` nezapisovat mimo něj). Novinky a nabídky e-mailem/pushem
  jen se souhlasem (`Predvolby`), každá změna souhlasu do `oznameni_souhlasy`. Nový layout webu: `@include('oznameni.pruh')`
  za `<body>` a `@include('oznameni.zvonecek')` do hlavičky. Superadmini nikdy v hromadném cílení ani v číslech.
- **Čas:** aplikace i databáze v `Europe/Prague` (`APP_TIMEZONE`), v databázi místní čas bez pásma.
  Ven (API, webhooky, JSON, shim) jen ISO 8601 s posunem (`toIso8601String()`), nikdy holé
  `Y-m-d H:i:s`. Čas zvenku převádí `App\Support\CasAplikace::zVenku()` (zobrazení, dotazy);
  `CasAplikace::zapni()` v `AppServiceProvider` je pojistka, aby Eloquent neuložil UTC jako místní
  čas (GitHub „18:24Z“ by jinak bylo 18:24 místo 20:24). Pozor na Carbon 3: `createFromTimestamp()`
  vrací UTC. V JS a mobilu čas z API vždy převést na místní (`new Date(iso)`, Dart `.toLocal()`).
  Test `CasVPasmuAplikaceTest`.
- **Verze se vydávají jen v portálu** (projekt → Verze → Vydat) – nikdy `gh release create`
  ani `git tag vX.Y.Z` + push (ani v agentech). Vydání mimo portál přeskočí přepočet instalací
  a portál ho hlásí jako incident.

## Obsah webu

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
  formuláře mají `{email}`, `{telefon}`). Výchozí hodnoty: migrace `2026_10_03_200000_udaje_exportex`.
- `config/web.php`: kanonická adresa (`https://exportex.cz`), popisy pro vyhledávače a sdílení,
  pevná část JSON-LD (`StrukturovanaData`), přesměrování www.
- **Fotky**: původní v `public/assets/img` (WebP, u sortimentu `?v=` – při výměně souboru pod
  stejným jménem datum změnit). Nahrané v administraci → `App\Support\Fotky` (WebP) na disk
  `public`; `ObsahWebu::obrazek()` rozliší cestu. Formulář je nekontroluje na disku
  (`fetchFileInformation(false)`), jinak by původní fotky při uložení zahodil.

## Poptávkový formulář a pošta

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
DMARC) hlídá Pošta. Řádky `posta.host/port/sifrovani/uzivatel` z migrace `udaje_exportex`
zůstávají – migrací se nemažou (docs prevodu, smaže je až převzetí schránky).
Web z portálu propojí s Poštou portál sám (`posta:z-portalu`, `app/Support/Posta/PrikazZPortalu.php`, klíče na stdin); `.env` (`POSTA_TOKEN`, `POSTA_WEBHOOK_TAJEMSTVI`, `POSTA_OD`) je jen záloha, když v nastavení nic není.

## Bezpečnost

`BezpecnostniHlavicky` je šablona + **přísná CSP pro veřejný web** (dřív v `<meta>`):
`script-src 'self'`, žádný vložený skript (data webu jsou JSON, ten se nespouští),
`frame-ancestors 'none'`. Až když se v SEO a měření zapne měření, přidá se `'unsafe-inline'`
a domény měřicích nástrojů (cookie lišta šablony). Do veřejných stránek **nepřidávat inline
`<script>`** – na produkci by ho CSP zablokovala. Administrace a nastavení hesla CSP nemají.
`KanonickaDomena` přesměruje www.exportex.cz na exportex.cz.

## Pasti

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
