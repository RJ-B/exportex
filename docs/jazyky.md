# Jazyky

Texty aplikace nejsou v kódu natvrdo. V kódu je jen klíč, text je v `lang/<jazyk>/<oblast>.php`:

```php
TextInput::make('nazev')->label(__('sprava.udaje_firmy.nazev_firmy'))
```

```blade
<h2>{{ __('saas.registrace.zalozit_firmu') }}</h2>
```

Výchozí jazyk je čeština (`APP_LOCALE=cs`), druhý jazyk angličtina. Chybějící překlad se ukáže
česky (`APP_FALLBACK_LOCALE=cs`), nikdy jako klíč.

## Soubory

| Kde | Co |
|---|---|
| `config/jazyky.php` | zapnuté jazyky (`kód => název`), výchozí jazyk, jazyk podle prohlížeče |
| `lang/cs/*.php`, `lang/en/*.php` | texty podle oblasti: `web`, `administrace`, `platby`, `posta`, `seo`, `maily`, `modely`, `pravni`, `obecne`, `formaty` |
| `lang/cs/validation.php` … | hlášky Laravelu (validace, přihlášení, hesla, stránkování) |
| `App\Support\Jazyky\Jazyky` | zapnuté jazyky a určení jazyka požadavku |
| `App\Http\Middleware\NastavJazyk` | nastaví jazyk (skupina web i oba panely) |

Klíč je `oblast.skupina.klic`. Skupina je jméno souboru, odkud text je (`UdajeFirmy.php` →
`udaje_firmy`), klíč jsou první slova českého textu bez diakritiky. Parametry jsou `:jmeno`:
`__('maily.vitejte.dobry_den', ['jmeno' => $spravce->jmeno])`.

Filament má vlastní překlady (tlačítka, tabulky, kalendář) a řídí se stejným jazykem.

## Kdo jazyk určí

První zapnutý jazyk z tohoto pořadí (`Jazyky::urci`):

1. přihlášený uživatel (`users.jazyk`, volí si ho v Profilu),
2. prohlížeč (`Accept-Language`) – jen se zapnutým `JAZYK_PODLE_PROHLIZECE=true`,
3. výchozí jazyk (`APP_LOCALE`).

Veřejný web v jiném jazyce na vlastní doméně (en.example.cz) zatím šablona nemá – projekt by
v `Jazyky::urci` přidal jazyk podle domény (SaaS šablona to má jako `saas.znacky`).

E-mail uživateli jde v jeho jazyce (`User::preferredLocale`). Data, čísla a měny se formátují podle
jazyka (`Carbon`, `Number`, `__('formaty.datum')`). Výběr jazyka v Profilu se ukáže jen při víc
zapnutých jazycích.

## Nový jazyk

1. Zkopírovat angličtinu: `cp -r lang/en lang/de` a přeložit texty (klíče a `:parametry` nechat).
2. Hlášky Laravelu pro nový jazyk (`validation.php`, `auth.php`, `passwords.php`, `pagination.php`)
   vzít z [laravel-lang/lang](https://github.com/Laravel-Lang/lang) do `lang/de/`. Angličtinu má Laravel sám.
3. Zapnout v `config/jazyky.php`: `'de' => 'Deutsch'`.
4. `php artisan jazyky:kontrola --prisne` – vypíše, co v novém jazyce chybí nebo má jiné parametry.
   `JazykyTest` hlídá, že zapnutý jazyk má všechny texty.

## Nový kód

- Každý text pro člověka přes `__('oblast.skupina.klic')`, český text do `lang/cs/<oblast>.php`
  a hned i anglický do `lang/en/<oblast>.php`. Nejrychleji: napsat kód česky a pustit
  `php artisan jazyky:prevod app/…` (klíč vymyslí sám), pak doplnit angličtinu.
- **Filament:** vlastnosti s textem (`$navigationLabel`, `$title`, `$modelLabel`, `$heading`…)
  se vyhodnotí při načtení třídy – místo nich metody (`getNavigationLabel()`, `getTitle()`).
  Skupiny menu jen přes `App\Filament\Navigace` (`Navigace::firma()`), panel je registruje
  closurou. V `PanelProvider` text vždy v closuře (`->label(fn () => __('…'))`).
- **Konstanty s texty** nejdou (`const STAVY = ['aktivni' => 'Aktivní']`) – statická metoda
  (`Firma::stavy()`).
- **Porovnání s textem** nikdy s českým řetězcem – s klíčem nebo hodnotou (`$state === __('…')`).
- **JavaScript** v Blade dostane text z PHP: `const popis = @js(__('web.nastaveni.slabe_heslo'));`.
  **Statické skripty v `public/js`** (datum.js, passkeys.js…) berou texty z `lang/<jazyk>/js.php`
  (`T('datum.vyber_data')`, skupina = jméno souboru); stránka je předá přes
  `@include('jazyky.texty-js')` – vložit před skript.
- **Datum:** `->format(__('formaty.datum'))`, ne `'j. n. Y'`.
- **Jazyk stránky:** `<html lang="{{ app()->getLocale() }}">`.
- Texty pro vývojáře se nepřekládají: logy (`Log::`), výjimky chyb v kódu (`LogicException`,
  `InvalidArgumentException`…), konzolové příkazy (`app/Console`, potomci `Command`).
  Jinou výjimku označí komentář `// bez překladu` na řádku (nebo nad ním).

## Kontrola

```bash
php artisan jazyky:kontrola
```

Vypíše texty natvrdo (české řetězce v PHP, text a atributy v Blade, řetězce v JS), klíče z kódu,
které chybí ve výchozím jazyce, a co chybí v dalších jazycích. Selže na textu natvrdo a na
chybějícím klíči ve výchozím jazyce; `--prisne` i na chybějícím překladu, `--jen-hlasit` nikdy
(projekt, který převádí postupně). Totéž hlídá `tests/Feature/JazykyTest.php` – běží s ostatními testy.

Kontrola pozná text podle diakritiky; bez ní jen u popisků Filamentu (`->label('Firma')`,
`Section::make('Kontakty')`…), metod vracejících popisek (`getTitle()`, `popis…()`, `nazev…()`, `label…()`)
a u textu v Blade. Čistě anglicky nebo bez diakritiky napsaný text jinde neodhalí.

## Převod existujícího kódu (projekty ze šablony)

```bash
php artisan jazyky:prevod --nanecisto     # co by převedl
php artisan jazyky:prevod                 # celý projekt, nebo jen cesta: jazyky:prevod app/Filament
```

Převod nahradí texty klíči, zapíše `lang/cs/*.php` a vypíše, co musí člověk (konstanty tříd,
klíče polí, porovnání, text ve skriptu). Jde pustit opakovaně – stejné texty dostanou stejné
klíče. Po převodu: projít diff, upravit vypsaná místa podle „Nový kód“, `php artisan jazyky:kontrola`,
testy, nakonec doplnit `lang/en`.
