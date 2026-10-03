<?php

namespace App\Console\Commands;

use App\Models\MailLog;
use Illuminate\Console\Command;

/**
 * Uzavře maily, které zůstaly viset ve stavu „odesílá se".
 *
 * Stav `sending` se zapíše před předáním zprávy transportu a přepíše se hned po něm.
 * Když ale proces mezitím umře (fatal, timeout PHP-FPM, zabitý worker, spadlé spojení
 * do DB), `finish()` se nikdy nezavolá a záznam v tom stavu zůstane navždy: v logu
 * vypadá jako právě probíhající odeslání, takže se ani nedá znovu odeslat a nikdo
 * se nedozví, že mail nikam nedošel.
 *
 * Tenhle příkaz je po hodině prohlásí za selhané, aby byly vidět jako problém.
 * Znovu odeslat je zpravidla nepůjde — tělo se ukládá až při zachycené výjimce,
 * a zabitý proces žádnou nevyhodí. To je záměr: účelem je, aby se o tom ČLOVĚK
 * dozvěděl, ne aby to systém potichu opravil.
 */
class ReconcileMailLogs extends Command
{
    protected $signature = 'mail:reconcile {--minutes=60 : Po kolika minutách považovat „odesílá se" za selhané} {--dry-run}';

    protected $description = 'Označí zaseknuté e-maily ve stavu „odesílá se" jako selhané';

    public function handle(): int
    {
        $minutes = max((int) $this->option('minutes'), 5);
        $cutoff = now()->subMinutes($minutes);

        $query = MailLog::where('status', MailLog::STATUS_SENDING)
            ->where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('Žádné zaseknuté e-maily.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("[DRY RUN] {$count} zaseknutých e-mailů (déle než {$minutes} min ve stavu odesílá se)");

            return self::SUCCESS;
        }

        $query->update([
            'status' => MailLog::STATUS_FAILED,
            'failed_at' => now(),
            'error' => "Odesílání se nikdy nedokončilo — záznam zůstal přes {$minutes} min "
                .'ve stavu „odesílá se". Nejspíš spadl proces uprostřed odesílání. '
                .'Jestli e-mail došel, se z aplikace poznat nedá — ověř u příjemce.',
        ]);

        $this->warn("Označeno {$count} zaseknutých e-mailů jako selhané.");

        return self::SUCCESS;
    }
}
