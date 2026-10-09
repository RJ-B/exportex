<?php

namespace App\Enums;

/**
 * Kudy oznámení k člověku dojde. Oznámení žije jednou (tabulka oznameni),
 * kanály jsou jen cesty k němu – přečtení v centru, na mobilu i proklik
 * z e-mailu se zapisují k témuž příjemci.
 *
 * Web push a mobilní push jsou zatím jen připravené (docs/oznameni.md, krok 3):
 * v modelu existují, nabízet se začnou, až budou umět doručit.
 */
enum KanalOznameni: string
{
    /** Zvoneček na webu i v administraci (a seznam v mobilu přes API). */
    case Centrum = 'centrum';

    /** Pruh přes celý web a administraci – vidí ho i nepřihlášení, když je pro všechny. */
    case Pruh = 'pruh';

    /** E-mail přes Poštu (posta.simren.cz). */
    case Email = 'email';

    /** Web push (VAPID, bez cizí služby) – krok 3. */
    case WebPush = 'web_push';

    /** Push do mobilní aplikace přes Firebase – krok 3. */
    case MobilPush = 'mobil_push';

    public function nazev(): string
    {
        return match ($this) {
            self::Centrum => 'Centrum oznámení',
            self::Pruh => 'Pruh přes web',
            self::Email => 'E-mail',
            self::WebPush => 'Upozornění v prohlížeči',
            self::MobilPush => 'Mobilní aplikace',
        };
    }

    public function popis(): string
    {
        return match ($this) {
            self::Centrum => 'Zvoneček na webu i v administraci, nepřečtené s počtem.',
            self::Pruh => 'Pruh nahoře na každé stránce – odstávky a výpadky. Pro všechny ho vidí i nepřihlášení.',
            self::Email => 'Přes Poštu; novinky a nabídky jen těm, kdo s nimi souhlasili.',
            self::WebPush => 'Připravujeme.',
            self::MobilPush => 'Připravujeme.',
        };
    }

    /** Umí ho aplikace doručit? Push zatím ne (krok 3). */
    public function dostupny(): bool
    {
        return in_array($this, [self::Centrum, self::Pruh, self::Email], true);
    }

    /** Dojde k člověku mimo aplikaci – u novinek a nabídek jen se souhlasem. */
    public function vnejsi(): bool
    {
        return in_array($this, [self::Email, self::WebPush, self::MobilPush], true);
    }

    /** Doručuje se po příjemcích (řádek v oznameni_doruceni). Centrum a pruh ne. */
    public function doruceniPoPrijemcich(): bool
    {
        return $this->vnejsi();
    }

    /** @return list<self> Co si uživatel nastavuje v předvolbách (pruh ne – patří všem). */
    public static function predvolby(): array
    {
        return array_values(array_filter(self::cases(), fn (self $k) => $k !== self::Pruh && $k->dostupny()));
    }
}
