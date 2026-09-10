<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\PurposeHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CT-A5a (§0.5) — the READ-ONLY operator view of "can the engine post for this company at all?".
 *
 * `accounting:coa-linkage` answers the same question but is a repair tool: it mints leaves, writes
 * mappings, sweeps rows and records before-images. An owner who just wants to know where each
 * tenant stands should not have to run a writer, or read a `--dry-run` change list, to find out.
 *
 * Three buckets, deliberately kept apart because they have three different causes:
 *   - **unmapped** — no `system_accounts` row, or one whose account has been deleted. Fix: run the
 *     linkage repair.
 *   - **non-leaf** — a mapping that names an account which HAS CHILDREN. This is the TE-1
 *     onboarding defect exactly (a purpose mapped before supplier activation gave its target a
 *     child) and the CT-A4 G1 defect exactly (`2110 Creditors` stopped being a leaf when six
 *     payment instruments were minted under it). Fix: re-map, or move the children.
 *   - **dangling** — a `system_accounts` row pointing at an account id that does not exist. CT-A3
 *     R3 proved these are not inert: minting an account at that id makes the mapping RESOLVE, at
 *     whatever the new account happens to be, across tenants. Fix: `--sweep-dangling`.
 *
 * Writes nothing, ever. Exit code is 0 unless a company has a BLOCKING gap, so it is usable as a
 * pre-deploy check as well as a report.
 */
class AccountingPurposeHealth extends Command
{
    protected $signature = 'accounting:purpose-health
                            {--company=all : Company id, or "all" for every company that has accounts}
                            {--json : Emit the raw per-company result as JSON instead of a table}';

    protected $description = 'CT-A5a — read-only: list unmapped, non-leaf and dangling engine purposes per company. Writes nothing.';

    public function handle(PurposeHealthService $health): int
    {
        $companyIds = $this->resolveCompanyIds();

        if ($companyIds === []) {
            $this->warn('No company has any accounts; nothing to report.');

            return self::SUCCESS;
        }

        $results = [];
        $anyBlocking = false;

        foreach ($companyIds as $companyId) {
            $result = $health->inspect($companyId);
            $results[] = $result;
            $anyBlocking = $anyBlocking || $result['blocking'] > 0;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $anyBlocking ? self::FAILURE : self::SUCCESS;
        }

        $this->table(
            ['company', 'resolve', 'unmapped', 'of which deliberate', 'non-leaf', 'dangling', 'blocking'],
            array_map(fn (array $r) => [
                $r['company_id'],
                $r['resolved'].' / '.$r['total'],
                count($r['unresolved']),
                count(array_filter($r['unresolved'], fn (array $u) => $u['deliberate'])),
                count($r['non_leaf']),
                count($r['dangling']),
                $r['blocking'],
            ], $results)
        );

        foreach ($results as $r) {
            if ($r['blocking'] === 0 && $r['dangling'] === []) {
                continue;
            }

            $this->newLine();
            $this->line(sprintf('company %d', $r['company_id']));

            foreach ($r['non_leaf'] as $n) {
                $this->warn(sprintf(
                    '  NON-LEAF  %s%s -> #%d %s (%d children)',
                    $n['purpose'],
                    $n['service_type'] !== null ? '/'.$n['service_type'] : '',
                    $n['account_id'],
                    $n['account_name'],
                    $n['child_count']
                ));
            }

            foreach ($r['unresolved'] as $u) {
                if ($u['deliberate']) {
                    continue;
                }

                $this->warn(sprintf(
                    '  UNMAPPED  %s%s (%s)',
                    $u['purpose'],
                    $u['service_type'] !== null ? '/'.$u['service_type'] : '',
                    $u['exception']
                ));
            }

            foreach ($r['dangling'] as $d) {
                $this->warn(sprintf(
                    '  DANGLING  system_accounts #%d maps %s to accounts.id=%d, which does not exist',
                    $d['system_account_id'],
                    $d['purpose'],
                    $d['account_id']
                ));
            }
        }

        $this->newLine();
        $this->line('  Repair with: php artisan accounting:coa-linkage --company=<id> --dry-run   (then --apply, and --sweep-dangling when the DANGLING column is non-zero)');

        return $anyBlocking ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, int> */
    private function resolveCompanyIds(): array
    {
        $option = (string) $this->option('company');

        if ($option !== 'all' && $option !== '') {
            return [(int) $option];
        }

        return DB::table('accounts')
            ->whereNotNull('company_id')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
