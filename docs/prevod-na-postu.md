# Převod aplikace na Poštu

Pošta (**posta.simren.cz**) je společná služba Sim&Ren pro odesílání e-mailů. Aplikace
už neposílá přes vlastní SMTP schránku: předá zprávu API Pošty a Pošta ji pošle přes
schránku (Seznam, Gmail/Workspace, Microsoft 365, WEDOS, FORPSI, Active24, Webglobe nebo
vlastní SMTP; u Googlu a Microsoftu i bez hesla přihlášením přes účet), uloží kopii do
Odeslaných, při chybě zkouší znovu (1 min → 24 h), hlídá DNS domény (SPF, DKIM, DMARC –
zápis přes portál) a hlásí problémy portálu.

V aplikaci zůstává: `Mail::to()->send()` beze změny, **Logy → E-maily** (každý mail
se zapíše, stav a chyby doplní Pošta webhookem / dotazem, „Poslat znovu“ volá Poštu)
a stránka **Pošta** s tlačítkem **Propojit s poštou** a výchozím odesílatelem.

**Stará schránka se převezme sama** – heslo nikdo nepřepisuje ani nevidí: při prvním
propojení aplikace (`StaraSchranka`) nabídne Poště svou dosavadní schránku, správce Pošty
na souhlasu zaškrtne „Převzít stávající schránku aplikace“, aplikace pošle údaje i s heslem
ze svého serveru (`POST /api/v1/prevzeti-schranky`, jednou, do 10 minut) a po zkušebním
e-mailu, který Pošta potvrdí jako odeslaný, starou schránku u sebe smaže.

**Propojení zařídí portál – bez klikání.** Web, který portál vytvoří ze šablony (test pro CRM,
interní produkce, produkce klienta, přesun), dostane aplikaci v Poště sám: portál ji v Poště
založí (`PUT /api/portal/v1/aplikace/{slug}`, slug = doména webu) a token s tajemstvím předá
na server aplikace příkazem `php artisan posta:z-portalu` přes shim – **JSON na stdin**,
nikdy v argumentech ani v logu. Testovací weby dostanou v Poště testovací režim (doručí jen
na interní adresy). Existující instalace propojí tlačítko **Propojit s Poštou** u instalace
v portálu (nebo **Propojit vše** v sekci Pošta); tlačítko Propojit s poštou v aplikaci zůstává
jako záloha. Podmínka: aplikace má soubor `app/Support/Posta/PrikazZPortalu.php` (tenhle převod).

Šablony (`simren-laravel-starter`, `simren-saas-starter`) jsou převedené – nové projekty
nic nedělají. Tenhle návod je pro **existující aplikace**.

## Varianty (inventura 5. 10. 2026)

| Varianta | Projekty | Stará schránka (`StaraSchranka::cti/smaz`) | Co navíc smazat |
|---|---|---|---|
| **A – starší šablona** (Administrace → Pošta, `App\Support\Posta`, `PostaDns`) | gdpr-web, karty, licencni-server, radio, simren-web, trenink, vicenice, zapisnik (RJ-B), rootklima-web, exportex, tatana-krizova, reart (papir-mase-web), taxi (Laravel) | beze změny – klíče `posta.*` v `nastaveni` | `posta:kontrola`, `mail:retry-failed`, `mail:reconcile` |
| **A+ – šablona s vlastní stránkou** | gymporn-web (schedule v `bootstrap/app.php`), nabidky (Livewire `Superadmin/Posta`, `OdesilaZaFirmu`) | beze změny (gymporn-web: `settings` místo `nastaveni` – viz „Jiný model nastavení“) | + `mail:check-route` (`CheckMailRoute`); nabidky: `PostaNeniNastavena`, Livewire `Superadmin/Posta` |
| **A + IMAP** | rootklima-nabidky (`OdeslanePosta`, `PostaZEnvTest`, mailer `logujici-smtp`) | `cti()` vrátí i `imap_*` z `posta.imap_*` | `App\Services\OdeslanePosta` (kopii dělá Pošta – u schránky zapnout „Ukládat kopii do Odeslaných“), mailer `logujici-smtp` v `config/mail.php` |
| **B – jen .env** (MailLog, retry, bez stránky Pošta) | dochazka, gymporn-academy, trener-gymporn (Blade admin), gym-cards-pos (pokladna) | beze změny – čte `.env` (`mail.mailers.smtp`); smazat jde jen přes portál | `mail:retry-failed`, `mail:reconcile`, `mail:check-route`; stránku Pošta přidat (Blade admin: vlastní stránka podle šablony) |
| **B0 – .env bez logu** | trenuj | .env | žádné poštovní úlohy; přidat i `MailLog`/`LoggingTransport` ze šablony |
| **C – schránka na firmu (multi-tenant)** | fakturace (`TenantMailer`, `tenants.settings.smtp.*`), CRM (`NastaveniPosty`, `nastaveni` s `firma_id`, IMAP `OdeslanaPosta`) | úložiště firmy; **každá firma se propojuje sama** (slug `<aplikace>-<firma>`), propojení v nastavení firmy | per-tenant SMTP builder (`TenantMailer::mailer`, `NastaveniPosty::pouzij`) – bez propojení dál platí (moduly jen přidávají) |

Mimo šablonu (jen návrh, viz README Pošty / zpráva převodu): syncdesk (`portal/` Laravel bez šablony),
pmdp-kalendar (Node, nodemailer), rezervacni-system (Next.js demo), taxi.simren.cz (stará Java
platforma), portál (vlastní `MailSettings` – hlídá i Poštu, nemá na ní záviset), podpora (zaniká),
eshop (SaaS Server, odloženo), Pošta sama (systémová schránka).

## Krok za krokem

Pracuj na větvi `posta-prevod`, testy zelené, pak commit. Na serveru se nic nemění ručně.

1. **Zkopíruj ze šablony** (main `simren-laravel-starter`, u SaaS `simren-saas-starter`):

   ```
   app/Support/Posta/                    (celá složka – ovladač, i StaraSchranka.php)
   app/Providers/PostaServiceProvider.php
   config/posta.php
   database/migrations/2026_10_05_200000_posta_ovladac.php
   app/Filament/Pages/Posta.php          (+ resources/views/filament/pages/posta.blade.php)
   app/Services/MailRetrier.php          (Poslat znovu přes Poštu)
   tests/Feature/PostaTest.php
   ```

   a do `bootstrap/providers.php` přidej `App\Providers\PostaServiceProvider::class`.
   Starý `app/Filament/Pages/Posta.php` projektu se tím přepíše – co měl projekt navíc
   (příjemce formuláře, Obsah webu), přenes do nového.

2. **Uprav, co ovladač potřebuje** (podle šablony):
   - `app/Models/MailLog.php` – `STATUS_QUEUED` („Ve frontě Pošty“), `posta_id`
     a `posta_kontrola_at` ve `$fillable`/`casts`, `isRetryable()` = selhalo a má `posta_id`.
   - `app/Mail/LoggingTransport.php` – po odeslání přes `PostaTransport` zavolat
     `PostaTransport::poOdeslani($log, $sent)` (záznam „ve frontě Pošty“ s id zprávy);
     při chybě už neukládat `raw_mime`.
   - `app/Providers/AppServiceProvider.php` – v `extend('mail.manager')` místo
     `Posta::pouzij()` (stará schránka) volat `App\Support\Posta\Propojeni::pouzij()`.
   - `app/Support/Zdravi.php` – klíč `posta` = `App\Support\Posta\StavPosty::proZdravi()`
     (portál pak hlásí „Neodchází pošta“, když aplikace není propojená, Pošta je
     nedostupná přes 30 min nebo je ≥ 5 nedoručených za 24 h).
   - `app/Observers/AuditableObserver.php` – hodnoty klíčů `*.token`
     a `*.webhook_tajemstvi*` do Aktivity nepsat (jako `*.heslo`).
   - Přehled (`StavAplikace`), `MailLogResource` (stav „Ve frontě Pošty“, popis
     Poslat znovu), `mail-detail` (id v Poště) – podle šablony.

3. **Stará schránka** (`app/Support/Posta/StaraSchranka.php`) – ponech, jen podle varianty
   uprav `cti()` a `smaz()`:
   - **A, A+**: beze změny (klíče `posta.uzivatel`, `posta.heslo`…).
   - **A + IMAP**: v `cti()` doplň `imap_zapnuto`, `imap_host`, `imap_port`, `imap_sifrovani`,
     `imap_slozka` z klíčů projektu; do `KLICE` přidej jejich jména.
   - **B, B0** (.env): beze změny – `cti()` čte `mail.mailers.smtp`; `smaz()` nic nesmaže
     a stránka Pošta po zkoušce připomene smazat `MAIL_*` přes portál.
   - **C** (firmy): viz „Aplikace s firmami“ níž.
   - **Jiný model nastavení** (`settings` s `key`/`value` – gymporn-web, tatana-krizova, nabidky;
     vlastní tabulka): v `Propojeni` se mění jen `cti()`, `zapis()` a `smaz()`, ve `StaraSchranka`
     jen `cti()` a `smaz()`.

4. **Smaž starou poštovní logiku** – co v projektu je:

   | Co | Kde to bývá |
   |---|---|
   | stará schránka a DNS návod | `app/Support/Posta.php`, `app/Support/PostaDns.php`, vlastní `NastaveniPosty`, `KontrolaPosty` (služba), Livewire `Superadmin/Posta` (nabidky), `PostaNeniNastavena` |
   | příkazy | `posta:kontrola` (`KontrolaPosty`), `mail:retry-failed` (`RetryFailedMails`), `mail:reconcile` (`ReconcileMailLogs`), `mail:check-route` (`CheckMailRoute`) a podobné „kontroly cesty pošty“ |
   | plánované úlohy | jejich řádky v `routes/console.php` **i v `bootstrap/app.php`** (`->withSchedule`, gymporn-*, pokladna) – Pošta plánuje `posta:fronta` a `posta:obnov-token` v `PostaServiceProvider`. `schedule:run` a ostatní úlohy zůstávají. |
   | testy staré pošty | `tests/Feature/PostaTest.php` (stará verze), `MailRouteCheckTest`, `PostaZEnvTest`, testy `MailRetrier` s `raw_mime`, `Posta::$prihlaseni` v ostatních testech |
   | „pošle znovu sám“ v kódu | komentáře/větve, které spoléhají na `mail:retry-failed` (pokladna `CloseOpenShifts`, `SendMonthlyStats`; trener `Notifier`) – teď to zařídí odchozí fronta ovladače, výjimku při nedostupnosti Pošty ovladač nevyhazuje |
   | SMTP v `.env` | `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` – v repu jen `.env.example` (`MAIL_MAILER=log`); na serveru až po převzetí přes portál |
   | vlastní mailery | `logujici-smtp` a spol. v `config/mail.php` (výchozí `mail.default` zůstane `log` jako záloha bez propojení) |
   | IMAP kopie do Odeslaných v aplikaci | `OdeslanePosta` / `OdeslanaPosta` (rootklima-nabidky, CRM) – kopii dělá Pošta u schránky |
   | výchozí pošta v `config/nastaveni.php` | `posta.uzivatel`, `posta.host`… |

   Klíče staré schránky v `nastaveni` **nemaž migrací** – smaže je `StaraSchranka` po převzetí
   a zkušebním e-mailu. Migrace na úklid jen tam, kde se převzetí nepovedlo a schránku
   správce zadal v Poště ručně (pak `Nastaveni::smaz('posta.heslo')`… novou migrací).
   `mail_logs.raw_mime` zůstává (starší záznamy), nové se do něj nepíšou.

5. **Testy**: `php artisan test --compact </dev/null` – zelené. Testy se NIKDY nepřipojují
   ke skutečné Poště (`Http::fake`, `Http::preventStrayRequests()`).

6. **Nasazení a propojení** (po review, nasazení přes git/portál):
   1. V aplikaci: Administrace → Pošta → **Propojit s poštou** – v okně je zaškrtnuté
      „Převzít stávající schránku (adresa)“, když aplikace starou schránku má.
   2. V Poště na souhlasu: zaškrtnout **Převzít stávající schránku aplikace** (nebo přidělit
      existující schránku) a Povolit (potvrzení zařízením).
   3. Zpět v aplikaci: „Pošta převzala schránku … a přihlášení ověřila“. Nefunguje-li
      přihlášení, opravit heslo v Poště (Schránky → Ověřit).
   4. **Poslat zkušební e-mail** – po potvrzení Poštou (Logy → E-maily: Odesláno) se stará
      schránka z aplikace sama smaže (heslo z databáze). U `.env` (varianta B) pak přes portál
      smazat `MAIL_HOST/USERNAME/PASSWORD` (`MAIL_MAILER=log`).
   5. V Poště: Domény → detail → Nastavit DNS (zóna u nás) nebo návod pro klienta.

   Záloha bez tlačítka: v Poště Aplikace → Přidat ručně (klíč se ukáže jednou), v aplikaci
   Pošta → Klíč ručně (bez převzetí – schránku pak správce zadá v Poště sám).

## Aplikace s firmami (varianta C)

Každá firma má vlastní schránku, takže se s Poštou propojuje **každá firma sama** (jako
s Fakturací): v Poště je to samostatná aplikace se slugem `<aplikace>-<firma>` a vlastními
přidělenými adresami. Vzor je CRM (větev `posta-prevod`, `App\Sluzby\PostaFirmy`):

- propojení (token, tajemství webhooků, adresy) šifrovaně v nastavení **firmy**, návrat
  z Pošty a webhook (`/webhooky/posta/{firma}`) pracují v kontextu té firmy,
- mailer `posta` se zapne jen pro firmu, která je propojená; **nepropojená firma posílá
  postaru** (stará schránka) – moduly jen přidávají,
- stará schránka = schránka firmy; po převzetí a zkoušce se smaže jen u té firmy,
- kopii do Odeslaných dělá Pošta (u schránky zapnout), aplikace ji už nedělá.

## Jak to funguje

- `PostaTransport` (mail transport `posta`) převede zprávu na API Pošty: příjemci vždy
  jako pole, skrytá kopie i z obálky, přílohy base64, štítek z `->tag()`, metadata
  z `->metadata()`, hlavičky jen z whitelistu (List-Unsubscribe…). Odesílatel jen
  z přidělených adres – jiný From (z `.env`, z kódu) nahradí výchozí odesílatel, jméno
  zůstane.
- Pošta nedostupná (spojení, 5xx, 429, neplatný token) → zpráva do `posta_odchozi`
  (šifrovaně), aplikace jede dál; `posta:fronta` ji předá se **stejným Idempotency-Key**
  (Pošta ji nepošle dvakrát), po 72 h vzdá a záznam označí jako selhaný.
- Pošta odmítla (neplatná adresa…) → `TransportException` jako dřív u SMTP.
- Výsledek: webhook `POST /posta/webhook` (podpis V2 s časem jako u Fakturace, každé
  doručení jednou), záložně `posta:fronta` po 15 minutách zjistí stav dotazem.
- Token se obnovuje sám 30 dní před vypršením (`posta:obnov-token`).
- **Z portálu** (`PrikazZPortalu`, `posta:z-portalu`, stdin): `{"akce":"zjisti"}` vrátí, jestli
  je aplikace propojená a jakou má starou schránku (bez hesla); `{"akce":"propoj", url, token,
  webhook_tajemstvi, aplikace, prevzeti}` uloží klíče jako po tlačítku (`Propojeni::zPortalu`)
  a když Pošta otevřela převzetí, pošle starou schránku; `{"akce":"odpoj"}` klíče zapomene.
  Výstup = jeden řádek JSON bez tajemství.
- **.env jako záloha:** `POSTA_TOKEN`, `POSTA_WEBHOOK_TAJEMSTVI`, `POSTA_OD` (a `POSTA_URL`)
  platí, jen když v nastavení propojení není – web pak posílá hned po nasazení. Klíče
  v nastavení (portál, tlačítko) mají přednost.
- **Testovací režim Pošty:** zpráva, jejíž žádný příjemce není interní, skončí v Poště jako
  „Zadrženo (test)“ – webhook `zprava.zadrzena`, v Logy → E-maily stav **Zadrženo (test)**
  (`MailLog::STATUS_HELD`), neopakuje se.
- Převzetí staré schránky: `Propojeni::zacni()` pošle `from` = stará adresa a `prevzeti=1`,
  `dokonci()` po výměně kódu zavolá `POST /prevzeti-schranky` (Pošta: jednou, do 10 minut,
  existující schránku jen přidělí, heslo nepřepíše), stav v `posta.prevzeti`; zkušební
  e-mail si zapamatuje id a `Webhook::aktualizuj` po „odeslano“ zavolá `StaraSchranka::poDoruceni()`.

**Cron na serveru se kvůli Poště nemění**: plánovač `schedule:run` zůstává, poštovní
úlohy zmizí z kódu. Poštovní úlohy šablony běží jako closure uvnitř `schedule:run` (od 6. 10. 2026,
`OdchoziFronta` jen když je co předat) – nový proces PHP každou minutu nepřidávají. Řádek
`queue:work` v cronu nahrazuje fronta z plánovače (`FrontaUloh`, manifest `fronta_spousti: planovac`);
řádek cronu odebírá portál (`portal-sprava crony-upravit`), ne ručně. Samostatné poštovní crony v Hestii při inventuře 5. 10. 2026 nebyly
(SimRen-server, Saas Server, Zálohy) – kdyby se objevily, ruší se přes portál, ne ručně.

V SaaS šabloně je propojení jedno pro celou platformu (Administrace na hlavní doméně) –
firmy posílají přes něj z adres, které platformě přidělil správce Pošty.

API Pošty: v repu Pošty `docs/api.md`.
