<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\MastersAuditor;
use Illuminate\Console\Command;

/**
 * legacy-ledger-pilot LP0.4/LP1.4 masters audit: currency poison rows,
 * AccTransType dirt (evidence only — never used for classification),
 * cost-centre single-row confirmation, unposted-header count, OJV
 * inventory (one per year), and the 2025 posted-document census. Writes
 * one row per check to legacy_pilot.legacy_masters_audit and prints a
 * summary. Exits non-zero if the currency-poison check fails (an
 * in-window line referencing a poison currency halts the phase).
 */
class LegacyAuditMastersCommand extends Command
{
    protected $signature = 'legacy:audit-masters';

    protected $description = 'Audit staged legacy masters: currency poison, AccTransType dirt, cost centre, unposted headers, OJV inventory (LP0.4/LP1.4).';

    public function handle(MastersAuditor $auditor): int
    {
        $results = $auditor->run();

        $failed = false;

        foreach ($results as $key => $result) {
            $status = strtoupper($result['status']);
            $this->line("[{$status}] {$key}");

            foreach ($result['detail'] ?? [] as $field => $value) {
                if (is_scalar($value)) {
                    $this->line("    {$field}: {$value}");
                } else {
                    $this->line("    {$field}: ".json_encode($value));
                }
            }

            if ($result['status'] === 'fail') {
                $failed = true;
            }
        }

        if ($failed) {
            $this->error('legacy:audit-masters: one or more checks FAILED — see legacy_pilot.legacy_masters_audit.');

            return self::FAILURE;
        }

        $this->info('legacy:audit-masters complete.');

        return self::SUCCESS;
    }
}
