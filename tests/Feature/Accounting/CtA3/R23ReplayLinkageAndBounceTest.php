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
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 R2-3 — VERIFY-CT-A3-STACK-R1 §3.2 **D7, BLOCKER**: *"`bounce()` reverses nothing for every
 * receipt the replay backfills."*
 *
 * ── The defect, restated from the verify report ─────────────────────────────────────────────────
 * `ReceiptVoucherController::bounce()` gates the receipt reversal on the row's own linkage column:
 *
 * ```php
 * if ($bounceDecision->shouldReverse && $invoiceReceipt->transaction_id !== null) { … }
 * $invoiceReceipt->cheque_clearance_date = null;
 * $invoiceReceipt->status = InvoiceReceipt::STATUS_BOUNCED;   // ← OUTSIDE the guard
 * ```
 *
 * …and `ReceiptReplaySource::replay()` posted the document but never wrote `transaction_id` back —
 * *"grep for `transaction_id` across `app/Services/Accounting/Replay/` returns one unrelated
 * comment"*. So every receipt the cutover backfill posts had a LIVE `rv:{id}` document on the
 * ledger and a NULL `transaction_id` on its row. A bounce then marked the row `bounced`, left the
 * receipt document standing and the invoice allocations `paid`, and reported success.
 *
 * That is exactly the defect W2-2 exists to close — *"a bounced cheque left the agency showing a
 * collected receivable, a settled invoice, and a permanent debit in the cheque float for money it
 * never received"* — still open for the entire replay population. `destroy()` had the mirror
 * problem: `findOrFail($invoiceReceipt->transaction_id)` throws on those rows.
 *
 * It is a STACK-INTRODUCED interaction: the replay is wave 2's own new code and the bounce
 * reversal is wave 2's own new fix, and neither was tested against the other.
 *
 * ── The fix this file pins, both halves ─────────────────────────────────────────────────────────
 *  1. **The replay writes back the same linkage the live feeder does.** `postVoucher()` sets
 *     `invoice_receipts.transaction_id` after posting; the replay source now does the same, so a
 *     backfilled row is indistinguishable from a live-posted one to every consumer of that column.
 *  2. **`bounce()`, `destroy()` and `update()` no longer trust that column alone.** They resolve
 *     the posted document by the receipt's own idempotency key family (`rv:{id}` and its
 *     revisions) when `transaction_id` is missing or stale — the fix shape the report itself
 *     names. Belt and braces on purpose: half 1 fixes new backfills, half 2 fixes every row a
 *     backfill has already left behind.
 */
class R23ReplayLinkageAndBounceTest extends AccountingTestCase
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
        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /**
     * A receipt in exactly the state a cutover leaves behind: `approved`, allocated to an invoice,
     * a cleared cheque — and NO `transaction_id`, because it was never posted through the live
     * approve() path. This is the legacy shape (CT-A2 §3.2: 109 of 109 rows).
     */
    private function makeUnpostedApprovedReceipt(Company $company, Branch $branch, Client $client, Invoice $invoice, float $amount = 100.0): InvoiceReceipt
    {
        return InvoiceReceipt::create([
            'type' => 'invoice',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->subDay()->toDateString(),
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'bank_account_id' => $this->accountByCode($company->id, '1201')->id,
            'cheque_no' => 'CHQ-R23',
            'cheque_date' => now()->subDays(2)->toDateString(),
            'cheque_clearance_date' => now()->subDay()->toDateString(),
            'status' => InvoiceReceipt::STATUS_APPROVED,
            'transaction_id' => null,
        ]);
    }

    private function netDebit(int $accountId): float
    {
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $accountId);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Half 1 — the replay writes the linkage back
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * THE MISSING LINK. After `accounting:replay --class=receipt`, the row must carry the id of
     * the document that was posted for it — the same column, with the same meaning,
     * `postVoucher()` writes on the live path.
     */
    public function test_the_receipt_replay_writes_the_posted_document_back_onto_the_row(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 100.000,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $receipt = $this->makeUnpostedApprovedReceipt($company, $branch, $client, $invoice);

        Artisan::call('accounting:replay', ['--company' => (string) $company->id, '--class' => 'receipt']);

        $receipt->refresh();

        $document = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'rv:'.$receipt->id)
            ->first();

        $this->assertNotNull($document, 'precondition: the replay posted the receipt document');
        $this->assertSame(
            (int) $document->id,
            (int) $receipt->transaction_id,
            'The replay must write the SAME linkage the live feeder writes — every bounce, delete '
                .'and edit path reads this column.'
        );
    }

    /** A dry run must write NOTHING — including the linkage. */
    public function test_a_dry_run_writes_no_linkage_back(): void
    {
        [$company, $branch, $agent, $client] = $this->makeFixture();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 100.000,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $receipt = $this->makeUnpostedApprovedReceipt($company, $branch, $client, $invoice);

        Artisan::call('accounting:replay', [
            '--company' => (string) $company->id,
            '--class' => 'receipt',
            '--dry-run' => true,
        ]);

        $this->assertNull($receipt->fresh()->transaction_id);
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'rv:'.$receipt->id)->count()
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Half 2 — bounce() finds the document even without the column
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * THE DEFECT. A replayed receipt, then a bounce. Before the fix the bounce marked the row
     * `bounced` and returned success while the receipt document stayed live on the ledger and the
     * invoice stayed `paid` — money the agency never received, still shown as collected.
     *
     * The row's `transaction_id` is nulled out AFTER the replay here on purpose: it reproduces
     * every row an EARLIER backfill (one run before half 1 shipped) already left behind, which is
     * the population that made this a blocker.
     */
    public function test_bouncing_a_replayed_receipt_reverses_the_ledger(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 100.000,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $receipt = $this->makeUnpostedApprovedReceipt($company, $branch, $client, $invoice);

        Artisan::call('accounting:replay', ['--company' => (string) $company->id, '--class' => 'receipt']);

        $document = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'rv:'.$receipt->id)
            ->firstOrFail();

        $bank = $this->accountByCode($company->id, '1201');
        $this->assertSame(100.0, $this->netDebit($bank->id), 'precondition: the replay put the money in the bank');

        // The state a pre-R2-3 backfill leaves: a live document, no linkage.
        InvoiceReceipt::withoutGlobalScopes()->whereKey($receipt->id)->update(['transaction_id' => null]);

        $this->actingAs($admin)->post(route('receipt-voucher.bounce', $receipt->id), [
            'bounce_fee_amount' => 0,
        ])->assertRedirect();

        $this->assertSame(
            'reversed',
            (string) $document->fresh()->posting_status,
            'A bounced cheque must reverse the RECEIPT document even when the row carries no '
                .'transaction_id — the replay population is exactly that shape.'
        );

        $this->assertSame(
            0.0,
            $this->netDebit($bank->id),
            'The bank must be back to zero: the cheque did not clear, the money never arrived.'
        );

        $this->assertSame(InvoiceReceipt::STATUS_BOUNCED, (string) $receipt->fresh()->status);
    }

    /**
     * `destroy()`'s mirror of the same defect: `findOrFail($invoiceReceipt->transaction_id)` threw
     * a raw ModelNotFoundException on a replayed row, so the voucher could not be reversed at all.
     */
    public function test_deleting_a_replayed_receipt_reverses_rather_than_throwing(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 100.000,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $receipt = $this->makeUnpostedApprovedReceipt($company, $branch, $client, $invoice);

        Artisan::call('accounting:replay', ['--company' => (string) $company->id, '--class' => 'receipt']);

        $document = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'rv:'.$receipt->id)
            ->firstOrFail();

        InvoiceReceipt::withoutGlobalScopes()->whereKey($receipt->id)->update(['transaction_id' => null]);

        $bank = $this->accountByCode($company->id, '1201');

        $this->actingAs($admin)->delete(route('receipt-voucher.destroy', $receipt->id))->assertRedirect();

        $this->assertSame('reversed', (string) $document->fresh()->posting_status);
        $this->assertSame(0.0, $this->netDebit($bank->id));
        $this->assertSame(InvoiceReceipt::STATUS_REVERSED, (string) $receipt->fresh()->status);
    }

    /**
     * THE SHAPE THE SERVER RUN FOUND, and the reason this file gained a case after it was written.
     *
     * `invoice_receipts.company_id` is NULL on every legacy row on the City Travelers data (CT-F35,
     * 109 of 109). The first cut of this fix resolved the document with
     * `where('company_id', (int) $invoiceReceipt->company_id)` — which casts that NULL to the
     * sentinel 0, matches nothing, and made the whole fix silently do nothing for exactly the
     * population it exists for. `bounce()` then also gated the engine on `isEnabledFor(0)`, which
     * is false, so even a found document would have been DELETED rather than reversed.
     *
     * Not caught by the cases above, and could not have been: every one of them builds a receipt
     * through a fixture, and a fixture that omitted `company_id` could not be built. It was caught
     * by running the bounce lifecycle against the real dataset on the scratch copy — which is what
     * that exercise is for.
     */
    public function test_a_legacy_receipt_with_no_company_id_still_bounces(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 100.000,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $receipt = $this->makeUnpostedApprovedReceipt($company, $branch, $client, $invoice);

        Artisan::call('accounting:replay', ['--company' => (string) $company->id, '--class' => 'receipt']);

        $document = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'rv:'.$receipt->id)
            ->firstOrFail();

        $bank = $this->accountByCode($company->id, '1201');
        $this->assertSame(100.0, $this->netDebit($bank->id), 'precondition: the replay posted the receipt');

        // THE LEGACY SHAPE: no linkage AND no company_id of its own. The company is recoverable
        // only through invoice -> agent -> branch.
        InvoiceReceipt::withoutGlobalScopes()->whereKey($receipt->id)->update([
            'transaction_id' => null,
            'company_id' => null,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.bounce', $receipt->id), [
            'bounce_fee_amount' => 0,
        ])->assertRedirect();

        $this->assertSame(
            'reversed',
            (string) $document->fresh()->posting_status,
            'A NULL company_id must be RESOLVED, not cast to the sentinel 0 — otherwise the whole '
                .'fix silently no-ops for the entire legacy population.'
        );
        $this->assertSame(0.0, $this->netDebit($bank->id));
        $this->assertSame(InvoiceReceipt::STATUS_BOUNCED, (string) $receipt->fresh()->status);

        // And it was REVERSED, not deleted: the original lines survive as a dated REV pair.
        $this->assertGreaterThan(0, JournalEntry::where('transaction_id', $document->id)->count());
    }

    /**
     * A receipt with NO posted document at all (a genuine `pending` draft that somehow reached a
     * bounce) must still not invent one to reverse. The resolution is by key, so "no document"
     * stays "no document" — the fix widens the lookup, it does not weaken the guard.
     */
    public function test_a_receipt_with_no_posted_document_reverses_nothing(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 100.000,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $receipt = $this->makeUnpostedApprovedReceipt($company, $branch, $client, $invoice);

        $this->actingAs($admin)->post(route('receipt-voucher.bounce', $receipt->id), [
            'bounce_fee_amount' => 0,
        ])->assertRedirect();

        $this->assertSame(InvoiceReceipt::STATUS_BOUNCED, (string) $receipt->fresh()->status);
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('idempotency_key')->count(),
            'Nothing was ever posted for this receipt, so the bounce must post and reverse nothing.'
        );
    }
}
