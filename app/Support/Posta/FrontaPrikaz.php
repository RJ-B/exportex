<?php

namespace App\Support\Posta;

use App\Models\MailLog;
use Illuminate\Console\Command;
use Throwable;

/**
 * posta:fronta (plánovač každou minutu):
 *  1. odchozí fronta – zprávy, které Pošta nepřijala, zkusí předat znovu se
 *     stejným Idempotency-Key (1 → 2 → 5 → 10 → 30 → 60 min, pak každou hodinu,
 *     po 72 h vzdá a záznam v logu označí jako selhaný),
 *  2. dotaz na stav – zprávy „ve frontě Pošty“, u kterých nepřišel webhook
 *     (aplikace bez veřejné adresy, výpadek), se zeptá na výsledek.
 */
class FrontaPrikaz extends Command
{
    protected $signature = 'posta:fronta {--limit=100}';

    protected $description = 'Předá Poště zprávy z odchozí fronty a zjistí výsledek zpráv bez webhooku';

    public function handle(Klient $klient): int
    {
        if (! Propojeni::propojeno()) {
            $this->line('Aplikace není propojená s Poštou.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $predano = 0;

        foreach (Odchozi::query()->where('dalsi_pokus_at', '<=', now())->oldest('id')->limit($limit)->get() as $odchozi) {
            $predano += $this->predej($klient, $odchozi) ? 1 : 0;
        }

        $zjisteno = 0;
        $kontrolaPo = now()->subMinutes((int) config('posta.dotaz_na_stav_po_minutach', 15));

        $cekajici = MailLog::query()
            ->where('status', MailLog::STATUS_QUEUED)
            ->whereNotNull('posta_id')
            ->where('created_at', '<=', $kontrolaPo)
            ->where(fn ($q) => $q->whereNull('posta_kontrola_at')->orWhere('posta_kontrola_at', '<=', $kontrolaPo))
            ->oldest('id')->limit($limit)->get();

        foreach ($cekajici as $log) {
            try {
                Webhook::aktualizuj($klient->stav($log->posta_id));
                $zjisteno++;
            } catch (PostaOdmitla $e) {
                $log->update(['posta_kontrola_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            } catch (Throwable) {
                break;   // Pošta nedostupná – zkusí se příště
            }
        }

        $this->line("Předáno z fronty: {$predano}, zjištěn stav: {$zjisteno}.");

        return self::SUCCESS;
    }

    private function predej(Klient $klient, Odchozi $odchozi): bool
    {
        try {
            $vysledek = $klient->posli($odchozi->zprava, $odchozi->idempotency_klic);
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
