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
use App\Models\InvoiceReceipt;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\Replay\CommissionReplaySource;
use App\Services\Accounting\Replay\IssuanceReplaySource;
use App\Services\Accounting\Replay\ReassignReplaySource;
use App\Services\Accounting\Replay\ReceiptReplaySource;
use App\Services\Accounting\Replay\RefundReplaySource;
use App\Services\Accounting\Replay\ReplaySource;
use App\Services\Accounting\Replay\SaleReplaySource;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 R2-4 — VERIFY-CT-A3-STACK-R1 §3.2 **D8, BLOCKER AT THE CUTOVER**:
 * *"`ReceiptReplaySource::rows()` takes `$companyId` and never uses it."*
 *
 * ── The defect, restated from the verify report ─────────────────────────────────────────────────
 * ```php
 * public function rows(int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit): iterable
 * {
 *     $query = InvoiceReceipt::query()
 *         ->when($from, …)->when($to, …)->orderByRaw(…)->orderBy('id');
 * ```
 *
 * The docblock stated this was deliberate — `invoice_receipts.company_id` is NULL on every legacy
 * row (CT-F35), so the whole population is walked and *"a row belonging to another company [is]
 * refused by the engine gate exactly as it would be live"*. The reasoning is sound and the outcome
 * is not: **the engine gate is a per-company FEATURE FLAG, not a tenant boundary.**
 * `--company=1 --class=receipt` posts through `PostingService::post()` directly, bypassing the
 * command's own `assertEngineGate($companyId)`, so the moment `accounting:engine 2 --enable` runs
 * — the documented cutover step the command itself echoes — a company-1 replay writes real
 * balanced RV documents into **company 2's** ledger, counted in company 1's POSTED tally. A later
 * legitimate `--company=2` run then reports them `already_posted`, so the double-run check looks
 * clean.
 *
 * The report checked the other five sources and found them safe; this file turns that reading into
 * a RATCHET, over all six, on a two-company fixture — because "I read it and it was fine" does not
 * survive the next person adding a seventh source.
 *
 * ── Also fixed here (same method, D8's own "Secondary") ─────────────────────────────────────────
 * The `--from`/`--to` window was `whereDate('doc_date', …)` while the ordering was
 * `COALESCE(doc_date, created_at)`, so a row with a NULL `doc_date` was silently dropped from any
 * windowed run with no report line. `RefundReplaySource` had the identical mismatch. Both windows
 * now use the same expression the ordering does.
 */
class R24ReplayCompanyScopeTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /**
     * One fully-populated company: chart, branch, agent, client, and one of every source row.
     *
     * @return array{company: Company, branch: Branch, agent: Agent, client: Client, task: Task, detail: InvoiceDetail, refund: Refund, receipt: InvoiceReceipt}
     */
    private function makeCompanyWithEverySourceRow(bool $enableEngine): array
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
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => 100.000,
            'issued_date' => now()->subDays(3),
            // ReassignReplaySource only walks tasks that nominate a payee account -- without one
            // there is nothing to reclassify, so the ratchet would pass vacuously for that class.
            'payment_method_account_id' => Account::withoutGlobalScopes()
                ->where('company_id', $company->id)->where('code', '1201')->value('id'),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 120.000,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $detail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'task_price' => 120.000,
            'commission' => 5.000,
        ]);

        $refund = Refund::create([
            'refund_number' => 'REF-R24-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now()->subDay(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 0,
        ]);

        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 120.000,
            'original_task_cost' => 100.000,
            'original_task_profit' => 20.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'supplier_refund_amount' => null,
            'new_task_profit' => 0,
            'total_refund_to_client' => 120.000,
        ]);

        // The legacy receipt shape D8 is about: NO company_id of its own, resolvable only through
        // the invoice -> agent -> branch chain.
        $receipt = InvoiceReceipt::create([
            'type' => 'invoice',
            'company_id' => null,
            'branch_id' => $branch->id,
            'doc_date' => now()->subDay()->toDateString(),
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 50.000,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 50.000]],
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'bank_account_id' => Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->value('id'),
            'status' => InvoiceReceipt::STATUS_APPROVED,
        ]);

        $this->trackCompanyForInvariants($company->id);

        if ($enableEngine) {
            config(['accounting.engine.enabled' => true]);
            (new SystemAccountsSeeder)->run();
            Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
            Artisan::call('accounting:periods:init', ['--company' => $company->id]);
        }

        return compact('company', 'branch', 'agent', 'client', 'task', 'detail', 'refund', 'receipt');
    }

    /** @return array<string, ReplaySource> */
    private function allSources(): array
    {
        return [
            'sale' => app(SaleReplaySource::class),
            'commission' => app(CommissionReplaySource::class),
            'issuance' => app(IssuanceReplaySource::class),
            'receipt' => app(ReceiptReplaySource::class),
            'refund' => app(RefundReplaySource::class),
            'reassign' => app(ReassignReplaySource::class),
        ];
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // THE RATCHET
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * THE DEFECT, generalised. On a two-company fixture, EVERY source's `rows()` for company A
     * must yield nothing belonging to company B.
     *
     * Before the fix `receipt` yielded company B's voucher — and on a cutover where B's engine
     * flag is on (the documented next step), replaying A would have posted a real balanced RV
     * document into B's ledger and counted it in A's tally.
     */
    public function test_every_replay_source_yields_only_its_own_companys_rows(): void
    {
        $a = $this->makeCompanyWithEverySourceRow(true);
        $b = $this->makeCompanyWithEverySourceRow(true);

        $foreign = [
            'sale' => [(int) $b['detail']->id],
            'commission' => [(int) $b['detail']->id],
            'issuance' => [(int) $b['task']->id],
            'receipt' => [(int) $b['receipt']->id],
            'refund' => [(int) $b['refund']->id],
            'reassign' => [(int) $b['task']->id],
        ];

        $own = [
            'sale' => (int) $a['detail']->id,
            'commission' => (int) $a['detail']->id,
            'issuance' => (int) $a['task']->id,
            'receipt' => (int) $a['receipt']->id,
            'refund' => (int) $a['refund']->id,
            'reassign' => (int) $a['task']->id,
        ];

        foreach ($this->allSources() as $name => $source) {
            $ids = [];

            foreach ($source->rows((int) $a['company']->id, null, null, null) as $row) {
                $ids[] = (int) $row->getKey();
            }

            $leaked = array_values(array_intersect($ids, $foreign[$name]));

            $this->assertSame(
                [],
                $leaked,
                sprintf(
                    '%sReplaySource::rows(company #%d) yielded company #%d row id(s) %s. The engine gate is a '
                    .'FEATURE FLAG, not a tenant boundary — a source must scope itself.',
                    ucfirst($name),
                    $a['company']->id,
                    $b['company']->id,
                    implode(',', $leaked)
                )
            );

            // And the scoping must not be achieved by yielding nothing at all.
            $this->assertContains(
                $own[$name],
                $ids,
                sprintf('%sReplaySource::rows() must still yield its OWN company\'s row.', ucfirst($name))
            );
        }
    }

    /**
     * The structural half of D8's fix. `ReplaySource::replay(Model $row)` carries no `$companyId`
     * — the report names that as the reason the assertion was missing — so the receipt source
     * remembers the company `rows()` was called for and REFUSES a row that resolves elsewhere,
     * rather than trusting the caller to have filtered.
     */
    public function test_the_receipt_source_refuses_a_row_belonging_to_another_company(): void
    {
        $a = $this->makeCompanyWithEverySourceRow(true);
        $b = $this->makeCompanyWithEverySourceRow(true);

        /** @var ReceiptReplaySource $source */
        $source = app(ReceiptReplaySource::class);

        // Scope the source to company A, the way the command does.
        iterator_to_array($source->rows((int) $a['company']->id, null, null, null));

        $outcome = $source->replay($b['receipt']->fresh());

        $this->assertTrue($outcome->isRefused(), 'A foreign row handed to a company-scoped source must be REFUSED.');
        $this->assertStringContainsString('compan', strtolower($outcome->reason));

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'rv:'.$b['receipt']->id)->count(),
            'The refusal must post nothing at all into the other company\'s ledger.'
        );
    }

    /**
     * A full `--class=all` run for company A must leave company B's ledger byte-for-byte untouched
     * — the end-to-end statement of the same property, through the command an operator actually
     * types at the cutover.
     */
    public function test_a_full_replay_for_one_company_writes_nothing_into_the_other(): void
    {
        $a = $this->makeCompanyWithEverySourceRow(true);
        $b = $this->makeCompanyWithEverySourceRow(true);

        $before = Transaction::withoutGlobalScopes()->where('company_id', $b['company']->id)->count();

        Artisan::call('accounting:replay', ['--company' => (string) $a['company']->id, '--class' => 'all']);

        $this->assertGreaterThan(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $a['company']->id)->whereNotNull('idempotency_key')->count(),
            'precondition: the replay actually posted something for company A'
        );

        $this->assertSame(
            $before,
            Transaction::withoutGlobalScopes()->where('company_id', $b['company']->id)->count(),
            'A replay of company A must not write one row into company B — its engine flag is ON, '
                .'which is exactly the cutover state D8 is dangerous in.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // The command's own guard, and the window
    // ────────────────────────────────────────────────────────────────────────────────────────

    /** `--company` is not optional. A replay with no company named must refuse, not default. */
    public function test_the_replay_command_refuses_when_company_is_absent(): void
    {
        // The EXIT CODE is what a deploy runbook gates on, so that is what is pinned. (The
        // command's own `--company is required` line goes to STDERR, which Artisan::output() does
        // not capture in the test harness -- asserting on it would pin the harness, not the
        // command.)
        $this->assertSame(1, Artisan::call('accounting:replay', ['--class' => 'receipt']));
        $this->assertSame(1, Artisan::call('accounting:replay', ['--company' => '0', '--class' => 'receipt']));
        $this->assertSame(1, Artisan::call('accounting:replay', ['--company' => '', '--class' => 'receipt']));

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->whereNotNull('idempotency_key')->count(),
            'A refused run must post nothing.'
        );
    }

    /**
     * D8's "Secondary": the window and the ordering disagreed. A receipt with a NULL `doc_date` was
     * ORDERED by `COALESCE(doc_date, created_at)` but WINDOWED by `doc_date` alone, so any
     * `--from`/`--to` run dropped it silently, with no report line saying so.
     */
    public function test_a_row_with_a_null_doc_date_is_inside_a_window_that_covers_its_created_at(): void
    {
        $a = $this->makeCompanyWithEverySourceRow(true);

        InvoiceReceipt::withoutGlobalScopes()->whereKey($a['receipt']->id)->update(['doc_date' => null]);

        /** @var ReceiptReplaySource $source */
        $source = app(ReceiptReplaySource::class);

        $ids = [];
        foreach ($source->rows((int) $a['company']->id, now()->subMonth(), now()->addDay(), null) as $row) {
            $ids[] = (int) $row->getKey();
        }

        $this->assertContains(
            (int) $a['receipt']->id,
            $ids,
            'A NULL doc_date must fall back to created_at for the WINDOW, exactly as it already '
                .'does for the ORDER — otherwise a windowed backfill drops rows and says nothing.'
        );
    }
}
