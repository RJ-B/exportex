# Exportex — prezentační web

Statický jednostránkový web pro **Exportex s.r.o.** (textil z Uzbekistánu a Střední Asie do EU).
Bez buildu, bez frameworku, bez závislostí — čisté HTML + CSS + vanilla JS. Nasazuje se na GitHub Pages.

## Struktura

```
index.html              celá stránka (sekce 01–07 + hlavička, patička, modal)
assets/css/style.css    kompletní styly, tokeny nahoře v :root
assets/js/data.js       veškerý obsah CZ/EN — sortiment, kroky, trasy, doklady, reference
assets/js/main.js       chování: jazyk, filtry, modal, trasa, formulář, animace
assets/img/             fotky (WebP), logo, favicon
.nojekyll               vypíná Jekyll na GitHub Pages
```

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

Oba koridory mají stejné body, liší se jen vyklenutím oblouku k jihu
(`bow` v `data.js`) — kamion přes Turecko se klene níž než železniční
Střední koridor. Pod 720 px se v mapě skryjí podtitulky a štítek s clem;
clo hlásí řádek faktů pod mapou.

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

**Formulář zatím nikam neodesílá.** Funguje validace (povinná pole, formát e-mailu,
minimální délka zprávy, telefon nepovinný) a potvrzovací stav, ale odeslání je záměrně
neřešené — GitHub Pages je čistě statický hosting a nemá backend.

Napojení je v `assets/js/main.js` ve funkci `initForm()`, u komentáře `TODO`.
Nejjednodušší varianty:

- **Formspree** — `<form action="https://formspree.io/f/XXXX" method="POST">` a odeslat `fetch`em
- **Web3Forms** — zdarma, jen `access_key` v POST datech
- vlastní endpoint (Cloudflare Worker, Netlify Function apod.)

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

## Přístupnost a výkon

- respektuje `prefers-reduced-motion` — animace se vypnou
- kontrast textu na pozadí splňuje WCAG AA (akcentní `#E5544A` má na `#070B16` 5,4:1)
- mobilní menu je ovladatelné klávesnicí, modal se zavírá Escapem a vrací focus
- fotky mají `width`/`height` proti poskakování layoutu a `loading="lazy"` mimo hero
