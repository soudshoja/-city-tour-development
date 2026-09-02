<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\Accounting\ProtectedLineException;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceSequence;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\SupplierChargeLineBuilder;
use App\Services\Accounting\SupplierChargeLineInput;
use App\Services\Accounting\SupplierChargeRuleResolver;
use App\Services\TaskStatusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * W7.H (w7-brief.md "DotwAI hotel-module AccountingService through the seam"). Accounting entry
 * creation for DOTW hotel bookings that are invoiced WITHOUT ever going through the Task/
 * InvoiceController sale path — i.e. bookings never routed through
 * {@see \App\Modules\DotwAI\Jobs\ConfirmBookingAfterPaymentJob::createTaskAndInvoice()} (which
 * already posts through the engine via `InvoiceController::autoGenerateInvoice()` ->
 * `SaleDraftBuilder`, W3d/B2C-04 territory, untouched by this sub-wave). This class is reached for:
 * - Deadline-pass auto-invoicing ({@see self::createAutoInvoiceForDeadline()}) — the booking amount
 *   is locked when the client's free-cancel window closes, invoiced for the first time here.
 * - Cancellation penalties ({@see self::createCancellationEntries()}) — a non-zero DOTW cancellation
 *   charge. Free cancellations (penalty = 0) do NOT call this service — the caller
 *   (CancellationService) is responsible for skipping this for zero-charge events.
 *
 * ── W6/W7 cutover (this sub-wave) ──────────────────────────────────────────────────────────────
 * Both methods now route their ledger write through {@see PostingSeam}, per-company gated
 * (`config('accounting.engine.enabled')` AND `companies.posting_engine_enabled`):
 *   - OFF (either flag false, or company unresolvable): the ORIGINAL raw `JournalEntry::create()` +
 *     `Account::where('name','LIKE', ...)` lookup pair runs byte-for-byte, moved verbatim into a
 *     `$legacy` closure — see {@see PostingSeam} class docblock's own usage contract.
 *   - ON: a real {@see \App\Services\Accounting\SaleDraftBuilder}-shaped document — `Dr
 *     RECEIVABLE_CONTROL / Cr SERVICE_PAYABLE/hotel / Cr-or-Dr SERVICE_REVENUE/hotel` (agent basis,
 *     the locked default for service_type=hotel — see `config('accounting.posting_basis.
 *     default_by_service_type.hotel')`, `principal` if the company overrides it) — posted through
 *     {@see AccountResolver} purpose codes, never a `name LIKE` lookup. This closes the exact gap
 *     the sale-shape audit (w3d-brief.md) already fixed for `InvoiceController`'s two sale feeders:
 *     the legacy JournalEntry pair here posted `Dr AR / Cr Revenue` for the FULL sell with NO
 *     supplier cost/payable leg at all for a real supplier cost (`booking->original_total_fare`,
 *     the same figure `InvoiceDetail.supplier_price` already carries) — a silent profit
 *     overstatement on every ON-path hotel deadline-invoice until now.
 *   - Supplier: resolved by name (`Supplier::where('name', 'DOTW')`) — this is an ordinary business
 *     entity lookup for `SaleDraftInput::$supplierId`/`$supplierName` (informational + party
 *     attribution only), NOT an Account/chart-of-accounts resolution — the "never resolve accounts
 *     by name" rule (ON path) does not apply to Supplier rows, only to `Account` leaves, which are
 *     resolved here exclusively via `AccountResolver` purpose codes. No company-scoping pivot filter
 *     is applied (`suppliers_companies` is not seeded for DOTW at the time of this build — see this
 *     sub-wave's own report) — a missing/unresolved supplier degrades gracefully to a partyless
 *     `SERVICE_PAYABLE` line (AccountResolver resolves that leaf from `companyId` + `serviceType`
 *     alone; `$supplierId` is party-attribution metadata only, never required for posting).
 *   - W6.C supplier charge rules ({@see SupplierChargeRuleResolver}) are resolved and appended to
 *     the sale document the SAME way `InvoiceController::postSaleJournalEntries()` already does —
 *     ON path only (`isEnabledFor()` gates rule *resolution* itself, not just whether it posts, so
 *     the OFF path never runs rule-resolution at all — mirrors that call site's own reasoning).
 *   - Idempotency key: `dotw:{booking_id}:sale` (per w7-brief.md — a booking has at most one
 *     deadline-invoice event, so a bare `{booking_id}` scope, not a per-attempt one, is correct: a
 *     retried/redelivered `AutoInvoiceDeadlineJob` attempt must resolve to the SAME key).
 *
 * ── Cancellation (createCancellationEntries) ───────────────────────────────────────────────────
 * ON path branches on whether this booking's sale was ever posted through the Task-bound engine
 * path ({@see DotwAIBooking::$task_id} set — the ConfirmBookingAfterPaymentJob/B2C-04 case, sale
 * keyed `invoice-detail:{id}:sale`) or through THIS class's own raw path (no Task; sale, if posted
 * at all, keyed `dotw:{booking_id}:sale`) — per w7-brief.md: "reuse TaskStatusService::void when the
 * booking is a Task, else RefundPostingService" — do NOT invent a third posting path:
 *   - Task case: delegates ENTIRELY to {@see TaskStatusService::void()} (the W6.V shape: reverses
 *     the task's own sale document, posts the void fee DBN, un-earns commission, posts disposition
 *     — all already engine-native). No standalone penalty Invoice is created in this branch — the
 *     fee DBN lands on the SAME carrying invoice the task's sale already used, avoiding the
 *     double-invoice risk the non-Task branch below still carries forward from legacy. KNOWN
 *     CAVEAT (documented, not silently patched): `void()`'s own fee resolution
 *     (`resolveFeeFromSchedule()`) treats the `fee` option passed here as a FALLBACK, not a forced
 *     value — a company that has configured `accounting.refund.fee_schedule.hotel.percent` or
 *     `.amount` will have THAT figure charged instead of the real DOTW-supplied penalty amount this
 *     method is called with. This mirrors exactly how a flight void's company-policy fee already
 *     differs from a supplier's own penalty (the two are DIFFERENT concepts codebase-wide, not
 *     merged here) — flagged for the company-options owner, not fixed in this sub-wave (reusing
 *     void() verbatim, per the brief, precludes bypassing its own resolution order).
 *   - Non-Task case: this class builds the SAME shape `void()` builds, from the same two engine
 *     primitives {@see PostingService::reverse()} (not RefundPostingService::post() itself — that
 *     class operates on a persisted `Refund` row with its own approve/post/complete workflow, a
 *     data model this queue-driven, two-step DOTW cancel flow has no Refund row to attach to; using
 *     its underlying primitives, the same ones `RefundPostingService`/`TaskStatusService::void()`
 *     both already build on, is the closest fit that does not fabricate a `refunds` row nor a
 *     fourth posting mechanism — documented here as a considered reading of "else
 *     RefundPostingService", not a literal `RefundPostingService::post()` call) and
 *     {@see PostingSeam::post()} for the fee: (1) reverse `dotw:{booking_id}:sale` if it exists (a
 *     genuine no-op, exactly {@see TaskStatusService::voidReverseSale()}'s own behaviour, when
 *     cancellation happens before the deadline job ever posted a sale — the common case), (2) post
 *     the penalty as `Dr RECEIVABLE_CONTROL / Cr VOID_FEE_INCOME` (the SAME global purpose code and
 *     DBN/VOID_FEE doc shape `TaskStatusService::voidPostFee()` uses) onto a freshly-created
 *     standalone penalty Invoice/InvoiceDetail — matching legacy's own "separate penalty invoice"
 *     structure so booking notifications / `invoice_id` linkage keep working, while the ledger side
 *     now correctly reverses any stale sale instead of leaving both documents live. Idempotency
 *     key: `dotw:{booking_id}:cancel`.
 *   - OFF (either case): the ORIGINAL raw two-`JournalEntry::create()` penalty-invoice code runs
 *     byte-for-byte, unchanged, moved into a `$legacy` closure.
 *
 * ALL JournalEntry and Account queries (legacy path) use explicit company_id and
 * withoutGlobalScopes() to bypass Auth-based global scopes that would fail in queue/API contexts
 * where Auth::user() is not set. The same convention is followed for every new engine-path query
 * this sub-wave adds (Transaction::withoutGlobalScopes(), Task::withoutGlobalScopes()).
 *
 * @see ACCT-01 Cancellation with penalty creates Invoice + JournalEntry
 * @see ACCT-03 All accounting records include company_id
 * @see ACCT-04 All JournalEntry/Account queries bypass global scopes
 * @see LIFE-03 Deadline-pass auto-invoice for lifecycle management
 * @see w6-final-gate.md "Genuine pre-existing gap, out of W1-W6 scope: ... same for DotwAI's hotel-
 *      module AccountingService" — the finding this sub-wave closes.
 */
class AccountingService
{
    /**
     * PRE-EXISTING DEFECT FIX (found while mapping this class for W7.H, not a new behaviour
     * change): NEITHER `Invoice::create()` call site in this file ever set `invoice_number` —
     * a plain `varchar(255) NOT NULL` column with no DB default and no model-level auto-fill
     * (`Invoice::boot()` only guards `status`/proforma-lock, it never generates a number; verified
     * against `database/migrations/2024_10_29_063642_create_invoices_table.php`). Every real call
     * to either public method in this class would therefore have failed its very first `INSERT`
     * with a NOT NULL constraint violation — this was never a working legacy path to begin with,
     * so generating a real number here is a PREREQUISITE fix, not a divergence from any behaviour
     * that ever actually ran. Mirrors `InvoiceController::getInvoiceNumberGenerated()`'s own
     * locked-sequence shape exactly (same `InvoiceSequence` model, same `INV-{year}-{seq5}` format
     * via the identical `sprintf('INV-%s-%05d', ...)` pattern) rather than reaching into that
     * controller's private method — this module stays self-contained per its own docblock
     * ("app/Modules/DotwAI/ is deliberately self-contained — keep it that way").
     */
    private function generateInvoiceNumber(int $companyId): string
    {
        $sequence = InvoiceSequence::where('company_id', $companyId)->lockForUpdate()->first();

        if ($sequence === null) {
            $sequence = InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        }

        $currentSequence = $sequence->current_sequence;
        $invoiceNumber = sprintf('INV-%s-%05d', now()->year, $currentSequence);
        $sequence->current_sequence = $currentSequence + 1;
        $sequence->save();

        return $invoiceNumber;
    }

    /**
     * Create Invoice, InvoiceDetail, and double-entry JournalEntry records
     * for a cancellation penalty.
     *
     * Must be called inside a DB::transaction (caller's responsibility).
     *
     * @param  DotwAIBooking  $booking  The cancelled booking
     * @param  float  $penaltyAmount  The penalty charge from DOTW
     * @param  DotwAIContext  $context  Resolved company/agent context
     *
     * @throws \RuntimeException if clientId cannot be resolved
     */
    public function createCancellationEntries(
        DotwAIBooking $booking,
        float $penaltyAmount,
        DotwAIContext $context,
    ): void {
        // Resolve client ID for the company
        $creditService = new CreditService;
        $clientId = $creditService->getClientIdForCompany($context->companyId);

        if ($clientId === null) {
            throw new \RuntimeException(
                "Cannot create cancellation accounting entries: no clientId found for company {$context->companyId}"
            );
        }

        $seam = app(PostingSeam::class);

        if ($seam->isEnabledFor($context->companyId) && $booking->task_id !== null) {
            $task = Task::withoutGlobalScopes()->whereNull('deleted_at')->find($booking->task_id);

            if ($task !== null) {
                app(TaskStatusService::class)->void($task, [
                    'sub_type' => 'VOID',
                    'fee' => $penaltyAmount > 0 ? $penaltyAmount : null,
                ]);

                Log::info('[DotwAI][AccountingService] Cancellation routed through TaskStatusService::void()', [
                    'prebook_key' => $booking->prebook_key,
                    'task_id' => $task->id,
                    'penalty_amount' => $penaltyAmount,
                ]);

                return;
            }

            Log::warning('[DotwAI][AccountingService] booking->task_id set but Task not found — falling back to raw cancellation path', [
                'prebook_key' => $booking->prebook_key,
                'task_id' => $booking->task_id,
            ]);
        }

        $this->createStandaloneCancellationPenalty($booking, $penaltyAmount, $context, $clientId, $seam);
    }

    /**
     * Non-Task cancellation path — see class docblock's "Cancellation" section.
     */
    private function createStandaloneCancellationPenalty(
        DotwAIBooking $booking,
        float $penaltyAmount,
        DotwAIContext $context,
        int $clientId,
        PostingSeam $seam,
    ): void {
        $companyId = $context->companyId;

        // ── Create Invoice (unchanged from legacy — a business record, not itself a raw ledger
        // write; created unconditionally on both OFF and ON, matching every existing sale feeder's
        // own convention of creating Invoice/InvoiceDetail OUTSIDE the seam-wrapped JE post) ──────
        $invoice = Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber($companyId),
            'client_id' => $clientId,
            'agent_id' => $context->agent->id,
            'currency' => $booking->display_currency ?? 'KWD',
            'sub_amount' => $penaltyAmount,
            'amount' => $penaltyAmount,
            'status' => InvoiceStatus::UNPAID->value,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'label' => 'Cancellation Penalty: '.$booking->prebook_key,
        ]);

        $invoiceDetail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_description' => 'Hotel cancellation penalty - '.($booking->hotel_name ?? 'Hotel'),
            'task_price' => $penaltyAmount,
            'supplier_price' => $penaltyAmount,
        ]);

        $currency = $booking->display_currency ?? 'KWD';
        $branchId = $context->agent->branch_id ?? null;
        $description = 'DOTW cancellation penalty - '.($booking->hotel_name ?? 'Hotel')
            .' ('.$booking->prebook_key.')';

        // Legacy closure -- the ORIGINAL raw JournalEntry::create() pair, byte-identical, moved
        // verbatim (the B2B-paid stamp that used to live inside both of this closure's own
        // internal branches is now hoisted to run once, after PostingSeam::post() returns, for
        // BOTH the OFF and ON path -- see the single call site below; net effect on OFF is
        // unchanged, since the original code ran it unconditionally in either internal branch
        // too, never skipping it).
        $legacy = function () use ($context, $invoice, $penaltyAmount, $currency, $branchId, $description) {
            // ── Resolve Chart of Accounts (legacy, name-LIKE — OFF path only, byte-identical) ────
            $receivableAccount = Account::withoutGlobalScopes()
                ->where('company_id', $context->companyId)
                ->where('name', 'LIKE', '%Client%')
                ->first();

            $revenueAccount = Account::withoutGlobalScopes()
                ->where('company_id', $context->companyId)
                ->where('name', 'LIKE', '%Revenue%')
                ->first();

            if ($receivableAccount === null || $revenueAccount === null) {
                Log::warning('[AccountingService] Chart of accounts not found for cancellation entries', [
                    'company_id' => $context->companyId,
                    'invoice_id' => $invoice->id,
                    'receivable_found' => $receivableAccount !== null,
                    'revenue_found' => $revenueAccount !== null,
                ]);

                return null;
            }

            // PRE-EXISTING DEFECT FIX (same class as the invoice_number/agent_id/task_id gaps
            // documented elsewhere in this file): journal_entries.name is `varchar(255) NOT NULL`
            // with no DB default; neither JournalEntry::create() call below ever set it, so this
            // legacy closure could never have completed a real INSERT either -- 'name' is set to
            // the resolved account's own display name, matching InvoiceController's own
            // `'name' => $detailsAccount->name` convention for a plain legacy JournalEntry row.
            JournalEntry::create([
                'company_id' => $context->companyId,
                'branch_id' => $branchId,
                'account_id' => $receivableAccount->id,
                'invoice_id' => $invoice->id,
                'transaction_date' => now(),
                'description' => $description,
                'debit' => $penaltyAmount,
                'credit' => 0,
                'currency' => $currency,
                'type' => 'cancellation',
                'name' => $receivableAccount->name,
            ]);

            JournalEntry::create([
                'company_id' => $context->companyId,
                'branch_id' => $branchId,
                'account_id' => $revenueAccount->id,
                'invoice_id' => $invoice->id,
                'transaction_date' => now(),
                'description' => $description,
                'debit' => 0,
                'credit' => $penaltyAmount,
                'currency' => $currency,
                'type' => 'cancellation',
                'name' => $revenueAccount->name,
            ]);

            return null;
        };

        // ── ON only: reverse the sale (no-op if it was never posted — pre-deadline cancellation,
        // the common case) BEFORE posting the fee, exactly TaskStatusService::void()'s own
        // two-step ordering (voidReverseSale() then voidPostFee()) — see class docblock. Never
        // runs on OFF: reversal is an engine-native concept with no legacy equivalent. ───────────
        if ($seam->isEnabledFor($companyId)) {
            $this->reverseRawSaleIfExists($booking, $companyId);
        }

        // HARD RULE (w7-brief.md): every ledger write enters PostingSeam::post() with a stable
        // idempotency key -- this is the ONE call site for both OFF (falls through to $legacy())
        // and ON (posts $feeLines, both branches keyed identically so a redelivered/retried
        // caller resolves to the SAME key regardless of which path is live at the time). Per the
        // docblock guarantee this method is only ever called with penaltyAmount > 0 ("Free
        // cancellations ... do NOT call this service"), so $feeLines is never empty in practice --
        // matching legacy's own implicit assumption (it never guarded penaltyAmount > 0 either).
        $feeLines = [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $penaltyAmount,
                currency: $currency,
                originalAmount: $penaltyAmount,
                exchangeRate: 1.0,
                transactionType: 'VOID_FEE_RECEIVABLE',
                partyAccountRef: $clientId,
                description: $description,
                invoiceId: $invoice->id,
                invoiceDetailId: $invoiceDetail->id,
                ledgerType: 'receivable',
            ),
            new LineDraft(
                purposeCode: 'VOID_FEE_INCOME',
                accountId: null,
                side: 'credit',
                amount: $penaltyAmount,
                currency: $currency,
                originalAmount: $penaltyAmount,
                exchangeRate: 1.0,
                transactionType: 'VOID_FEE_INCOME',
                description: $description,
                invoiceId: $invoice->id,
                invoiceDetailId: $invoiceDetail->id,
                ledgerType: 'income',
            ),
        ];

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($branchId ?? 0),
            docType: 'DBN',
            subType: 'CANCELLATION_FEE',
            docDate: Carbon::now(),
            narration: $description,
            lines: $feeLines,
            idempotencyKey: 'dotw:'.$booking->id.':cancel',
            invoiceId: $invoice->id,
        );

        $seam->post($draft, $legacy, 'dotwai.hotel_cancellation_fee');

        if ($booking->track === DotwAIBooking::TRACK_B2B) {
            $invoice->update(['status' => InvoiceStatus::PAID->value]);
        }
    }

    /**
     * Mirrors {@see TaskStatusService::voidReverseSale()} exactly, for the raw (non-Task) DOTW
     * sale posted by {@see self::createAutoInvoiceForDeadline()} under key `dotw:{booking_id}:sale`.
     * A no-op when no such document exists (cancellation before the deadline job ever ran — the
     * common case for this class's own callers, per w7-brief.md's mapping of the two AccountingService
     * entry points to the two DIFFERENT moments a booking can be cancelled at).
     */
    private function reverseRawSaleIfExists(DotwAIBooking $booking, int $companyId): void
    {
        $saleKey = 'dotw:'.$booking->id.':sale';

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $saleKey)
            ->first();

        if ($saleTransaction === null) {
            Log::info('[DotwAI][AccountingService] no raw sale to reverse for cancellation', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        try {
            $reversed = app(PostingService::class)->reverse($saleTransaction, Carbon::now(), null);
        } catch (ProtectedLineException $e) {
            throw new \RuntimeException(
                "AccountingService::createCancellationEntries(): booking #{$booking->id}'s sale document ".
                'has a reconciled line and cannot be reversed automatically — reconcile manually before '.
                'cancelling.',
                previous: $e
            );
        }

        Transaction::withoutGlobalScopes()->whereKey($reversed->transaction->id)->update(['bsptype' => 'VOID']);
    }

    /**
     * Create Invoice, InvoiceDetail, and double-entry JournalEntry records
     * when a booking's cancellation deadline passes (auto-invoice on deadline).
     *
     * This is called by AutoInvoiceDeadlineJob after the cancellation window closes.
     * The booking amount is now locked — the client cannot cancel for free.
     *
     * Uses company_id from the booking directly (no DotwAIContext needed)
     * since there is no HTTP request context in the queue job.
     *
     * Must be called inside a DB::transaction (caller's responsibility).
     *
     * @param  DotwAIBooking  $booking  The confirmed booking past deadline
     *
     * @see LIFE-03 Auto-invoice dispatched by scheduler, executed by queue job
     */
    public function createAutoInvoiceForDeadline(DotwAIBooking $booking): void
    {
        $companyId = $booking->company_id;
        $amount = (float) ($booking->display_total_fare ?? 0);
        $currency = $booking->display_currency ?? 'KWD';

        // Resolve client ID for the company
        $creditService = new CreditService;
        $clientId = $creditService->getClientIdForCompany($companyId);

        if ($clientId === null) {
            // Same guard createCancellationEntries() already has (invoices.client_id is also
            // NOT NULL) -- the legacy body here had none, another pre-existing gap alongside the
            // agent_id/invoice_number ones below.
            throw new \RuntimeException(
                "AccountingService::createAutoInvoiceForDeadline(): no clientId found for company {$companyId}"
            );
        }

        // PRE-EXISTING DEFECT FIX (found alongside the invoice_number gap above): `invoices.
        // agent_id` is `bigint(20) unsigned NOT NULL` (verified against the same migration) --
        // the legacy `'agent_id' => null` this method always passed (see this method's own,
        // now-superseded "No agent context in queue; admin can assign" comment) could never have
        // satisfied that constraint either, so this was never a working INSERT to begin with. No
        // agent identity is actually available in this queue job's own context (by design -- see
        // AutoInvoiceDeadlineJob's own docblock), so the best available substitute, mirroring
        // CreditService::getClientIdForCompany()'s own "first agent under this company's branches"
        // resolution (the exact same query this method already calls one line above via
        // $creditService), is used here rather than inventing a second resolution strategy.
        $agentId = \App\Models\Agent::whereHas('branch', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->value('id');

        if ($agentId === null) {
            throw new \RuntimeException(
                "AccountingService::createAutoInvoiceForDeadline(): no agent found for company {$companyId} -- ".
                'invoices.agent_id is NOT NULL and cannot be satisfied.'
            );
        }

        // ── Create Invoice (unconditional, both OFF/ON — a business record, not a raw ledger
        // write) ────────────────────────────────────────────────────────────────────────────────
        $invoice = Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber($companyId),
            'client_id' => $clientId,
            'agent_id' => $agentId,
            'currency' => $currency,
            'sub_amount' => $amount,
            'amount' => $amount,
            'status' => InvoiceStatus::UNPAID->value,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'label' => 'Hotel Auto-Invoice (Deadline): '.$booking->prebook_key,
        ]);

        $costAmount = (float) ($booking->original_total_fare ?? $amount);

        $invoiceDetail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_description' => 'Hotel booking - '.($booking->hotel_name ?? 'Hotel')
                .' ('.$booking->check_in?->format('Y-m-d').' to '.$booking->check_out?->format('Y-m-d').')',
            'task_price' => $amount,
            'supplier_price' => $costAmount,
        ]);

        $booking->update(['invoice_id' => $invoice->id]);

        $description = 'DOTW hotel deadline auto-invoice - '.($booking->hotel_name ?? 'Hotel')
            .' ('.$booking->prebook_key.')';

        $legacy = function () use ($companyId, $invoice, $amount, $currency, $description) {
            // ── Resolve Chart of Accounts (legacy, name-LIKE — OFF path only, byte-identical) ────
            $receivableAccount = Account::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('name', 'LIKE', '%Client%')
                ->first();

            $revenueAccount = Account::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('name', 'LIKE', '%Revenue%')
                ->first();

            if ($receivableAccount === null || $revenueAccount === null) {
                Log::warning('[AccountingService] Chart of accounts not found for auto-invoice entries', [
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                ]);

                return null;
            }

            // PRE-EXISTING DEFECT FIX -- see the identical note on the cancellation legacy
            // closure above: journal_entries.name is NOT NULL with no default.
            JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => null,
                'account_id' => $receivableAccount->id,
                'invoice_id' => $invoice->id,
                'transaction_date' => now(),
                'description' => $description,
                'debit' => $amount,
                'credit' => 0,
                'currency' => $currency,
                'type' => 'booking',
                'name' => $receivableAccount->name,
            ]);

            JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => null,
                'account_id' => $revenueAccount->id,
                'invoice_id' => $invoice->id,
                'transaction_date' => now(),
                'description' => $description,
                'debit' => 0,
                'credit' => $amount,
                'currency' => $currency,
                'type' => 'booking',
                'name' => $revenueAccount->name,
            ]);

            return null;
        };

        $seam = app(PostingSeam::class);
        $engineOwnsSupplierCharges = $seam->isEnabledFor($companyId);
        $applicableChargeRules = [];
        $chargeRuleResolver = new SupplierChargeRuleResolver;

        $supplier = Supplier::where('name', 'DOTW')->first();
        $postingBasis = SaleDraftBuilder::resolvePostingBasis($companyId, 'hotel');
        $baseCurrency = (string) config('accounting.engine.base_currency');

        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: 'hotel',
            sellAmount: $amount,
            costAmount: $costAmount,
            postingBasis: $postingBasis,
            clientId: $clientId,
            supplierId: $supplier?->id,
            supplierName: $supplier?->name,
            invoiceId: $invoice->id,
            invoiceDetailId: $invoiceDetail->id,
            currency: $baseCurrency,
            receivableDescription: $description,
            payableDescription: 'Cost of hotel booking '.$booking->prebook_key.' owed to supplier: '.($supplier?->name ?? 'DOTW'),
            revenueDescription: $description,
            marginPositiveDescription: 'Margin earned on hotel booking '.$booking->prebook_key,
            marginNegativeDescription: 'Margin shortfall (sold below cost) on hotel booking '.$booking->prebook_key,
            costDescription: 'Supplier cost booked for hotel booking '.$booking->prebook_key,
        ));

        if ($engineOwnsSupplierCharges && ! empty($booking->prebook_key)) {
            $applicableChargeRules = $chargeRuleResolver->resolveApplicable(
                $companyId,
                $supplier?->id,
                'hotel',
                null,
                Carbon::now()
            );

            if (! empty($applicableChargeRules)) {
                $chargeLines = (new SupplierChargeLineBuilder($chargeRuleResolver))->buildLines(
                    $applicableChargeRules,
                    new SupplierChargeLineInput(
                        serviceType: 'hotel',
                        postingBasis: $postingBasis,
                        companyId: $companyId,
                        reference: (string) $booking->prebook_key,
                        fareAmount: $amount,
                        totalAmount: $amount,
                        supplierId: $supplier?->id,
                        supplierName: $supplier?->name,
                        clientId: $clientId,
                        invoiceId: $invoice->id,
                        invoiceDetailId: $invoiceDetail->id,
                        currency: $baseCurrency,
                    )
                );

                $lines = array_merge($lines, $chargeLines);
            }
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: 0, // No branch context in queue — matches legacy's own `'branch_id' => null`.
            docType: 'INV',
            subType: 'SALE',
            docDate: Carbon::now(),
            narration: $description,
            lines: $lines,
            idempotencyKey: 'dotw:'.$booking->id.':sale',
            invoiceId: $invoice->id,
        );

        $seam->post($draft, $legacy, 'dotwai.hotel_sale');

        if ($engineOwnsSupplierCharges && ! empty($applicableChargeRules)) {
            $firedAt = Carbon::now();

            foreach ($applicableChargeRules as $rule) {
                $chargeRuleResolver->recordFiring($rule, (string) $booking->prebook_key, $companyId, null, $firedAt);
            }
        }
    }
}
