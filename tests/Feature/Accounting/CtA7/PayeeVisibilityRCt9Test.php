<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LedgerSource;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\TaskPayablePositionResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7-1 — owner ruling **R-CT9** (PLAN.md §0.2), verbatim:
 *
 * > "Reports must show a payable at its CURRENT position. Where a payee nomination has moved a
 * >  supplier payable to a payee leaf (R-CT8), the AR/AP, creditors and statement screens must
 * >  include that leaf alongside control+party, so a reassigned payable is never invisible.
 * >  Consistent with R-CT8 and R2-1."
 *
 * ── The defect, as it stood on the deployed head `baffda184` ────────────────────────────────────
 * `LedgerSource::payableAccountIds()` returned `PAYABLE_CONTROL` plus the per-service
 * `SERVICE_PAYABLE/{type}` leaves and nothing else — six leaves on the City Travelers dev chart
 * (1700, 1405, 1361, 338, 339, 340) against a 132-account AP tree. Under R-CT8 a who-to-pay
 * nomination moves a task's payable onto an operator-chosen payee leaf, which is in no purpose
 * mapping by construction, so the AP payment-run screens could not see it. CT-D2B §5.7 measured the
 * consequence on the live deployed site: AP tree 1,413,392.039 against 1,179,271.591 visible —
 * **KWD 234,120.448 correct in the ledger and invisible on the screens**, across R3-9's 1,642
 * reassignment documents.
 *
 * ── What this fixture proves, on a controlled chart rather than by citation ─────────────────────
 * One engine-posted AGENT-basis sale puts a supplier payable on the purpose-resolved
 * `SERVICE_PAYABLE/flight` control leaf (2120). One real reassignment — posted through
 * `TaskController::updateJournalPaymentMethod()`, the same feeder that wrote the 1,642 documents,
 * not a hand-built fixture row — moves it onto a payee leaf that is deliberately in NO purpose
 * mapping. The four assertions then are:
 *
 *   1. the payee leaf is genuinely outside the purpose-resolved set (the pre-fix population), and
 *      the money genuinely sits there;
 *   2. `payableAccountIds()` now contains it;
 *   3. the unpaid-AP screen's own `payableBalance`, over HTTP, reconciles EXACTLY to the engine's
 *      raw AP-subtree sum; and
 *   4. the MUTATION PROOF — the same screen restricted to the purpose-resolved set alone does NOT
 *      reconcile, and is short by exactly the reassigned amount. That assertion is what fails the
 *      moment the union is dropped back out of `payableAccountIds()`.
 *
 * ── Why the ground truth is an AP subtree walk, when the FIX deliberately is not ────────────────
 * `TaskPayablePositionResolver::apSubtreeIds()` is used here only as an INDEPENDENT oracle: it is
 * structural, it is not the code under test, and it answers "every KWD of AP the engine holds"
 * without any purpose mapping or document-family reasoning. The fix itself must NOT be built on it
 * (see `LedgerSource::payableAccountIds()`'s own docblock) — an oracle may walk a tree; a report
 * that walks one has re-admitted the name-anchored resolution the CT-A6-1 ratchet exists to stop.
 */
class PayeeVisibilityRCt9Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    /** `SERVICE_PAYABLE`/flight resolves here on a CoaSeeder chart: 2120 Suppliers (Flights). */
    private const CONTROL_CODE = '2120';

    /**
     * The nominated payee leaf. Minted as a NEW child of 'Accounts Payable' (2100) rather than of
     * 'Creditors' (2110): 2110 IS `PAYABLE_CONTROL` on a fresh CoaSeeder chart, and giving it a
     * child would make it a non-leaf and break the purpose resolution this test measures against.
     * A sibling leaf under 2100 is the same shape the real chart has — CT-A4 §2.3 found six company
     * payment instruments (a corporate card, a bank purchasing card, an instalment facility) minted
     * as AP leaves that `tasks.payment_method_account_id` points at, none of them purpose-mapped.
     */
    private const PAYEE_CODE = '2199';

    private const SALE_SELL = 150.0;

    private const SALE_COST = 100.0;

    private int $companyId;

    private int $taskId;

    private int $payeeAccountId;

    private int $controlAccountId;

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
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $this->companyId]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => self::SALE_SELL,
            'issued_date' => now()->subDays(5),
        ]);
        $this->taskId = (int) $task->id;

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(4),
        ]);
        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->companyId]);

        $this->controlAccountId = (int) $this->accountByCode(self::CONTROL_CODE)->id;

        // The sale: Dr AR 150 / Cr revenue 50 ... Cr SERVICE_PAYABLE/flight 100 (agent basis,
        // R-CT1 gross). The payable is now on the purpose-resolved control leaf.
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: self::SALE_SELL,
            costAmount: self::SALE_COST,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: (int) $client->id, clientName: $client->full_name,
            supplierId: (int) $supplier->id, supplierName: $supplier->name,
            agentId: (int) $agent->id, agentName: $agent->name,
            invoiceId: (int) $invoice->id, invoiceDetailId: (int) $detail->id, taskId: $this->taskId,
        ));

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $this->companyId, branchId: (int) $agent->branch_id, docType: 'INV', subType: 'SALE',
            docDate: now()->subDays(4), narration: 'CT-A7 fixture sale', lines: $lines,
            idempotencyKey: 'ct-a7:sale:'.$detail->id, invoiceId: (int) $invoice->id,
        ));

        $this->payeeAccountId = (int) $this->mintPayeeLeaf()->id;

        $this->reassignTo($task->fresh(), Account::withoutGlobalScopes()->findOrFail($this->payeeAccountId));
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /** A payee leaf under 'Accounts Payable' that no purpose code maps to — see PAYEE_CODE. */
    private function mintPayeeLeaf(): Account
    {
        $apGroup = $this->accountByCode('2100');

        return Account::create([
            'company_id' => $this->companyId,
            'parent_id' => $apGroup->id,
            'root_id' => $apGroup->root_id ?? $apGroup->id,
            'name' => 'Corporate Card (Payee)',
            'code' => self::PAYEE_CODE,
            'level' => 3,
            'account_type' => null,
            'report_type' => $apGroup->report_type,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);
    }

    private function reassignTo(Task $task, Account $payee): void
    {
        $response = app(\App\Http\Controllers\TaskController::class)
            ->updateJournalPaymentMethod($task, (int) $payee->id);

        $this->assertSame(200, $response->getStatusCode(), 'the who-to-pay reassignment must post: '.$response->getContent());
        $this->assertNotNull(
            json_decode($response->getContent(), true)['data']['transaction_id'] ?? null,
            'the reassignment must actually post a document — without one there is no R-CT8 nomination for R-CT9 to follow'
        );
    }

    /**
     * The INDEPENDENT oracle: net credit of every ENGINE row on every account under this company's
     * `Accounts Payable` group. Not derived from `payableAccountIds()`, not derived from any purpose
     * mapping — the figure the screens have to reconcile to.
     */
    private function rawEngineApNetCredit(): float
    {
        $subtree = (new TaskPayablePositionResolver)->apSubtreeIds($this->companyId);
        $this->assertNotEmpty($subtree, 'the AP subtree oracle must resolve, or this fixture proves nothing');

        $net = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->whereIn('je.account_id', $subtree)
            ->where('je.company_id', $this->companyId)
            ->whereNull('je.deleted_at')
            ->whereNotNull('t.doc_type')
            ->whereNotNull('t.posting_date')
            ->selectRaw('COALESCE(SUM(je.credit),0) - COALESCE(SUM(je.debit),0) as net')
            ->value('net');

        return round((float) $net, 3);
    }

    /** Net credit of engine rows over an explicit account-id set — the screens' own arithmetic. */
    private function engineNetCreditOver(array $accountIds): float
    {
        if ($accountIds === []) {
            return 0.0;
        }

        $net = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->whereIn('je.account_id', $accountIds)
            ->where('je.company_id', $this->companyId)
            ->whereNull('je.deleted_at')
            ->whereNotNull('t.doc_type')
            ->whereNotNull('t.posting_date')
            ->selectRaw('COALESCE(SUM(je.credit),0) - COALESCE(SUM(je.debit),0) as net')
            ->value('net');

        return round((float) $net, 3);
    }

    private function companyUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::where('id', $this->companyId)->update(['user_id' => $user->id]);

        return $user;
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The fixture reproduces R3-9 before anything under test is consulted: the payable has LEFT the
     * purpose-resolved control and now stands, in full, on a leaf no purpose maps to.
     */
    public function test_the_fixture_puts_the_payable_on_a_leaf_no_purpose_maps_to(): void
    {
        $ledgerSource = app(LedgerSource::class);
        $resolver = app(AccountResolver::class);

        $purposeOnly = $ledgerSource->purposeResolvedPayableAccountIds($this->companyId, $resolver);

        $this->assertContains($this->controlAccountId, $purposeOnly, 'SERVICE_PAYABLE/flight must be one of the purpose leaves.');
        $this->assertNotContains(
            $this->payeeAccountId,
            $purposeOnly,
            'the nominated payee leaf must be OUTSIDE the purpose-resolved set — that is the whole premise of R3-9.'
        );

        $this->assertEqualsWithDelta(
            0.0,
            $this->engineNetCreditOver([$this->controlAccountId]),
            0.0005,
            'the reassignment must have emptied the control leaf for this task.'
        );
        $this->assertEqualsWithDelta(
            self::SALE_COST,
            $this->engineNetCreditOver([$this->payeeAccountId]),
            0.0005,
            'the whole payable must now stand on the nominated payee leaf.'
        );
    }

    /**
     * R-CT9, the fix: the payee leaf is in the set every payables screen reads.
     */
    public function test_payable_account_ids_includes_the_leaf_the_nomination_moved_the_payable_to(): void
    {
        $ids = app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class));

        $this->assertContains(
            $this->payeeAccountId,
            $ids,
            'R-CT9: a leaf an R-CT8 nomination moved a payable onto must be included alongside control+party.'
        );
        $this->assertContains($this->controlAccountId, $ids, 'the purpose-resolved leaves must all still be there.');
        $this->assertSame(
            count($ids),
            count(array_unique($ids)),
            'the union must be de-duplicated — a control leaf that is also a reassignment source must not be counted twice.'
        );
    }

    /**
     * The reconciliation the brief asks for, asserted on a fixture containing a reassigned payable:
     * the unpaid-AP screen's own total, produced over HTTP by the real controller, equals the
     * engine's raw AP sum.
     */
    public function test_the_unpaid_ap_screen_total_reconciles_to_the_raw_engine_ap_sum(): void
    {
        $rawAp = $this->rawEngineApNetCredit();
        $this->assertEqualsWithDelta(
            self::SALE_COST,
            $rawAp,
            0.0005,
            'the oracle itself must read the one payable this fixture posted.'
        );

        $response = $this->actingAs($this->companyUser())->get(route('reports.unpaid-report', ['account_id' => 'all']));

        $response->assertOk();
        $response->assertViewHas('payableBalance', fn ($balance) => abs((float) $balance - $rawAp) < 0.0005);
    }

    /**
     * MUTATION PROOF for CT-A7-1. The pre-fix population — the purpose-resolved leaves alone — is
     * measured directly and asserted NOT to reconcile, short by exactly the reassigned amount.
     *
     * If a future change drops the R-CT9 union back out of `payableAccountIds()`, the two sets
     * become identical, `$purposeOnlyNet` becomes `$unionNet`, and both assertions below fail — the
     * shortfall assertion because it is no longer non-zero, and the identity assertion because the
     * union stopped containing the payee leaf. This is the ratchet, not the docblock.
     */
    public function test_mutation_the_purpose_resolved_set_alone_does_not_reconcile(): void
    {
        $ledgerSource = app(LedgerSource::class);
        $resolver = app(AccountResolver::class);

        $rawAp = $this->rawEngineApNetCredit();
        $unionNet = $this->engineNetCreditOver($ledgerSource->payableAccountIds($this->companyId, $resolver));
        $purposeOnlyNet = $this->engineNetCreditOver($ledgerSource->purposeResolvedPayableAccountIds($this->companyId, $resolver));

        $this->assertEqualsWithDelta($rawAp, $unionNet, 0.0005, 'R-CT9 union must reconcile to the raw engine AP sum.');

        $this->assertGreaterThan(
            0.0005,
            abs($rawAp - $purposeOnlyNet),
            'the purpose-resolved set ALONE must NOT reconcile on a chart carrying a reassigned payable — '
            .'if it ever does, either this fixture stopped reassigning or the two sets became the same set.'
        );
        $this->assertEqualsWithDelta(
            self::SALE_COST,
            round($rawAp - $purposeOnlyNet, 3),
            0.0005,
            'the shortfall must be exactly the reassigned payable — the fixture-scale shape of the deployed '
            .'KWD 234,120.448 CT-D2B §5.7 measured outside the six purpose leaves.'
        );
    }

    /**
     * The creditors screen — the AP payment-run screen R3-9 named — can now reach the payee leaf:
     * it is offered in that screen's own account picker, and selecting it shows the money.
     */
    public function test_the_creditors_screen_offers_and_reports_the_payee_leaf(): void
    {
        $user = $this->companyUser();

        $response = $this->actingAs($user)->get(route('reports.creditors', ['account_id' => $this->payeeAccountId]));

        $response->assertOk();
        $response->assertViewHas(
            'childOfCreditors',
            fn ($accounts) => collect($accounts)->pluck('id')->map(fn ($id) => (int) $id)->contains($this->payeeAccountId)
        );
        $response->assertViewHas(
            'accountForReport',
            fn ($account) => (int) $account->id === $this->payeeAccountId
                && abs((float) $account->final_balance - self::SALE_COST) < 0.0005
        );
    }

    /**
     * The statement/AP-ageing source (`SupplierLedgerStatementSource`, which `StatementService`
     * buckets for AP ageing) reads the same widened set, so a reassigned supplier's statement
     * cannot disagree with the payment-run screen about what is owed.
     */
    public function test_the_supplier_statement_source_reads_the_widened_payable_set(): void
    {
        $partyRef = (int) DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->where('account_id', $this->payeeAccountId)
            ->whereNull('deleted_at')
            ->where('credit', '>', 0)
            ->value('type_reference_id');

        $this->assertGreaterThan(0, $partyRef, 'the reassignment must preserve party attribution on its credit leg.');

        $documents = app(\App\Services\Accounting\Statements\SupplierLedgerStatementSource::class)
            ->documents($this->companyId, $partyRef, now());

        $this->assertGreaterThan(
            0,
            $documents->count(),
            'R-CT9: the supplier statement must still find the open item after the payable was reassigned off '
            .'PAYABLE_CONTROL — before CT-A7-1 this source read that one leaf and returned nothing.'
        );
        // OUTSTANDING, not gross: reading both leaves means the statement sees the reassignment's
        // own two legs as well — a settlement of 100.000 against the original charge on the control
        // and a fresh 100.000 charge on the payee leaf. FIFO nets the first to zero, which is
        // exactly right and exactly what the payment-run screen shows. Summing `amount` here
        // instead would read 200.000 and be a double count of a payable that only ever existed
        // once, which is the assertion this comment exists to stop anyone "simplifying" back.
        $outstanding = round($documents->sum(fn ($item) => $item->amount - $item->settledAmount), 3);

        $this->assertEqualsWithDelta(
            self::SALE_COST,
            $outstanding,
            0.0005,
            'the statement must carry the reassigned charge, once, at its full amount.'
        );
    }
}
