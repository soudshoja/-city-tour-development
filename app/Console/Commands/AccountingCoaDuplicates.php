<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\CoaLinkageChange;
use App\Services\Accounting\AccountCodeGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CT-A4b — read-only report and safe renumber for the duplicate-code groups CT-A4 §1.6 measured
 * on the real chart (28 groups, 290 accounts on company 1 alone) and CT-A5a §5.5 found being
 * MINTED, live, by `SupplierActivationService::activate()`.
 *
 * That minting bug is fixed at the source in this same lane (SupplierActivationService, CoaSeeder,
 * TaskController's currency/issued-by child accounts all now go through the single
 * {@see AccountCodeGenerator} allocator, whose `codeExists()` refuses any code already taken
 * anywhere in the company's chart). This command is the OTHER half: the 290 accounts already
 * sharing codes on the real, already-populated chart before this fix landed.
 *
 * ── Why a code is never renumbered out from under money ─────────────────────────────────────────
 * An account code is a reporting identity — a printed statement, an export, a saved report filter
 * all key on it. Renumbering an account that carries journal activity would silently break every
 * one of those for a document that has already been posted. So this command draws exactly one
 * line: an account with ZERO journal rows can be renumbered (nothing downstream keys on its code
 * yet); an account with ANY journal activity is reported, never touched, and left for the owner.
 * A duplicate-code group where every member carries activity is reported the same way — there is
 * no safe automatic choice of which one "keeps" the code.
 *
 * ── Rollback reuses the existing mechanism, not a second one ─────────────────────────────────────
 * Every renumber this command applies is recorded as a `coa_linkage_changes` before-image —
 * `subject_table='accounts'`, `column_name='code'` — under a `Str::ulid()` run id, exactly the
 * shape `accounting:coa-linkage --apply` already writes. `code` was added to
 * {@see CoaLinkageChange::REVERSIBLE_COLUMNS} for this. Undo with:
 *
 *     php artisan accounting:coa-linkage --rollback={runId}
 *
 * — the run id this command prints. No separate `--rollback` flag is implemented here; there is
 * only one rollback mechanism in this codebase for `coa_linkage_changes` rows and this command
 * writes into it rather than growing a second one.
 */
class AccountingCoaDuplicates extends Command
{
    protected $signature = 'accounting:coa-duplicates
                            {--company= : Company id to process, or "all" for every company that has accounts}
                            {--report : List every duplicate-code group and each account\'s journal activity (the default when neither --report nor --renumber is given)}
                            {--renumber : Renumber the zero-activity accounts in each duplicate-code group to a free code; accounts carrying journal activity are only ever reported}
                            {--dry-run : With --renumber, show what would change without writing anything (the default whenever --renumber is passed without this flag being explicitly false is NOT assumed — --renumber alone WRITES; pass --dry-run to preview)}';

    protected $description = 'CT-A4b — report duplicate account codes on a real chart, and safely renumber only the zero-activity members of each group (never one carrying journal activity), with a rollback-able before-image.';

    private string $runId = '';

    /** @var array<int, array{company_id: int, subject_id: int, before: string, after: string}> */
    private array $changes = [];

    public function handle(AccountCodeGenerator $codes): int
    {
        $report = (bool) $this->option('report');
        $renumber = (bool) $this->option('renumber');
        $dryRun = (bool) $this->option('dry-run');

        if (! $report && ! $renumber) {
            $report = true;
        }

        $companyOption = $this->option('company');
        $companyIds = $this->resolveCompanyIds($companyOption);

        if ($companyIds === []) {
            $this->warn('No companies with accounts found.');

            return self::SUCCESS;
        }

        $this->runId = (string) Str::ulid();

        $anyUnresolvedGroup = false;

        foreach ($companyIds as $companyId) {
            $groups = $this->duplicateGroups($companyId);

            if ($groups === []) {
                continue;
            }

            $this->line("Company {$companyId}: ".count($groups).' duplicate-code group(s).');

            foreach ($groups as $code => $accounts) {
                $this->line("  code '{$code}':");

                $withActivity = [];
                $withoutActivity = [];

                foreach ($accounts as $account) {
                    $rows = $this->journalRowCount((int) $account->id);
                    $tag = $rows > 0 ? "ACTIVITY ({$rows} journal row(s))" : 'zero activity';
                    $this->line("    #{$account->id} {$account->name} (parent #{$account->parent_id}) — {$tag}");

                    if ($rows > 0) {
                        $withActivity[] = $account;
                    } else {
                        $withoutActivity[] = $account;
                    }
                }

                if (! $renumber) {
                    continue;
                }

                if (count($withActivity) > 1) {
                    // Two or more accounts sharing this code both carry activity — there is no
                    // safe automatic choice of which keeps the code. Report, touch nothing.
                    $this->warn(
                        "    REPORTED, NOT RENUMBERED — {$this->pluralAccounts(count($withActivity))} sharing code "
                            ."'{$code}' carry journal activity; an owner has to choose which keeps the code."
                    );
                    $anyUnresolvedGroup = true;

                    continue;
                }

                if (count($withActivity) === 1 && $withoutActivity === []) {
                    // Exactly one account holds this code once the (impossible, by definition of
                    // "duplicate") zero-activity siblings are gone — nothing left to renumber.
                    continue;
                }

                // The account(s) that keep the code: the one with activity if there is exactly
                // one, else the lowest id among the zero-activity accounts (deterministic, not
                // "whichever the query happened to return first").
                if (count($withActivity) === 1) {
                    $keeper = $withActivity[0];
                    $toRenumber = $withoutActivity;
                } else {
                    usort($withoutActivity, static fn ($a, $b) => $a->id <=> $b->id);
                    $keeper = array_shift($withoutActivity);
                    $toRenumber = $withoutActivity;
                }

                foreach ($toRenumber as $account) {
                    $parent = $account->parent_id !== null
                        ? Account::withoutGlobalScopes()->find($account->parent_id)
                        : null;

                    $newCode = $codes->generate($parent, $companyId);

                    if ($newCode === null) {
                        $newCode = (string) $account->id;
                    }

                    $verb = $dryRun ? 'would renumber' : 'renumbered';
                    $this->info(
                        "    {$verb} #{$account->id} {$account->name}: '{$code}' -> '{$newCode}' "
                            ."(keeping '{$code}' on #{$keeper->id} {$keeper->name})"
                    );

                    if ($dryRun) {
                        continue;
                    }

                    $this->changes[] = [
                        'company_id' => $companyId,
                        'subject_id' => (int) $account->id,
                        'before' => (string) $account->code,
                        'after' => $newCode,
                    ];

                    DB::table('accounts')->where('id', $account->id)->update([
                        'code' => $newCode,
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if ($renumber && ! $dryRun) {
            $this->flushChanges();
        }

        return $anyUnresolvedGroup ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int>
     */
    private function resolveCompanyIds(?string $companyOption): array
    {
        if ($companyOption !== null && $companyOption !== 'all') {
            return [(int) $companyOption];
        }

        return DB::table('accounts')
            ->select('company_id')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<string, array<int, object>> code => accounts sharing it
     */
    private function duplicateGroups(int $companyId): array
    {
        $codes = DB::table('accounts')
            ->select('code')
            ->selectRaw('COUNT(*) as n')
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->whereNull('deleted_at')
            ->groupBy('code')
            ->having('n', '>', 1)
            ->pluck('code');

        if ($codes->isEmpty()) {
            return [];
        }

        $groups = [];

        foreach ($codes as $code) {
            $groups[(string) $code] = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();
        }

        return $groups;
    }

    private function journalRowCount(int $accountId): int
    {
        return DB::table('journal_entries')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->count();
    }

    private function pluralAccounts(int $n): string
    {
        return $n.' accounts';
    }

    private function flushChanges(): void
    {
        if ($this->changes === []) {
            return;
        }

        foreach (array_chunk($this->changes, 200) as $chunk) {
            DB::table('coa_linkage_changes')->insert(array_map(function (array $c): array {
                return [
                    'run_id' => $this->runId,
                    'company_id' => $c['company_id'],
                    'subject_table' => 'accounts',
                    'subject_id' => $c['subject_id'],
                    'column_name' => 'code',
                    'before_value' => $c['before'],
                    'after_value' => $c['after'],
                    'rolled_back_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $chunk));
        }

        $this->newLine();
        $this->info(sprintf(
            'RUN ID %s — %d account code(s) renumbered.',
            $this->runId,
            count($this->changes)
        ));
        $this->line("  Undo:  php artisan accounting:coa-linkage --rollback={$this->runId}");
    }
}
