<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7 ROUND 2, finding **F2** — `accounting:backfill-payable-party`.
 *
 * CT-A7-2 stamps the supplier on payment vouchers posted from now on. The supplier filter on the
 * creditors and unpaid-AP screens is `where('type_reference_id', $supplierId)`, so **every
 * pre-deploy voucher still carries NULL** and still shows a supplier's invoices while hiding their
 * payments — R3-10a's exact symptom, live for all history. Round 1 deferred the repair and
 * specified its shape; this file proves the command has that shape.
 *
 * The properties under test are the ones that make a historical ledger repair safe:
 *   - dry-run is the DEFAULT and writes nothing;
 *   - it refuses to guess — an account that names no supplier is reported and left alone;
 *   - a per-row before-image is written in the same transaction as the write;
 *   - re-running is a no-op;
 *   - `--rollback={runId}` puts every row back, and refuses a row that has moved since;
 *   - no money moves.
 */
class BackfillPayablePartyF2Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    private int $companyId;

    private int $branchId;

    /** A per-supplier payable leaf carrying `accounts.supplier_id` — derivable. */
    private int $supplierLeafId;

    /** A per-supplier payable leaf carrying only the pivot link — derivable by the second path. */
    private int $pivotLeafId;

    /** A pooled control leaf naming no supplier — must be REFUSED, never guessed. */
    private int $pooledLeafId;

    private int $supplierId;

    private int $pivotSupplierId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->companyId, 'user_id' => $branchOwner->id]);
        $this->branchId = (int) $branch->id;

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);
        (new SystemAccountsSeeder)->run();

        $supplier = Supplier::factory()->create();
        $this->supplierId = (int) $supplier->id;

        $pivotSupplier = Supplier::factory()->create();
        $this->pivotSupplierId = (int) $pivotSupplier->id;

        SupplierCompany::create([
            'supplier_id' => $pivotSupplier->id,
            'company_id' => $this->companyId,
            'is_active' => true,
        ]);
        $pivot = SupplierCompany::where('supplier_id', $pivotSupplier->id)
            ->where('company_id', $this->companyId)->firstOrFail();

        $this->supplierLeafId = (int) $this->mintApLeaf('Supplier One (Payable)', '2191', [
            'supplier_id' => $supplier->id,
        ])->id;

        $this->pivotLeafId = (int) $this->mintApLeaf('Supplier Two (Payable)', '2192', [
            'supplier_company_id' => $pivot->id,
        ])->id;

        // The pooled control leaf CoaSeeder already seeds: names no supplier, by construction.
        $this->pooledLeafId = (int) $this->accountByCode('2120')->id;
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    /** @param array<string, mixed> $extra */
    private function mintApLeaf(string $name, string $code, array $extra = []): Account
    {
        $apGroup = $this->accountByCode('2100');

        return Account::create($extra + [
            'company_id' => $this->companyId,
            'parent_id' => $apGroup->id,
            'root_id' => $apGroup->root_id ?? $apGroup->id,
            'name' => $name,
            'code' => $code,
            'level' => 3,
            'account_type' => null,
            'report_type' => $apGroup->report_type,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);
    }

    /**
     * A historical, BALANCED two-legged voucher whose AP leg carries NO party — the exact shape a
     * pre-CT-A7-2 payment voucher wrote. The bank contra leg exists so the document balances (the
     * suite's own per-transaction invariant checker asserts that in tearDown) and so the "no money
     * moves" fingerprint has something on the other side to be byte-equal about.
     *
     * Returns the AP leg's id — the row under repair.
     */
    private function historicalApLine(int $accountId, float $debit, float $credit): int
    {
        $amount = $debit + $credit;

        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'PV', 'amount' => $amount, 'description' => 'historical voucher',
            'reference_type' => 'Invoice', 'reference_number' => 'F2-'.substr(uniqid(), -8),
            'name' => 'historical voucher', 'transaction_date' => now()->subMonth(),
            'doc_type' => 'PV', 'doc_year' => (int) now()->subMonth()->format('Y'),
            'posting_status' => 'posted', 'posting_date' => now()->subMonth(),
            'total_debit' => $amount, 'total_credit' => $amount,
            'idempotency_key' => 'f2:'.uniqid(),
        ]);

        $apLegId = (int) JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $accountId, 'transaction_date' => now()->subMonth(),
            'description' => 'historical voucher',
            'debit' => $debit, 'credit' => $credit, 'name' => 'a party name, which must NEVER be matched on',
            'type' => 'payable', 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => $amount, 'voucher_number' => 'F2',
            'type_reference_id' => null,
        ])->id;

        // The contra leg, on the bank. Outside the AP subtree, so it is never in scope for the
        // repair — which is itself worth having in the fixture.
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $this->accountByCode('1201')->id, 'transaction_date' => now()->subMonth(),
            'description' => 'historical voucher (contra)',
            'debit' => $credit, 'credit' => $debit, 'name' => 'bank', 'type' => 'bank',
            'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => $amount, 'voucher_number' => 'F2',
            'type_reference_id' => null,
        ]);

        return $apLegId;
    }

    private function partyOf(int $journalEntryId): ?int
    {
        $value = DB::table('journal_entries')->where('id', $journalEntryId)->value('type_reference_id');

        return $value === null ? null : (int) $value;
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * Named `backfill`, not `run`: PHPUnit\Framework\TestCase::run() is FINAL — the same helper-name
     * collision CT-A6 hit with a method called post().
     */
    private function backfill(array $options = []): int
    {
        return Artisan::call('accounting:backfill-payable-party', $options + ['--company' => $this->companyId]);
    }

    private function moneyFingerprint(): string
    {
        return DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'account_id', 'debit', 'credit', 'transaction_id'])
            ->map(fn ($r) => implode('|', (array) $r))
            ->implode("\n");
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * DRY RUN IS THE DEFAULT. No flag at all must write nothing.
     */
    public function test_the_default_is_a_dry_run_and_writes_nothing(): void
    {
        $line = $this->historicalApLine($this->supplierLeafId, 60.0, 0.0);

        // `$this->artisan()->expectsOutputToContain()`, never `Artisan::call()` + `Artisan::output()`:
        // a console-output match against Artisan::output() reads EMPTY in this codebase — the same
        // property AccountingVerifyCommandTest, E2ReceiptVoucherCompanyTest and R41DanglingSweepTest
        // each record in their own comments. Measured here too: len=0.
        $this->artisan('accounting:backfill-payable-party', ['--company' => $this->companyId])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $this->assertNull($line ? $this->partyOf($line) : null, 'a bare invocation must not write');
        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')->where('subject_table', 'journal_entries')->count(),
            'and must record no before-image'
        );
    }

    /**
     * The repair itself, through both derivation paths.
     */
    public function test_apply_stamps_the_party_from_the_accounts_own_supplier_columns(): void
    {
        $direct = $this->historicalApLine($this->supplierLeafId, 60.0, 0.0);
        $viaPivot = $this->historicalApLine($this->pivotLeafId, 45.0, 0.0);

        $this->assertSame(0, $this->backfill(['--apply' => true]));

        $this->assertSame($this->supplierId, $this->partyOf($direct), 'accounts.supplier_id path');
        $this->assertSame($this->pivotSupplierId, $this->partyOf($viaPivot), 'supplier_companies pivot path');
    }

    /**
     * IT REFUSES TO GUESS. The pooled control leaf names no supplier; the line's own
     * `journal_entries.name` does, and must never be used. Nothing is stamped, and it is reported.
     */
    public function test_a_line_whose_account_names_no_supplier_is_refused_not_guessed(): void
    {
        $pooled = $this->historicalApLine($this->pooledLeafId, 0.0, 300.0);

        $this->artisan('accounting:backfill-payable-party', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('REFUSED account')
            ->assertExitCode(0);

        $this->assertNull(
            $this->partyOf($pooled),
            'F2: a line on an account that names no supplier must be left alone — a WRONG party is '
            .'worse than a null one, because it appears on another supplier\'s statement as a real payment'
        );
    }

    /**
     * A per-row before-image exists for every row written, and for none that were not.
     */
    public function test_a_before_image_is_recorded_for_every_row_written(): void
    {
        $direct = $this->historicalApLine($this->supplierLeafId, 60.0, 0.0);
        $this->historicalApLine($this->pooledLeafId, 0.0, 300.0);

        $this->backfill(['--apply' => true]);

        $images = DB::table('coa_linkage_changes')->where('subject_table', 'journal_entries')->get();

        $this->assertCount(1, $images, 'one image per row WRITTEN, none for a refused row');
        $this->assertSame($direct, (int) $images->first()->subject_id);
        $this->assertSame('type_reference_id', $images->first()->column_name);
        $this->assertNull($images->first()->before_value);
        $this->assertSame((string) $this->supplierId, $images->first()->after_value);
        $this->assertSame($this->companyId, (int) $images->first()->company_id);
    }

    /**
     * Idempotent: a second --apply stamps nothing further and records no second image.
     */
    public function test_a_second_apply_is_a_no_op(): void
    {
        $this->historicalApLine($this->supplierLeafId, 60.0, 0.0);

        $this->backfill(['--apply' => true]);
        $imagesAfterFirst = DB::table('coa_linkage_changes')->where('subject_table', 'journal_entries')->count();

        $this->assertSame(0, $this->backfill(['--apply' => true]));

        $this->assertSame(
            $imagesAfterFirst,
            DB::table('coa_linkage_changes')->where('subject_table', 'journal_entries')->count(),
            'a row that already carries a party is out of scope by definition'
        );
    }

    /**
     * The undo.
     */
    public function test_rollback_restores_every_row_the_run_stamped(): void
    {
        $direct = $this->historicalApLine($this->supplierLeafId, 60.0, 0.0);
        $viaPivot = $this->historicalApLine($this->pivotLeafId, 45.0, 0.0);

        $this->backfill(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')
            ->where('subject_table', 'journal_entries')->value('run_id');

        $this->assertNotSame('', $runId);
        $this->assertSame(0, Artisan::call('accounting:backfill-payable-party', ['--rollback' => $runId]));

        $this->assertNull($this->partyOf($direct));
        $this->assertNull($this->partyOf($viaPivot));

        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')
                ->where('subject_table', 'journal_entries')->whereNull('rolled_back_at')->count(),
            'every image must be marked rolled back'
        );
    }

    /**
     * A row that has MOVED since the run is left alone and the rollback says so — putting a
     * before-image back over someone else's later, deliberate value is not an undo.
     */
    public function test_rollback_refuses_a_row_that_has_moved_since_the_run(): void
    {
        $direct = $this->historicalApLine($this->supplierLeafId, 60.0, 0.0);

        $this->backfill(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')
            ->where('subject_table', 'journal_entries')->value('run_id');

        DB::table('journal_entries')->where('id', $direct)->update(['type_reference_id' => 987654]);

        $this->artisan('accounting:backfill-payable-party', ['--rollback' => $runId])
            ->expectsOutputToContain('left alone')
            ->assertExitCode(1);

        $this->assertSame(987654, $this->partyOf($direct), 'and must not overwrite the newer value');
    }

    /**
     * NO MONEY MOVES. Byte-equality of every money column, before and after.
     */
    public function test_the_repair_moves_no_money(): void
    {
        $this->historicalApLine($this->supplierLeafId, 60.0, 0.0);
        $this->historicalApLine($this->pooledLeafId, 0.0, 300.0);

        $before = $this->moneyFingerprint();

        $this->backfill(['--apply' => true]);

        $this->assertSame($before, $this->moneyFingerprint(), 'type_reference_id is attribution, not money');
    }

    /**
     * The shared before-image table has two owners. `accounting:coa-linkage --rollback` must REFUSE
     * a run id belonging to this command and name the owner, rather than skipping every row and
     * still reporting a complete undo.
     */
    public function test_coa_linkage_rollback_refuses_a_run_it_does_not_own(): void
    {
        $direct = $this->historicalApLine($this->supplierLeafId, 60.0, 0.0);

        $this->backfill(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')
            ->where('subject_table', 'journal_entries')->value('run_id');

        $this->artisan('accounting:coa-linkage', ['--rollback' => $runId])
            ->expectsOutputToContain('Run contains before-images for: journal_entries')
            ->expectsOutputToContain('accounting:backfill-payable-party --rollback')
            ->assertExitCode(1);

        $this->assertSame(
            $this->supplierId,
            $this->partyOf($direct),
            'and must have restored nothing'
        );
    }

    /**
     * `--limit` caps a staged rollout, so an operator can stamp a hundred rows, look at a screen,
     * and then continue.
     */
    public function test_limit_caps_the_rows_considered(): void
    {
        $first = $this->historicalApLine($this->supplierLeafId, 10.0, 0.0);
        $second = $this->historicalApLine($this->supplierLeafId, 20.0, 0.0);

        $this->backfill(['--apply' => true, '--limit' => 1]);

        $this->assertSame($this->supplierId, $this->partyOf($first));
        $this->assertNull($this->partyOf($second), '--limit must stop after the first row');
    }

    /**
     * A user who asks for both gets told, rather than getting one of them silently.
     */
    public function test_dry_run_and_apply_together_are_refused(): void
    {
        $this->assertSame(1, $this->backfill(['--apply' => true, '--dry-run' => true]));
    }
}
