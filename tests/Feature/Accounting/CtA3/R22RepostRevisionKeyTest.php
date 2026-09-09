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
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 R2-2 — VERIFY-CT-A3-STACK-R1 §3.2 **D6, BLOCKER**: *"the second edit of a posted receipt
 * or payment voucher posts nothing at all."*
 *
 * ── The defect, restated from the verify report ─────────────────────────────────────────────────
 * {@see PostingService::repost()} applied its replacement-key suffix CONDITIONALLY:
 *
 * ```php
 * if ($new->idempotencyKey !== null && $new->idempotencyKey === $old->idempotency_key) {
 *     $new = $this->withRepostIdempotencyKey($new, ':repost:'.$old->id);
 * }
 * ```
 *
 * Every caller builds the SAME base key from the row's own id (`rv:{id}`, `pv:{id}`,
 * `invoice-detail:{id}:sale`), because the row id does not change across an edit. So:
 *
 *  - **First edit** — `$new->idempotencyKey` (`rv:5`) equals `$old->idempotency_key` (`rv:5`), the
 *    suffix is applied, T1 is reversed and T2 posts under `rv:5:repost:{T1}`. Correct.
 *  - **Second edit** — `$old` is now T2, whose key is `rv:5:repost:{T1}`, which does NOT equal
 *    `rv:5`. No suffix. `reverse(T2)` runs, then `post()`'s step-1 idempotency short-circuit finds
 *    the ALREADY-REVERSED T1 under `rv:5` and returns it **without posting anything**.
 *
 * Net ledger effect of the whole voucher: **zero** (T1+REV(T1)=0, T2+REV(T2)=0). The controller
 * then re-points `invoice_receipts.transaction_id` at the dead T1, re-applies the allocations,
 * marks the invoice `paid`, and flashes *"Updated Successfully (reversed and reposted)"*. No
 * exception, no log, and a trial balance that still foots.
 *
 * ── The fix this file pins ──────────────────────────────────────────────────────────────────────
 * `repost()` now ALWAYS mints a replacement key from a MONOTONICALLY INCREASING REVISION of the
 * document's own base key — `{base}:rev{n}`, where `n` is the count of postings that already exist
 * for that base — instead of asking whether two strings happen to be equal. The revision is
 * derived from the LEDGER (how many documents already occupy this base key's family), not from a
 * flag or a column, so it is correct however many times a document is edited, whichever call site
 * does the editing, and whether or not the caller happens to reuse `$old`'s key verbatim.
 *
 * Six other `repost()` call sites inherit the fix; this file pins the two the report measured
 * (money in: `ReceiptVoucherController::update()`; money out: `BankPaymentController::update()`
 * shares the identical shape) plus the primitive itself.
 */
class R22RepostRevisionKeyTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(): array
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

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /** Dr − Cr on one account id, over EVERY document (posted or reversed), from the raw rows. */
    private function netDebit(int $accountId): float
    {
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $accountId);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    private function twoLineDraft(Company $company, Branch $branch, Account $debit, Account $credit, float $amount, string $key): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'R2-2 probe',
            lines: [
                new LineDraft(
                    purposeCode: '', accountId: $debit->id, side: 'debit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'JOURNAL', description: 'R2-2 probe debit', ledgerType: 'bank',
                ),
                new LineDraft(
                    purposeCode: '', accountId: $credit->id, side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'JOURNAL', description: 'R2-2 probe credit', ledgerType: 'receivable',
                ),
            ],
            idempotencyKey: $key,
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // The primitive
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * THE DEFECT, at the level it actually lives. Three postings of one document under one base
     * key: an original and two corrections. Before the fix the third `post()` inside `repost()`
     * silently returned the dead first transaction and wrote no journal row at all, leaving the
     * document's net ledger position at ZERO instead of the final 30.
     */
    public function test_a_second_repost_posts_a_new_revision_rather_than_returning_the_dead_original(): void
    {
        [$company, $branch] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');
        $clients = $this->accountByCode($company->id, '1351');

        /** @var PostingService $posting */
        $posting = app(PostingService::class);
        $base = 'r2-2-probe:1';

        $t1 = $posting->post($this->twoLineDraft($company, $branch, $bank, $clients, 10.0, $base))->transaction;

        $t2 = $posting->repost(
            Transaction::withoutGlobalScopes()->findOrFail($t1->id),
            $this->twoLineDraft($company, $branch, $bank, $clients, 20.0, $base),
            now(),
            null
        )->transaction;

        $t3 = $posting->repost(
            Transaction::withoutGlobalScopes()->findOrFail($t2->id),
            $this->twoLineDraft($company, $branch, $bank, $clients, 30.0, $base),
            now(),
            null
        )->transaction;

        $this->assertNotSame((int) $t1->id, (int) $t3->id, 'The second edit must not hand back the first, dead document.');
        $this->assertNotSame((int) $t2->id, (int) $t3->id, 'The second edit must post a NEW document.');

        // The replacement keys are monotonic revisions of the base, never a re-use and never a
        // second document under the base key itself.
        $this->assertSame($base, (string) $t1->fresh()->idempotency_key);
        $this->assertSame($base.':rev1', (string) $t2->fresh()->idempotency_key);
        $this->assertSame($base.':rev2', (string) $t3->fresh()->idempotency_key);

        // Both predecessors are reversed; only the newest revision is live.
        $this->assertSame('reversed', (string) $t1->fresh()->posting_status);
        $this->assertSame('reversed', (string) $t2->fresh()->posting_status);
        $this->assertSame('posted', (string) $t3->fresh()->posting_status);

        // THE MONEY. Everything that ever posted, plus every reversal, must net to the FINAL
        // amount — not to zero, and not to the first amount.
        $this->assertSame(30.0, $this->netDebit($bank->id), 'The ledger must carry the final amount, not zero.');
        $this->assertSame(-30.0, $this->netDebit($clients->id));
    }

    /**
     * Revisions keep counting past two — the fix is a counter, not a special case for "the second
     * edit".
     */
    public function test_revisions_keep_increasing_for_every_further_edit(): void
    {
        [$company, $branch] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');
        $clients = $this->accountByCode($company->id, '1351');

        /** @var PostingService $posting */
        $posting = app(PostingService::class);
        $base = 'r2-2-probe:9';

        $current = $posting->post($this->twoLineDraft($company, $branch, $bank, $clients, 1.0, $base))->transaction;

        foreach ([2.0, 3.0, 4.0, 5.0] as $amount) {
            $current = $posting->repost(
                Transaction::withoutGlobalScopes()->findOrFail($current->id),
                $this->twoLineDraft($company, $branch, $bank, $clients, $amount, $base),
                now(),
                null
            )->transaction;
        }

        $this->assertSame($base.':rev4', (string) $current->fresh()->idempotency_key);
        $this->assertSame(5.0, $this->netDebit($bank->id));

        // Five distinct documents, no key reused.
        $keys = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'like', $base.'%')
            ->pluck('idempotency_key')
            ->all();

        $this->assertCount(5, $keys);
        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // The path the report measured
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The operator-visible half of D6: a receipt voucher edited TWICE. Before the fix the second
     * edit left the bank leaf at zero, the receivable still open, the invoice marked `paid`, and
     * the operator told the edit succeeded.
     */
    public function test_a_receipt_voucher_edited_twice_leaves_the_final_amount_on_the_ledger(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');
        $clientsLeaf = $this->accountByCode($company->id, '1351');

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 300.000,
            'status' => 'unpaid',
            'invoice_date' => now(),
        ]);

        $receipt = InvoiceReceipt::create([
            'type' => 'invoice',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 100]],
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'bank_account_id' => $bank->id,
            'status' => InvoiceReceipt::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $payload = fn (float $amount): array => [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'invoice',
            'client_id' => $client->id,
            'amount' => $amount,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
            'bank_account_id' => $bank->id,
        ];

        $this->actingAs($admin)->put(route('receipt-voucher.update', $receipt->id), $payload(150.0))->assertRedirect();
        $this->actingAs($admin)->put(route('receipt-voucher.update', $receipt->id), $payload(200.0))->assertRedirect();

        $receipt->refresh();

        // THE MONEY: the bank carries 200, the last amount the operator entered — not 0 (D6) and
        // not 100 (the original).
        $this->assertSame(200.0, $this->netDebit($bank->id), 'A twice-edited receipt must leave the FINAL amount in the bank.');
        $this->assertSame(-200.0, $this->netDebit($clientsLeaf->id));

        // And the row points at a LIVE document, not at the dead original.
        $live = Transaction::withoutGlobalScopes()->findOrFail($receipt->transaction_id);
        $this->assertSame('posted', (string) $live->posting_status, 'transaction_id must name the live revision, not a reversed one.');
        $this->assertSame(200.0, round((float) $live->total_debit, 3));
        $this->assertStringStartsWith('rv:'.$receipt->id, (string) $live->idempotency_key);
        $this->assertNotSame('rv:'.$receipt->id, (string) $live->idempotency_key, 'The replacement must not re-occupy the base key.');
    }

    /**
     * The operator response has to be TRUE. `update()` reports success only when a live
     * replacement document actually exists carrying the amount that was typed — this asserts the
     * two together, because D6's whole signature was a truthful-looking message over an empty
     * ledger.
     */
    public function test_the_operator_response_matches_what_reached_the_ledger(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');

        $receipt = InvoiceReceipt::create([
            'type' => 'account',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'account_id' => $this->accountByCode($company->id, '1351')->id,
            'client_id' => $client->id,
            'amount' => 40,
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'bank_account_id' => $bank->id,
            'status' => InvoiceReceipt::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $payload = fn (float $amount): array => [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $this->accountByCode($company->id, '1351')->id,
            'client_id' => $client->id,
            'amount' => $amount,
            'bank_account_id' => $bank->id,
        ];

        $this->actingAs($admin)->put(route('receipt-voucher.update', $receipt->id), $payload(55.0));
        $second = $this->actingAs($admin)->put(route('receipt-voucher.update', $receipt->id), $payload(77.0));

        $second->assertSessionHas('success');
        $second->assertSessionMissing('error');

        $this->assertSame(77.0, $this->netDebit($bank->id));
    }
}
