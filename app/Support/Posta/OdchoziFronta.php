<?php

namespace App\Support\Posta;

use App\Models\MailLog;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Co po odeslání ještě zbývá: e-mail jde do Pošty hned při odeslání
 * (PostaTransport, jedno volání API). Sem se dostane jen to, co Pošta tehdy
 * nepřijala, a zprávy, ke kterým nepřišel výsledek webhookem:
 *
 *  1. odchozí fronta – zprávy, které Pošta nepřijala, zkusí předat znovu se
 *     stejným Idempotency-Key (1 → 2 → 5 → 10 → 30 → 60 min, pak každou hodinu,
 *     po 72 h vzdá a záznam v logu označí jako selhaný); Pošta tak zprávu nikdy
 *     nepošle dvakrát,
 *  2. dotaz na stav – zprávy „ve frontě Pošty“, u kterých nepřišel webhook
 *     (aplikace bez veřejné adresy, výpadek), se zeptá na výsledek.
 *
 * Plánovač to spouští uvnitř schedule:run (closure, žádný nový proces PHP)
 * a jen když `maPraci()` – v běžném provozu je fronta prázdná a nestojí to nic.
 */
class OdchoziFronta
{
    public function __construct(private Klient $klient) {}

    /** Je co předat nebo dotázat? Levný dotaz pro ->when() v plánovači. */
    public static function maPraci(): bool
    {
        if (! Propojeni::propojeno()) {
            return false;
        }

        return Odchozi::query()->where('dalsi_pokus_at', '<=', now())->exists()
            || self::cekajiciNaStav()->exists();
    }

    /** @return array{predano: int, zjisteno: int} */
    public function zpracuj(int $limit = 100): array
    {
        $vysledek = ['predano' => 0, 'zjisteno' => 0];

        if (! Propojeni::propojeno()) {
            return $vysledek;
        }

        $limit = max(1, $limit);

        foreach (Odchozi::query()->where('dalsi_pokus_at', '<=', now())->oldest('id')->limit($limit)->get() as $odchozi) {
            $vysledek['predano'] += $this->predej($odchozi) ? 1 : 0;
        }

        foreach (self::cekajiciNaStav()->oldest('id')->limit($limit)->get() as $log) {
            try {
                Webhook::aktualizuj($this->klient->stav($log->posta_id));
                $vysledek['zjisteno']++;
            } catch (PostaOdmitla $e) {
                $log->update(['posta_kontrola_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            } catch (Throwable) {
                break;   // Pošta nedostupná – zkusí se příště
            }
        }

        return $vysledek;
    }

    /** Zprávy předané Poště, u kterých výsledek nepřišel do posta.dotaz_na_stav_po_minutach. */
    private static function cekajiciNaStav(): Builder
    {
        $kontrolaPo = now()->subMinutes((int) config('posta.dotaz_na_stav_po_minutach', 15));

        return MailLog::query()
            ->where('status', MailLog::STATUS_QUEUED)
            ->whereNotNull('posta_id')
            ->where('created_at', '<=', $kontrolaPo)
            ->where(fn ($q) => $q->whereNull('posta_kontrola_at')->orWhere('posta_kontrola_at', '<=', $kontrolaPo));
    }

    private function predej(Odchozi $odchozi): bool
    {
        try {
            $vysledek = $this->klient->posli($odchozi->zprava, $odchozi->idempotency_klic);
        } catch (PostaOdmitla $e) {
            $odchozi->mailLog?->update(['status' => MailLog::STATUS_FAILED, 'failed_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            $odchozi->delete();

            return false;
        } catch (Throwable $e) {
            $vzdat = $odchozi->created_at->lt(now()->subHours((int) config('posta.fronta.vzdat_po_hodinach', 72)));

            if ($vzdat) {
                $odchozi->mailLog?->update(['status' => MailLog::STATUS_FAILED, 'failed_at' => now(),
                    'error' => 'Pošta zprávu nepřijala ani po '.config('posta.fronta.vzdat_po_hodinach', 72).' hodinách: '.mb_substr($e->getMessage(), 0, 500)]);
                $odchozi->delete();

                return false;
            }

            $rozestupy = (array) config('posta.fronta.opakovani_minut', [1, 2, 5, 10, 30, 60]);
            $odchozi->update([
                'pokusu' => $odchozi->pokusu + 1,
                'dalsi_pokus_at' => now()->addMinutes((int) ($rozestupy[$odchozi->pokusu] ?? end($rozestupy))),
                'chyba' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            return false;
        }

        $odchozi->mailLog?->update(['posta_id' => $vysledek['id'], 'error' => null]);
        $odchozi->delete();

        return true;
    }
}
