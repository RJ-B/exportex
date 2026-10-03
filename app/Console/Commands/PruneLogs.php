<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ErrorLog;
use App\Models\MailLog;
use App\Models\Nastaveni;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Úklid všech tří logů – audit, maily, chyby.
 *
 * Retence je NASTAVENÍ (`logy.retence_dni`, výchozí 30; mění se v Logy →
 * Aktivita → Retence logů), ne konstanta v kódu: mazání je nevratné a jak
 * dlouho se dozadu dohledává, ví provoz. Audit se drží rok – ptá se na něj
 * s odstupem měsíců („kdo tohle změnil?“).
 */
class PruneLogs extends Command
{
    protected $signature = 'logs:prune {--days= : Přepíše retenci z nastavení} {--dry-run}';

    protected $description = 'Smaže staré záznamy z logů (audit, e-maily, chyby) dle retence';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: Nastaveni::hodnota('logy.retence_dni', 30));

        if ($days < 1) {
            $this->error('Retence musí být aspoň 1 den. Nic nemazáno.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');

        // Audit se drží déle: odpovídá na „kdo to udělal", a to se ptá
        // s odstupem měsíců, ne dnů.
        $auditDays = 365;
        $auditCutoff = now()->subDays(max($auditDays, 1));

        $targets = [
            'audit' => [AuditLog::where('created_at', '<', $auditCutoff), $auditDays],
            'maily' => [MailLog::where('created_at', '<', $cutoff), $days],
            'chyby' => [ErrorLog::where('last_seen_at', '<', $cutoff), $days],
        ];

        $summary = [];

        foreach ($targets as $label => [$query, $keepDays]) {
            $count = $query->count();
            $summary[$label] = $count;

            if ($dry) {
                $this->line("[DRY RUN] {$label}: ke smazání {$count} (starší než {$keepDays} dní)");

                continue;
            }

            if ($count > 0) {
                $query->delete();
            }

            $this->line("{$label}: smazáno {$count} (starší než {$keepDays} dní)");
        }

        if (! $dry && array_sum($summary) > 0) {
            // Do laravel.log, ne do audit_logu — ten se právě maže a zápis „smazal
            // jsem audit" by v něm stejně za měsíc nebyl.
            Log::info('logs:prune — úklid logů', $summary + ['retention_days' => $days]);
        }

        return self::SUCCESS;
    }
}
