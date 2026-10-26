# Fakturace (doplněk šablony)

Doklady (faktury, zálohy, příjmové doklady) vystavuje **fakturace.simren.cz**. Web si je jen objedná
přes API a o výsledku se dozví webhookem. Doplněk je ve výchozím stavu **vypnutý**
(`config/sablona.php` → `doplnky.fakturace`). Vypnutý = žádný webhook ani stránka v administraci.

## Zapnutí a napojení – nic ručně

1. V CRM se u projektu zaškrtne doplněk **Fakturace** (okno „Realizace: projekt a web“ nebo tlačítko
   Doplňky). CRM ho v repu zapne commitem „Zapnout doplněk fakturace (CRM)“ – změní výchozí hodnotu
   `'fakturace' => (bool) env('SABLONA_DOPLNEK_FAKTURACE', false)` na `true` (env je jen pro testy).
2. Portál web napojí sám a klíče zapíše do `.env` (`FAKTURACE_URL`, `FAKTURACE_TOKEN`,
   `FAKTURACE_WEBHOOK_SECRET`, `FAKTURACE_REZIM`):
   - **test** (`*.test.simren.cz`) dostane **testovací napojení** – doklady `TEST-…`, mimo účetnictví,
     e-mailem jen na testovací adresy; produkce je nikdy neuvidí,
   - **produkce** (při předání) dostane **nové ostré napojení** s vlastními klíči. `.env` se při
     přenosu dat z testu nepřenáší, produkce testovací klíče nedostane.
3. Firmu, které projekt patří (klient zakázky, u interního projektu firma CRM), najde fakturace podle
   IČO; když ji nemá, založí ji z CRM a ARES. E-mail pro doklady pošle CRM (kontakt klienta).
4. Jediný údaj, který nikdo jiný nezná, je **bankovní účet** – správce ho zadá v administraci webu
   (níž). Nikdo nemusí do fakturace.

Kdo v CRM nebo portálu klikne na **Můj účet správce**, je zároveň **majitel firmy ve fakturaci**,
na kterou je web napojený (dělá portál po úspěšném `simren:spravce`, šablona nic nedělá).

## Administrace: Nastavení → Fakturace (jen správce)

- **Napojení na fakturaci**: Napojeno (ostré doklady) / Testovací režim / Čeká na napojení.
- **Bankovní účet pro doklady**: aktuální účet z fakturace (číslo, banka, IBAN, SWIFT) a pole Číslo
  účtu. Uložení jde do fakturace (`PATCH /api/v1/firma`, oprávnění `firma:udaje`), IBAN a SWIFT se
  dopočítají, neplatné číslo (kontrola ČNB) se ukáže u pole. Do auditu `fakturace.ucet`.
  **Testovací web účet jen ukazuje** – mění se na ostrém webu, v CRM nebo ve fakturaci.

## Kód

- `App\Fakturace\Fakturace` – klient API: `vystav($doklad)` (`POST /invoices`; posílejte
  `external_id` a `idempotency_key`, opakování pak nevyrobí druhý doklad), `udajeFirmy()`,
  `ulozUdajeFirmy()`, `stav()`, `napojeno()`, `testovaci()`.
- Webhook `POST /webhooky/fakturace` (`App\Fakturace\Http\FakturaceWebhook`): podpis V2
  (`X-Fakturace-Signature-V2`, max. 5 minut, `App\Fakturace\PodpisWebhooku`), každé doručení jednou.
  Ověřená událost jde dál jako `App\Fakturace\FakturaceUdalost` – projekt si pověsí posluchače:

  ```php
  Event::listen(FakturaceUdalost::class, function (FakturaceUdalost $u) {
      if ($u->udalost === 'invoice.paid') {
          Objednavka::where('cislo', $u->doklad()['external_id'])->update(['zaplaceno' => true]);
      }
  });
  ```

- Testy: `tests/Feature/Fakturace` (zapnutý i vypnutý doplněk).

Dokumentace API fakturace: ve fakturaci po přihlášení, Automatizace → Napojení → Dokumentace API.
