# Testovací schránka test@simren.cz

Testovací weby (`*.test.simren.cz`, `APP_ENV=staging`) posílají poštu z testovací schránky
Sim&Ren a **nic z nich nesmí odejít skutečným lidem**. Produkce se toho nikdy netýká.

## Kdo co dělá

- **Pošta** (posta.simren.cz) přidělí testovacím aplikacím (`rezim=test`) schránku test@simren.cz.
  Heslo je jen v Poště – do `.env` webů ani do repa nepatří. Přidělení zařizuje portál.
- **Aplikace** posílá dál normálně přes `Mail::` a Poštu. Na testu každý e-mail přesměruje
  (`App\Support\Posta\TestovaciSchranka`, `PresmerovaniNaTest`, obal v `LoggingMailManager`):
  - odesílatel i jediný příjemce `test@simren.cz`, kopie, skryté kopie a Reply-To se zruší,
  - komu by šel: předmět `[TEST → jana@…, karel@…] Původní předmět` a hlavičky
    `X-Simren-Puvodni-Prijemci`, `X-Simren-Puvodni-Odesilatel`, `X-Simren-Puvodni-Reply-To`,
  - platí pro každý mailer, frontu, notifikace i Poštu (obaluje se transport, ne volání).

## Kdy je přesměrování zapnuté

| prostředí | přesměrování |
|---|---|
| `production` | **nikdy** – ani kdyby byla testovací adresa nastavená |
| `staging` (testovací web) | vždy |
| `local`, `testing` | jen když je odesílatelem test@simren.cz (Pošta, `MAIL_FROM_ADDRESS`, `POSTA_OD`) |

## Pojistky

- **/zdravi v produkci:** odesílatel = test@simren.cz (přidělený Poštou nebo z `.env`) → tvrdá
  kontrola `posta_test` selže a `/zdravi` vrátí 503. `simren:zdravi --json` hlásí v bloku `posta`
  i `od` (adresa, ze které aplikace opravdu posílá) a `smtp_uzivatel` (jen bez Pošty, nikdy heslo) –
  portál z nich otevře incident „Produkce posílá z testovací schránky“.
- **Databáze:** testovací schránka se do ní nezapisuje (config `mail.*` se nemění, přesměrování je
  jen v transportu). Starou schránku test@simren.cz (`posta.uzivatel`…) aplikace Poště k převzetí
  nenabídne (`StaraSchranka::zjisti`).
- **Přenos dat test → produkce:** části nepřenesou řádky `nastaveni` s hodnotou test@simren.cz
  (`Casti::patri`); po importu celé databáze `PoImportu` smaže starou schránku test@simren.cz
  i nastavení s touto hodnotou (výsledek `testovaci_schranka`: `zapomenuta` / `zadna`).

Testy: `tests/Feature/TestovaciSchrankaTest.php`.
