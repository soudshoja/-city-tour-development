<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\Charge;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;
use Throwable;

/**
 * CT-A3 R2-5 — VERIFY-CT-A3-STACK-R1 §3.2 **D14**, plus the MERGE-TIME defect of §2b.
 *
 * ── D14, both halves ────────────────────────────────────────────────────────────────────────────
 * *"`accounting:coa-linkage --apply` rewrites `report_type` on accounts that already have one,
 * changing historical reported profit, with no way back."* `backfillReportType()` skips a row only
 * when its `report_type` already EQUALS what the root implies; otherwise it overwrites, in both
 * directions. `ReportController` selects the P&L by that column, so the 87 accounts CT-A4 measured
 * change reported profit for every historical period on the next render. It was dry-runnable, and
 * **not reversible from the command's own output**: a count, no ids, no before-values, and nothing
 * written to `coa_linkage_findings` for `SET_REPORT_TYPE`.
 *
 * *"Also: `--apply` exits 0 even with BLOCKING findings — failure is set only on a thrown
 * exception. Do not gate a deploy on this command's exit status."* A command whose exit code cannot
 * be gated on is one every runbook will gate on regardless.
 *
 * Four things are pinned here, each of which failed before R2-5:
 *   1. every changed column is recorded with its BEFORE value, per run;
 *   2. `--rollback=<run_id>` puts every one of them back, exactly;
 *   3. the exit code is non-zero while a BLOCKING finding remains;
 *   4. a dry run PRINTS the P&L composition delta — which accounts move into the P&L, and what
 *      balance each brings with it.
 *
 * ── §2b, the merge-time defect ──────────────────────────────────────────────────────────────────
 * *"the two branches ship mutually exclusive contracts about one purpose code."* Wave 2 §4.7
 * registered `REFUND_PAYOUT_CASH_BANK` as mappable and deliberately did not auto-map it in the
 * SEEDER; CT-A4's `CoaLinkageCommandTest` asserts no BLOCKING purpose survives `--apply`. On the
 * merge, one fails.
 *
 * The report's own suggested fix was to add an exclusion to the test — which would have left the
 * OPERATIONAL half open: *"every `refund_out` disposition will throw `UnmappedPurposeException`
 * until an operator picks the account… a day-one operator task, not something that resolves
 * itself."* This lane closes the operational half instead: the linkage command MAPS the purpose
 * onto the company's own default refund-payout instrument, by the same R-CT3 resolution the receipt
 * instrument leg uses, and FLAGS the mapping when it had to infer rather than read a configured
 * default. Wave 2's reasoning is untouched — a SEEDER, which knows nothing about the company, still
 * must not guess; an explicit, dry-runnable, now-reversible repair reading that company's own
 * configured payment methods is a different thing.
 */
class R25CoaLinkageReversibilityTest extends AccountingTestCase
{
    private function freshCompany(): Company
    {
        $company = Company::factory()->create();
        (new AccountTypeSeeder)->run();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function account(int $companyId, string $name): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('name', $name)->firstOrFail();
    }

    private function runLinkage(array $options = []): int
    {
        return Artisan::call('accounting:coa-linkage', $options);
    }

    private function lastRunId(int $companyId): string
    {
        return (string) DB::table('coa_linkage_changes')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('run_id');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // D14 — reversibility
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * THE DEFECT. An account whose `report_type` contradicts its root is rewritten by `--apply`.
     * Before R2-5 the previous value was gone: a count in the console and nothing on disk.
     */
    public function test_apply_records_the_before_value_of_every_column_it_rewrites(): void
    {
        $company = $this->freshCompany();

        // An EXPENSE account filed as a balance-sheet line — the exact shape CT-A4 measured 87 of
        // on the dev chart, and the one that changes reported profit when it is corrected.
        $expense = $this->account($company->id, 'Bank Charges');
        DB::table('accounts')->where('id', $expense->id)->update([
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => 1,
            'account_type_id' => null,
        ]);

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $runId = $this->lastRunId($company->id);
        $this->assertNotSame('', $runId, 'Every --apply run must record a run id.');

        $recorded = DB::table('coa_linkage_changes')
            ->where('run_id', $runId)
            ->where('subject_id', $expense->id)
            ->pluck('before_value', 'column_name')
            ->all();

        $this->assertArrayHasKey('report_type', $recorded);
        $this->assertSame(
            Account::REPORT_TYPES['BALANCE_SHEET'],
            $recorded['report_type'],
            'The BEFORE value must be recorded — without it the P&L change cannot be undone.'
        );

        $this->assertArrayHasKey('is_group', $recorded);
        $this->assertSame('1', $recorded['is_group']);

        $this->assertArrayHasKey('account_type_id', $recorded);
        $this->assertNull($recorded['account_type_id'], 'A NULL before-value must stay NULL, not become 0.');

        // And the command actually changed them.
        $after = DB::table('accounts')->where('id', $expense->id)->first(['report_type', 'is_group', 'account_type_id']);
        $this->assertSame(Account::REPORT_TYPES['PROFIT_LOSS'], (string) $after->report_type);
        $this->assertSame(0, (int) $after->is_group);
        $this->assertNotNull($after->account_type_id);
    }

    /** A dry run records nothing — there is nothing to undo. */
    public function test_a_dry_run_records_no_before_values(): void
    {
        $company = $this->freshCompany();

        $expense = $this->account($company->id, 'Bank Charges');
        DB::table('accounts')->where('id', $expense->id)->update(['report_type' => Account::REPORT_TYPES['BALANCE_SHEET']]);

        $this->runLinkage(['--company' => (string) $company->id, '--dry-run' => true]);

        $this->assertSame(0, DB::table('coa_linkage_changes')->where('company_id', $company->id)->count());
        $this->assertSame(
            Account::REPORT_TYPES['BALANCE_SHEET'],
            (string) DB::table('accounts')->where('id', $expense->id)->value('report_type')
        );
    }

    /**
     * THE WAY BACK. `--rollback=<run_id>` restores every column the run changed to what it held
     * before — the whole point of the before-image.
     */
    public function test_rollback_restores_every_column_the_run_changed(): void
    {
        $company = $this->freshCompany();

        $expense = $this->account($company->id, 'Bank Charges');
        DB::table('accounts')->where('id', $expense->id)->update([
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => 1,
            'account_type_id' => null,
        ]);

        $before = $this->classificationFingerprint($company->id);

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);
        $runId = $this->lastRunId($company->id);

        $this->assertNotSame(
            $before,
            $this->classificationFingerprint($company->id, array_keys($before)),
            'precondition: the apply actually changed something'
        );

        $this->assertSame(0, $this->runLinkage(['--rollback' => $runId]));

        // Compared over the accounts that existed BEFORE the run: `--apply` also MINTS leaves
        // (`accounting:ensure-system-leaves`, the control-pool repair), and a rollback deliberately
        // does not delete an account -- this command never deletes one under any flag. What it
        // undoes is the CLASSIFICATION COLUMNS it rewrote, and that is what is asserted.
        $this->assertSame(
            $before,
            $this->classificationFingerprint($company->id, array_keys($before)),
            'A rollback must restore every column to its exact pre-run value.'
        );

        // Rolling the same run back twice must be a no-op, not a re-application of stale values.
        $this->assertSame(0, $this->runLinkage(['--rollback' => $runId]));
        $this->assertSame($before, $this->classificationFingerprint($company->id, array_keys($before)));
    }

    /**
     * The three classification columns per account, keyed by id.
     *
     * @param  array<int, string>|null  $onlyIds  restrict to these account ids
     * @return array<string, string>
     */
    private function classificationFingerprint(int $companyId, ?array $onlyIds = null): array
    {
        return DB::table('accounts')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->when($onlyIds !== null, fn ($q) => $q->whereIn('id', $onlyIds))
            ->orderBy('id')
            ->get(['id', 'report_type', 'is_group', 'account_type_id'])
            ->mapWithKeys(fn ($r) => [(string) $r->id => implode('|', [(string) $r->report_type, (string) $r->is_group, (string) $r->account_type_id])])
            ->all();
    }

    /**
     * A rollback must never overwrite a value something ELSE changed after the run. Those rows are
     * named and left alone — a repair command that silently reverts a human's edit is a second
     * source of unexplained column changes, which is what D14 was about in the first place.
     */
    public function test_rollback_refuses_a_column_that_has_moved_since_the_run(): void
    {
        $company = $this->freshCompany();

        // A leaf wrongly flagged as a group: the run sets is_group to 0 and records 1 as the
        // before-value. is_group rather than report_type because report_type is a strict ENUM, and
        // "somebody set it to a third value" is not expressible there -- the case is about a value
        // that has MOVED since the run, whichever column carries it.
        $expense = $this->account($company->id, 'Bank Charges');
        DB::table('accounts')->where('id', $expense->id)->update(['is_group' => 1]);

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);
        $runId = $this->lastRunId($company->id);

        $this->assertSame(
            '1',
            (string) DB::table('coa_linkage_changes')->where('run_id', $runId)->where('subject_id', $expense->id)
                ->where('column_name', 'is_group')->value('before_value'),
            'precondition: the run recorded 1 as the before-value'
        );
        $this->assertSame(0, (int) DB::table('accounts')->where('id', $expense->id)->value('is_group'));

        // Somebody edits it afterwards.
        DB::table('accounts')->where('id', $expense->id)->update(['is_group' => 1]);

        $this->runLinkage(['--rollback' => $runId]);

        $this->assertSame(
            1,
            (int) DB::table('accounts')->where('id', $expense->id)->value('is_group'),
            'A value that has moved since the run must be left alone by the rollback.'
        );

        $this->assertNull(
            DB::table('coa_linkage_changes')->where('run_id', $runId)->where('subject_id', $expense->id)
                ->where('column_name', 'is_group')->value('rolled_back_at'),
            'A skipped row must NOT be marked rolled back.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // D14 — the exit code, and the P&L delta
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * *"--apply exits 0 even with BLOCKING findings."* It does not any more. A chart with a purpose
     * the command cannot repair and no operator has mapped must exit non-zero, so a deploy gate
     * that reads `$?` means what its author assumed.
     */
    public function test_the_exit_code_is_non_zero_while_a_blocking_finding_remains(): void
    {
        $company = $this->freshCompany();

        // A repaired chart exits 0 …
        $this->assertSame(
            0,
            $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]),
            'A chart with no blocking finding must exit 0.'
        );

        $this->assertSame(
            0,
            DB::table('coa_linkage_findings')->where('company_id', $company->id)->where('severity', 'blocking')->count()
        );

        // … and a chart with a genuinely unpostable purpose does not. Delete the receivable
        // control's mapping AND its target, so nothing the command runs can put it back.
        $receivable = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
        DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'RECEIVABLE_CONTROL')->delete();
        DB::table('accounts')->where('id', $receivable->id)->update(['name' => 'Renamed Beyond Recognition']);

        $this->assertSame(
            1,
            $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]),
            'A blocking finding must produce a non-zero exit code.'
        );

        $this->assertGreaterThan(
            0,
            DB::table('coa_linkage_findings')->where('company_id', $company->id)->where('severity', 'blocking')->count()
        );
    }

    /**
     * The gateway-clearing family is a RULING, not a blocker: a leaf for a gateway the company does
     * not transact on is not a defect, and `SystemAccountsSeeder` deliberately refuses to guess one
     * from the `1300 Payment Gateway` pool. CT-A4's own test carved this family out of its
     * assertion by hand; the carve-out now lives in the command, so the exit code can honour it.
     */
    public function test_the_gateway_clearing_family_is_a_ruling_not_a_blocker(): void
    {
        $company = $this->freshCompany();

        $this->assertSame(0, $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]));

        $gateway = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'UNRESOLVED_PURPOSE')
            ->where('summary', 'like', '%GATEWAY_CLEARING_%')
            ->get();

        foreach ($gateway as $finding) {
            $this->assertSame(
                'ruling',
                (string) $finding->severity,
                'An unmapped gateway family is an operator decision, never a silent hygiene note and '
                    .'never a blocker on a company that does not use that gateway.'
            );
        }
    }

    /**
     * *"dry-run prints the P&L composition delta (which accounts move into P&L and their current
     * balances)"* — the half of the deploy condition an operator has to READ BEFORE they decide to
     * run the repair.
     */
    public function test_the_dry_run_prints_which_accounts_move_into_the_profit_and_loss(): void
    {
        $company = $this->freshCompany();

        $expense = $this->account($company->id, 'Bank Charges');
        DB::table('accounts')->where('id', $expense->id)->update(['report_type' => Account::REPORT_TYPES['BALANCE_SHEET']]);

        $this->artisan('accounting:coa-linkage', ['--company' => (string) $company->id, '--dry-run' => true])
            ->expectsOutputToContain('P&L COMPOSITION DELTA')
            ->expectsOutputToContain('moving INTO the P&L')
            // By ID and CODE, never only counted: "87 accounts move" is not something an operator
            // can review before a deploy; "#349  5310  Bank Charges … balance" is.
            ->expectsOutputToContain('#'.$expense->id)
            ->expectsOutputToContain((string) $expense->code)
            ->assertExitCode(0)
            ->run();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // §2b — the merge-time defect, closed operationally
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * THE MERGE DEFECT. With no payment method configured at all, the command still maps the refund
     * payout onto the configured cash/bank fallback leaf, so `refund_out` works on day one — and
     * FLAGS it, so the operator can see it was inferred rather than chosen.
     */
    public function test_refund_payout_is_mapped_to_the_cash_bank_leaf_and_flagged_when_nothing_is_configured(): void
    {
        $company = $this->freshCompany();

        DB::table('system_accounts')->where('company_id', $company->id)
            ->where('purpose_code', 'REFUND_PAYOUT_CASH_BANK')->delete();

        $this->assertSame(0, $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]));

        $payout = app(AccountResolver::class)->resolve('REFUND_PAYOUT_CASH_BANK', $company->id);
        $cash = app(AccountResolver::class)->resolve('CASH_IN_HAND', $company->id);

        $this->assertSame((int) $cash->id, (int) $payout->id, 'With nothing configured, the receipt fallback purpose is the payout leaf.');

        $flag = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'REFUND_PAYOUT_INFERRED')
            ->first();

        $this->assertNotNull($flag, 'An INFERRED payout account must be flagged — a guess the operator has not seen is still a guess.');
        $this->assertSame('ruling', (string) $flag->severity);
    }

    /**
     * The R-CT3 half: when the company HAS configured a default payment method, the payout maps
     * onto that method's own bank account — read from `charges.acc_bank_id`, exactly as
     * `ReceiptPostingRule::instrumentAccountFor()` reads it for money coming in — and is NOT
     * flagged, because nothing was inferred.
     */
    public function test_refund_payout_uses_the_configured_default_payment_method_account(): void
    {
        $company = $this->freshCompany();

        DB::table('system_accounts')->where('company_id', $company->id)
            ->where('purpose_code', 'REFUND_PAYOUT_CASH_BANK')->delete();

        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        Charge::withoutGlobalScopes()->create([
            'name' => 'Company Current Account',
            'type' => 'bank',
            'amount' => 0,
            'company_id' => $company->id,
            'acc_bank_id' => $bank->id,
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $payout = app(AccountResolver::class)->resolve('REFUND_PAYOUT_CASH_BANK', $company->id);

        $this->assertSame((int) $bank->id, (int) $payout->id);

        $this->assertNull(
            DB::table('coa_linkage_findings')->where('company_id', $company->id)
                ->where('code', 'REFUND_PAYOUT_INFERRED')->first(),
            'A payout read from the company\'s OWN configured default is not an inference and must not be flagged.'
        );
    }

    /** An existing mapping is never overwritten — the operator's own choice always wins. */
    public function test_an_existing_refund_payout_mapping_is_left_alone(): void
    {
        $company = $this->freshCompany();

        $chosen = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        DB::table('system_accounts')->updateOrInsert(
            ['company_id' => $company->id, 'purpose_code' => 'REFUND_PAYOUT_CASH_BANK', 'service_type' => null],
            ['account_id' => $chosen->id, 'created_at' => now(), 'updated_at' => now()]
        );

        // A configured default that points somewhere ELSE must not win over the existing mapping.
        $other = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1120')->firstOrFail();

        Charge::withoutGlobalScopes()->create([
            'name' => 'Petty Cash',
            'type' => 'cash',
            'amount' => 0,
            'company_id' => $company->id,
            'acc_bank_id' => $other->id,
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $this->assertSame(
            (int) $chosen->id,
            (int) DB::table('system_accounts')->where('company_id', $company->id)
                ->where('purpose_code', 'REFUND_PAYOUT_CASH_BANK')->value('account_id')
        );
    }

    /** Guard rails on the new mode itself. */
    public function test_rollback_cannot_be_combined_with_apply_or_dry_run(): void
    {
        $company = $this->freshCompany();
        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);
        $runId = $this->lastRunId($company->id);

        $this->assertSame(1, $this->runLinkage(['--rollback' => $runId, '--apply' => true]));
        $this->assertSame(1, $this->runLinkage(['--rollback' => $runId, '--dry-run' => true]));

        // An unknown run id is a warning, not a crash — and changes nothing.
        $this->assertSame(0, $this->runLinkage(['--rollback' => 'no-such-run']));
    }

    /**
     * The whole point of D14, restated as the property that matters: none of this moves money. The
     * repair, the rollback and the payout mapping are all classification-only.
     */
    public function test_neither_apply_nor_rollback_touches_a_journal_row(): void
    {
        $company = $this->freshCompany();

        $before = DB::table('journal_entries')->where('company_id', $company->id)->count();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);
        $runId = $this->lastRunId($company->id);
        $this->runLinkage(['--rollback' => $runId]);

        $this->assertSame($before, DB::table('journal_entries')->where('company_id', $company->id)->count());
    }

    /** Sanity: the AccountResolver import is used, and a refund payout that already resolves stays put. */
    public function test_resolver_is_the_oracle_not_the_system_accounts_row(): void
    {
        $company = $this->freshCompany();

        try {
            app(AccountResolver::class)->resolve('REFUND_PAYOUT_CASH_BANK', $company->id);
            $this->fail('A freshly seeded chart must NOT auto-map the refund payout — that is wave 2 §4.7.');
        } catch (Throwable $e) {
            $this->assertStringContainsString('REFUND_PAYOUT_CASH_BANK', $e->getMessage());
        }
    }
}
