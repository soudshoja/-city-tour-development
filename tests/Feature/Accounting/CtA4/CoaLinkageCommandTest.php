<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA4;

use App\Models\Account;
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
 * CT-A4 — `accounting:coa-linkage`.
 *
 * The oracle these tests are built around is G1, the defect the whole lane exists for: on a chart
 * where anything has minted a child under `2110 Creditors`, PAYABLE_CONTROL goes unmapped, and
 * because wave 1's E5 fallback resolves SERVICE_PAYABLE/{type} THROUGH PAYABLE_CONTROL, every
 * flight and hotel sale dies on UnmappedPurposeException. Several cases below therefore
 * REPRODUCE that failure before asserting the fix, rather than only asserting the happy path — a
 * test that never sees the bug cannot prove it is gone.
 *
 * Balance neutrality is pinned structurally, not by inspection: helpers take a full per-root
 * trial balance before and after the command and assert byte equality.
 */
class CoaLinkageCommandTest extends AccountingTestCase
{
    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Fixtures
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /** A CoaSeeder-fresh chart with its purposes mapped — the "nothing has happened yet" state. */
    private function freshCompany(): Company
    {
        $company = Company::factory()->create();
        (new AccountTypeSeeder)->run();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        return $company;
    }

    /**
     * The City Travelers dev chart's shape, reproduced minimally: a company payment instrument
     * minted as a CHILD of `2110 Creditors`, which is what turns the PAYABLE_CONTROL target from
     * a leaf into a group and breaks the mapping.
     *
     * @return array{0: Company, 1: Account, 2: Account} company, the Creditors pool, the instrument
     */
    private function companyWithChildUnderCreditors(): array
    {
        $company = Company::factory()->create();
        (new AccountTypeSeeder)->run();
        CoaSeeder::run($company->id);

        $creditors = $this->account($company->id, 'Creditors');

        $instrument = new Account;
        $instrument->name = 'VISA Corporate Card';
        $instrument->code = '2111';
        $instrument->parent_id = $creditors->id;
        $instrument->root_id = $creditors->root_id;
        $instrument->level = $creditors->level + 1;
        $instrument->company_id = $company->id;
        $instrument->is_group = false;
        $instrument->disabled = false;
        $instrument->account_type = 'Liabilities';
        $instrument->report_type = Account::REPORT_TYPES['BALANCE_SHEET'];
        // The `accounts` money columns carry no DB default; AccountService::create() sets them
        // explicitly and so must a hand-built fixture (see that method's own "Residual 17 fix"
        // note on being column-identical to create()).
        $instrument->actual_balance = 0;
        $instrument->opening_balance = 0;
        $instrument->budget_balance = 0;
        $instrument->variance = 0;
        $instrument->save();

        // The OTHER half of the dev chart's real shape, and the reason G1 is fatal rather than
        // cosmetic: `Suppliers (Flights)` and `Flights Cost` have grown per-supplier children
        // (37 and 36 of them on the real chart), so mapSupplierPoolLeaf() refuses to pick one and
        // SERVICE_PAYABLE/flight + SERVICE_COST/flight get no per-service row at all. Wave 1's E5
        // fallback is then the ONLY thing standing between a flight sale and an exception -- and
        // the fallback lands on PAYABLE_CONTROL, which the child under Creditors has just
        // disabled. Without this half the fixture would map SERVICE_PAYABLE/flight straight onto
        // the pool and never exercise the chain this lane exists to repair.
        // Codes are deliberately UNIQUE here (21201/51101), not the colliding 2121/5111 the real
        // minting bug produces: this fixture is about the pool having CHILDREN, and the
        // suite-wide invariant rejects any duplicate code beyond CoaSeeder's own deferred 2130
        // pair. The duplicate-code defect gets its own test and its own fixture below.
        foreach ([['Suppliers (Flights)', 'Airline A (payable)', '21201'], ['Flights Cost', 'Airline A (cost)', '51101']] as [$poolName, $childName, $childCode]) {
            $pool = $this->account($company->id, $poolName);

            $child = new Account;
            $child->name = $childName;
            $child->code = $childCode;
            $child->parent_id = $pool->id;
            $child->root_id = $pool->root_id;
            $child->level = $pool->level + 1;
            $child->company_id = $company->id;
            $child->is_group = false;
            $child->disabled = false;
            $child->account_type = $poolName;
            $child->report_type = $pool->report_type;
            $child->actual_balance = 0;
            $child->opening_balance = 0;
            $child->budget_balance = 0;
            $child->variance = 0;
            $child->save();
        }

        // Purposes are (re)mapped only AFTER the chart is in its real shape, exactly as a
        // cutover would find it.
        (new SystemAccountsSeeder)->run();

        return [$company, $creditors->refresh(), $instrument];
    }

    private function account(int $companyId, string $name): Account
    {
        $account = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->first();

        $this->assertNotNull($account, "Fixture expects an account named '{$name}' on company {$companyId}.");

        return $account;
    }

    private function mappedAccountId(int $companyId, string $purposeCode, ?string $serviceType = null): ?int
    {
        return DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->when(
                $serviceType === null,
                fn ($q) => $q->whereNull('service_type'),
                fn ($q) => $q->where('service_type', $serviceType)
            )
            ->value('account_id');
    }

    /**
     * A balanced two-line document, written directly rather than through PostingService: this
     * lane never posts, it only needs history for the accounts it is about to reclassify, and
     * driving a real feeder would drag in a whole sale fixture that has nothing to do with COA
     * linkage.
     *
     * It still carries its own `transactions` header. The suite-wide invariant
     * ({@see \Tests\Support\AccountingInvariants::assertNoOrphanLines()}) refuses a
     * `transaction_id IS NULL` line appearing DURING a test — deliberately, even though
     * production is full of legacy orphans — so a fixture that skipped the header would be
     * asserting against the harness rather than against this command.
     */
    private function postBalancedPair(int $companyId, int $debitAccountId, int $creditAccountId, string $amount): void
    {
        $transactionId = DB::table('transactions')->insertGetId([
            'company_id' => $companyId,
            'entity_id' => $companyId,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => $amount,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'description' => 'CT-A4 fixture',
            'reference_type' => 'Invoice',
            'transaction_date' => '2026-01-15 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([[$debitAccountId, $amount, '0.000'], [$creditAccountId, '0.000', $amount]] as [$accountId, $dr, $cr]) {
            DB::table('journal_entries')->insert([
                'transaction_id' => $transactionId,
                'company_id' => $companyId,
                'account_id' => $accountId,
                'transaction_date' => '2026-01-15 00:00:00',
                'debit' => $dr,
                'credit' => $cr,
                'description' => 'CT-A4 fixture',
                'name' => 'CT-A4 fixture',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array<string, string> root name => 'dr|cr', the whole company's trial balance. */
    private function trialBalanceByRoot(int $companyId): array
    {
        $rows = DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->leftJoin('accounts as r', 'r.id', '=', 'a.root_id')
            ->selectRaw('COALESCE(r.name, a.name) AS root, ROUND(SUM(je.debit),3) dr, ROUND(SUM(je.credit),3) cr')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->groupBy('root')
            ->orderBy('root')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[(string) $row->root] = $row->dr.'|'.$row->cr;
        }

        return $out;
    }

    /** @return array<string, string> every account's classification columns, for no-op proofs. */
    private function accountFingerprint(int $companyId): array
    {
        return DB::table('accounts')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'parent_id', 'root_id', 'level', 'is_group', 'report_type', 'account_type_id'])
            ->mapWithKeys(fn ($r) => [(string) $r->id => implode('|', (array) $r)])
            ->all();
    }

    private function runLinkage(array $options = []): int
    {
        return Artisan::call('accounting:coa-linkage', $options);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // G1 — the defect, and the fix
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * REPRODUCES G1. Not "asserts a gap is reported" — asserts the real, downstream consequence:
     * SERVICE_PAYABLE/flight, the purpose ~97% of this company's sales need, cannot resolve.
     */
    public function test_child_under_creditors_leaves_payable_control_unmapped_and_breaks_service_payable(): void
    {
        [$company] = $this->companyWithChildUnderCreditors();

        $this->assertNull(
            $this->mappedAccountId($company->id, 'PAYABLE_CONTROL'),
            'G1: with a child under Creditors, mapByChain() skips the pool and PAYABLE_CONTROL gets no row.'
        );

        $this->expectExceptionMessageMatches('/SERVICE_PAYABLE|PAYABLE_CONTROL/');
        app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'flight');
    }

    public function test_apply_mints_creditors_control_and_payable_control_resolves(): void
    {
        [$company, $creditors] = $this->companyWithChildUnderCreditors();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $control = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('parent_id', $creditors->id)
            ->where('name', 'Creditors Control')
            ->first();

        $this->assertNotNull($control, 'The control leaf must be minted under the pool.');
        $this->assertSame($creditors->code.'09', (string) $control->code, "The '{pool code}09' convention (CT-A2 21209/21309, CT-A3-verified).");
        $this->assertFalse((bool) $control->is_group);

        $resolved = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id);
        $this->assertSame($control->id, $resolved->id);
    }

    /** G4 — the whole point of G1: the per-service fallback chain now terminates. */
    public function test_service_payable_for_every_service_type_resolves_through_the_fallback_after_apply(): void
    {
        [$company] = $this->companyWithChildUnderCreditors();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $resolver = app(AccountResolver::class);

        foreach ((array) config('accounting.purpose_codes.service_types') as $serviceType) {
            $account = $resolver->resolve('SERVICE_PAYABLE', $company->id, $serviceType);
            $this->assertNotNull($account, "SERVICE_PAYABLE/{$serviceType} must resolve after the repair.");
        }
    }

    /**
     * REGRESSION GUARD on the seeder change. On a CoaSeeder-fresh chart `Creditors` is still a
     * leaf, so mapControlPoolLeaf() must behave byte-identically to the mapByChain() it replaced:
     * map straight onto the pool, and mint NOTHING. A control leaf here would turn 2110 into a
     * group on every fresh company and move the purpose off an account that may carry history.
     */
    public function test_fresh_chart_maps_payable_control_onto_the_pool_and_mints_no_control_leaf(): void
    {
        $company = $this->freshCompany();
        $creditors = $this->account($company->id, 'Creditors');

        $this->assertSame($creditors->id, $this->mappedAccountId($company->id, 'PAYABLE_CONTROL'));

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $this->assertSame(
            $creditors->id,
            $this->mappedAccountId($company->id, 'PAYABLE_CONTROL'),
            'A fresh chart must keep PAYABLE_CONTROL on the pool itself.'
        );
        $this->assertDatabaseMissing('accounts', [
            'company_id' => $company->id,
            'name' => 'Creditors Control',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Balance neutrality and idempotency
    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_apply_changes_no_balance(): void
    {
        [$company, $creditors, $instrument] = $this->companyWithChildUnderCreditors();
        $this->trackCompanyForInvariants($company->id);

        // Both legs must be TRUE LEAVES: TrialBalanceService sums leaf accounts only, so a
        // fixture that posted to a group would report a one-sided balance and fail the suite
        // invariant for a reason that has nothing to do with this command.
        $flightCostLeaf = $this->account($company->id, 'Airline A (cost)');
        $cash = $this->account($company->id, 'Receipt Voucher Cash');
        $this->postBalancedPair($company->id, $flightCostLeaf->id, $instrument->id, '1250.750');
        $this->postBalancedPair($company->id, $cash->id, $instrument->id, '99.001');

        $before = $this->trialBalanceByRoot($company->id);
        $this->assertNotEmpty($before, 'Precondition: the fixture must actually have posted something.');

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $this->assertSame($before, $this->trialBalanceByRoot($company->id), 'No applied repair may move a single fils.');
    }

    public function test_second_apply_run_is_a_no_op(): void
    {
        [$company] = $this->companyWithChildUnderCreditors();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $accountsAfterFirst = $this->accountFingerprint($company->id);
        $mappingsAfterFirst = DB::table('system_accounts')->where('company_id', $company->id)
            ->orderBy('purpose_code')->orderBy('service_type')->get()
            ->map(fn ($r) => $r->purpose_code.'/'.($r->service_type ?? '-').'=>'.$r->account_id)->all();
        $findingsAfterFirst = DB::table('coa_linkage_findings')->where('company_id', $company->id)->count();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $this->assertSame($accountsAfterFirst, $this->accountFingerprint($company->id));
        $this->assertSame($mappingsAfterFirst, DB::table('system_accounts')->where('company_id', $company->id)
            ->orderBy('purpose_code')->orderBy('service_type')->get()
            ->map(fn ($r) => $r->purpose_code.'/'.($r->service_type ?? '-').'=>'.$r->account_id)->all());
        $this->assertSame($findingsAfterFirst, DB::table('coa_linkage_findings')->where('company_id', $company->id)->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        [$company] = $this->companyWithChildUnderCreditors();

        $before = $this->accountFingerprint($company->id);
        $mappingsBefore = DB::table('system_accounts')->where('company_id', $company->id)->count();

        $this->runLinkage(['--company' => (string) $company->id, '--dry-run' => true]);

        $this->assertSame($before, $this->accountFingerprint($company->id));
        $this->assertSame($mappingsBefore, DB::table('system_accounts')->where('company_id', $company->id)->count());
        $this->assertSame(0, DB::table('coa_linkage_findings')->where('company_id', $company->id)->count());
        $this->assertDatabaseMissing('accounts', ['company_id' => $company->id, 'name' => 'Creditors Control']);
    }

    public function test_dry_run_and_apply_together_is_refused(): void
    {
        $company = $this->freshCompany();

        $exit = $this->runLinkage(['--company' => (string) $company->id, '--dry-run' => true, '--apply' => true]);

        $this->assertSame(1, $exit);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // G6 / G7 / G8 — the classification columns
    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_account_type_id_is_backfilled_by_family_then_root(): void
    {
        $company = $this->freshCompany();
        DB::table('accounts')->where('company_id', $company->id)->update(['account_type_id' => null]);

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $bankLeaf = Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('name', 'Kuwait International Bank')->first();
        $cogsLeaf = $this->account($company->id, 'Flights Cost');
        $equityLeaf = $this->account($company->id, 'Retained Earnings');

        $typeName = fn (?int $id) => $id === null ? null : DB::table('account_types')->where('id', $id)->value('name');

        // The nearest FAMILY wins over the root: a bank leaf is 'Bank', not 'Current Asset'.
        $this->assertSame('Bank', $typeName((int) $bankLeaf->fresh()->account_type_id));
        $this->assertSame('Cost of Goods Sold', $typeName((int) $cogsLeaf->fresh()->account_type_id));
        // No family matches Retained Earnings, so it falls back to its root.
        $this->assertSame('Equity', $typeName((int) $equityLeaf->fresh()->account_type_id));

        $this->assertSame(
            0,
            DB::table('accounts')->where('company_id', $company->id)->whereNull('deleted_at')->whereNull('account_type_id')->count(),
            'Every account on a CoaSeeder chart must derive a type.'
        );
    }

    public function test_report_type_is_derived_from_the_root_and_moves_no_money(): void
    {
        $company = $this->freshCompany();
        $this->trackCompanyForInvariants($company->id);

        $expenseLeaf = $this->account($company->id, 'Office Rent');
        $incomeLeaf = $this->account($company->id, 'Flight Booking Revenue');
        $cash = $this->account($company->id, 'Receipt Voucher Cash');

        $this->postBalancedPair($company->id, $expenseLeaf->id, $cash->id, '500.000');

        // The dev chart's real defect: 87 Expenses accounts and 2 Income accounts filed as
        // balance-sheet lines, so any P&L selecting on this column silently drops them.
        DB::table('accounts')->whereIn('id', [$expenseLeaf->id, $incomeLeaf->id])
            ->update(['report_type' => Account::REPORT_TYPES['BALANCE_SHEET']]);

        $before = $this->trialBalanceByRoot($company->id);

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $this->assertSame(Account::REPORT_TYPES['PROFIT_LOSS'], $expenseLeaf->fresh()->report_type);
        $this->assertSame(Account::REPORT_TYPES['PROFIT_LOSS'], $incomeLeaf->fresh()->report_type);
        $this->assertSame($before, $this->trialBalanceByRoot($company->id), 'report_type is a classification, not a balance.');
    }

    public function test_is_group_is_set_to_exists_child_without_changing_the_leaf_rule(): void
    {
        [$company, $creditors, $instrument] = $this->companyWithChildUnderCreditors();

        // A leaf mis-flagged as a group, and a group mis-flagged as a leaf — the two directions
        // the dev chart has (215 and 1 respectively).
        DB::table('accounts')->where('id', $instrument->id)->update(['is_group' => 1]);
        DB::table('accounts')->where('id', $creditors->id)->update(['is_group' => 0]);

        $leafBefore = AccountResolver::isLeaf($instrument->fresh());
        $poolBefore = AccountResolver::isLeaf($creditors->fresh());

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $this->assertFalse((bool) $instrument->fresh()->is_group);
        $this->assertTrue((bool) $creditors->fresh()->is_group);

        // The engine ignores is_group entirely; repairing it must not change any leaf verdict.
        $this->assertSame($leafBefore, AccountResolver::isLeaf($instrument->fresh()));
        $this->assertSame($poolBefore, AccountResolver::isLeaf($creditors->fresh()));
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // The move guard
    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_move_is_refused_without_the_flag_even_though_the_pool_is_blocked(): void
    {
        [$company, $creditors, $instrument] = $this->companyWithChildUnderCreditors();
        $this->trackCompanyForInvariants($company->id);

        $flightCostLeaf = $this->account($company->id, 'Airline A (cost)');
        $this->postBalancedPair($company->id, $flightCostLeaf->id, $instrument->id, '77.000');

        $parentBefore = (int) $instrument->parent_id;

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $this->assertSame($parentBefore, (int) $instrument->fresh()->parent_id, 'No --allow-move means no relocation, full stop.');
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'name' => 'Creditors Control']);
        $this->assertSame($creditors->id, (int) $instrument->fresh()->parent_id);
    }

    public function test_allow_move_relocates_the_children_and_maps_the_pool_directly(): void
    {
        [$company, $creditors, $instrument] = $this->companyWithChildUnderCreditors();
        $this->trackCompanyForInvariants($company->id);

        $flightCostLeaf = $this->account($company->id, 'Airline A (cost)');
        $this->postBalancedPair($company->id, $flightCostLeaf->id, $instrument->id, '77.000');

        $before = $this->trialBalanceByRoot($company->id);
        $grandparentId = (int) $creditors->parent_id;

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true, '--allow-move' => true]);

        $this->assertSame($grandparentId, (int) $instrument->fresh()->parent_id, 'The instrument moves up to Accounts Payable.');
        $this->assertDatabaseMissing('accounts', ['company_id' => $company->id, 'name' => 'Creditors Control']);

        // With the pool a leaf again, the purpose maps straight onto it — no extra account at all.
        $this->assertSame($creditors->id, $this->mappedAccountId($company->id, 'PAYABLE_CONTROL'));
        $this->assertSame($creditors->id, app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id)->id);

        $this->assertSame($before, $this->trialBalanceByRoot($company->id), 'A move relocates an account; it must not re-post its history.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // The flag-only findings
    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_duplicate_codes_are_flagged_and_never_renumbered(): void
    {
        [$company, $creditors, $instrument] = $this->companyWithChildUnderCreditors();

        $twin = $instrument->replicate();
        $twin->name = 'VISA Second Card';
        $twin->save();

        $this->assertSame((string) $instrument->code, (string) $twin->code);

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $finding = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'DUPLICATE_CODE')
            ->first();

        $this->assertNotNull($finding);
        $this->assertSame('ruling', $finding->severity);
        $this->assertStringContainsString((string) $instrument->code, $finding->summary);

        // Flag only: neither account's code moved.
        $this->assertSame((string) $instrument->code, (string) $instrument->fresh()->code);
        $this->assertSame((string) $twin->code, (string) $twin->fresh()->code);
    }

    public function test_an_account_with_children_and_activity_is_flagged_not_repaired(): void
    {
        [$company, $creditors, $instrument] = $this->companyWithChildUnderCreditors();

        // NO trackCompanyForInvariants() here, and that is the point rather than an omission:
        // this fixture deliberately builds the exact shape the suite invariant exists to reject —
        // an account that carries journal history AND has children, so TrialBalanceService (which
        // sums leaves) drops its lines and the company reads as one-sided. Tracking it would
        // assert the harness's opinion of the fixture instead of this command's behaviour. The
        // dev chart has seven of these (CT-A4 §1.5) and the correct outcome is a FINDING, not a
        // repair, which is what this test pins.

        // The dev chart's shape: a per-supplier leaf that took postings, then grew a currency
        // child, so the parent keeps the history and fails the engine's leaf test.
        $child = $instrument->replicate();
        $child->name = 'VISA Corporate Card (USD)';
        $child->code = '21111';
        $child->parent_id = $instrument->id;
        $child->level = $instrument->level + 1;
        $child->save();

        $flightCostLeaf = $this->account($company->id, 'Airline A (cost)');
        $this->postBalancedPair($company->id, $flightCostLeaf->id, $instrument->id, '12.000');

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $finding = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'NON_LEAF_POSTING')
            ->where('subject_id', $instrument->id)
            ->first();

        $this->assertNotNull($finding, 'An account with both children and journal rows must be reported.');
        $this->assertSame('ruling', $finding->severity);
        $this->assertSame((int) $instrument->parent_id, (int) $instrument->fresh()->parent_id, 'Flag only — nothing moved.');
    }

    public function test_unused_leaves_are_flagged_as_hygiene(): void
    {
        $company = $this->freshCompany();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $unused = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'UNUSED_LEAF')
            ->get();

        $this->assertGreaterThan(0, $unused->count(), 'A freshly seeded chart is nothing but unused leaves.');
        $this->assertSame(['hygiene'], $unused->pluck('severity')->unique()->values()->all());
    }

    public function test_findings_are_rewritten_not_appended(): void
    {
        [$company] = $this->companyWithChildUnderCreditors();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);
        $first = DB::table('coa_linkage_findings')->where('company_id', $company->id)->count();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);
        $second = DB::table('coa_linkage_findings')->where('company_id', $company->id)->count();

        $this->assertSame($first, $second, 'The table is the latest measurement, not a ticket queue.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Whole-vocabulary verification
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * After the repair, the only purposes left unresolved on a seeded chart must be the ones the
     * command itself declares deliberate (SUSPENSE — the engine posts nothing there, mapping it
     * would hand a feeder a plug account; VAT_OUTPUT — Kuwait v1 has no VAT). Anything else
     * unresolved is a real gap and this assertion names it.
     */
    public function test_after_apply_no_blocking_purpose_remains_unresolved(): void
    {
        [$company] = $this->companyWithChildUnderCreditors();

        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $blocking = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'UNRESOLVED_PURPOSE')
            ->where('severity', 'blocking')
            ->pluck('summary')
            ->all();

        // The ONLY blocking residue a seeded chart may still carry is
        // GATEWAY_CLEARING_{gateway} for a gateway with no dedicated child under the
        // `1300 Payment Gateway` pool -- and that is a REFUSAL, not a miss.
        // SystemAccountsSeeder::resolveGatewayClearing() deliberately will not fall back onto
        // an unrelated sibling once the pool has any children, because real production data
        // proved the pool holds genuinely non-gateway instruments ('Cash', 'Cheques', 'Deema',
        // 'Tabby'). CoaSeeder seeds no per-gateway child and EnsureSystemLeaves backfills only
        // Knet (1311) and uPayment (1312), so on a FRESH chart the other three correctly report
        // a gap. On the City Travelers dev chart all five resolve: Tap/MyFatoorah/Hesabe
        // already have named children and the two backfilled leaves close the rest (CT-A4
        // GAP report section 2.3). An operator closes this by naming the leaf on the Purpose
        // Mapping screen -- this command must never guess it.
        $unexpected = array_values(array_filter(
            $blocking,
            static fn (string $summary): bool => ! str_contains($summary, 'GATEWAY_CLEARING_')
        ));

        $this->assertSame([], $unexpected, 'Unresolved after repair:
'.implode('
', $unexpected));

        // And what remains must be only that family, not a long tail hiding behind it.
        $this->assertLessThanOrEqual(5, count($blocking), 'Only the gateway-clearing family may remain.');
    }

    public function test_every_engine_purpose_that_resolves_lands_on_a_leaf_of_the_right_root(): void
    {
        [$company] = $this->companyWithChildUnderCreditors();
        $this->runLinkage(['--company' => (string) $company->id, '--apply' => true]);

        $expectedRoot = [
            'RECEIVABLE_CONTROL' => 'Assets',
            'PAYABLE_CONTROL' => 'Liabilities',
            'RETAINED_EARNINGS' => 'Equity',
            'COST_OF_SALES_CONTROL' => 'Expenses',
            'UNBILLED_SUPPLIER_COST' => 'Assets',
            'COMMISSION_EXPENSE' => 'Expenses',
            'COMMISSION_PAYABLE' => 'Liabilities',
            'DEFERRED_REVENUE' => 'Liabilities',
        ];

        $resolver = app(AccountResolver::class);

        foreach ($expectedRoot as $purposeCode => $rootName) {
            try {
                $account = $resolver->resolve($purposeCode, $company->id);
            } catch (Throwable $e) {
                $this->fail("{$purposeCode} must resolve after the repair: {$e->getMessage()}");
            }

            $this->assertFalse(
                Account::query()->withoutGlobalScopes()->where('parent_id', $account->id)->exists(),
                "{$purposeCode} must land on a leaf."
            );

            $root = Account::query()->withoutGlobalScopes()->find($account->root_id);
            $this->assertSame($rootName, $root?->name, "{$purposeCode} must sit under {$rootName}.");
        }
    }
}
