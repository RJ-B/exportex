<?php

namespace App\Console\Commands;

use App\Models\MailLog;
use App\Services\MailRetrier;
use Illuminate\Console\Command;

/**
 * Automaticky zkusí znovu odeslat selhané e-maily, u kterých máme uložené tělo.
 *
 * Jeden výpadek SMTP jinak znamená tiše ztracený mail – tlačítko v administraci
 * nikdo nezmáčkne, protože o selhání neví.
 *
 * Odstup mezi pokusy roste mocninou dvou od 5 minut (5, 10, 20, 40, 80 min) —
 * první opakování přijde rychle, ať se o problému ví hned a nečeká se hodinu,
 * ale u trvale nefunkční adresy se nezacyklíme. Poslední pokus vyjde ~2,5 h po
 * prvním selhání. Po vyčerpání pokusů zůstane záznam selhaný i s tlačítkem na
 * ruční odeslání.
 *
 * Pozor: odstup má smysl jen když příkaz běží aspoň tak často — v plánovači je
 * po 5 minutách. Kdyby se vrátil na hodinovou frekvenci, kratší odstupy by se
 * zahodily a první opakování by stejně přišlo až za hodinu.
 *
 * Ruční odeslání z adminu tuhle frontu neobchází ani nedubluje: po úspěchu je
 * stav `sent`, takže sem už nespadne. Po neúspěchu se jen zvýší `attempts`,
 * takže se ruční pokus započítá do limitu a posune odstup.
 */
class RetryFailedMails extends Command
{
    protected $signature = 'mail:retry-failed
        {--max-attempts=6 : Kolik pokusů celkem (včetně původního odeslání)}
        {--base-minutes=5 : Odstup před 1. opakováním; dál se zdvojnásobuje}
        {--max-age-days=7 : Starší selhané maily už neposílat}
        {--limit=25 : Kolik jich zkusit v jednom běhu (ať nezahltíme SMTP)}
        {--dry-run : Jen vypsat, co by se odeslalo}';

    protected $description = 'Znovu odešle selhané e-maily s uloženým tělem (exponenciální odstup)';

    public function handle(MailRetrier $retrier): int
    {
        $maxAttempts = max((int) $this->option('max-attempts'), 1);
        $maxAgeDays = max((int) $this->option('max-age-days'), 1);
        $limit = max((int) $this->option('limit'), 1);
        $baseMinutes = max((int) $this->option('base-minutes'), 1);

        $candidates = MailLog::query()
            ->where('status', MailLog::STATUS_FAILED)
            ->whereNotNull('raw_mime')       // bez těla není co poslat
            ->where('attempts', '<', $maxAttempts)
            ->where('created_at', '>=', now()->subDays($maxAgeDays))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            // Odstup se počítá od POSLEDNÍHO neúspěchu, ne od vzniku — jinak by se
            // po restartu fronty odpálily všechny pokusy hned za sebou.
            ->filter(fn (MailLog $log) => $this->isDue($log, $baseMinutes));

        if ($candidates->isEmpty()) {
            $this->info('Žádné e-maily k opakovanému odeslání.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line('[DRY RUN] k odeslání: '.$candidates->count());
            foreach ($candidates as $log) {
                $this->line("  #{$log->id} → {$log->to_email} | pokus ".($log->attempts + 1)."/{$maxAttempts} | {$log->subject}");
            }

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($candidates as $log) {
            $result = $retrier->retry($log);

            if ($result['ok']) {
                $ok++;
                $this->info("#{$log->id} odesláno na {$log->to_email}");
            } else {
                $failed++;
                $this->warn("#{$log->id} znovu selhalo: ".$result['message']);
            }
        }

        $this->newLine();
        $this->line("Odesláno: {$ok} · znovu selhalo: {$failed}");

        // Nenulový kód jen když se NIC nepovedlo — ať cron nekřičí kvůli jednomu
        // mailu, který se prostě poslat nedá.
        return $ok === 0 && $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Uplynul od posledního neúspěchu dost dlouhý odstup? (5, 10, 20, 40, 80 min …) */
    private function isDue(MailLog $log, int $baseMinutes): bool
    {
        $last = $log->failed_at ?? $log->updated_at ?? $log->created_at;

        if ($last === null) {
            return true;
        }

        $waitMinutes = $baseMinutes * (2 ** max($log->attempts - 1, 0));

        return $last->copy()->addMinutes($waitMinutes)->isPast();
    }
}
