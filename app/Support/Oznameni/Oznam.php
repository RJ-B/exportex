<?php

namespace App\Support\Oznameni;

use App\Enums\DruhOznameni;
use App\Enums\KanalOznameni;
use App\Enums\ZavaznostOznameni;
use App\Models\Oznameni;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Oznámení z kódu aplikace – jedna třída pro vývojáře projektu.
 *
 *   Oznam::provozni('Objednávka 2026-104 je na cestě')
 *       ->text('Balík jsme předali PPL, doručení zítra.')
 *       ->odkaz(route('objednavky.detail', $objednavka), 'Sledovat zásilku')
 *       ->komu($objednavka->user)
 *       ->klic('objednavka-'.$objednavka->id.'-odeslana')   // podruhé už nic nepošle
 *       ->posli();
 *
 *   Oznam::servisni('V noci na neděli bude web 30 minut nedostupný')
 *       ->vsem()->kanaly(KanalOznameni::Centrum, KanalOznameni::Pruh)
 *       ->odstavka($od, $do)->pruh(now(), $do)->posli();
 *
 * Výchozí kanály: centrum a e-mail. Předvolby a souhlasy platí stejně jako
 * z administrace (novinky e-mailem jen se souhlasem); limity a potvrzení
 * počtu ne – za rozesílku z kódu odpovídá kód.
 */
class Oznam
{
    private array $data;

    private ?string $klic = null;

    private function __construct(DruhOznameni $druh, string $titulek)
    {
        $this->data = [
            'zdroj' => 'aplikace',
            'druh' => $druh,
            'titulek' => mb_substr($titulek, 0, 160),
            'kanaly' => [KanalOznameni::Centrum->value, KanalOznameni::Email->value],
            'cileni' => ['komu' => 'vybrani', 'uzivatele' => []],
            'zavaznost' => ZavaznostOznameni::Info,
        ];
    }

    public static function provozni(string $titulek): self
    {
        return new self(DruhOznameni::Provozni, $titulek);
    }

    public static function servisni(string $titulek): self
    {
        return new self(DruhOznameni::Servisni, $titulek);
    }

    public static function novinky(string $titulek): self
    {
        return new self(DruhOznameni::Novinky, $titulek);
    }

    /** Text v Markdownu (odstavce, tučné, odkazy, odrážky). */
    public function text(string $text): self
    {
        $this->data['text'] = $text;

        return $this;
    }

    /** Kam vede (relativní /cesta nebo celá adresa) a co je na tlačítku. */
    public function odkaz(string $url, ?string $text = null): self
    {
        $this->data['odkaz'] = $url;
        $this->data['odkaz_text'] = $text;

        return $this;
    }

    /** Jeden člověk, víc lidí nebo jejich id – každý dostane jednou. */
    public function komu(User|int|iterable $kdo): self
    {
        $ids = is_iterable($kdo) ? collect($kdo)->map(fn ($u) => $u instanceof User ? $u->getKey() : (int) $u)->all() : [$kdo instanceof User ? $kdo->getKey() : $kdo];
        $this->data['cileni']['komu'] = 'vybrani';
        $this->data['cileni']['uzivatele'] = array_values(array_unique([...($this->data['cileni']['uzivatele'] ?? []), ...$ids]));

        return $this;
    }

    /** @param  string|list<string>  $role  admin, klient */
    public function roli(string|array $role): self
    {
        $this->data['cileni']['komu'] = 'vybrani';
        $this->data['cileni']['role'] = array_values(array_unique([...($this->data['cileni']['role'] ?? []), ...(array) $role]));

        return $this;
    }

    public function skupine(int ...$skupiny): self
    {
        $this->data['cileni']['komu'] = 'vybrani';
        $this->data['cileni']['skupiny'] = array_values(array_unique([...($this->data['cileni']['skupiny'] ?? []), ...$skupiny]));

        return $this;
    }

    public function vsem(): self
    {
        $this->data['cileni'] = ['komu' => 'vsichni'];

        return $this;
    }

    public function kanaly(KanalOznameni ...$kanaly): self
    {
        $this->data['kanaly'] = array_map(fn (KanalOznameni $k) => $k->value, $kanaly);

        return $this;
    }

    public function zavaznost(ZavaznostOznameni $zavaznost): self
    {
        $this->data['zavaznost'] = $zavaznost;

        return $this;
    }

    /** Pruh přes web od–do (přidá kanál pruh). Jen servisní. */
    public function pruh(?DateTimeInterface $od = null, ?DateTimeInterface $do = null): self
    {
        $this->data['kanaly'] = array_values(array_unique([...$this->data['kanaly'], KanalOznameni::Pruh->value]));
        $this->data['pruh_od'] = $od;
        $this->data['pruh_do'] = $do;

        return $this;
    }

    /** Odstávka od–do: pruh ukazuje odpočet do začátku. */
    public function odstavka(DateTimeInterface $od, ?DateTimeInterface $do = null): self
    {
        $this->data['udalost_od'] = $od;
        $this->data['udalost_do'] = $do;

        return $this;
    }

    public function naplanovat(DateTimeInterface $kdy): self
    {
        $this->data['naplanovano_na'] = $kdy;

        return $this;
    }

    /** Vlastní klíč – stejný klíč podruhé nic nepošle (vrátí původní oznámení). */
    public function klic(string $klic): self
    {
        $this->klic = mb_substr($klic, 0, 120);

        return $this;
    }

    /**
     * Uloží a odešle (nebo naplánuje). E-maily jdou do fronty, centrum je hned.
     * Bez transakce kolem odeslání – pomalé I/O do transakce nepatří; souběh
     * se stejným klíčem zastaví unikátní klíč (zdroj, zdroj_klic).
     *
     * @throws OznameniNejdeOdeslat
     */
    public function posli(): Oznameni
    {
        if ($this->klic !== null && $existujici = $this->existujici()) {
            return $existujici;
        }

        $oznameni = new Oznameni([...$this->data, 'zdroj_klic' => $this->klic]);

        // Chybné oznámení z kódu se ani neuloží (jinak by klíč zablokoval opravené).
        if ($chyby = app(Odeslani::class)->chyby($oznameni, zAdministrace: false)) {
            throw new OznameniNejdeOdeslat($chyby);
        }

        try {
            $oznameni->save();
        } catch (UniqueConstraintViolationException $e) {
            return $this->existujici() ?? throw $e;
        }

        app(Odeslani::class)->odeslat($oznameni, null, zAdministrace: false);

        return $oznameni->refresh();
    }

    private function existujici(): ?Oznameni
    {
        return Oznameni::query()->where('zdroj', 'aplikace')->where('zdroj_klic', $this->klic)->first();
    }
}
