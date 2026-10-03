# Nasazení a předání

Web běží **jen u nás na serveru** (simren-server, HestiaCP). GitHub Pages jsou zrušené –
soubor `CNAME` ani `.nojekyll` v repu nejsou a nesmí se vracet.

Od 10/2026 je web Laravel na šabloně Sim&Ren. Nasazuje ho portál podle
`.simren/projekt.yml`: commit na `main` → CRM → portál → stažení nové verze mimo web,
`composer install`, migrace, cache, `storage:link`, restart fronty, kontrola `/zdravi`;
když kontrola neprojde, běží dál předchozí verze. **Ručně se na server nic nekopíruje
a nespouští** (žádný `git pull`, scp ani seedery) – jen git.

| Věc | Hodnota |
|---|---|
| Produkce | `https://exportex.cz` (www → exportex.cz přesměruje web sám, `KanonickaDomena`) |
| Test | `https://exportex-web.test.simren.cz` – nasazuje se sám z `main` |
| Server | simren-server (Contabo, EU), HestiaCP |
| Kořen webu | `public/` (ne kořen repa) |
| PHP | 8.5 |
| Databáze | MariaDB (zakládá portál, údaje jen v `.env` na serveru) |
| Plánovač, fronta | cron `schedule:run` každou minutu, `queue:work` fronty `default` (zakládá portál) |
| Kontrola | `/zdravi` (200/503 podle databáze, cache a storage; plánovač, frontu a poštu hlásí portálu) |
| Repozitář | `RJ-B/exportex` (veřejný) |

## Co musí být na serveru

Zakládá a udržuje portál – tady pro kontrolu, kdyby se web zakládal znovu:

1. **Kořen webu `public/`** a PHP **8.5** (Hestia šablona pro Laravel).
2. **Databáze MariaDB** pro web a `.env` s klíči:
   `APP_NAME=Exportex`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` (vygenerovaný),
   `APP_URL=https://exportex.cz` (u testu adresa testu), `DB_CONNECTION=mariadb`,
   `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
   `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`,
   `MAIL_MAILER=log` (skutečná pošta se nastavuje v administraci, viz níž).
   Hesla a klíče jen v `.env` na serveru, nikdy v repu.
3. Po prvním nasazení: `php artisan migrate` doplní údaje firmy a předvyplní poštu
   (bez hesla), `storage:link` zpřístupní fotky nahrané v administraci.
4. Účet správce z CRM (*Můj účet správce* → `simren:spravce`) – heslo si nastaví sám přes odkaz.

## Pošta – Forpsi

Schránky exportex.cz jsou u **Forpsi** a tam i zůstanou. Web neposílá přes server
(ten žádnou poštu nemá), ale přihlásí se ke schránce u Forpsi:

- Administrace → *Obsah webu → Kontakt a formulář*: poskytovatel **Forpsi**
  (`smtp.forpsi.com`, port 465, SSL/TLS), schránka `mikyska@exportex.cz` – obojí je
  předvyplněné. **Heslo ke schránce zadá správce sám** a uloží (aplikace se nejdřív zkusí
  přihlásit; heslo se ukládá zašifrované a do formuláře se nevrací). Pak *Poslat zkušební
  e-mail*.
- Dokud heslo není uložené, poptávky se na webu ukládají do *Zpráv z webu*, jen e-mail
  neodchází – Pošta to v menu hlásí štítkem „!“.
- Upozornění na poptávky chodí na `mikyska@exportex.cz` (kontaktní e-mail v *Hlavička
  a patička*, jinou adresu jde nastavit v *Kontakt a formulář*). Odpověď jde rovnou
  zákazníkovi.
- Kontrola přihlášení ke schránce běží každých 6 hodin (`posta:kontrola`); když se heslo
  ve Forpsi změní, portál otevře incident „Neodchází pošta“.

## DNS (u Forpsi)

Doména je registrovaná u Forpsi (INTERNET CZ, a.s.), DNS zóna tamtéž (stav 3. 10. 2026):

| Typ | Název | Hodnota |
|---|---|---|
| A | `exportex.cz` | `13.140.162.57` (náš server) |
| A | `www` | `13.140.162.57` |
| MX | `exportex.cz` | `10 mxavas.forpsi.com` |
| TXT | `exportex.cz` | `v=spf1 include:_spf.forpsi.com -all` |
| TXT | `_dmarc` | `v=DMARC1; p=quarantine` |
| TXT | `f2026._domainkey` | DKIM od Forpsi |

Pošta a kalendáře (`MX`, `autoconfig`, `autodiscover`, SRV na `syncdav.forpsi.com`, DKIM,
DMARC) se při změnách webu **nemění** – smazáním MX přestane chodit pošta i upozornění
na poptávky. Kontrolu MX, SPF a DMARC umí *Kontakt a formulář → Zkontrolovat DNS*.

- **SPF bez mechanismu `a`.** A záznam míří na náš server a ten poštu neposílá; `a` by
  povolil posílat za exportex.cz komukoli s IP serveru. Web posílá přes SMTP Forpsi,
  takže stačí `include:_spf.forpsi.com`.
- **Bez wildcardu `*.exportex.cz`.** Subdomény se zakládají jednotlivě.

## Formulář

Poptávky jdou na náš server (`/kontakt`), ne k cizí službě – formsubmit.co se už
nepoužívá a jeho aktivační e-mail ani alias nejsou potřeba. Ochrana proti spamu je na
serveru: skryté pole, podepsaný čas zobrazení (rychlejší než 3 s nebo starší než 2 h =
robot, tváří se jako odesláno), limit 3 odeslání za minutu a 20 za den z jedné IP.
Žádná captcha.
