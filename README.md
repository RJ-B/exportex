# Exportex — prezentační web

Statický jednostránkový web pro **Exportex s.r.o.** (textil z Uzbekistánu a Střední Asie do EU).
Bez buildu, bez frameworku, bez závislostí — čisté HTML + CSS + vanilla JS. Nasazuje se na GitHub Pages.

## Struktura

```
index.html                úvodní stránka (sekce 01–07 + hlavička, patička, modal)
cookies.html              cookies a místní úložiště
soukromi.html             ochrana osobních údajů
404.html                  stránka nenalezena

assets/css/style.css      kompletní styly, tokeny nahoře v :root
assets/css/fonts.css      vlastní kopie písem (@font-face)
assets/js/config.js       ⚙ nastavení formuláře — jediné místo k úpravě
assets/js/theme-init.js   nastaví motiv před vykreslením (proti probliknutí)
assets/js/data.js         veškerý obsah CZ/EN — sortiment, kroky, trasa, doklady
assets/js/main.js         chování: motiv, jazyk, filtry, modal, mapa, formulář
assets/fonts/             Outfit a JetBrains Mono (woff2, latin + latin-ext)
assets/img/               fotky (WebP), logo, ikony

robots.txt, sitemap.xml, site.webmanifest, favicon.ico
.nojekyll                 vypíná Jekyll na GitHub Pages
NASAZENI.md               předávací postup: doména, formulář, spam
```

## Světlý a tmavý režim

Přepíná se tlačítkem v hlavičce, volba se pamatuje v `localStorage`
(`exportex-theme`). **Výchozí je tmavý** — na něm stojí identita webu; systémové
nastavení se záměrně nepřebírá, aby byl první dojem vždy stejný. Změnit se to dá
jedním řádkem v `initTheme()` v `main.js`.

Světlý režim je jeden blok proměnných v `:root[data-theme="light"]` — žádné
pravidlo se nepřepisuje, mění se jen hodnoty. Dvě místa zůstávají tmavá v obou
režimech, protože leží na fotce: **hero** a **hlavička, dokud není přilepená**.
Řeší se lokálním přepsáním proměnných na `.hero` a `.hdr:not(.is-stuck)`; když je
otevřené mobilní menu (`body.is-locked`), přepis se vypne, aby byla hlavička
čitelná nad světlým menu.

Akcent se ve světlém režimu ztmavuje na `#A31D14`, aby text prošel kontrastem
(7,0:1 na `#F4F6FA`); `#E5544A` z tmavého režimu by mělo jen 3,3:1.

Aby světlý režim neproblikl tmavě, nastavuje se atribut `data-theme` malým
inline skriptem v `<head>` ještě před vykreslením.

## Vzhled

Celý web je postavený na **liquid glass** — průsvitné vrstvy s rozostřeným pozadím
(`backdrop-filter`) a světelným okrajem, který se kreslí gradientem přes
`mask-composite: exclude` v `.glass::before`. Aby mělo sklo co rozostřovat, mají
sekce v pozadí měkké barevné záře (`.section::before`); bez nich by sklo vypadalo
jako plochý průsvitný obdélník. Prohlížeče bez `backdrop-filter` dostanou plnou
výplň přes `@supports not`.

Firemní červená je odečtená přímo z loga: **`#B0221A`**. Světlejší odstíny pro text
(`--ac-l: #E5544A`) jsou doladěné ručně, protože čisté míchání s bílou dává růžovou.
Všechny barvy, rádiusy, stíny a časování jsou tokeny v `:root` na začátku `style.css` —
změna vzhledu se dělá tam, ne po jednotlivých pravidlech.

Písma: **Outfit** (text) a **JetBrains Mono** (popisky, čísla) z Google Fonts.

## Mapa trasy

Sekce „Trasa" ukazuje jednoduchý oblouk **Uzbekistán → Evropa** — bez mezizastávek.
Celá Evropa je zvýrazněná jako oblast rozvozu, šipka míří do České republiky,
protože leží uprostřed kontinentu. Žádný sklad v Praze není: zboží se rozváží
přímo tam, kam zákazník potřebuje.

Podklad je vygenerovaný z Natural Earth (world-atlas 50m) skriptem, který
státy promítne, ořízne na výřez, zjednoduší (Douglas–Peucker) a rozdělí do
skupin `map__land` / `map__eu` / `map__cz` / `map__origin`. Výsledek je vložený
přímo v `index.html` (~51 kB, po gzipu ~18 kB).

Projekce je válcová se standardní rovnoběžkou 45° s. š.:

```
x = 100 + 7.0711 · zeměpisná délka
y = 800 −     10 · zeměpisná šířka
```

Mapa má jediné zobrazení bez přepínání; oblouk se ohýbá o `bow` z `data.js`.
Pod 720 px se v mapě skryjí podtitulky a štítek s clem — clo hlásí řádek
faktů hned pod mapou.

## Obsah

Texty se needitují v HTML na dvou místech:

- **statické texty** (nadpisy, odstavce, navigace) jsou v `index.html`
  s dvojicí atributů `data-cs` / `data-en`. Viditelný text v elementu = česká verze;
  `data-en` je anglický překlad. Přepínač CZ/EN prohodí `innerHTML`.
- **opakující se obsah** (sortiment, kroky, koridory, doklady, reference, pole formuláře)
  je v `assets/js/data.js` ve dvou blocích `cs` a `en`. Přidání produktu = přidat objekt
  do pole `products` v obou jazycích.

Zvolený jazyk se pamatuje v `localStorage` (`exportex-lang`). Výchozí je čeština.

## Fotky

Všechny fotky jsou WebP, tonálně sjednocené (odbarvené, jemně přetažené firemní
červenou, ztmavené) tak, aby seděly na tmavý podklad.

| soubor | rozměr | kde se používá |
|---|---|---|
| `hero.webp` | 2400×1350 | úvodní obrazovka |
| `about.webp` | 800×1000 | sekce 05 O nás |
| `product-frotte.webp` | 1200×900 | karta Frotté |
| `product-lozni.webp` | 1200×900 | karta Ložní prádlo |
| `product-uplety.webp` | 1200×900 | karta Pletené úplety |
| `product-konfekce.webp` | 1200×900 | karta Konfekce |
| `product-tkaniny.webp` | 1200×900 | karta Tkaniny |
| `product-horeca.webp` | 1200×900 | karta HORECA |
| `og.webp` | 1200×630 | náhled při sdílení |
| `logo.webp` / `logo.png` | 532×120 | hlavička a patička |

Výměna fotky = nahradit soubor stejným jménem (stejný poměr stran), nebo změnit
cestu u `img` v `assets/js/data.js`, případně v `index.html` u hero a O nás.

Karty mají v CSS ještě jemný odbarvovací filtr (`grayscale(.85)`), který se při najetí
myší rozpouští — sjednocuje fotky z různých provozů. Vypíná se v `style.css` u `.pcard__media img`.

## Formulář

Odesílání je připravené a nastavuje se **na jediném místě**: `assets/js/config.js`.
Přednastavený je FormSubmit s adresou `mikyska@exportex.cz`; aktivuje se jedním
kliknutím v potvrzovacím e-mailu. Přesný postup i alternativy jsou v
[NASAZENI.md](NASAZENI.md).

Web nemá backend a mít ho nebude — prohlížeč sám e-mail odeslat neumí, takže
odeslání obstarává externí služba. **Žádná hesla proto v kódu nejsou ani být
nemohou:** statický web nemá `.env` a všechno, co si prohlížeč načte, je veřejné.
Ke schránce, která jen přijímá, přihlašovací údaje nejsou potřeba.

Dokud je `endpoint` prázdný, formulář jen zvaliduje a zobrazí potvrzení —
hodí se pro ukázku.

### Ochrana proti robotům

Vrstvená, bez captchy (nezdržuje zákazníka a nevolá cizí server). Všechna síta
se navenek tváří jako úspěšné odeslání, aby robot nepoznal, že ho web odhalil:

| Síto | Co odhalí |
|---|---|
| skryté pole `website` | robot vyplňující všechna pole |
| nejméně 3 s od zobrazení | okamžité automatické odeslání |
| stopa po interakci | POST bez kliknutí a psaní ve formuláři |
| shodná zpráva do 45 s | smyčka nebo dvojklik |
| 5 a více odkazů ve zprávě | typický spamový vzkaz |

Doplňkově: `maxlength` na všech polích a validace včetně minimální délky zprávy.
Prahy jsou pohromadě v objektu `BOT` v `main.js`.

## SEO

- title, description, kanonická adresa, Open Graph i Twitter karty
- `hreflang` pro češtinu a angličtinu; angličtina má vlastní adresu `?lang=en`,
  takže ji jde indexovat zvlášť
- strukturovaná data (`Organization`, `WebSite`, `Service` s katalogem sortimentu)
- `robots.txt`, `sitemap.xml`, vlastní stránka `404.html`
- ikony odvozené z písmene „e" ve firemním logu — `favicon.ico`, SVG,
  apple-touch-icon a maskovatelné ikony pro Android včetně `site.webmanifest`

Adresy míří na GitHub Pages, dokud web nepoběží na vlastní doméně —
seznam míst k přepsání je v [NASAZENI.md](NASAZENI.md).

## Bezpečnost a soukromí

- **Přísná CSP** v `<meta>`: `default-src 'none'`, skripty jen z vlastního
  původu, žádné `'unsafe-inline'` pro skripty. Styly `'unsafe-inline'` potřebují
  kvůli inline atributům `style` v rozvržení.
- **Písma jsou hostovaná na webu**, ne u Google Fonts — návštěvníkova IP adresa
  se tak neposílá třetí straně a odpadá externí spojení.
- Jediné povolené odchozí spojení je odeslání formuláře (`connect-src`).
- `referrer-policy` přes `<meta>`, odchozí odkazy mají `rel="noopener noreferrer"`.
- Web nepoužívá žádné cookies; v `localStorage` drží jen jazyk a motiv.

Co na GitHub Pages nejde a doplní se až na vlastním hostingu: HTTP hlavičky
`X-Content-Type-Options`, `X-Frame-Options` a `frame-ancestors` (v `<meta>`
se ignoruje).

## Přístupnost

- kontrast textu splňuje **WCAG AA v obou režimech** (ověřeno měřením
  vykreslené stránky, ne odhadem)
- odkaz „Přeskočit na obsah", který se objeví při zaměření klávesnicí
- modal i mobilní menu drží focus uvnitř, zavírají se Escapem a vracejí focus
- pole formuláře mají `autocomplete`, `aria-required`, `aria-invalid`
  a navázané chybové hlášky; potvrzení odeslání se ohlásí přes `aria-live`
- `prefers-reduced-motion` vypne animace
- dotykové cíle v hlavičce mají alespoň 34 px

## Nasazení

Web je statický, žádný build:

```bash
git push
```

GitHub Pages servíruje větev `main` z kořene repozitáře. Soubor `.nojekyll` zabraňuje
tomu, aby Jekyll ignoroval složky začínající podtržítkem.

**Vlastní doména:** v Settings → Pages nastavit doménu a u registrátora přidat
`CNAME` (nebo `A` záznamy na GitHub Pages IP). GitHub si do repozitáře uloží soubor `CNAME`.

## Lokální vývoj

```bash
python3 -m http.server 4321
```

Otevřít <http://localhost:4321>. Otevírat `index.html` přímo přes `file://` nefunguje
(fetch fontů a relativní cesty).
