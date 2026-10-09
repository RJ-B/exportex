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
php artisan db:seed      # nepovinné: dvě vymyšlené poptávky (jen lokálně)
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
