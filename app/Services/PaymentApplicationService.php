<?php

namespace App\Services;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentApplicationService
{
    /**
     * Apply multiple payments to an invoice
     * 
     * Supports three modes:
     * - 'full': Credit covers entire invoice (selected amount must >= invoice amount)
     * - 'partial': Pay a portion with credit, leave remaining unpaid for later
     * - 'split': Pay a portion with credit, pay rest with another gateway (cash, card, etc.)
     * 
     * @param int $invoiceId
     * @param array $paymentAllocations Array of ['credit_id' => X, 'amount' => Y]
     * @param string $paymentMode 'full', 'partial', or 'split'
     * @param array $options Additional options for split/partial modes:
     *   - 'other_gateway' => gateway name for remaining amount (for split)
     *   - 'other_method' => payment method (for split)
     *   - 'charge_id' => charge id (for split)
     * @return array Result with status and message
     */
    public function applyPaymentsToInvoice(
        int $invoiceId,
        array $paymentAllocations,
        string $paymentMode = 'full',
        array $options = []
    ): array {
        Log::info('[PAYMENT APPLICATION] applyPaymentsToInvoice - Request', [
            'invoice_id' => $invoiceId,
            'payment_allocations' => $paymentAllocations,
            'payment_mode' => $paymentMode,
            'options' => $options,
            'user_id' => Auth::id(),
        ]);

        $invoice = Invoice::findOrFail($invoiceId);
        $invoiceAmount = $invoice->amount;

        // Calculate total credit selected
        $totalCreditSelected = array_sum(array_column($paymentAllocations, 'amount'));

        Log::info('[PAYMENT APPLICATION] applyPaymentsToInvoice - Validation', [
            'invoice_amount' => $invoiceAmount,
            'total_credit_selected' => $totalCreditSelected,
            'payment_mode' => $paymentMode,
        ]);

        // Validate based on payment mode
        if ($paymentMode === 'full') {
            // Full payment: credit must cover entire invoice
            if ($totalCreditSelected < $invoiceAmount) {
                $response = [
                    'success' => false,
                    'message' => "Insufficient credit selected. You selected {$totalCreditSelected} but need {$invoiceAmount}. Use partial or split payment mode.",
                    'shortfall' => $invoiceAmount - $totalCreditSelected,
                ];
                Log::warning('[PAYMENT APPLICATION] Full payment - Insufficient amount', $response);
                return $response;
            }
        } elseif ($paymentMode === 'split') {
            // Split payment: credit + other gateway must cover entire invoice
            if ($totalCreditSelected <= 0) {
                return [
                    'success' => false,
                    'message' => "Please select at least some credit amount for split payment."
                ];
            }
            if ($totalCreditSelected >= $invoiceAmount) {
                return [
                    'success' => false,
                    'message' => "Credit covers entire invoice. Use full payment mode instead."
                ];
            }
            if (empty($options['other_gateway'])) {
                return [
                    'success' => false,
                    'message' => "Please select a payment gateway for the remaining amount."
                ];
            }
        } elseif ($paymentMode === 'partial') {
            if ($totalCreditSelected <= 0) {
                return [
                    'success' => false,
                    'message' => "Please select at least some credit amount for partial payment."
                ];
            }
            if ($totalCreditSelected >= $invoiceAmount) {
                return [
                    'success' => false,
                    'message' => "Credit covers entire invoice. Use full payment mode instead."
                ];
            }
        }

        DB::beginTransaction();
        try {
            $appliedPayments = [];
            $createdInvoicePartials = [];
            $remainingToApply = min($totalCreditSelected, $invoiceAmount); // Don't over-apply

            // STEP 1A: Check if Invoice Generation COA exists
            $invoiceGenerationCOAExists = Transaction::where('invoice_id', $invoice->id)
                ->where('reference_type', 'Invoice')
                ->exists();

            if (!$invoiceGenerationCOAExists) {
                Log::info('[PAYMENT APPLICATION] Creating Invoice Generation COA via addJournalEntry()', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ]);

                $transaction = Transaction::create([
                    'company_id' => $invoice->agent?->branch->company_id,
                    'branch_id' => $invoice->agent->branch_id,
                    'entity_id' => $invoice->agent?->branch->company_id,
                    'entity_type' => 'company',
                    'transaction_type' => 'credit',
                    'amount' =>  $invoice->amount,
                    'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
                    'invoice_id' => $invoice->id,
                    'reference_type' => 'Invoice',
                    'transaction_date' => $invoice->invoice_date,
                ]);

                $invoiceController = app(\App\Http\Controllers\InvoiceController::class);
                $clientName = $invoice->client?->full_name;

                $invoiceDetails = $invoice->invoiceDetails()->with('task.agent')->get();
                foreach ($invoiceDetails as $invoiceDetail) {
                    if (!$invoiceDetail->task) {
                        continue;
                    }
                    $task = $invoiceDetail->task;
                    $invoiceController->addJournalEntry(
                        $task,
                        $invoice->id,
                        $invoiceDetail->id,
                        $transaction->id,
                        $clientName
                    );
                }

                Log::info('[PAYMENT APPLICATION] Invoice Generation COA created successfully');
            } else {
                Log::info('[PAYMENT APPLICATION] Invoice Generation COA already exists', [
                    'invoice_id' => $invoice->id,
                ]);
            }

            // STEP 1B: Process each payment allocation
            foreach ($paymentAllocations as $allocation) {
                if ($remainingToApply <= 0) break;

                $creditId = $allocation['credit_id'];
                $requestedAmount = $allocation['amount'];

                $sourceCredit = Credit::findOrFail($creditId);

                if ($sourceCredit->type === Credit::TOPUP) {
                    $availableBalance = Credit::getAvailableBalanceByPayment($sourceCredit->payment_id);
                    $voucherNumber = $sourceCredit->payment?->voucher_number ?? 'TOPUP';
                } elseif ($sourceCredit->type === Credit::REFUND) {
                    $availableBalance = Credit::getAvailableBalanceByRefund($sourceCredit->refund_id);
                    $voucherNumber = $sourceCredit->refund?->refund_number ?? ('RF-' . $sourceCredit->refund_id);
                } else {
                    throw new Exception("This credit type cannot be used: {$sourceCredit->type}");
                }

                Log::info('[PAYMENT APPLICATION] Processing credit allocation', [
                    'credit_id' => $sourceCredit->id,
                    'credit_type' => $sourceCredit->type,
                    'payment_id' => $sourceCredit->payment_id,
                    'refund_id' => $sourceCredit->refund_id,
                    'voucher_number' => $voucherNumber,
                    'requested_amount' => $requestedAmount,
                    'available_balance' => $availableBalance,
                    'remaining_to_apply' => $remainingToApply,
                ]);

                if ($availableBalance < $requestedAmount) {
                    throw new Exception(
                        "Credit source {$voucherNumber} only has {$availableBalance} available, but {$requestedAmount} was requested."
                    );
                }

                // Calculate how much to actually apply from this payment
                $applyFromThis = min($requestedAmount, $remainingToApply);

                // Create InvoicePartial record for credit portion
                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'agent_id' => $invoice->agent_id,
                    'amount' => $applyFromThis,
                    'status' => 'paid',
                    'type' => $paymentMode,
                    'payment_gateway' => 'Credit',
                    'payment_method' => 'Credit Balance',
                    'service_charge' => 0,
                ]);

                Log::info('[PAYMENT APPLICATION] Created InvoicePartial for credit', [
                    'invoice_partial_id' => $invoicePartial->id,
                    'amount' => $applyFromThis,
                ]);

                $createdInvoicePartials[] = $invoicePartial;

                // Create Credit record (negative - deducting from balance)
                $credit = Credit::create([
                    'company_id' => $invoice->agent?->branch?->company_id,
                    'branch_id' => $invoice->agent?->branch_id,
                    'client_id' => $invoice->client_id,
                    'payment_id' => $sourceCredit->payment_id,
                    'refund_id'  => $sourceCredit->refund_id,
                    'invoice_id' => $invoiceId,
                    'invoice_partial_id' => $invoicePartial->id,
                    'type' => Credit::INVOICE,
                    'amount' => -$applyFromThis,
                    'description' => "Payment for {$invoice->invoice_number} via {$voucherNumber}",
                ]);

                Log::info('[PAYMENT APPLICATION] Created Credit record', [
                    'credit_id' => $credit->id,
                    'amount' => -$applyFromThis,
                    'payment_id' => $credit->payment_id,
                    'refund_id' => $credit->refund_id,
                ]);

                // Create PaymentApplication record (audit trail)
                PaymentApplication::create([
                    'payment_id' => $sourceCredit->payment_id,
                    'credit_id' => $sourceCredit->id,
                    'invoice_id' => $invoiceId,
                    'invoice_partial_id' => $invoicePartial->id,
                    'amount' => $applyFromThis,
                    'applied_by' => Auth::id(),
                    'applied_at' => now(),
                    'notes' => "Applied from {$voucherNumber} ({$paymentMode} payment)",
                ]);

                // Collect for COA creation (will be done AFTER the loop)
                $appliedPayments[] = [
                    'credit_id' => $sourceCredit->id,
                    'payment_id' => $sourceCredit->payment_id,
                    'refund_id' => $sourceCredit->refund_id,
                    'voucher_number' => $voucherNumber,
                    'amount_applied' => $applyFromThis,
                    'remaining_balance' => $availableBalance - $applyFromThis,
                    'invoice_partial_id' => $invoicePartial->id,
                ];

                $remainingToApply -= $applyFromThis;
            }

            // STEP 2: Create Credit Payment COA (AFTER the loop)
            $creditApplied = array_sum(array_column($appliedPayments, 'amount_applied'));

            if ($creditApplied > 0 && !empty($appliedPayments)) {
                $this->createCreditPaymentCOA($invoice, $appliedPayments, $creditApplied);
            }

            // STEP 3: Handle invoice status based on payment mode
            $remainingAmount = $invoiceAmount - $creditApplied;

            // Handle based on payment mode
            if ($paymentMode === 'full') {
                // Full payment with credit - mark invoice as paid
                $invoice->status = 'paid';
                $invoice->paid_date = now();
                $invoice->payment_type = 'credit';
                $invoice->is_client_credit = true;
                $invoice->save();

                Log::info('[PAYMENT APPLICATION] Full payment - Invoice marked as paid');
            } elseif ($paymentMode === 'split') {
                // Split payment - create unpaid partial for remaining amount
                $splitPartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'agent_id' => $invoice->agent_id,
                    'amount' => $remainingAmount,
                    'status' => 'unpaid',
                    'type' => 'split',
                    'payment_gateway' => $options['other_gateway'] ?? null,
                    'payment_method' => $options['other_method'] ?? null,
                    'service_charge' => 0,
                    'charge_id' => $options['charge_id'] ?? null,
                ]);

                Log::info('[PAYMENT APPLICATION] Split payment - Created unpaid partial for other gateway', [
                    'invoice_partial_id' => $splitPartial->id,
                    'amount' => $remainingAmount,
                    'gateway' => $options['other_gateway'] ?? null,
                ]);

                $createdInvoicePartials[] = $splitPartial;

                // Mark invoice as partial
                $invoice->status = 'partial';
                $invoice->payment_type = 'split';
                $invoice->is_client_credit = true;
                $invoice->save();

                Log::info('[PAYMENT APPLICATION] Split payment - Invoice marked as partial');
            } elseif ($paymentMode === 'partial') {
                // Partial payment - leave remaining unpaid (no second partial needed)
                $invoice->status = 'partial';
                $invoice->payment_type = 'partial';
                $invoice->is_client_credit = true;
                $invoice->save();

                Log::info('[PAYMENT APPLICATION] Partial payment - Invoice marked as partial, remaining: ' . $remainingAmount);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => $this->buildSuccessMessage($paymentMode, $creditApplied, $remainingAmount, $options),
                'payment_mode' => $paymentMode,
                'credit_applied' => $creditApplied,
                'remaining_amount' => $remainingAmount,
                'applied_payments' => $appliedPayments,
                'invoice_status' => $invoice->status,
                'invoice_partials_created' => count($createdInvoicePartials),
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('[PAYMENT APPLICATION] applyPaymentsToInvoice - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to apply payments: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build success message based on payment mode
     */
    private function buildSuccessMessage(string $mode, float $creditApplied, float $remaining, array $options): string
    {
        switch ($mode) {
            case 'full':
                return "Successfully paid invoice in full using {$creditApplied} KWD credit.";

            case 'split':
                $gateway = $options['other_gateway'] ?? 'other method';
                return "Successfully applied {$creditApplied} KWD credit. Remaining {$remaining} KWD to be paid via {$gateway}.";

            case 'partial':
                return "Successfully applied {$creditApplied} KWD credit. Remaining {$remaining} KWD balance on invoice.";

            default:
                return "Payment applied successfully.";
        }
    }

    /**
     * Get available payments for a client that can be used to pay invoices
     * 
     * @param int $clientId
     * @return array
     */
    public function getAvailablePaymentsForClient(int $clientId): array
    {
        return Credit::getAvailablePaymentsForClient($clientId);
    }

    /**
     * Validate if selected payments can cover the required amount
     * 
     * @param array $paymentAllocations
     * @param float $requiredAmount
     * @return array Validation result
     */
    public function validatePaymentSelection(array $paymentAllocations, float $requiredAmount): array
    {
        $totalSelected = 0;
        $issues = [];

        foreach ($paymentAllocations as $allocation) {
            $requestedAmount = (float) ($allocation['amount'] ?? 0);

            if (isset($allocation['payment_id'])) {
                $paymentId = (int) $allocation['payment_id'];
                $availableBalance = Credit::getAvailableBalanceByPayment($paymentId);

                if ($requestedAmount > $availableBalance) {
                    $payment = Payment::find($paymentId);
                    $voucher = $payment?->voucher_number ?? 'UNKNOWN';
                    $issues[] = "Payment {$voucher} only has {$availableBalance} available, but {$requestedAmount} was requested.";
                }

                $totalSelected += min($requestedAmount, $availableBalance);
                continue;
            }

            if (isset($allocation['credit_id'])) {
                $creditId = (int) $allocation['credit_id'];
                $credit = Credit::find($creditId);

                if (!$credit) {
                    $issues[] = "Credit source {$creditId} not found.";
                    continue;
                }

                if ($credit->type === Credit::TOPUP) {
                    $availableBalance = Credit::getAvailableBalanceByPayment($credit->payment_id);
                } elseif ($credit->type === Credit::REFUND) {
                    $availableBalance = Credit::getAvailableBalanceByRefund($credit->refund_id);
                } else {
                    $issues[] = "This credit type cannot be used: {$credit->type}";
                    continue;
                }

                if ($requestedAmount > $availableBalance) {
                    $issues[] = "Credit source only has {$availableBalance} available, but {$requestedAmount} was requested.";
                }

                $totalSelected += min($requestedAmount, $availableBalance);
                continue;
            }

            $issues[] = "Invalid allocation format. Must include payment_id or credit_id.";
        }

        $isValid = empty($issues) && $totalSelected >= $requiredAmount;

        return [
            'valid' => $isValid,
            'total_selected' => $totalSelected,
            'required_amount' => $requiredAmount,
            'shortfall' => max(0, $requiredAmount - $totalSelected),
            'excess' => max(0, $totalSelected - $requiredAmount),
            'issues' => $issues,
        ];
    }

    /**
     * Get payment history for an invoice (which payments paid this invoice)
     * 
     * @param int $invoiceId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPaymentHistoryForInvoice(int $invoiceId)
    {
        return PaymentApplication::getApplicationsForInvoice($invoiceId);
    }

    /**
     * Get invoice history for a payment (which invoices were paid by this payment)
     * 
     * @param int $paymentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInvoiceHistoryForPayment(int $paymentId)
    {
        return PaymentApplication::getApplicationsFromPayment($paymentId);
    }

    /**
     * Link payments to an existing InvoicePartial
     * 
     * This is used when the InvoicePartial has already been created by the existing flow,
     * and we just need to create the Credit and PaymentApplication records for audit trail.
     * 
     * @param Invoice $invoice
     * @param InvoicePartial $invoicePartial
     * @param array $paymentAllocations Array of ['credit_id' => X, 'amount' => Y]
     * @return array Result with status and applied payments
     */
    public function linkPaymentsToInvoicePartial(
        Invoice $invoice,
        InvoicePartial $invoicePartial,
        array $paymentAllocations
    ): array {
        Log::info('[PAYMENT APPLICATION] linkPaymentsToInvoicePartial - Request', [
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => $invoicePartial->id,
            'payment_allocations' => $paymentAllocations,
            'user_id' => Auth::id(),
        ]);

        $appliedPayments = [];
        $remainingToApply = $invoicePartial->amount;

        foreach ($paymentAllocations as $allocation) {
            if ($remainingToApply <= 0) break;

            $requestedAmount = $allocation['amount'];

            $paymentId = null;
            $sourceCredit = null;

            if (isset($allocation['credit_id'])) {
                $sourceCredit = Credit::findOrFail($allocation['credit_id']);

                if ($sourceCredit->type === Credit::TOPUP) {
                    $paymentId = $sourceCredit->payment_id;
                    $voucherNumber = $sourceCredit->payment?->voucher_number ?? 'TOPUP';
                    $availableBalance = Credit::getAvailableBalanceByPayment($paymentId);
                } elseif ($sourceCredit->type === Credit::REFUND) {
                    $voucherNumber = $sourceCredit->refund?->refund_number ?? ('RF-' . $sourceCredit->refund_id);
                    $availableBalance = Credit::getAvailableBalanceByRefund($sourceCredit->refund_id);
                } else {
                    throw new Exception("This credit type cannot be used: {$sourceCredit->type}");
                }
            } else {
                $paymentId = $allocation['payment_id'];
                $payment = Payment::findOrFail($paymentId);
                $voucherNumber = $payment->voucher_number;
                $availableBalance = Credit::getAvailableBalanceByPayment($paymentId);
            }

            Log::info('[PAYMENT APPLICATION] Processing payment allocation', [
                'payment_id' => $paymentId,
                'voucher_number' => $voucherNumber,
                'requested_amount' => $requestedAmount,
                'available_balance' => $availableBalance,
                'remaining_to_apply' => $remainingToApply,
            ]);

            if ($availableBalance < $requestedAmount) {
                throw new Exception(
                    "Credit source {$voucherNumber} only has {$availableBalance} available, but {$requestedAmount} was requested."
                );
            }

            $applyFromThis = min($requestedAmount, $remainingToApply);

            $credit = Credit::create([
                'company_id' => $invoice->agent?->branch?->company_id,
                'branch_id' => $invoice->agent?->branch_id,
                'client_id' => $invoice->client_id,
                'payment_id' => $paymentId,
                'refund_id' => $sourceCredit?->refund_id,
                'invoice_id' => $invoice->id,
                'invoice_partial_id' => $invoicePartial->id,
                'type' => Credit::INVOICE,
                'amount' => -$applyFromThis,
                'description' => "Payment for $invoice->invoice_number via {$voucherNumber}",
            ]);

            Log::info('[PAYMENT APPLICATION] Created Credit record', [
                'credit_id' => $credit->id,
                'amount' => -$applyFromThis,
                'payment_id' => $credit->payment_id,
                'refund_id' => $credit->refund_id,
            ]);

            $paymentApplication = PaymentApplication::create([
                'payment_id' => $paymentId,
                'credit_id' => $sourceCredit?->id,
                'invoice_id' => $invoice->id,
                'invoice_partial_id' => $invoicePartial->id,
                'amount' => $applyFromThis,
                'applied_by' => Auth::id(),
                'applied_at' => now(),
                'notes' => "Applied from {$voucherNumber} ({$invoice->payment_type} payment)",
            ]);

            Log::info('[PAYMENT APPLICATION] Created PaymentApplication record', [
                'payment_application_id' => $paymentApplication->id,
                'payment_id' => $sourceCredit->payment_id,
                'invoice_partial_id' => $invoicePartial->id,
                'amount' => $applyFromThis,
            ]);

            $appliedPayments[] = [
                'payment_id' => $paymentId,
                'voucher_number' => $voucherNumber,
                'amount_applied' => $applyFromThis,
                'remaining_balance' => $availableBalance - $applyFromThis,
            ];

            $remainingToApply -= $applyFromThis;
        }

        return [
            'success' => true,
            'applied_payments' => $appliedPayments,
            'total_applied' => array_sum(array_column($appliedPayments, 'amount_applied')),
        ];
    }

    /**
     * Create COA Journal Entries when paying invoice with client credit
     * 
     * Creates ONE transaction with:
     * - Multiple DEBIT entries (one per voucher/credit source used)
     * - Single CREDIT entry (total amount paid)
     * 
     * @param \App\Models\Invoice $invoice
     * @param array $appliedPayments Array of applied payments with voucher info:
     *   [
     *     ['voucher_number' => 'VCH-001', 'amount_applied' => 60.00, 'invoice_partial_id' => 1],
     *     ['voucher_number' => 'VCH-002', 'amount_applied' => 35.00, 'invoice_partial_id' => 2],
     *   ]
     * @param float $totalAmount Total amount applied from all credits
     * @return \App\Models\Transaction|null
     */
    protected function createCreditPaymentCOA($invoice, array $appliedPayments, float $totalAmount)
    {
        try {
            $companyId = $invoice->agent?->branch?->company_id;
            $branchId = $invoice->agent?->branch_id;

            if (!$companyId) {
                Log::warning('[CREDIT PAYMENT COA] Company ID not found', [
                    'invoice_id' => $invoice->id,
                ]);
                return null;
            }

            $voucherList = implode(', ', array_column($appliedPayments, 'voucher_number'));

            $transaction = Transaction::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'entity_id' => $invoice->client_id,
                'entity_type' => 'Client',
                'transaction_type' => 'debit',
                'amount' => $totalAmount,
                'description' => "Credit Payment for {$invoice->invoice_number}",
                'invoice_id' => $invoice->id,
                'reference_type' => 'Payment',
                'reference_number' => $invoice->invoice_number,
                'transaction_date' => now(),
            ]);

            Log::info('[CREDIT PAYMENT COA] Created Transaction', [
                'transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'total_amount' => $totalAmount,
                'vouchers_used' => $voucherList,
            ]);

            // ENTRY 1: DEBIT Liability (Clear advance/credit held)
            $liabilities = Account::where('name', 'Liabilities')
                ->where('company_id', $companyId)
                ->whereNull('parent_id')
                ->value('id');

            $advances = Account::where('name', 'Advances')
                ->where('company_id', $companyId)
                ->where('parent_id', $liabilities)
                ->first();

            $clientAdvance = Account::where('name', 'Client')
                ->where('company_id', $companyId)
                ->where('parent_id', $advances?->id)
                ->first();

            $liabilityAccount = Account::where('name', 'Payment Gateway')
                ->where('company_id', $companyId)
                ->where('parent_id', $clientAdvance?->id)
                ->first();

            if ($liabilityAccount) {
                foreach ($appliedPayments as $payment) {
                    $voucherNumber = $payment['voucher_number'] ?? 'N/A';
                    $amountApplied = $payment['amount_applied'] ?? 0;
                    $invoicePartialId = $payment['invoice_partial_id'] ?? null;

                    if ($amountApplied <= 0) continue;

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $branchId,
                        'company_id' => $companyId,
                        'account_id' => $liabilityAccount->id,
                        'invoice_id' => $invoice->id,
                        'invoice_partial_id' => $invoicePartialId,
                        'agent_id' => $invoice->agent_id,
                        'transaction_date' => now(),
                        'description' => "Apply Client Credit from {$voucherNumber}",
                        'debit' => $amountApplied,
                        'credit' => 0,
                        'balance' => $liabilityAccount->actual_balance ?? 0,
                        'name' => $liabilityAccount->name,
                        'type' => 'payable',
                        'currency' => $invoice->currency ?? 'KWD',
                    ]);
                }
            } else {
                Log::warning('[CREDIT PAYMENT COA] Liability account NOT FOUND', [
                    'company_id' => $companyId,
                ]);
            }

            // ENTRY 2: CREDIT Receivable (Invoice debt cleared)
            $accountsReceivable = Account::where('name', 'Accounts Receivable')
                ->where('company_id', $companyId)
                ->first();

            $receivableAccount = Account::where('name', 'Clients')
                ->where('company_id', $companyId)
                ->where('parent_id', $accountsReceivable?->id)
                ->first();

            if ($receivableAccount) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'account_id' => $receivableAccount->id,
                    'invoice_id' => $invoice->id,
                    'invoice_partial_id' => null,
                    'agent_id' => $invoice->agent_id,
                    'transaction_date' => now(),
                    'description' => "Invoice {$invoice->invoice_number} paid via Client Credit",
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'balance' => $receivableAccount->actual_balance ?? 0,
                    'name' => $receivableAccount->name,
                    'type' => 'receivable',
                    'currency' => $invoice->currency ?? 'KWD',
                ]);
            } else {
                Log::warning('[CREDIT PAYMENT COA] Receivable account NOT FOUND', [
                    'company_id' => $companyId,
                ]);
            }

            Log::info('[CREDIT PAYMENT COA] Journal entries created', [
                'transaction_id' => $transaction->id,
                'debit_account' => $liabilityAccount?->name,
                'credit_account' => $receivableAccount?->name,
                'total_amount' => $totalAmount,
            ]);

            return $transaction;
        } catch (\Exception $e) {
            Log::error('[CREDIT PAYMENT COA] Failed to create COA entries', [
                'invoice_id' => $invoice->id,
                'total_amount' => $totalAmount,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
