<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAIBooking;
use Illuminate\Support\Facades\Log;

/**
 * Accounting entry creation for DOTW hotel bookings.
 *
 * Creates Invoice + InvoiceDetail + two JournalEntry records (debit receivable,
 * credit revenue) for:
 * - Cancellation penalties (createCancellationEntries)
 * - Deadline-pass auto-invoicing (createAutoInvoiceForDeadline)
 *
 * Free cancellations (penalty = 0) do NOT call this service — the caller
 * (CancellationService) is responsible for skipping this for zero-charge events.
 *
 * ALL JournalEntry and Account queries use explicit company_id and
 * withoutGlobalScopes() to bypass Auth-based global scopes that would fail
 * in queue/API contexts where Auth::user() is not set.
 *
 * @see ACCT-01 Cancellation with penalty creates Invoice + JournalEntry
 * @see ACCT-03 All accounting records include company_id
 * @see ACCT-04 All JournalEntry/Account queries bypass global scopes
 * @see LIFE-03 Deadline-pass auto-invoice for lifecycle management
 */
class AccountingService
{
    /**
     * Create Invoice, InvoiceDetail, and double-entry JournalEntry records
     * for a cancellation penalty.
     *
     * Must be called inside a DB::transaction (caller's responsibility).
     *
     * @param DotwAIBooking $booking       The cancelled booking
     * @param float         $penaltyAmount The penalty charge from DOTW
     * @param DotwAIContext $context       Resolved company/agent context
     *
     * @throws \RuntimeException if clientId cannot be resolved
     */
    public function createCancellationEntries(
        DotwAIBooking $booking,
        float $penaltyAmount,
        DotwAIContext $context,
    ): void {
        // Resolve client ID for the company
        $creditService = new CreditService();
        $clientId = $creditService->getClientIdForCompany($context->companyId);

        if ($clientId === null) {
            throw new \RuntimeException(
                "Cannot create cancellation accounting entries: no clientId found for company {$context->companyId}"
            );
        }

        // ── Create Invoice ────────────────────────────────────────────────
        $invoice = Invoice::create([
            'client_id'    => $clientId,
            'agent_id'     => $context->agent->id,
            'currency'     => $booking->display_currency ?? 'KWD',
            'sub_amount'   => $penaltyAmount,
            'amount'       => $penaltyAmount,
            'status'       => InvoiceStatus::UNPAID->value,
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->toDateString(),
            'label'        => 'Cancellation Penalty: ' . $booking->prebook_key,
        ]);

        // ── Create InvoiceDetail ──────────────────────────────────────────
        InvoiceDetail::create([
            'invoice_id'       => $invoice->id,
            'invoice_number'   => $invoice->invoice_number,
            'task_description' => 'Hotel cancellation penalty - ' . ($booking->hotel_name ?? 'Hotel'),
            'task_price'       => $penaltyAmount,
            'supplier_price'   => $penaltyAmount,
        ]);

        // ── Resolve Chart of Accounts ────────────────────────────────────
        $receivableAccount = Account::withoutGlobalScopes()
            ->where('company_id', $context->companyId)
            ->where('name', 'LIKE', '%Client%')
            ->first();

        $revenueAccount = Account::withoutGlobalScopes()
            ->where('company_id', $context->companyId)
            ->where('name', 'LIKE', '%Revenue%')
            ->first();

        // If accounts are not found, log a warning and skip JournalEntry creation.
        // Invoice was still created successfully — accounting team can reconcile.
        if ($receivableAccount === null || $revenueAccount === null) {
            Log::warning('[AccountingService] Chart of accounts not found for cancellation entries', [
                'company_id'        => $context->companyId,
                'prebook_key'       => $booking->prebook_key,
                'receivable_found'  => $receivableAccount !== null,
                'revenue_found'     => $revenueAccount !== null,
            ]);

            // For B2B: mark invoice paid since credit was already deducted
            if ($booking->track === DotwAIBooking::TRACK_B2B) {
                $invoice->update(['status' => InvoiceStatus::PAID->value]);
            }

            return;
        }

        $currency    = $booking->display_currency ?? 'KWD';
        $branchId    = $context->agent->branch_id ?? null;
        $description = 'DOTW cancellation penalty - ' . ($booking->hotel_name ?? 'Hotel')
            . ' (' . $booking->prebook_key . ')';

        // Debit: Accounts Receivable (client owes us)
        JournalEntry::create([
            'company_id'       => $context->companyId,
            'branch_id'        => $branchId,
            'account_id'       => $receivableAccount->id,
            'invoice_id'       => $invoice->id,
            'transaction_date' => now(),
            'description'      => $description,
            'debit'            => $penaltyAmount,
            'credit'           => 0,
            'currency'         => $currency,
            'type'             => 'cancellation',
        ]);

        // Credit: Revenue (we earned the penalty fee)
        JournalEntry::create([
            'company_id'       => $context->companyId,
            'branch_id'        => $branchId,
            'account_id'       => $revenueAccount->id,
            'invoice_id'       => $invoice->id,
            'transaction_date' => now(),
            'description'      => $description,
            'debit'            => 0,
            'credit'           => $penaltyAmount,
            'currency'         => $currency,
            'type'             => 'cancellation',
        ]);

        // For B2B bookings: the penalty was deducted from the credit line, so the
        // invoice is effectively paid (credit line settlement = payment in kind)
        if ($booking->track === DotwAIBooking::TRACK_B2B) {
            $invoice->update(['status' => InvoiceStatus::PAID->value]);
        }
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
     * @param DotwAIBooking $booking The confirmed booking past deadline
     *
     * @see LIFE-03 Auto-invoice dispatched by scheduler, executed by queue job
     */
    public function createAutoInvoiceForDeadline(DotwAIBooking $booking): void
    {
        $companyId = $booking->company_id;
        $amount    = (float) ($booking->display_total_fare ?? 0);
        $currency  = $booking->display_currency ?? 'KWD';

        // Resolve client ID for the company
        $creditService = new CreditService();
        $clientId = $creditService->getClientIdForCompany($companyId);

        // ── Create Invoice ────────────────────────────────────────────────
        $invoice = Invoice::create([
            'client_id'    => $clientId,
            'agent_id'     => null,  // No agent context in queue; admin can assign
            'currency'     => $currency,
            'sub_amount'   => $amount,
            'amount'       => $amount,
            'status'       => InvoiceStatus::UNPAID->value,
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->toDateString(),
            'label'        => 'Hotel Auto-Invoice (Deadline): ' . $booking->prebook_key,
        ]);

        // ── Create InvoiceDetail ──────────────────────────────────────────
        InvoiceDetail::create([
            'invoice_id'       => $invoice->id,
            'invoice_number'   => $invoice->invoice_number,
            'task_description' => 'Hotel booking - ' . ($booking->hotel_name ?? 'Hotel')
                . ' (' . $booking->check_in?->format('Y-m-d') . ' to ' . $booking->check_out?->format('Y-m-d') . ')',
            'task_price'       => $amount,
            'supplier_price'   => (float) ($booking->original_total_fare ?? $amount),
        ]);

        // ── Link invoice to booking ───────────────────────────────────────
        $booking->update(['invoice_id' => $invoice->id]);

        // ── Resolve Chart of Accounts ─────────────────────────────────────
        $receivableAccount = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', 'LIKE', '%Client%')
            ->first();

        $revenueAccount = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', 'LIKE', '%Revenue%')
            ->first();

        // If accounts not found, log and skip JournalEntry — invoice created for reconciliation
        if ($receivableAccount === null || $revenueAccount === null) {
            Log::warning('[AccountingService] Chart of accounts not found for auto-invoice entries', [
                'company_id'       => $companyId,
                'prebook_key'      => $booking->prebook_key,
                'receivable_found' => $receivableAccount !== null,
                'revenue_found'    => $revenueAccount !== null,
            ]);

            return;
        }

        $description = 'DOTW hotel deadline auto-invoice - ' . ($booking->hotel_name ?? 'Hotel')
            . ' (' . $booking->prebook_key . ')';

        // Debit: Accounts Receivable (client owes us for the booking)
        JournalEntry::create([
            'company_id'       => $companyId,
            'branch_id'        => null,  // No branch context in queue
            'account_id'       => $receivableAccount->id,
            'invoice_id'       => $invoice->id,
            'transaction_date' => now(),
            'description'      => $description,
            'debit'            => $amount,
            'credit'           => 0,
            'currency'         => $currency,
            'type'             => 'booking',
        ]);

        // Credit: Revenue (we earned the booking fare)
        JournalEntry::create([
            'company_id'       => $companyId,
            'branch_id'        => null,
            'account_id'       => $revenueAccount->id,
            'invoice_id'       => $invoice->id,
            'transaction_date' => now(),
            'description'      => $description,
            'debit'            => 0,
            'credit'           => $amount,
            'currency'         => $currency,
            'type'             => 'booking',
        ]);
    }
}
