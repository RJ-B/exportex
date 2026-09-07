# Nasazení a předání

Vše, co je potřeba udělat, aby web běžel na vlastní doméně a formulář doručoval
poptávky. Postup je psaný tak, aby se dal předat člověku, který spravuje doménu
a poštu — ten nepotřebuje nic z kódu měnit kromě jednoho řádku.

---

## 1. Doména

Web teď běží na `https://rj-b.github.io/exportex/`. Doména `exportex.cz` je
registrovaná, ale ukazuje na parkovací stránku registrátora (Aruba).

**U registrátora nastavit DNS:**

| Typ | Název | Hodnota |
|---|---|---|
| A | `@` | `185.199.108.153` |
| A | `@` | `185.199.109.153` |
| A | `@` | `185.199.110.153` |
| A | `@` | `185.199.111.153` |
| CNAME | `www` | `rj-b.github.io.` |

Pozor: A záznamy pro `@` musí nahradit ty stávající, které míří na parkovací
stránku. Pokud na doméně běží pošta, **záznamy MX nechat být** — s webem nemají
nic společného a jejich smazáním by přestaly chodit maily.

**Na GitHubu:** Settings → Pages → Custom domain → `exportex.cz` → Save.
GitHub si sám vytvoří v repozitáři soubor `CNAME`. Až se DNS rozšíří (běžně
do hodiny, výjimečně 24 h), zaškrtnout **Enforce HTTPS**.

Soubor `CNAME` schválně není v repozitáři předem — dokud DNS nemíří na GitHub,
shodil by tím současnou adresu.

**Po přepnutí domény přepsat adresu na těchto místech** (všude je to
`https://rj-b.github.io/exportex/` → `https://exportex.cz/`):

- `index.html` — `canonical`, tři řádky `hreflang`, `og:url`, `og:image`, `twitter:image`
- `index.html` — blok `application/ld+json` (klíče `url` a `@id`)
- `robots.txt` — adresa sitemapy
- `sitemap.xml` — `loc` a `xhtml:link`

---

## 2. Formulář

Web je statický a nemá server. Odeslání proto obstará externí služba; web
maily neodesílá sám. **Heslo ke schránce není potřeba** — schránka jen přijímá.

Nastavení je v jediném souboru: `assets/js/config.js`.

### Zapnutí (jednou, zabere minutu)

Formulář je předvyplněný na **FormSubmit** a cílovou adresu `mikyska@exportex.cz`.
Služba nevyžaduje registraci ani klíč.

1. Po nasazení odeslat formulář jednou nanečisto.
2. Na `mikyska@exportex.cz` přijde od FormSubmit potvrzovací e-mail.
3. Kliknout v něm na aktivační odkaz. Hotovo — od té chvíle poptávky chodí.

Do aktivace se zprávy nedoručují.

### Volitelně: skrýt adresu ze zdrojového kódu

Po aktivaci nabídne FormSubmit náhradní „alias" adresu ve tvaru
`https://formsubmit.co/ajax/xxxxxxxxxxxx`. Když se v `config.js` doplní místo
adresy s e-mailem, zmizí e-mail ze zdrojového kódu a hůř se sbírá roboty.
(Na stránce je e-mail stejně vidět v kontaktech, takže jde spíš o kosmetiku.)

### Proč v kódu nejsou žádná hesla

Statický web nemá `.env`. Cokoliv si prohlížeč načte, je veřejné — kdokoliv si
to přečte ve zdrojovém kódu. Proto v `config.js` **nesmí být heslo ke schránce,
SMTP údaje ani jiné skutečné tajemství**.

Adresa příjemce (a u jiných služeb i jejich klíč) jsou veřejné záměrně; tak
jsou tyhle služby postavené. Nechrání se utajením, ale nastavením na straně
služby — omezením na vlastní doménu a ochranou proti spamu. Kdyby bylo někdy
potřeba skutečné tajemství, musel by web dostat malou serverovou funkci
(viz bod 4).

### Alternativní služby

V `config.js` stačí přepnout `provider` a `endpoint`:

| Služba | Registrace | Poznámka |
|---|---|---|
| `formsubmit` | ne | přednastaveno, stačí e-mail |
| `web3forms` | ne (klíč přijde e-mailem) | `accessKey` z e-mailu |
| `formspree` | ano | archiv zpráv a statistiky |

Při změně služby zkontrolovat `connect-src` v CSP hlavičce v `index.html` —
nepoužité domény je vhodné ze seznamu vyhodit.

---

## 3. Aby poptávky nekončily ve spamu

Tohle je potřeba říct na rovinu: **žádná formulářová služba nedokáže doručení
do složky Doručená pošta zaručit.** Zpráva přijde ze serveru té služby, ne
z `exportex.cz`, a o zařazení rozhoduje přijímající poštovní server.

Co pomůže hned:

1. **Po aktivaci poslat testovací poptávku.** Když skončí ve spamu, označit
   „Není spam" a odesílatele přidat mezi kontakty. Jednorázově, ale účinné.
2. **Ve schránce založit pravidlo**, které zprávy od odesílatele FormSubmit
   (`noreply@formsubmit.co`) nikdy neoznačí jako spam a rovnou je složkuje
   třeba do „Poptávky".
3. **Nepřeposílat schránku dál** na jinou adresu (Gmail apod.). Přeposílání
   rozbíjí ověření SPF a je to nejčastější důvod, proč zprávy spadnou do spamu.

Co to vyřeší spolehlivě (doporučeno, až bude web pod vlastní doménou):

**Posílat z vlastní domény.** Zprávy pak chodí z `web@exportex.cz`, ověřené
podpisem domény, a poštovní servery je berou jako důvěryhodné. Je k tomu
potřeba:

- účet u služby pro transakční poštu (Resend, Postmark, Brevo, Mailgun —
  všechny mají tarif zdarma, který na poptávkový formulář bohatě stačí),
- do DNS domény přidat záznamy **SPF**, **DKIM** a **DMARC**, které ta služba
  vygeneruje,
- malou serverovou funkci, která poptávku převezme a odešle (viz bod 4).

Bez těchto tří záznamů v DNS bude část zpráv končit ve spamu vždycky, ať se
použije jakákoliv služba.

---

## 4. Proč tu je externí služba

Web je záměrně **čistě statický a bez backendu** — je to rozhodnutí, ne
opomenutí. Prohlížeč sám e-mail odeslat neumí; k odeslání je vždycky potřeba
server, který mluví SMTP. Když web žádný nemá, musí ho zastoupit externí
služba. Jiná cesta u statického webu neexistuje.

Do formulářové služby proto **nepatří žádné přihlašovací údaje** — jen adresa,
kam se má poptávka doručit. Kdyby se web měl někdy úplně obejít bez třetí
strany, znamenalo by to přesunout ho z GitHub Pages na hosting s PHP a doplnit
malý odesílací skript. To ale není v plánu.

## 5. Pošta na doméně

S webem nesouvisí, ale patří to k předání: schránka `mikyska@exportex.cz` musí
existovat u poskytovatele pošty a doména musí mít odpovídající **MX** záznamy.
Při změně DNS kvůli webu je nechat beze změny.

---

## 6. Kontrolní seznam po nasazení

- [ ] DNS míří na GitHub Pages, `www` má CNAME
- [ ] V Settings → Pages nastavená doména a zapnuté Enforce HTTPS
- [ ] Přepsané adresy v `index.html`, `robots.txt` a `sitemap.xml`
- [ ] Odeslaná testovací poptávka a kliknutý aktivační odkaz FormSubmit
- [ ] Poptávka dorazila do schránky, ne do spamu (jinak viz bod 3)
- [ ] Sitemapa odeslaná v Google Search Console
- [ ] MX záznamy domény nedotčené, pošta chodí dál
