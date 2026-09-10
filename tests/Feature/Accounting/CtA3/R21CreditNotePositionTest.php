<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Exceptions\Accounting\NothingOutstandingToCreditException;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RefundPostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 R2-1 — VERIFY-CT-A3-STACK-R1 §3.2 **D5, BLOCKER**: *"`postCrnForDetail()` silently
 * reverses nothing after any prior reversal of the sale."*
 *
 * ── The defect, restated from the verify report ─────────────────────────────────────────────────
 * `RefundPostingService::postCrnForDetail()` looked the sale up by the FIXED key
 * `invoice-detail:{id}:sale` **in any status** and handed it to `PostingService::reverse()`, which
 * short-circuits on any pre-existing reversal:
 *
 * ```php
 * $existingReversal = Transaction::…->where('reversal_of_transaction_id', $posted->id)->first();
 * if ($existingReversal !== null) { return $this->toPostedDocument($existingReversal); }
 * ```
 *
 * …and the CRN returned that as a success. Three ordinary producers reach it:
 *
 *  1. **An invoice price correction, then a refund.** `repost()` renames the NEW document, so after
 *     any correction the DEAD, reversed sale still owns `invoice-detail:{id}:sale` and the LIVE
 *     sale sits under the replacement key. The refund reverses the corpse.
 *  2. **A second refund on the same task.** Nothing prevents one — `RefundController::store()`
 *     validates `tasks.*.task_id` as `exists:tasks,id`, with no `distinct` and no
 *     already-refunded check.
 *  3. **A refund raised against the original task of a reissue**, whose sale
 *     `TaskStatusService::reissueReverseOldSale()` already reversed.
 *
 * WORKED EXAMPLE from the report (§3.2 D5), reproduced verbatim by the first case below:
 * *"sale 100, penalty 20, fee 5, credit disposition: revenue stays +100 un-reversed; AR = 100
 * (live sale) + 25 (recharge) + 75 (disposition) = +200 debit while CLIENT_ADVANCE is credited 75.
 * The client is credited KWD 75 AND carries KWD 200 of receivable."* The COST half was relieved
 * correctly the whole time, because `postSupplierCreditForDetail()` reads the LEDGER — which is
 * what made this invisible to every supplier-side and AP-control check the wave reports ran.
 *
 * ── The fix this file pins ──────────────────────────────────────────────────────────────────────
 * The credit note now computes against the CURRENT POSTED POSITION of the sale: it reverses every
 * sale-family document for the invoice detail that is STILL LIVE (the base key plus every
 * revision), which is by construction the outstanding revenue / AR / COGS / payable for that
 * detail. When nothing is live — every sale document for the detail already reversed, or a legacy
 * sale already credited by an earlier refund — it REFUSES LOUDLY with
 * {@see NothingOutstandingToCreditException} and an audit log line, instead of returning someone
 * else's reversal as this refund's credit note.
 *
 * And the CRN finally carries the key its own docblock has always claimed —
 * `refund:{refund_id}:crn:{refund_detail_id}` — which the report's §4 recorded as *"never
 * constructed anywhere in the file"*. That is what keeps a genuine retry idempotent now that
 * "the sale is already reversed" no longer means "this refund already ran".
 */
class R21CreditNotePositionTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task, 5: Invoice, 6: InvoiceDetail}
     */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'refunded',
            'total' => 60.000,
            'issued_date' => now()->subDays(5),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(4),
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
        ]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        return [$company, $agent, $client, $supplier, $task->fresh(), $invoice, $invoiceDetail];
    }

    private function saleDraft(Company $company, Agent $agent, Client $client, Supplier $supplier, Task $task, Invoice $invoice, InvoiceDetail $detail, float $sell, float $cost, string $key): DocumentDraft
    {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $task->type,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $client->id,
            clientName: $client->full_name,
            supplierId: $supplier->id,
            supplierName: $supplier->name,
            agentId: $agent->id,
            agentName: $agent->name,
            invoiceId: $invoice->id,
            invoiceDetailId: $detail->id,
            taskId: $task->id,
        ));

        return new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: now()->subDays(4),
            narration: 'Sale',
            lines: $lines,
            idempotencyKey: $key,
            invoiceId: $invoice->id,
        );
    }

    private function makeRefund(Company $company, Agent $agent, Invoice $invoice, Task $task, Client $client, array $detailOverrides = [], string $suffix = 'a'): Refund
    {
        $refund = Refund::create([
            'refund_number' => 'REF-R21-'.$suffix.'-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 0,
        ]);

        RefundDetail::create(array_merge([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'original_task_profit' => 40.000,
            'refund_fee_to_client' => 5.000,
            'supplier_charge' => 20.000,
            'supplier_refund_amount' => null,
            'new_task_profit' => 0,
            'total_refund_to_client' => 75.000,
        ], $detailOverrides));

        return $refund->fresh();
    }

    /** Dr − Cr on the leaf a PURPOSE resolves to, over every non-deleted row. */
    private function netDebitForPurpose(int $companyId, string $purpose, ?string $serviceType = null): float
    {
        $account = app(AccountResolver::class)->resolve($purpose, $companyId, $serviceType);

        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Producer 1 — a price correction, then a refund. THE WORKED EXAMPLE.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * A sale is posted, then CORRECTED (an ordinary invoice price edit, `repost()`), then
     * refunded. The credit note must reverse the LIVE sale, not the corpse that still owns the
     * base key.
     *
     * Before the fix, measured: revenue **+100 un-reversed** and AR **+200** against a client who
     * was credited 75 — the report's own worked example. After the fix: revenue 0, AR 100.
     */
    public function test_a_credit_note_after_a_price_correction_reverses_the_live_sale(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        /** @var PostingService $posting */
        $posting = app(PostingService::class);
        $saleKey = 'invoice-detail:'.$detail->id.':sale';

        $original = $posting->post($this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 90.0, 60.0, $saleKey))->transaction;

        // The price correction: exactly what InvoiceController does — reverse and repost under the
        // SAME base key, which repost() turns into the next revision.
        $corrected = $posting->repost(
            Transaction::withoutGlobalScopes()->findOrFail($original->id),
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 100.0, 60.0, $saleKey),
            now()->subDays(3),
            null
        )->transaction;

        // Precondition: the base key belongs to the DEAD document. This is the whole trap.
        $this->assertSame($saleKey, (string) $original->fresh()->idempotency_key);
        $this->assertSame('reversed', (string) $original->fresh()->posting_status);
        $this->assertNotSame($saleKey, (string) $corrected->fresh()->idempotency_key);
        $this->assertSame('posted', (string) $corrected->fresh()->posting_status);

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client);

        app(RefundPostingService::class)->post($refund, null);

        // THE MONEY. Revenue fully reversed; AR carries the recharge (25) + the disposition (75)
        // against a sale (100) the CRN took back — 100, not the 200 D5 measured.
        $this->assertSame(
            0.0,
            $this->netDebitForPurpose($company->id, 'SERVICE_REVENUE', 'flight'),
            'The credit note must reverse the LIVE sale\'s revenue. D5 left it standing at +100.'
        );

        $this->assertSame(
            100.0,
            $this->netDebitForPurpose($company->id, 'RECEIVABLE_CONTROL'),
            'AR must be 100 (recharge 25 + disposition 75), not 200 (a live sale the CRN never took off).'
        );

        // And the CORRECTED document is what was reversed — not the corpse.
        $this->assertSame('reversed', (string) $corrected->fresh()->posting_status);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Producers 2 and 3 — nothing left to credit
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * A SECOND refund on an already-refunded task must refuse loudly. Before the fix it "succeeded"
     * by handing back the FIRST refund's reversal as its own credit note, then went on to post a
     * second recharge and a second disposition against it.
     */
    public function test_a_second_refund_on_the_same_task_refuses_loudly(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        app(PostingService::class)->post(
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 100.0, 60.0, 'invoice-detail:'.$detail->id.':sale')
        );

        app(RefundPostingService::class)->post($this->makeRefund($company, $agent, $invoice, $task, $client, [], 'first'), null);

        $arAfterFirst = $this->netDebitForPurpose($company->id, 'RECEIVABLE_CONTROL');
        $revenueAfterFirst = $this->netDebitForPurpose($company->id, 'SERVICE_REVENUE', 'flight');

        $second = $this->makeRefund($company, $agent, $invoice, $task, $client, [], 'second');

        try {
            app(RefundPostingService::class)->post($second, null);
            $this->fail('A second refund against a fully credited sale must refuse, not report success.');
        } catch (NothingOutstandingToCreditException $e) {
            $this->assertSame((int) $detail->id, $e->invoiceDetailId);
            $this->assertStringContainsString('nothing outstanding', strtolower($e->getMessage()));
        }

        // The refusal is a REFUSAL: the whole second refund rolls back, so not one of its other
        // documents (recharge, disposition, supplier credit) reached the ledger either.
        $this->assertSame($arAfterFirst, $this->netDebitForPurpose($company->id, 'RECEIVABLE_CONTROL'));
        $this->assertSame($revenueAfterFirst, $this->netDebitForPurpose($company->id, 'SERVICE_REVENUE', 'flight'));
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'like', 'refund:'.$second->id.':%')->count(),
            'A refused refund must leave no documents at all behind.'
        );
    }

    /**
     * Producer 3: the original task of a REISSUE. `TaskStatusService::reissueReverseOldSale()`
     * already reversed that sale, so a refund raised against it has nothing to credit — and must
     * say so rather than returning the reissue's own reversal.
     */
    public function test_a_refund_against_an_already_reversed_sale_refuses_loudly(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        /** @var PostingService $posting */
        $posting = app(PostingService::class);

        $sale = $posting->post(
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 100.0, 60.0, 'invoice-detail:'.$detail->id.':sale')
        )->transaction;

        // What a reissue does to the old sale.
        $posting->reverse(Transaction::withoutGlobalScopes()->findOrFail($sale->id), now(), null);

        $this->expectException(NothingOutstandingToCreditException::class);

        app(RefundPostingService::class)->post($this->makeRefund($company, $agent, $invoice, $task, $client), null);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // The property the fix must NOT break
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * A genuine RETRY of the SAME refund is still a no-op, and still returns the same credit note.
     *
     * This is the property that used to be carried, accidentally, by "the sale is already
     * reversed, so reverse() returns the existing reversal" — the same accident that made D5
     * silent. It is now carried deliberately, by the CRN's own per-refund-detail idempotency key.
     */
    public function test_reposting_the_same_refund_is_still_idempotent(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        app(PostingService::class)->post(
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 100.0, 60.0, 'invoice-detail:'.$detail->id.':sale')
        );

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client);

        $first = app(RefundPostingService::class)->post($refund, null);
        $arAfterFirst = $this->netDebitForPurpose($company->id, 'RECEIVABLE_CONTROL');
        $documentsAfterFirst = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count();

        $second = app(RefundPostingService::class)->post($refund->fresh(), null);

        $this->assertSame(
            (int) $first['crn'][0]->transaction->id,
            (int) $second['crn'][0]->transaction->id,
            'A retry must return the SAME credit note, not a new one and not a refusal.'
        );
        $this->assertSame($arAfterFirst, $this->netDebitForPurpose($company->id, 'RECEIVABLE_CONTROL'));
        $this->assertSame(
            $documentsAfterFirst,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'A retry must write no new document.'
        );
    }

    /**
     * §4 claim 1 of the verify report: *"`RefundPostingService:204` states the CRN key is
     * `refund:{refund_id}:crn:{refund_detail_id}`. That string is never constructed anywhere in the
     * file."* It is now — pinned as the literal, because a docblock that describes a key nothing
     * mints is exactly how D5 stayed invisible.
     */
    public function test_the_credit_note_carries_the_key_its_docblock_claims(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        app(PostingService::class)->post(
            $this->saleDraft($company, $agent, $client, $supplier, $task, $invoice, $detail, 100.0, 60.0, 'invoice-detail:'.$detail->id.':sale')
        );

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client);
        $refundDetailId = (int) RefundDetail::where('refund_id', $refund->id)->value('id');

        $posted = app(RefundPostingService::class)->post($refund, null);

        $this->assertSame(
            'refund:'.$refund->id.':crn:'.$refundDetailId,
            (string) $posted['crn'][0]->transaction->fresh()->idempotency_key
        );
    }
}
