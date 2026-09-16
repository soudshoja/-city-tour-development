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
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LedgerSource;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7 ROUND 2, finding **F4** — the R-CT8 payee nomination destination was UNCONSTRAINED.
 *
 * `TaskController::updateJournalPaymentMethod()` resolved it with a bare
 * `Account::find($payment_method_account_id)`, which had **no AP-subtree check at all**, and
 * `TaskPayablePositionResolver::nominatedPayeeAccountIdsForCompany()` then plucked every
 * `account_id` on the resulting document unconditionally into `payableAccountIds()`.
 *
 * Round 1's docblock argued the union "can never hide money". That is true and it is not the whole
 * claim: nominate a bank, an expense, a suspense account — or **another company's** account — and
 * that account joins `payableAccountIds()` and INFLATES the payables total on the unpaid-AP screen,
 * the creditors screen and the supplier statement. Cross-tenant is the worse half: a nomination
 * could pull a different company's balance onto this company's payment-run screen.
 *
 * The destination is now required to be (a) an account of the TASK'S OWN company and (b) inside
 * that company's `Accounts Payable` subtree. Both are refusals, not warnings — a nomination that
 * cannot name a payable position is not a payable position, and this is also what lets
 * {@see \App\Services\Accounting\LedgerSource::payableAccountIds()} claim completeness by
 * construction (finding F1).
 */
class NominationDestinationF4Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    private const SELL = 150.0;

    private const COST = 100.0;

    private int $companyId;

    private int $taskId;

    private int $legitimatePayeeLeafId;

    private int $bankAccountId;

    private int $foreignApLeafId;

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
            docDate: now()->subDays(4), narration: 'F4 fixture sale', lines: $lines,
            idempotencyKey: 'ct-a7-f4:sale:'.$detail->id, invoiceId: (int) $invoice->id,
        ));

        $this->legitimatePayeeLeafId = (int) $this->mintApLeaf($this->companyId, 'Corporate Card (Payee)', '2198')->id;
        $this->bankAccountId = (int) $this->accountByCode($this->companyId, '1201')->id;

        // A SECOND company, with its own chart, so "another company's AP leaf" is a real account of
        // a real other tenant rather than a synthetic row.
        $foreign = Company::factory()->create();
        CoaSeeder::run((int) $foreign->id);
        $this->trackCompanyForInvariants((int) $foreign->id);
        $this->foreignApLeafId = (int) $this->mintApLeaf((int) $foreign->id, 'Foreign Payee Leaf', '2198')->id;
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    private function mintApLeaf(int $companyId, string $name, string $code): Account
    {
        $apGroup = $this->accountByCode($companyId, '2100');

        return Account::create([
            'company_id' => $companyId,
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

    private function nominate(int $accountId): \Illuminate\Http\JsonResponse
    {
        return app(TaskController::class)->updateJournalPaymentMethod(
            Task::withoutGlobalScopes()->findOrFail($this->taskId),
            $accountId
        );
    }

    private function reassignmentDocumentCount(): int
    {
        return (int) DB::table('transactions')
            ->where('idempotency_key', 'like', 'task:'.$this->taskId.':supplier-reassign:%')
            ->whereNull('deleted_at')
            ->count();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The happy path must not regress: a real AP leaf of this company is still nominatable, and
     * still moves the payable.
     */
    public function test_an_ap_leaf_of_this_company_is_still_accepted(): void
    {
        $response = $this->nominate($this->legitimatePayeeLeafId);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertNotNull(
            json_decode($response->getContent(), true)['data']['transaction_id'] ?? null,
            'the nomination must still post a reassignment document'
        );
        $this->assertSame(1, $this->reassignmentDocumentCount());

        $this->assertContains(
            $this->legitimatePayeeLeafId,
            app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class)),
            'and the payable must be visible at its new position (R-CT9)'
        );
    }

    /**
     * ATTACK — nominate a BANK account. It is an asset, not a payable position; before F4 it was
     * accepted and then joined `payableAccountIds()`, inflating every AP screen by the bank's own
     * balance.
     */
    public function test_a_bank_account_is_refused_as_a_nomination_destination(): void
    {
        $response = $this->nominate($this->bankAccountId);

        $this->assertSame(
            422,
            $response->getStatusCode(),
            'a non-payable destination must be REFUSED, not accepted and then reported as a payable'
        );
        $this->assertSame(0, $this->reassignmentDocumentCount(), 'and nothing may have posted');

        $this->assertNotContains(
            $this->bankAccountId,
            app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class)),
            'F4: a bank account must never enter the payables set'
        );
    }

    /**
     * ATTACK — nominate ANOTHER COMPANY'S AP leaf.
     *
     * CT-A7 ROUND 3 (R3-2) corrects what an earlier version of this docblock said. `Account` DOES
     * carry a global company scope, through `use App\Traits\BelongsToCompany`
     * (`app/Models/Account.php:11`). It binds only in some states, though (`app/Helper/helper.php`
     * lines 6-43): an ADMIN always binds because `getCompanyId()` falls back to
     * `session('company_id', 1)`; a COMPANY user binds unless linked to no company; BRANCH, AGENT,
     * ACCOUNTANT without a branch/company and any unknown role yield null; and an unauthenticated
     * context — a console command, a queued job, a seeder — does not bind at all. This test drives
     * the controller DIRECTLY, with no authenticated user, which is one of the states in which the
     * scope is a no-op. Before F4 the nomination resolved happily there and the cross-tenant
     * account was caught only much later, as an uncaught `CrossTenantAccountException` from
     * `PostingService::post()` — a 500, not a refusal, and only on the engine path.
     */
    public function test_another_companys_account_is_refused(): void
    {
        $response = $this->nominate($this->foreignApLeafId);

        $this->assertSame(422, $response->getStatusCode(), 'cross-tenant nomination must be refused');
        $this->assertSame(0, $this->reassignmentDocumentCount());

        $this->assertNotContains(
            $this->foreignApLeafId,
            app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class)),
            'F4: another company\'s account must never enter this company\'s payables set'
        );
    }

    /**
     * An account that does not exist at all still 404s, unchanged — the refusal added by F4 is a
     * 422 about WHAT the account is, and must not swallow the pre-existing "no such account".
     */
    public function test_a_missing_account_still_reports_not_found(): void
    {
        $this->assertSame(404, $this->nominate(2147483600)->getStatusCode());
    }
}
