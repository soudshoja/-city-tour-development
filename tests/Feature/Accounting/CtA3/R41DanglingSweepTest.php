<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\CoaLinkageChange;
use App\Models\Company;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 **R4-1** — `CT-A3-R3-2026-09-10.md` §3.4 / §9 item 2: the cross-tenant chart hazard the R3
 * lane found on the server and deliberately did NOT fix.
 *
 * ── The hazard, as measured ─────────────────────────────────────────────────────────────────────
 *
 *   > company 3's `system_accounts` rows **#188** (`SERVICE_REVENUE/lounge`) and **#189**
 *   > (`SERVICE_REVENUE/ferry`), written 2026-09-05 pointing at account ids **1738/1739 that did
 *   > not exist**, became live pointers at **company 1's** `21109 Creditors Control` and
 *   > `4132 Markup Income` the moment `--apply` minted those two leaves at exactly those ids.
 *
 * `AUTO_INCREMENT` is the whole mechanism, and the foreign key is no defence: it only ever asked
 * whether an account with that id exists, never whose it is. CT-A4 catalogued the dangling rows as
 * a hygiene finding (33 of them, ids 1662–1694); that a MINT can turn one into a live pointer at
 * another tenant's account is what nobody had noticed, and it is not a hygiene finding — it is one
 * company's purpose resolution silently reading another company's ledger account.
 *
 * ── What is pinned here ─────────────────────────────────────────────────────────────────────────
 *   1. **The hazard is real**, demonstrated on the raw database before any guard is asserted — a
 *      test that never sees the bug cannot prove it is gone (this file's sibling
 *      {@see \Tests\Feature\Accounting\CtA4\CoaLinkageCommandTest} states the same principle).
 *   2. `--apply` **REFUSES** while any company carries a dangling mapping, and writes nothing.
 *   3. `--apply --sweep-dangling` removes them with a {@see CoaLinkageChange::ROW_SWEPT}
 *      before-image and proceeds.
 *   4. A DRY RUN reports and does not refuse.
 *   5. The **ratchet** catches an adoption created between the scan and the mint — the one case a
 *      before-the-fact guard structurally cannot cover — and the run exits non-zero.
 *   6. `--rollback` after a sweep names the swept rows and does NOT report itself incomplete over
 *      rows no undo could ever restore.
 */
class R41DanglingSweepTest extends AccountingTestCase
{
    /**
     * A chart in the City Travelers dev shape: a company payment instrument minted as a CHILD of
     * `2110 Creditors`, which is what makes `--apply` MINT `21109 Creditors Control` rather than
     * map PAYABLE_CONTROL onto a still-leaf pool. A chart with nothing to mint cannot exercise an
     * adoption, so the fixture has to be this one.
     */
    private function companyThatWillMint(): Company
    {
        $company = Company::factory()->create();
        (new AccountTypeSeeder)->run();
        CoaSeeder::run($company->id);

        $creditors = $this->account((int) $company->id, 'Creditors');

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
        $instrument->actual_balance = 0;
        $instrument->opening_balance = 0;
        $instrument->budget_balance = 0;
        $instrument->variance = 0;
        $instrument->save();

        (new SystemAccountsSeeder)->run();

        $this->trackCompanyForInvariants((int) $company->id);

        return $company;
    }

    /**
     * A second tenant, whose only job here is to own the dangling row.
     *
     * Deliberately WITHOUT a chart of its own, because that is the shape the real hazard had and
     * because anything else silently repairs itself mid-run. `accounting:coa-linkage` delegates to
     * `accounting:ensure-system-leaves`, whose own comment records that
     * `SystemAccountsSeeder::run()` *"maps EVERY company in one pass"* — so a second tenant that
     * DOES have a mappable leaf for the purpose in question gets its dangling row quietly
     * re-pointed at its own account partway through the run, and the adoption never materialises.
     * That is a genuine mitigation and it is worth knowing, but it is not the case that bit: company
     * 3's rows dangled precisely BECAUSE nothing on its chart could carry `SERVICE_REVENUE/lounge`
     * or `/ferry`. A fixture whose second tenant can self-heal would assert the mitigation and call
     * it the guard.
     */
    private function otherCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants((int) $company->id);

        return $company;
    }

    private function account(int $companyId, string $name): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('name', $name)->firstOrFail();
    }

    /**
     * Write a `system_accounts` row pointing at an account id that does not exist.
     *
     * `SET FOREIGN_KEY_CHECKS=0` is not a convenience here, it is the fixture being faithful: the
     * real rows predate the constraint (`CT-A4-COA-GAP-2026-09-09.md` — 33 of them on a chart whose
     * highest real account id was 1661), so the state this command has to survive is one the
     * constraint would refuse to create today. A fixture that could not build it would be asserting
     * against the schema instead of against the chart.
     */
    private function plantDangling(int $companyId, string $purposeCode, ?string $serviceType, int $ghostAccountId): int
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            return (int) DB::table('system_accounts')->insertGetId([
                'company_id' => $companyId,
                'purpose_code' => $purposeCode,
                'service_type' => $serviceType,
                'account_id' => $ghostAccountId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** The id the very next `accounts` INSERT will take. */
    private function nextAccountId(): int
    {
        return ((int) DB::table('accounts')->max('id')) + 1;
    }

    private function runLinkage(array $options = []): int
    {
        return Artisan::call('accounting:coa-linkage', $options);
    }

    private function accountCount(int $companyId): int
    {
        return DB::table('accounts')->where('company_id', $companyId)->whereNull('deleted_at')->count();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // 1. THE HAZARD ITSELF, on the raw database — before any guard is asserted.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * REPRODUCES the R3 §3.4 finding. No command involved: this is the mechanism, and it is what
     * makes the guard below a fix rather than a formality.
     */
    public function test_a_dangling_mapping_is_adopted_by_the_next_account_minted_at_its_id(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $ghostId = $this->nextAccountId();
        $mappingId = $this->plantDangling((int) $theirs->id, 'SERVICE_REVENUE', 'lounge', $ghostId);

        // It is inert: the join finds nothing.
        $this->assertNull(
            DB::table('system_accounts as sa')->join('accounts as a', 'a.id', '=', 'sa.account_id')
                ->where('sa.id', $mappingId)->value('a.id'),
            'a dangling mapping resolves to nothing — that is what makes it look harmless'
        );

        // Mint ONE account for the OTHER company, by hand, at the ghost id.
        $parent = $this->account((int) $mine->id, 'Creditors');
        $minted = new Account;
        $minted->name = 'Creditors Control';
        $minted->code = '21109';
        $minted->parent_id = $parent->id;
        $minted->root_id = $parent->root_id;
        $minted->level = $parent->level + 1;
        $minted->company_id = $mine->id;
        $minted->is_group = false;
        $minted->disabled = false;
        $minted->account_type = 'Liabilities';
        $minted->report_type = Account::REPORT_TYPES['BALANCE_SHEET'];
        $minted->actual_balance = 0;
        $minted->opening_balance = 0;
        $minted->budget_balance = 0;
        $minted->variance = 0;
        $minted->save();

        $this->assertSame($ghostId, (int) $minted->id, 'the fixture depends on AUTO_INCREMENT handing out exactly this id');

        // And now company B's purpose resolves — to company A's ledger account.
        $adopted = DB::table('system_accounts as sa')
            ->join('accounts as a', 'a.id', '=', 'sa.account_id')
            ->where('sa.id', $mappingId)
            ->select('a.id', 'a.company_id', 'a.code')
            ->first();

        $this->assertNotNull($adopted, 'THE HAZARD: the dangling mapping is now live');
        $this->assertSame((int) $mine->id, (int) $adopted->company_id);
        $this->assertNotSame((int) $theirs->id, (int) $adopted->company_id, 'and it points across the tenant boundary');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // 2. --apply refuses while any company carries one.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_apply_is_refused_while_another_company_carries_a_dangling_mapping(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $before = $this->accountCount((int) $mine->id);
        $this->plantDangling((int) $theirs->id, 'SERVICE_REVENUE', 'lounge', $this->nextAccountId());

        // CT-A3-R3 §4.3, reason 1: a Laravel test mocks the console output by default, so a command
        // write goes to the mock and `Artisan::output()` reads back empty. Reason 2 (the delegated
        // `ensure-system-leaves` output handle replacing `lastOutput`) does NOT apply here — the
        // refusal returns before any delegation — so this is the one place in this file where the
        // console text IS assertable, and it is asserted, because the message is the whole
        // deliverable: an operator who is refused has to be told the way forward.
        $this->withoutMockingConsoleOutput();

        $exit = $this->runLinkage(['--company' => (int) $mine->id, '--apply' => true]);

        $this->assertSame(1, $exit, 'the run is refused, and the exit code says so');
        $this->assertSame($before, $this->accountCount((int) $mine->id), 'not one account was minted');
        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')->count(),
            'and nothing was written — a refused run leaves no before-images because it changed nothing'
        );

        $output = Artisan::output();
        $this->assertStringContainsString('DANGLING system_accounts rows', $output);
        $this->assertStringContainsString('--sweep-dangling', $output, 'the refusal names the way forward');
    }

    /**
     * The scan is over EVERY company, whatever `--company` says. This is the case a
     * per-company scan cannot see, and it is the shape the hazard actually took on the real chart:
     * the row belongs to company 3, the mint belongs to company 1.
     */
    public function test_the_scan_is_not_scoped_to_the_company_being_repaired(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $this->plantDangling((int) $theirs->id, 'SERVICE_REVENUE', 'ferry', $this->nextAccountId());

        $this->assertSame(
            0,
            DB::table('system_accounts as sa')->leftJoin('accounts as a', 'a.id', '=', 'sa.account_id')
                ->where('sa.company_id', (int) $mine->id)->whereNull('a.id')->count(),
            'the company being repaired has NO dangling row of its own — a scoped scan would see nothing'
        );

        $this->assertSame(1, $this->runLinkage(['--company' => (int) $mine->id, '--apply' => true]));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // 3. --sweep-dangling: removed, evidenced, and the run proceeds.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_sweep_dangling_removes_the_row_with_a_before_image_and_lets_the_run_proceed(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $before = $this->accountCount((int) $mine->id);
        $ghostId = $this->nextAccountId();
        $mappingId = $this->plantDangling((int) $theirs->id, 'SERVICE_REVENUE', 'lounge', $ghostId);

        $this->runLinkage(['--company' => (int) $mine->id, '--apply' => true, '--sweep-dangling' => true]);

        $this->assertSame(
            0,
            DB::table('system_accounts')->where('id', $mappingId)->count(),
            'the dangling row is gone'
        );

        $this->assertGreaterThan($before, $this->accountCount((int) $mine->id), 'and the repair went ahead and minted');

        $image = DB::table('coa_linkage_changes')
            ->where('subject_table', 'system_accounts')
            ->where('column_name', CoaLinkageChange::ROW_SWEPT)
            ->where('subject_id', $mappingId)
            ->first();

        $this->assertNotNull($image, 'the removal is evidenced, per row');
        $this->assertSame((int) $theirs->id, (int) $image->company_id, 'against the company that OWNED it, not the one being repaired');

        $was = json_decode((string) $image->before_value, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('SERVICE_REVENUE', $was['purpose_code']);
        $this->assertSame('lounge', $was['service_type']);
        $this->assertSame($ghostId, (int) $was['account_id'], 'including the id it named, which is the evidence that it was dangling');
    }

    /**
     * The point of the sweep, stated as an outcome rather than as a row count: after it, no mapping
     * of any other company points at anything this company owns.
     */
    public function test_after_the_sweep_no_foreign_mapping_points_at_a_minted_leaf(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $this->plantDangling((int) $theirs->id, 'SERVICE_REVENUE', 'lounge', $this->nextAccountId());

        $exit = $this->runLinkage(['--company' => (int) $mine->id, '--apply' => true, '--sweep-dangling' => true]);

        $this->assertSame(0, $exit, 'a swept run completes cleanly');

        $crossTenant = DB::table('system_accounts as sa')
            ->join('accounts as a', 'a.id', '=', 'sa.account_id')
            ->whereColumn('sa.company_id', '!=', 'a.company_id')
            ->count();

        $this->assertSame(0, $crossTenant, 'no purpose mapping resolves across the tenant boundary');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // 4. A dry run reports; it does not refuse.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_dry_run_reports_the_dangling_rows_and_exits_zero(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $mappingId = $this->plantDangling((int) $theirs->id, 'SERVICE_REVENUE', 'lounge', $this->nextAccountId());

        $before = $this->accountCount((int) $mine->id);

        $exit = $this->runLinkage(['--company' => (int) $mine->id, '--dry-run' => true]);

        // No output assertion here, unlike the refusal case above: a dry run RUNS, so it reaches
        // `accounting:ensure-system-leaves`, whose own output handle replaces the console kernel's
        // `lastOutput` and leaves `Artisan::output()` empty for the rest of the test (CT-A3-R3 §4.3,
        // reason 2). What matters is asserted on state instead.
        $this->assertSame(0, $exit, 'a dry run cannot cause the adoption, so it is a warning and not a failure');
        $this->assertSame(1, DB::table('system_accounts')->where('id', $mappingId)->count(), 'the dangling row is still there');
        $this->assertSame($before, $this->accountCount((int) $mine->id), 'and nothing was minted, so nothing could have adopted it');
    }

    public function test_a_dry_run_with_sweep_dangling_writes_nothing(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $mappingId = $this->plantDangling((int) $theirs->id, 'SERVICE_REVENUE', 'lounge', $this->nextAccountId());

        $this->runLinkage(['--company' => (int) $mine->id, '--dry-run' => true, '--sweep-dangling' => true]);

        $this->assertSame(1, DB::table('system_accounts')->where('id', $mappingId)->count());
        $this->assertSame(0, DB::table('coa_linkage_changes')->count());
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // 5. The ratchet — the case the guard structurally cannot cover.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The guard reads the chart BEFORE the mints. A mapping written DURING the run — a concurrent
     * seeder, a second operator, a UI save — is invisible to it by construction. The ratchet reads
     * the chart AFTER, and that is the difference between the two checks.
     *
     * Simulated by an `Account::created` listener, which is the honest in-process stand-in for
     * "something else wrote a row between the scan and the mint": it fires at exactly the moment a
     * concurrent writer would have to have fired to cause this.
     */
    public function test_the_ratchet_catches_a_foreign_mapping_written_during_the_run(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $theirCompanyId = (int) $theirs->id;
        $myCompanyId = (int) $mine->id;
        $raced = false;

        Account::created(function (Account $account) use ($theirCompanyId, $myCompanyId, &$raced): void {
            if ($raced || (int) $account->company_id !== $myCompanyId) {
                return;
            }

            $raced = true;

            DB::table('system_accounts')->insert([
                'company_id' => $theirCompanyId,
                'purpose_code' => 'SERVICE_REVENUE',
                'service_type' => 'lounge',
                'account_id' => $account->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $exit = $this->runLinkage(['--company' => $myCompanyId, '--apply' => true]);

        $this->assertTrue($raced, 'the fixture must actually have raced a mint, or it proves nothing');
        $this->assertSame(1, $exit, 'the ratchet fails the run even though the pre-run scan was clean');

        // The state the ratchet is failing over, asserted directly rather than through
        // Artisan::output() -- see the clean-run case below for why that string is empty here.
        $this->assertSame(
            1,
            DB::table('system_accounts as sa')->join('accounts as a', 'a.id', '=', 'sa.account_id')
                ->where('sa.company_id', $theirCompanyId)
                ->where('a.company_id', $myCompanyId)
                ->count(),
            'and it is failing over a real cross-tenant pointer, not over a counter'
        );

        // The mint is NOT undone in band: --apply is not one transaction, and deleting an account
        // to tidy up a ratchet is the class of write this command refuses everywhere else. The run
        // id is recorded so the operator undoes it deliberately.
        $this->assertGreaterThan(
            0,
            DB::table('coa_linkage_changes')->where('column_name', CoaLinkageChange::ROW_CREATED)->count(),
            'the before-images are still written, because --rollback is the way back'
        );
    }

    /**
     * The other side of the ratchet: an ordinary repair, with mints, is not failed by it.
     *
     * Asserted on state and exit code, never on `Artisan::output()` — CT-A3-R3 §4.3 measured that
     * empty for this command from the moment it delegates to `accounting:ensure-system-leaves`,
     * which replaces the console kernel's `lastOutput` with its own handle. A test asserting on an
     * empty string asserts nothing.
     */
    public function test_the_ratchet_reports_a_clean_run_when_nothing_adopted_a_mint(): void
    {
        $mine = $this->companyThatWillMint();
        $before = $this->accountCount((int) $mine->id);

        $exit = $this->runLinkage(['--company' => (int) $mine->id, '--apply' => true]);

        $this->assertSame(0, $exit);
        $this->assertGreaterThan($before, $this->accountCount((int) $mine->id), 'the run did mint, so the ratchet had something to check');
        $this->assertSame(
            0,
            DB::table('system_accounts as sa')->join('accounts as a', 'a.id', '=', 'sa.account_id')
                ->whereColumn('sa.company_id', '!=', 'a.company_id')->count()
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // 6. --rollback after a sweep.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * A swept row named an account that does not exist, and `system_accounts.account_id` is a real
     * FK — so the database itself refuses it back. The sweep is a documented ONE-WAY repair, and
     * the undo says so by name instead of landing it in the "this undo was NOT complete" list,
     * where it would drown the refusals that genuinely ARE actionable.
     */
    public function test_rollback_after_a_sweep_names_the_swept_row_and_still_reports_a_complete_undo(): void
    {
        $mine = $this->companyThatWillMint();
        $theirs = $this->otherCompany();

        $mappingId = $this->plantDangling((int) $theirs->id, 'SERVICE_REVENUE', 'lounge', $this->nextAccountId());

        $this->runLinkage(['--company' => (int) $mine->id, '--apply' => true, '--sweep-dangling' => true]);

        $runId = (string) DB::table('coa_linkage_changes')->orderByDesc('id')->value('run_id');
        $this->assertNotSame('', $runId);

        $exit = $this->runLinkage(['--rollback' => $runId]);

        $this->assertSame(0, $exit, 'the undo is complete: nothing it could restore was left unrestored');
        $this->assertSame(
            0,
            DB::table('system_accounts')->where('id', $mappingId)->count(),
            'and the swept row is still gone: an undo that could not restore it must not pretend it did'
        );

        $this->assertNotNull(
            DB::table('coa_linkage_changes')
                ->where('subject_id', $mappingId)
                ->where('column_name', CoaLinkageChange::ROW_SWEPT)
                ->value('rolled_back_at'),
            'the before-image is stamped as dealt with, so a second --rollback is a no-op rather than a repeat report'
        );
    }
}
