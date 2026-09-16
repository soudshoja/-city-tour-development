<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\LedgerSource;
use App\Services\Accounting\TaskPayablePositionResolver;
use App\Services\Accounting\VoucherOptions;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7 ROUND 2, finding **F1** — is the R-CT9 union complete **by construction**, or only on
 * today's data?
 *
 * Adapted from the adversarial verifier's own reproducer and kept as a permanent regression test,
 * because the answer round 1 shipped was "complete on today's data", which is not an answer.
 *
 * ── The attack ─────────────────────────────────────────────────────────────────────────────────
 * `PostingService::targetAccountId()` is the single R-CT8 seam **only for lines that resolve by
 * `purposeCode`**. Its first branch returns `$line->accountId` verbatim, before any nomination
 * lookup. Four production writers build AP-side lines with an explicit `accountId` and therefore
 * entered NEITHER half of round 1's union — the purpose-resolved half (they name no purpose) nor
 * the reassignment-document half (they are not reassignments):
 *
 *   - `AccountingController::storePayableDetail()`   — JV_PAYABLE, operator dropdown over AP leaves
 *   - `AccountingController::storeReceivableDetail()` — JV_RECEIVABLE, same dropdown
 *   - `AccountingController::storeBankPayment()`      — JV_TRANSFER
 *   - `BankPaymentController::buildVoucherDraft()`    — the supplier payment voucher, the
 *     highest-volume AP writer, account from the operator-picked `bank_payments.target_account_id`
 *
 * Measured by the verifier through real HTTP routes: raw AP subtree 250.000,
 * `reports.unpaid-report?account_id=all` → `payableBalance = 0.000`. R3-9 reproduced *after* the
 * round-1 fix. The live shortfall read 0.000 only because on the City Travelers chart the AP leaves
 * carrying money happen to also be the 1,642 reassignment targets.
 *
 * ── The completeness argument the fix now rests on ─────────────────────────────────────────────
 * Stated as an argument, not as a measurement:
 *
 *   1. **Any line that is a supplier payable lands on an account inside the company's
 *      `Accounts Payable` subtree.** Purpose-resolved lines do, because `PAYABLE_CONTROL` and
 *      `SERVICE_PAYABLE/{type}` map there and `accounting:coa-linkage` asserts the root of every
 *      purpose it resolves. Operator-picked lines do, because every screen that offers an AP
 *      account builds its dropdown from that subtree. R-CT8 nomination destinations do, because
 *      CT-A7-R2 finding F4 now CONSTRAINS them to it (see
 *      {@see \Tests\Feature\Accounting\CtA7\NominationDestinationF4Test}).
 *   2. **An account with no posted journal line contributes exactly 0.000 to any total**, so
 *      excluding it loses no money and admitting it would only widen the set.
 *
 *   ∴ scanning (AP subtree ∩ accounts with posted movement) ∪ (the purpose-resolved control leaves,
 *   always, so the screen's account picker is stable at zero) reaches **every KWD of accounts
 *   payable**. Complete by construction; bounded by movement, so it is not "the whole subtree".
 *
 * The round-1 objection to the subtree — "it admits all 132 AP accounts whether or not a KWD moved
 * there" — is answered by the movement filter, which is the same filter the document-family half
 * already applied to itself. The name-anchor objection was inconsistent and is withdrawn:
 * `apSubtreeIds()` already anchors on `Account::where('name', 'Accounts Payable')` and is already
 * production code in the money path (`SupplierReassignDraftBuilder` uses it to decide what a
 * reassignment debits).
 *
 * The reassignment-document half is KEPT rather than subsumed, deliberately: F4 constrains
 * nominations from now on, but HISTORICAL nominations were unconstrained, so a pre-F4 destination
 * outside the AP subtree is still reachable only through that half.
 */
class UnionCompletenessR2Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    private int $companyId;

    private int $branchId;

    /** An AP leaf in no purpose mapping and named by no reassignment — the attack surface. */
    private int $unmappedApLeafId;

    /** A second AP leaf that is deliberately never posted to — the BOUND on the fix. */
    private int $untouchedApLeafId;

    private int $bankAccountId;

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
        Client::factory()->create(['company_id' => $this->companyId]);

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);

        Setting::create([
            'company_id' => $this->companyId,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '100000',
            'type' => 'string',
        ]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->companyId]);

        $this->unmappedApLeafId = (int) $this->mintApLeaf('Payee Leaf A', '2196')->id;
        $this->untouchedApLeafId = (int) $this->mintApLeaf('Payee Leaf B (never posted)', '2197')->id;
        $this->bankAccountId = (int) $this->accountByCode('1201')->id;
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

    /** A leaf under `2100 Accounts Payable` that no purpose maps to. */
    private function mintApLeaf(string $name, string $code): Account
    {
        $apGroup = $this->accountByCode('2100');

        return Account::create([
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

    private function companyUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::where('id', $this->companyId)->update(['user_id' => $user->id]);

        return $user;
    }

    /** The independent oracle: every KWD of AP the engine holds, structurally. */
    private function rawEngineApNetCredit(): float
    {
        $subtree = (new TaskPayablePositionResolver)->apSubtreeIds($this->companyId);
        $this->assertNotEmpty($subtree, 'the AP subtree oracle must resolve, or this fixture proves nothing');

        return round((float) DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->whereIn('je.account_id', $subtree)
            ->where('je.company_id', $this->companyId)
            ->whereNull('je.deleted_at')
            ->whereNotNull('t.doc_type')->whereNotNull('t.posting_date')
            ->selectRaw('COALESCE(SUM(je.credit),0) - COALESCE(SUM(je.debit),0) as net')
            ->value('net'), 3);
    }

    private function screenPayableBalance(): float
    {
        $response = $this->actingAs($this->companyUser())
            ->get(route('reports.unpaid-report', ['account_id' => 'all']));

        $response->assertOk();

        return round((float) $response->viewData('payableBalance'), 3);
    }

    /** Credit an account through the manual JV "receivable" screen (JV_RECEIVABLE). */
    private function creditThroughJv(int $accountId, float $amount, string $note): void
    {
        $this->actingAs($this->companyUser())
            ->post(route('receivable-details.receivable-store'), [
                'transaction_date' => now()->subDays(3)->format('Y-m-d'),
                'account_id' => $accountId,
                'branch_id' => $this->branchId,
                'bank_account' => $this->bankAccountId,
                'description' => $note,
                'name' => 'counterparty',
                'amount' => $amount,
                'type' => 'receivable',
            ])->assertRedirect();
    }

    /** Debit an account through the manual JV "payable" screen (JV_PAYABLE). */
    private function debitThroughJv(int $accountId, float $amount, string $note): void
    {
        $this->actingAs($this->companyUser())
            ->post(route('payable-details.payable-store'), [
                'transaction_date' => now()->subDay()->format('Y-m-d'),
                'account_id' => $accountId,
                'branch_id' => $this->branchId,
                'bank_account' => $this->bankAccountId,
                'description' => $note,
                'amount' => $amount,
                'type' => 'payable',
            ])->assertRedirect();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ATTACK 1 — the manual journal screen credits an AP leaf that is in no purpose mapping and is
     * the destination of no reassignment. The money is real; the screen must show it.
     */
    public function test_a_payable_credited_through_the_manual_jv_screen_is_visible(): void
    {
        $this->creditThroughJv($this->unmappedApLeafId, 250.0, 'F1 attack: credit an AP leaf');

        $posted = round((float) DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->where('account_id', $this->unmappedApLeafId)
            ->whereNull('deleted_at')->sum('credit'), 3);
        $this->assertEqualsWithDelta(250.0, $posted, 0.0005, 'the JV must actually have credited the AP leaf');

        $raw = $this->rawEngineApNetCredit();
        $this->assertEqualsWithDelta(250.0, $raw, 0.0005, 'the AP subtree oracle must see the money');

        $this->assertContains(
            $this->unmappedApLeafId,
            app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class)),
            'F1: an AP leaf carrying posted movement must be in payableAccountIds() even though no '
            .'purpose maps to it and no reassignment named it — the four explicit-accountId writers '
            .'enter neither half of the round-1 union'
        );

        $this->assertEqualsWithDelta(
            $raw,
            $this->screenPayableBalance(),
            0.0005,
            'the unpaid-AP screen must reconcile to the raw engine AP sum'
        );
    }

    /**
     * ATTACK 2 — the same leaf accrued and then partly paid, both through the manual screens. The
     * NET is what the screen owes the operator.
     */
    public function test_a_payable_accrued_and_partly_paid_through_the_manual_screens_nets_on_the_screen(): void
    {
        $this->creditThroughJv($this->unmappedApLeafId, 400.0, 'F1 attack: opening payable');
        $this->debitThroughJv($this->unmappedApLeafId, 150.0, 'F1 attack: pay part of it');

        $raw = $this->rawEngineApNetCredit();
        $this->assertEqualsWithDelta(250.0, $raw, 0.0005, 'truth: 400.000 accrued less 150.000 paid');

        $this->assertEqualsWithDelta($raw, $this->screenPayableBalance(), 0.0005);
    }

    /**
     * ATTACK 3 — the highest-volume AP writer. A supplier payment voucher debits the leaf the
     * operator picked; that leaf resolves by explicit `accountId`, so it never reaches the R-CT8
     * seam and was in neither half of the round-1 union unless it had also been reassigned.
     */
    public function test_a_supplier_payment_voucher_against_a_never_reassigned_leaf_is_visible(): void
    {
        $this->creditThroughJv($this->unmappedApLeafId, 500.0, 'F1 attack: something to pay');
        $this->fundBank(1000.0);

        $supplier = Supplier::factory()->create();
        Account::withoutGlobalScopes()->whereKey($this->unmappedApLeafId)->update(['supplier_id' => $supplier->id]);

        $this->actingAs($this->companyUser())->post(route('bank-payments.store'), [
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $this->bankAccountId,
            'remarks_create' => 'F1 attack: pay the supplier',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $this->unmappedApLeafId, 'credit' => 200.0],
            ],
        ])->assertRedirect();

        $raw = $this->rawEngineApNetCredit();

        $this->assertEqualsWithDelta(
            $raw,
            $this->screenPayableBalance(),
            0.0005,
            'the payment voucher moved AP; the screen must move with it'
        );
    }

    /**
     * THE BOUND, and the reason this is not "resolve the whole AP subtree". An AP leaf with no
     * posted line must NOT be admitted — that is exactly what the round-1 objection to the subtree
     * walk was about, and the movement filter is what answers it. If this ever fails, the fix has
     * become the tree-walk the CT-A6-1 ratchet exists to prevent.
     */
    public function test_an_ap_leaf_with_no_movement_is_not_admitted(): void
    {
        $this->creditThroughJv($this->unmappedApLeafId, 250.0, 'F1 attack: one leaf moves');

        $ids = app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class));

        $this->assertContains($this->unmappedApLeafId, $ids, 'the leaf that moved is in');
        $this->assertNotContains(
            $this->untouchedApLeafId,
            $ids,
            'an AP leaf that no KWD has ever touched must stay OUT — the set is bounded by movement, '
            .'not by the shape of the chart'
        );
    }

    /**
     * The purpose-resolved control leaves stay in the set at zero movement, so the screens\' own
     * account pickers do not lose their options on a quiet chart.
     */
    public function test_the_purpose_resolved_control_leaves_survive_at_zero_movement(): void
    {
        $resolver = app(AccountResolver::class);
        $ledgerSource = app(LedgerSource::class);

        $ids = $ledgerSource->payableAccountIds($this->companyId, $resolver);

        foreach ($ledgerSource->purposeResolvedPayableAccountIds($this->companyId, $resolver) as $purposeLeaf) {
            $this->assertContains($purposeLeaf, $ids, 'a purpose-resolved control leaf must be offered even at zero');
        }
    }

    /** Fund the bank leaf so the payment voucher clears its own overdraft refusal. */
    private function fundBank(float $amount): void
    {
        $bank = Account::withoutGlobalScopes()->findOrFail($this->bankAccountId);
        $incomeSuspense = $this->accountByCode('4133');

        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Opening funding',
            'reference_type' => 'Invoice', 'reference_number' => 'FUND-'.substr(uniqid(), -8),
            'name' => 'Opening funding', 'transaction_date' => now(),
            'doc_type' => 'JV', 'doc_year' => (int) now()->format('Y'), 'posting_status' => 'posted',
            'posting_date' => now(),
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => 'f1fund:'.uniqid(),
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $bank->id, 'transaction_date' => now(), 'description' => 'Opening funding',
            'debit' => $amount, 'credit' => 0, 'name' => $bank->name, 'type' => 'bank', 'currency' => 'KWD',
            'exchange_rate' => 1, 'amount' => $amount, 'voucher_number' => 'FUND',
        ]);
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $incomeSuspense->id, 'transaction_date' => now(), 'description' => 'Opening funding',
            'debit' => 0, 'credit' => $amount, 'name' => $incomeSuspense->name, 'type' => 'income', 'currency' => 'KWD',
            'exchange_rate' => 1, 'amount' => $amount, 'voucher_number' => 'FUND',
        ]);
    }
}
