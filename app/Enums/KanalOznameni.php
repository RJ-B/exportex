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
            self::Centrum => __('modely.kanal_oznameni.centrum_oznameni'),
            self::Pruh => __('modely.kanal_oznameni.pruh_pres_web'),
            self::Email => __('modely.kanal_oznameni.e_mail'),
            self::WebPush => __('modely.kanal_oznameni.upozorneni_v_prohlizeci'),
            self::MobilPush => __('modely.kanal_oznameni.mobilni_aplikace'),
        };
    }

    public function popis(): string
    {
        return match ($this) {
            self::Centrum => __('modely.kanal_oznameni.zvonecek_na_webu_i_v_administraci'),
            self::Pruh => __('modely.kanal_oznameni.pruh_nahore_na_kazde_strance_odstavky'),
            self::Email => __('modely.kanal_oznameni.pres_postu_novinky_a_nabidky_jen'),
            self::WebPush => __('modely.kanal_oznameni.pripravujeme'),
            self::MobilPush => __('modely.kanal_oznameni.pripravujeme'),
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
