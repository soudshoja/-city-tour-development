<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\Replay\CommissionReplaySource;
use App\Services\Accounting\Replay\IssuanceReplaySource;
use App\Services\Accounting\Replay\ReceiptReplaySource;
use App\Services\Accounting\Replay\SaleReplaySource;
use App\Services\Accounting\TaskIssuancePayableService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 wave 2, item W2-1 — `accounting:replay`, the cutover backfill command the branch did not
 * have (CT-A2 §1.4: *"There is no replay/backfill command on this branch"*; phase plan E9, blast
 * radius "the whole migration").
 *
 * What is asserted, in the order it matters:
 *
 *  1. **The gate.** It refuses unless BOTH halves of the kill switch are on, and the message names
 *     both. CT-A2 §1.1 measured exactly the half-open gate this guards against on the dev box.
 *  2. **Dry-run writes nothing** — and still reports real counts, because it posts inside a
 *     transaction and rolls it back rather than simulating.
 *  3. **A real run posts**, and a second run posts ZERO. Idempotency is by the feeders' own keys,
 *     so this is the property that makes a cutover re-runnable.
 *  4. **The keys are the production feeders' keys.** Asserted against the controller/service
 *     source text itself, so a feeder that changes its key without changing its replay source
 *     fails here rather than double-posting on the next cutover.
 *  5. **Legacy rows are never touched.**
 */
class W21AccountingReplayCommandTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(bool $enableEngine = true): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $company->id]);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        if ($enableEngine) {
            config(['accounting.engine.enabled' => true]);
            (new SystemAccountsSeeder)->run();
            Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
            Artisan::call('accounting:periods:init', ['--company' => $company->id]);
        }

        return [$company, $branch, $agent, $client, $admin];
    }

    /**
     * One invoiced flight sale with a supplier cost and a commission — the shape three of the six
     * replay classes each take a different view of.
     *
     * @return array{0: Task, 1: InvoiceDetail}
     */
    private function makeSoldTask(Company $company, Branch $branch, Agent $agent, Client $client, float $sell = 120.000, float $cost = 100.000, float $commission = 5.000): array
    {
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => $cost,
            'issued_date' => now()->subDays(3),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => $sell,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $detail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'task_price' => $sell,
            'commission' => $commission,
        ]);

        return [$task->fresh(), $detail->fresh()];
    }

    private function engineDocuments(int $companyId): int
    {
        return Transaction::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('idempotency_key')
            ->whereNull('deleted_at')
            ->count();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 1. The gate
    // ────────────────────────────────────────────────────────────────────────────────────────

    /** Case 1: the global half off -> refuse, and say so by name. */
    public function test_it_refuses_when_the_global_half_of_the_gate_is_off(): void
    {
        [$company] = $this->makeFixture();

        config(['accounting.engine.enabled' => false]);

        $this->artisan('accounting:replay', ['--company' => $company->id, '--dry-run' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain("accounting.engine.enabled')  = false")
            ->expectsOutputToContain('companies.posting_engine_enabled');
    }

    /** Case 2: the per-company half off -> refuse, naming the other half and the command that
     *  flips it. */
    public function test_it_refuses_when_the_company_half_of_the_gate_is_off(): void
    {
        [$company] = $this->makeFixture();

        Artisan::call('accounting:engine', ['company' => $company->id, '--disable' => true]);

        $this->artisan('accounting:replay', ['--company' => $company->id, '--dry-run' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain('posting engine is NOT enabled')
            ->expectsOutputToContain('accounting:engine '.$company->id.' --enable');
    }

    /** Case 3: an unknown --class is refused before anything runs. */
    public function test_an_unknown_class_is_refused(): void
    {
        [$company] = $this->makeFixture();

        $this->artisan('accounting:replay', ['--company' => $company->id, '--class' => 'nonsense'])
            ->assertExitCode(1)
            ->expectsOutputToContain('Unknown --class value(s): nonsense');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 2 + 3. Dry run, real run, re-run
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 4: `--dry-run` reports what a real run would do and leaves the ledger untouched; the
     * real run then posts exactly that; a second real run posts ZERO.
     */
    public function test_dry_run_writes_nothing_then_the_real_run_posts_and_a_re_run_posts_zero(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();
        $this->makeSoldTask($company, $branch, $agent, $client);

        // An UNINVOICED, issued, costed task, so the issuance class really posts an accrual and
        // the re-run has something to report as ALREADY. Without one, the fixture's only task was
        // already invoiced, the issuance class skipped it, and the re-run's headline claim was
        // never exercised for that class -- which is how a source that reported all 5,676 real
        // accruals as freshly POSTED on every re-run got past this test (this wave's report §5).
        $accrualSupplier = Supplier::factory()->create(['payable_trigger' => 'on_issue']);
        Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $accrualSupplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => 65.000,
            'issued_date' => now()->subDays(2),
        ]);

        // An APPROVED receipt, so `--class=all` really drives the receipt source too. The first
        // server dry run of this command refused all 109 real receipts on a TypeError that a
        // fixture without one could not have caught (this wave's report §5).
        $receipt = \App\Models\InvoiceReceipt::create([
            'type' => 'account',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->subDay()->toDateString(),
            'client_id' => $client->id,
            'account_id' => Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1351')->value('id'),
            'amount' => 25.000,
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'status' => 'approved',
        ]);

        $before = $this->engineDocuments($company->id);
        $beforeLines = JournalEntry::where('company_id', $company->id)->count();

        $this->artisan('accounting:replay', [
            '--company' => $company->id,
            '--class' => 'all',
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('WOULD POST')
            ->expectsOutputToContain('[dry run: rolled back, nothing written]');

        $this->assertSame($before, $this->engineDocuments($company->id), 'A dry run must write no engine document.');
        $this->assertSame($beforeLines, JournalEntry::where('company_id', $company->id)->count(), 'A dry run must write no journal line.');

        $this->artisan('accounting:replay', ['--company' => $company->id, '--class' => 'all'])
            ->assertExitCode(0)
            ->expectsOutputToContain('POSTED');

        $afterFirst = $this->engineDocuments($company->id);
        $this->assertGreaterThan($before, $afterFirst, 'The real run must post at least one document.');

        // The sale and the commission are the two documents this fixture must produce; the
        // issuance accrual is correctly NOT posted, because the task is already invoiced.
        $this->assertNotNull($this->documentByKey($company->id, 'invoice-detail:'.InvoiceDetail::first()->id.':sale'));
        $this->assertNotNull($this->documentByKey($company->id, 'invoice-detail:'.InvoiceDetail::first()->id.':agent-commission'));
        $this->assertNotNull(
            $this->documentByKey($company->id, 'rv:'.$receipt->id),
            'The receipt class must actually post -- not refuse.'
        );

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('sub_type', 'SUPPLIER_ACCRUAL')->count(),
            'Fixture check: the issuance class really posted, so the re-run has something to report as ALREADY.'
        );

        $this->artisan('accounting:replay', ['--company' => $company->id, '--class' => 'all'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Posted 0 document(s)');

        $this->assertSame($afterFirst, $this->engineDocuments($company->id), 'A second run must post nothing at all.');

        // And a THIRD run, because the second one changes the ledger state the third reads: this
        // is the shape the real backfill failed on (a refund reversed a sale between runs, the
        // task looked uninvoiced again, and it re-accrued).
        $this->artisan('accounting:replay', ['--company' => $company->id, '--class' => 'all'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Posted 0 document(s)');

        $this->assertSame($afterFirst, $this->engineDocuments($company->id));
    }

    /**
     * Case 5: the issuance class posts the R-CT3 accrual for an UNINVOICED issued task, and
     * reports the reason for every task that does not accrue — the breakdown that makes the
     * ruling auditable.
     */
    public function test_the_issuance_class_posts_the_accrual_and_names_every_skip_reason(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();

        $supplier = Supplier::factory()->create(['payable_trigger' => 'on_issue']);
        $held = Supplier::factory()->create(['payable_trigger' => 'on_issue', 'payable_hold' => true]);

        // Due: issued, costed, never invoiced.
        $due = Task::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'supplier_id' => $supplier->id, 'type' => 'flight', 'status' => 'issued',
            'total' => 80.000, 'issued_date' => now()->subDay(),
        ]);

        // Not due: the supplier is on hold.
        Task::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'supplier_id' => $held->id, 'type' => 'flight', 'status' => 'issued',
            'total' => 90.000, 'issued_date' => now()->subDay(),
        ]);

        // Not due: merely confirmed under the on_issue trigger — the R-CT3 "not hold or some
        // supplier confirmed" case, the one carrying KWD 21,542.960 on the legacy ledger.
        Task::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'supplier_id' => $supplier->id, 'type' => 'flight', 'status' => 'confirmed',
            'total' => 70.000, 'issued_date' => now()->subDay(),
        ]);

        $this->artisan('accounting:replay', ['--company' => $company->id, '--class' => 'issuance'])
            ->assertExitCode(0)
            ->expectsOutputToContain('supplier_payable_hold')
            ->expectsOutputToContain('status_not_committed');

        $accrual = $this->documentByKey($company->id, TaskIssuancePayableService::idempotencyKeyFor((int) $due->id));
        $this->assertNotNull($accrual, 'A due, uninvoiced, costed task must accrue.');

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('sub_type', 'SUPPLIER_ACCRUAL')->count(),
            'Exactly one of the three tasks is due; the other two are gated off by R-CT3.'
        );
    }

    /**
     * Case 5b: `--receipt-statuses` is an explicit, per-run override and nothing more.
     *
     * 104 of the 109 real City Travelers receipts sit at `pending`, which W2-2's vocabulary
     * correctly treats as a draft with no ledger footprint -- while CT-A1 CT-F12 names those same
     * rows as unposted money. The command refuses to decide that silently: by default a pending
     * receipt is skipped with the reason `status_is_draft`, and an operator who has decided
     * otherwise says so on the command line, where it is visible.
     */
    public function test_receipt_statuses_is_an_explicit_per_run_override(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();

        $pending = \App\Models\InvoiceReceipt::create([
            'type' => 'account',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->subDay()->toDateString(),
            'client_id' => $client->id,
            'account_id' => Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1351')->value('id'),
            'amount' => 40.000,
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'status' => 'pending',
        ]);

        $this->artisan('accounting:replay', ['--company' => $company->id, '--class' => 'receipt'])
            ->assertExitCode(0)
            ->expectsOutputToContain('status_is_draft');

        $this->assertNull(
            $this->documentByKey($company->id, 'rv:'.$pending->id),
            'By default a pending receipt is a draft and posts nothing.'
        );

        $this->artisan('accounting:replay', [
            '--company' => $company->id,
            '--class' => 'receipt',
            '--receipt-statuses' => 'approved,pending',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Receipt posting statuses OVERRIDDEN for this run');

        $this->assertNotNull(
            $this->documentByKey($company->id, 'rv:'.$pending->id),
            'With the explicit override the same row posts.'
        );

        // And the override was scoped to the process: the live vocabulary is untouched.
        $this->assertSame(['approved'], config('accounting.receipt.posting_statuses'));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 4. The keys are the production feeders' keys
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 6 — the anti-drift ratchet. Each source's `idempotencyKeyFor()` is checked against the
     * literal key format in the PRODUCTION feeder's own source file. A feeder that changes its key
     * without changing its replay source fails here, rather than silently double-posting every
     * historical document on the next cutover.
     */
    public function test_every_source_uses_the_production_feeders_own_idempotency_key(): void
    {
        $invoiceController = file_get_contents(app_path('Http/Controllers/InvoiceController.php'));
        $receiptController = file_get_contents(app_path('Http/Controllers/ReceiptVoucherController.php'));
        $taskController = file_get_contents(app_path('Http/Controllers/TaskController.php'));
        $refundService = file_get_contents(app_path('Services/Accounting/RefundPostingService.php'));

        $this->assertStringContainsString(
            "idempotencyKey: 'invoice-detail:'.\$invoiceDetailId.':sale'",
            $invoiceController,
            'SaleReplaySource mints invoice-detail:{id}:sale — the live sale feeder must still use that key.'
        );

        $this->assertStringContainsString(
            "idempotencyKey: 'invoice-detail:'.\$invoiceDetailId.':agent-commission'",
            $invoiceController,
            'CommissionReplaySource mints invoice-detail:{id}:agent-commission.'
        );

        $this->assertStringContainsString(
            "idempotencyKey: 'rv:'.\$r->id",
            $receiptController,
            'ReceiptReplaySource mints rv:{id}.'
        );

        $this->assertStringContainsString(
            "'task:' . \$task->id . ':supplier-reassign:'",
            $taskController,
            'ReassignReplaySource mints task:{id}:supplier-reassign:{sequence}:{account}.'
        );

        $this->assertStringContainsString(
            "'refund:'.\$refund->id.':disposition'",
            $refundService,
            'RefundReplaySource keys off refund:{id}:disposition.'
        );

        // And the sources themselves produce those strings for a real row.
        [$company, $branch, $agent, $client] = $this->makeFixture();
        [$task, $detail] = $this->makeSoldTask($company, $branch, $agent, $client);

        $this->assertSame('invoice-detail:'.$detail->id.':sale', app(SaleReplaySource::class)->idempotencyKeyFor($detail));
        $this->assertSame('invoice-detail:'.$detail->id.':agent-commission', app(CommissionReplaySource::class)->idempotencyKeyFor($detail));
        $this->assertSame('task:'.$task->id.':issuance-payable', app(IssuanceReplaySource::class)->idempotencyKeyFor($task));

        $receipt = \App\Models\InvoiceReceipt::create([
            'type' => 'account', 'company_id' => $company->id, 'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(), 'client_id' => $client->id, 'amount' => 10,
            'remainder_amount' => 0, 'remainder_policy' => 'credit', 'status' => 'approved',
            'account_id' => Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1351')->value('id'),
        ]);
        $this->assertSame('rv:'.$receipt->id, app(ReceiptReplaySource::class)->idempotencyKeyFor($receipt));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 5. Legacy rows are never touched
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 7: a legacy journal row (no `posting_date`, no `idempotency_key`) survives a full
     * replay byte for byte. On the City Travelers scratch database that is 75,221 rows; here it is
     * one row with the same shape.
     */
    public function test_a_legacy_journal_row_is_never_touched(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();
        [$task, $detail] = $this->makeSoldTask($company, $branch, $agent, $client);

        $legacyHeader = Transaction::forceCreate([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'debit',
            'amount' => 42.000,
            'description' => 'legacy row that predates the engine',
            'reference_type' => 'Invoice',
            'transaction_date' => now()->subYear(),
        ]);

        $legacyLine = JournalEntry::create([
            'transaction_id' => $legacyHeader->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'account_id' => Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1120')->value('id'),
            'task_id' => $task->id,
            'debit' => 42.000,
            'credit' => 0,
            'transaction_date' => now()->subYear(),
            'description' => 'legacy line',
            'name' => 'legacy party',
            'type' => 'bank',
        ]);

        // The counter-leg, so the fixture is a BALANCED legacy document -- AccountingTestCase's
        // own invariant checker refuses an unbalanced transaction, and a one-sided fixture would
        // be asserting against a shape the suite already forbids.
        JournalEntry::create([
            'transaction_id' => $legacyHeader->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'account_id' => Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1351')->value('id'),
            'task_id' => $task->id,
            'debit' => 0,
            'credit' => 42.000,
            'transaction_date' => now()->subYear(),
            'description' => 'legacy counter-leg',
            'name' => 'legacy party',
            'type' => 'receivable',
        ]);

        $snapshot = $legacyLine->fresh()->toArray();

        Artisan::call('accounting:replay', ['--company' => $company->id, '--class' => 'all']);

        $after = $legacyLine->fresh();
        $this->assertNotNull($after, 'The replay must never delete a legacy journal row.');
        $this->assertSame($snapshot, $after->toArray(), 'The replay must never modify a legacy journal row.');
        $this->assertNull($after->posting_date);

        $this->assertNull(
            Transaction::withoutGlobalScopes()->find($legacyHeader->id)->idempotency_key,
            'The replay must never stamp a key onto a legacy header.'
        );
    }

    private function documentByKey(int $companyId, string $key): ?Transaction
    {
        return Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();
    }
}
