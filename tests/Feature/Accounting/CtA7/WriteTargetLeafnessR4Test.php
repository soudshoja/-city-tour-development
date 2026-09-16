<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Http\Controllers\TaskController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\TaskPayablePositionResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7 ROUND 4, finding **R4-1** — round 3 turned a clean 422 into a 500.
 *
 * R3-1 changed `apSubtreeIds()` from *descendants only* to `subtreeIds()` = **groups plus
 * descendants**. That is right for READING: on a chart that never split its control, a payable can
 * sit directly on the group, and a payables screen that skipped it would hide money. But two
 * consumers use the same array to decide what may be **written to**, and a group is never a legal
 * write target — `PostingService` (line ~802) refuses any line whose account has children,
 * regardless of how that account was chosen.
 *
 *   (a) The F4 nomination guard was `in_array($id, $apSubtreeIds)`, so from R3-1 it ADMITTED the AP
 *       group itself and handed it straight to the posting layer, which threw
 *       `NonLeafAccountException` — an uncaught 500 where round 2 had returned a 422. The guard was
 *       passing through exactly what the next layer would refuse.
 *
 *   (b) `TaskPayablePositionResolver::openPositions()` reads the same array and
 *       `SupplierReassignDraftBuilder` builds `LineDraft(accountId: $position['account_id'],
 *       side: 'debit')` from each row. Company 2's money-bearing group `463` IS a group, with 210
 *       descendants — so "Update For Whom to Pay" would build a debit against it and die the same
 *       way. Before R3-1 it silently skipped.
 *
 * ── The fix, and why it is not "revert to descendants only" ─────────────────────────────────────
 * Reverting would re-open the read hole R3-1 closed. The groups stay in the array; LEAF-NESS is
 * required at the two points where the array decides a WRITE. Leaf-ness is derived from "has
 * children", the same test `AccountResolver::isLeaf()` and `PostingService` use — never from
 * `accounts.is_group`, which CT-A1 §1.4 measured wrong on 613 accounts.
 */
class WriteTargetLeafnessR4Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    private const SELL = 150.0;

    private const COST = 100.0;

    private int $companyId;

    private int $taskId;

    private int $apGroupId;

    private int $payeeLeafId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->companyId, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id,
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $this->companyId]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => self::SELL,
            'issued_date' => now()->subDays(5),
        ]);
        $this->taskId = (int) $task->id;

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()->subDays(4),
        ]);
        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->companyId]);

        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: self::SELL,
            costAmount: self::COST,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: (int) $client->id, clientName: $client->full_name,
            supplierId: (int) $supplier->id, supplierName: $supplier->name,
            agentId: (int) $agent->id, agentName: $agent->name,
            invoiceId: (int) $invoice->id, invoiceDetailId: (int) $detail->id, taskId: $this->taskId,
        ));

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $this->companyId, branchId: (int) $agent->branch_id, docType: 'INV', subType: 'SALE',
            docDate: now()->subDays(4), narration: 'R4-1 fixture sale', lines: $lines,
            idempotencyKey: 'ct-a7-r4:sale:'.$detail->id, invoiceId: (int) $invoice->id,
        ));

        $this->apGroupId = (int) $this->accountByCode('2100')->id;
        $this->payeeLeafId = (int) $this->mintLeafUnderApGroup();
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    private function mintLeafUnderApGroup(): int
    {
        $apGroup = Account::withoutGlobalScopes()->findOrFail($this->apGroupId);

        return (int) Account::create([
            'company_id' => $this->companyId,
            'parent_id' => $apGroup->id,
            'root_id' => $apGroup->root_id ?? $apGroup->id,
            'name' => 'Corporate Card (Payee)',
            'code' => '2198',
            'level' => 3,
            'account_type' => null,
            'report_type' => $apGroup->report_type,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ])->id;
    }

    private function nominate(int $accountId): \Illuminate\Http\JsonResponse
    {
        return app(TaskController::class)->updateJournalPaymentMethod(
            Task::withoutGlobalScopes()->findOrFail($this->taskId),
            $accountId
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The premise: the AP GROUP is in the array the guard reads, and it really does have children.
     * That is deliberate and must stay — it is what lets the payables screens see a payable posted
     * directly onto an unsplit control.
     */
    public function test_the_ap_group_is_in_the_subtree_array_by_design(): void
    {
        $this->assertContains(
            $this->apGroupId,
            (new TaskPayablePositionResolver)->apSubtreeIds($this->companyId),
            'R3-1 put the groups in deliberately, for READING'
        );

        $this->assertTrue(
            Account::withoutGlobalScopes()->where('parent_id', $this->apGroupId)->exists(),
            'and the group really is a group — which is why it is not a legal WRITE target'
        );
    }

    /**
     * R4-1(a). Nominating the GROUP must be a clean 422 refusal, not a 500 from the posting layer.
     */
    public function test_nominating_the_ap_group_itself_is_refused_with_422_not_a_500(): void
    {
        $response = $this->nominate($this->apGroupId);

        $this->assertSame(
            422,
            $response->getStatusCode(),
            'R4-1: the guard must refuse a NON-LEAF destination itself. Round 3 admitted it and let '
            .'PostingService throw NonLeafAccountException — an uncaught 500 where round 2 had a 422.'
        );

        $this->assertSame(
            0,
            (int) DB::table('transactions')
                ->where('idempotency_key', 'like', 'task:'.$this->taskId.':supplier-reassign:%')
                ->whereNull('deleted_at')->count(),
            'and nothing may have posted'
        );
    }

    /** The happy path is untouched: a real leaf is still nominatable. */
    public function test_a_leaf_is_still_accepted(): void
    {
        $response = $this->nominate($this->payeeLeafId);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertNotNull(json_decode($response->getContent(), true)['data']['transaction_id'] ?? null);
    }

    /**
     * R4-1(b). `openPositions()` feeds `SupplierReassignDraftBuilder`, which builds a DEBIT line
     * from each row it returns. A position on a GROUP would therefore become a line the posting
     * layer refuses — so it must be skipped, and the skip must be logged rather than silent.
     *
     * The fixture posts a payable directly onto the AP group, which is exactly company 2's shape
     * (its money-bearing `463` is a group with 210 descendants).
     */
    public function test_open_positions_skips_a_position_that_sits_on_a_group(): void
    {
        $this->postPayableOntoTheGroup(70.0);

        $logged = [];
        Log::listen(function ($message) use (&$logged) {
            $logged[] = $message->message;
        });

        $positions = (new TaskPayablePositionResolver)
            ->openPositions($this->taskId, $this->companyId, null, 0.0005);

        $this->assertNotContains(
            $this->apGroupId,
            array_column($positions, 'account_id'),
            'R4-1: a position on a GROUP must be skipped — SupplierReassignDraftBuilder turns every '
            .'row this returns into a debit LineDraft, and PostingService refuses a non-leaf account'
        );

        $this->assertNotEmpty(
            array_filter($logged, fn ($m) => str_contains((string) $m, 'non-leaf')),
            'and the skip must be LOGGED, not silent — money on a control group is a chart problem '
            .'an operator has to hear about'
        );
    }

    /**
     * The leaf positions are still returned — the skip must not throw the baby out.
     */
    public function test_open_positions_still_returns_leaf_positions(): void
    {
        $positions = (new TaskPayablePositionResolver)
            ->openPositions($this->taskId, $this->companyId, null, 0.0005);

        $this->assertNotEmpty($positions, 'the sale left a payable on the SERVICE_PAYABLE/flight leaf');

        foreach ($positions as $position) {
            $this->assertFalse(
                Account::withoutGlobalScopes()->where('parent_id', $position['account_id'])->exists(),
                'every position returned must be a LEAF'
            );
        }
    }

    /**
     * A BALANCED document whose payable leg lands directly on the control GROUP — company 2's real
     * shape, where the money-bearing `463` is itself a group with 210 descendants. Balanced because
     * this suite's own per-transaction invariant checker asserts every document foots in tearDown;
     * a bare one-legged insert would fail on that instead of on the behaviour under test.
     */
    private function postPayableOntoTheGroup(float $amount): void
    {
        $branchId = (int) DB::table('branches')->where('company_id', $this->companyId)->value('id');

        $transactionId = (int) DB::table('transactions')->insertGetId([
            'company_id' => $this->companyId,
            'branch_id' => $branchId,
            'entity_id' => $this->companyId,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => $amount,
            'description' => 'R4-1: a payable sitting directly on the control GROUP',
            'reference_type' => 'Invoice',
            'reference_number' => 'R41-'.substr(uniqid(), -8),
            'name' => 'R4-1 group payable',
            'transaction_date' => now()->subDays(3),
            'doc_type' => 'JV',
            'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted',
            'posting_date' => now()->subDays(3),
            'total_debit' => $amount,
            'total_credit' => $amount,
            'idempotency_key' => 'r41:group:'.uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            [$this->apGroupId, 0.0, $amount, 'payable'],
            [(int) $this->accountByCode('5111')->id, $amount, 0.0, 'expense'],
        ] as [$accountId, $debit, $credit, $type]) {
            DB::table('journal_entries')->insert([
                'transaction_id' => $transactionId,
                'company_id' => $this->companyId,
                'branch_id' => $branchId,
                'account_id' => $accountId,
                'task_id' => $this->taskId,
                'transaction_date' => now()->subDays(3),
                'posting_date' => now()->subDays(3),
                'description' => 'R4-1: a payable sitting directly on the control GROUP',
                'debit' => $debit, 'credit' => $credit,
                'name' => 'counterparty', 'type' => $type,
                'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
                'voucher_number' => 'R41',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
