# Nasazení a předání

Vše, co je potřeba udělat, aby web běžel na vlastní doméně a formulář doručoval
poptávky. Postup je psaný tak, aby se dal předat člověku, který spravuje doménu
a poštu — ten nepotřebuje nic z kódu měnit kromě jednoho řádku.

---

## 1. Doména — hotovo (23. 9. 2026)

Web běží na `https://exportex.cz/`. Doména je registrovaná u **Forpsi**
(registrátor INTERNET CZ, a.s.), pošta běží tamtéž.

**DNS zóna, jak je nastavená:**

| Typ | Hostname | Hodnota |
|---|---|---|
| A | `exportex.cz` | `185.199.108.153` |
| A | `exportex.cz` | `185.199.109.153` |
| A | `exportex.cz` | `185.199.110.153` |
| A | `exportex.cz` | `185.199.111.153` |
| CNAME | `www` | `rj-b.github.io` |

Pošta a kalendáře zůstaly nedotčené: `MX 10 mxavas.forpsi.com`, `autoconfig`,
`autodiscover`, oba SRV záznamy na `syncdav.forpsi.com`, DKIM v selektoru
`f2026._domainkey` a `_dmarc`. Na nic z toho se nesahá — smazáním MX přestane
chodit pošta a s ní i poptávky z formuláře.

**Dvě věci, které se při přepnutí musely změnit:**

- **Wildcard `*.exportex.cz CNAME exportex.cz` byl smazán.** Mířil by na sdílené
  IP adresy GitHub Pages, takže kdokoliv s účtem na GitHubu by si mohl zabrat
  libovolnou subdoménu `exportex.cz` pro vlastní web (subdomain takeover).
  Místo něj je `www` zadaný natvrdo. Kdyby byl wildcard někdy potřeba, musí se
  doména v GitHubu ověřit: Settings → Pages → Verify domain.
- **SPF se zkrátil** z `v=spf1 a mx include:_spf.forpsi.com -all` na
  `v=spf1 include:_spf.forpsi.com -all`. Mechanismus `a` povoluje odesílat poštu
  tomu, co je v A záznamu — po přepnutí by to byly servery GitHub Pages sdílené
  se všemi weby na GitHubu. `mx` bylo zbytečné, adresa poštovního serveru
  (`81.2.195.200`) v tom includu už je.

**Na GitHubu:** Settings → Pages → Custom domain `exportex.cz`. GitHub si sám
vytvořil v repozitáři soubor `CNAME`; ten nesmí zmizet, jinak se web vrátí zpět
na adresu `rj-b.github.io/exportex/`.

Adresy v kódu jsou přepsané na `https://exportex.cz/`. Seznam míst, kde je
adresa natvrdo, je v komentáři v hlavičce `index.html`.

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

Co to vyřeší spolehlivě (web už pod vlastní doménou je, takže je to na stole):

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

- [x] DNS míří na GitHub Pages, `www` má CNAME, wildcard smazaný
- [x] V Settings → Pages nastavená doména `exportex.cz`
- [x] Přepsané adresy v `index.html`, `robots.txt` a `sitemap.xml`
- [x] SPF zkrácený na `v=spf1 include:_spf.forpsi.com -all`
- [x] MX záznamy domény nedotčené, pošta chodí dál
- [ ] Zapnuté **Enforce HTTPS** — až GitHub vydá certifikát Let's Encrypt
- [ ] Odeslaná testovací poptávka a kliknutý aktivační odkaz FormSubmit
- [ ] Poptávka dorazila do schránky, ne do spamu (jinak viz bod 3)
- [ ] Sitemapa odeslaná v Google Search Console
