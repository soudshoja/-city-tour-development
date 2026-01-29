<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\ChargeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixPaymentGatewayCOA extends Command
{
    protected $signature = 'fix:payment-gateway-coa 
                            {--type=all : Type to fix: invoice, topup, or all}
                            {--company= : Specific company ID}
                            {--invoice= : Specific invoice ID}
                            {--payment= : Specific payment ID}
                            {--dry-run : Preview changes without applying}
                            {--from-date= : Start date (Y-m-d)}
                            {--to-date= : End date (Y-m-d)}
                            {--force : Apply without confirmation}';

    protected $description = 'Fix Payment Gateway COA entries to use accountingFee (exact, no rounding)';

    private int $fixedInvoices = 0;
    private int $fixedPayments = 0;
    private int $fixedEntries = 0;
    private array $changes = [];

    public function handle()
    {
        $this->info('=============================================');
        $this->info('  Payment Gateway COA Fix Command');
        $this->info('  Uses accountingFee (exact) for COA entries');
        $this->info('=============================================');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $type = $this->option('type');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        if (in_array($type, ['invoice', 'all'])) {
            $this->fixInvoiceCOA($isDryRun);
        }

        if (in_array($type, ['topup', 'all'])) {
            $this->fixTopupCOA($isDryRun);
        }

        $this->printSummary($isDryRun);

        return 0;
    }

    /**
     * Fix Invoice Payment Gateway COA
     * 
     * COA Structure:
     * CREDIT Receivable: totalToPay (what client paid - correct already)
     * DEBIT Gateway Asset: totalToPay - accountingFee (net received)
     * DEBIT Gateway Fee Expense: accountingFee (exact, no rounding)
     */
    private function fixInvoiceCOA(bool $isDryRun): void
    {
        $this->info('--- Fixing Invoice Payment Gateway COA ---');

        $query = Invoice::with(['agent.branch', 'invoicePartials'])
            ->where('status', 'paid')
            ->whereHas('invoicePartials', fn($q) => $q->where('status', 'paid')->whereNotNull('payment_gateway'));

        if ($invoiceId = $this->option('invoice')) {
            $query->where('id', $invoiceId);
        }

        if ($companyId = $this->option('company')) {
            $query->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId));
        }

        if ($fromDate = $this->option('from-date')) {
            $query->where('paid_date', '>=', $fromDate);
        }

        if ($toDate = $this->option('to-date')) {
            $query->where('paid_date', '<=', $toDate);
        }

        $invoices = $query->get();
        $this->info("Found {$invoices->count()} paid invoices with gateway payments");

        if ($invoices->isEmpty()) return;

        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();

        foreach ($invoices as $invoice) {
            $this->processInvoice($invoice, $isDryRun);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function processInvoice(Invoice $invoice, bool $isDryRun): void
    {
        $companyId = $invoice->agent?->branch?->company_id;
        if (!$companyId) return;

        // Get all paid partials for this invoice
        $paidPartials = $invoice->invoicePartials->where('status', 'paid');

        foreach ($paidPartials as $partial) {
            // Skip if no gateway or not a real gateway payment
            if (!$partial->payment_gateway) continue;

            // Skip credit balance payments
            if (
                strtolower($partial->payment_gateway) === 'credit' ||
                strtolower($partial->payment_gateway) === 'credit balance'
            ) {
                continue;
            }

            $gateway = $partial->payment_gateway;
            $amount = (float) $partial->amount;

            // Skip if amount is not valid
            if ($amount <= 0) continue;

            // payment_method might be string name or int ID
            $methodId = null;
            if ($partial->payment_method) {
                if (is_numeric($partial->payment_method)) {
                    $methodId = (int) $partial->payment_method;
                } else {
                    // It's a string name, look up the ID
                    $method = \App\Models\PaymentMethod::where('name', $partial->payment_method)
                        ->where('company_id', $companyId)
                        ->first();
                    $methodId = $method?->id;
                }
            }
            $storedServiceCharge = (float) $partial->service_charge; // What client paid (rounded)

            // Calculate correct accounting fee (exact, no rounding)
            $result = ChargeService::calculate($amount, $companyId, $methodId, $gateway);
            $accountingFee = $result['accountingFee'] ?? 0;
            $paidBy = $result['paid_by'] ?? 'Company';

            // Total client paid
            $totalClientPaid = ($paidBy === 'Client') ? ($amount + $storedServiceCharge) : $amount;

            // Find transaction for this invoice
            $transaction = Transaction::where('invoice_id', $invoice->id)
                ->where(function ($q) use ($gateway) {
                    $q->where('description', 'LIKE', "%{$gateway}%")
                        ->orWhere('description', 'LIKE', '%payment success%');
                })
                ->first();

            if (!$transaction) continue;

            $this->fixInvoiceJournalEntries($transaction, $invoice, $accountingFee, $totalClientPaid, $isDryRun);
        }
    }

    private function fixInvoiceJournalEntries(Transaction $transaction, Invoice $invoice, float $accountingFee, float $totalClientPaid, bool $isDryRun): void
    {
        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
        $hasChanges = false;

        foreach ($entries as $entry) {
            $descLower = strtolower($entry->description ?? '');

            // Fix Gateway Fee Expense (DEBIT) - should use accountingFee (exact)
            if ((str_contains($descLower, 'gateway fee') || $entry->type === 'charges') && $entry->debit > 0) {
                $oldDebit = $entry->debit;

                if (abs($oldDebit - $accountingFee) > 0.001) {
                    $this->addChange('Invoice', $invoice->invoice_number, 'Gateway Fee', $oldDebit, $accountingFee);
                    $hasChanges = true;

                    if (!$isDryRun) {
                        // Calculate difference for balance adjustment
                        $difference = $accountingFee - $oldDebit;

                        $entry->debit = round($accountingFee, 3);
                        $entry->save();

                        // Update account balance
                        $account = $entry->account;
                        if ($account) {
                            $account->actual_balance += $difference; // Add difference (negative = reduce)
                            $account->save();

                            Log::info('[FIX COA] Updated account balance', [
                                'account_id' => $account->id,
                                'account_name' => $account->name,
                                'old_debit' => $oldDebit,
                                'new_debit' => $accountingFee,
                                'balance_adjustment' => $difference,
                            ]);
                        }

                        $this->fixedEntries++;
                    }
                }
            }

            // Fix Net Payment Received (DEBIT) - should be totalClientPaid - accountingFee
            if (str_contains($descLower, 'net payment received') && $entry->debit > 0) {
                $correctNet = $totalClientPaid - $accountingFee;
                $oldDebit = $entry->debit;

                if (abs($oldDebit - $correctNet) > 0.001) {
                    $this->addChange('Invoice', $invoice->invoice_number, 'Net Received', $oldDebit, $correctNet);
                    $hasChanges = true;

                    if (!$isDryRun) {
                        // Calculate difference for balance adjustment
                        $difference = $correctNet - $oldDebit;

                        $entry->debit = round($correctNet, 3);
                        $entry->save();

                        // Update account balance
                        $account = $entry->account;
                        if ($account) {
                            $account->actual_balance += $difference;
                            $account->save();

                            Log::info('[FIX COA] Updated account balance', [
                                'account_id' => $account->id,
                                'account_name' => $account->name,
                                'old_debit' => $oldDebit,
                                'new_debit' => $correctNet,
                                'balance_adjustment' => $difference,
                            ]);
                        }

                        $this->fixedEntries++;
                    }
                }
            }
        }

        if ($hasChanges) {
            $this->fixedInvoices++;
        }
    }

    /**
     * Fix Payment Link (Topup) COA
     */
    private function fixTopupCOA(bool $isDryRun): void
    {
        $this->info('--- Fixing Payment Link (Topup) COA ---');

        $query = Payment::with(['agent.branch', 'paymentMethod'])
            ->where('status', 'completed')
            ->whereNull('invoice_id')
            ->whereNotNull('payment_gateway');

        if ($paymentId = $this->option('payment')) {
            $query->where('id', $paymentId);
        }

        if ($companyId = $this->option('company')) {
            $query->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId));
        }

        if ($fromDate = $this->option('from-date')) {
            $query->where('payment_date', '>=', $fromDate);
        }

        if ($toDate = $this->option('to-date')) {
            $query->where('payment_date', '<=', $toDate);
        }

        $payments = $query->get();
        $this->info("Found {$payments->count()} completed topup payments");

        if ($payments->isEmpty()) return;

        $bar = $this->output->createProgressBar($payments->count());
        $bar->start();

        foreach ($payments as $payment) {
            $this->processTopup($payment, $isDryRun);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function processTopup(Payment $payment, bool $isDryRun): void
    {
        $companyId = $payment->agent?->branch?->company_id;
        if (!$companyId) return;

        $gateway = $payment->payment_gateway;
        $methodId = $payment->payment_method_id;
        $originalAmount = (float) $payment->amount;
        $storedServiceCharge = (float) $payment->service_charge;

        // Calculate correct fees
        $result = ChargeService::calculate($originalAmount, $companyId, $methodId, $gateway);
        $accountingFee = $result['accountingFee'] ?? 0;
        $paidBy = $result['paid_by'] ?? 'Company';

        // Total client paid
        $totalClientPaid = ($paidBy === 'Client') ? ($originalAmount + $storedServiceCharge) : $originalAmount;

        // Find transaction
        $transaction = Transaction::where('payment_id', $payment->id)->first();

        if (!$transaction) return;

        $this->fixTopupJournalEntries($transaction, $payment, $accountingFee, $totalClientPaid, $originalAmount, $storedServiceCharge, $paidBy, $isDryRun);
    }

    private function fixTopupJournalEntries(Transaction $transaction, Payment $payment, float $accountingFee, float $totalClientPaid, float $originalAmount, float $storedServiceCharge, string $paidBy, bool $isDryRun): void
    {
        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
        $hasChanges = false;

        foreach ($entries as $entry) {
            $descLower = strtolower($entry->description ?? '');

            // Fix Gateway Fee Expense (DEBIT) - use accountingFee (exact)
            if (str_contains($descLower, 'gateway fee') && $entry->debit > 0) {
                $oldDebit = $entry->debit;

                if (abs($oldDebit - $accountingFee) > 0.001) {
                    $this->addChange('Topup', $payment->voucher_number, 'Gateway Fee', $oldDebit, $accountingFee);
                    $hasChanges = true;

                    if (!$isDryRun) {
                        $difference = $accountingFee - $oldDebit;

                        $entry->debit = round($accountingFee, 3);
                        $entry->save();

                        if ($entry->account) {
                            $entry->account->actual_balance += $difference;
                            $entry->account->save();
                        }

                        $this->fixedEntries++;
                    }
                }
            }

            // Fix Gateway Asset (DEBIT) - Net amount = totalClientPaid - accountingFee
            if ((str_contains($descLower, 'client pays by') || str_contains($descLower, 'via (assets)')) && $entry->debit > 0) {
                $correctNet = $totalClientPaid - $accountingFee;
                $oldDebit = $entry->debit;

                if (abs($oldDebit - $correctNet) > 0.001) {
                    $this->addChange('Topup', $payment->voucher_number, 'Net Received', $oldDebit, $correctNet);
                    $hasChanges = true;

                    if (!$isDryRun) {
                        $difference = $correctNet - $oldDebit;

                        $entry->debit = round($correctNet, 3);
                        $entry->save();

                        if ($entry->account) {
                            $entry->account->actual_balance += $difference;
                            $entry->account->save();
                        }

                        $this->fixedEntries++;
                    }
                }
            }

            // Fix Client Advance Liability (CREDIT) - should be original amount
            if (str_contains($descLower, 'advance payment') && $entry->credit > 0) {
                $oldCredit = $entry->credit;

                if (abs($oldCredit - $originalAmount) > 0.001) {
                    $this->addChange('Topup', $payment->voucher_number, 'Client Liability', $oldCredit, $originalAmount);
                    $hasChanges = true;

                    if (!$isDryRun) {
                        $difference = $originalAmount - $oldCredit;

                        $entry->credit = round($originalAmount, 3);
                        $entry->save();

                        // For CREDIT entries, balance adjustment is opposite
                        if ($entry->account) {
                            $entry->account->actual_balance -= $difference; // Credit increases liability, so subtract difference
                            $entry->account->save();
                        }

                        $this->fixedEntries++;
                    }
                }
            }

            // Fix Fee Recovery Income (CREDIT) - only if client pays
            if (str_contains($descLower, 'fee recovery') && $entry->credit > 0 && $paidBy === 'Client') {
                $oldCredit = $entry->credit;

                if (abs($oldCredit - $storedServiceCharge) > 0.001) {
                    $this->addChange('Topup', $payment->voucher_number, 'Fee Recovery', $oldCredit, $storedServiceCharge);
                    $hasChanges = true;

                    if (!$isDryRun) {
                        $difference = $storedServiceCharge - $oldCredit;

                        $entry->credit = round($storedServiceCharge, 3);
                        $entry->save();

                        if ($entry->account) {
                            $entry->account->actual_balance -= $difference;
                            $entry->account->save();
                        }

                        $this->fixedEntries++;
                    }
                }
            }
        }

        if ($hasChanges) {
            $this->fixedPayments++;
        }
    }

    private function addChange(string $type, string $reference, string $entryType, float $old, float $new): void
    {
        $this->changes[] = [
            'type' => $type,
            'reference' => $reference,
            'entry_type' => $entryType,
            'old' => $old,
            'new' => $new,
            'diff' => $new - $old,
        ];
    }

    private function printSummary(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('=============================================');
        $this->info('  Summary');
        $this->info('=============================================');
        $this->info("Invoices fixed: {$this->fixedInvoices}");
        $this->info("Payments fixed: {$this->fixedPayments}");
        $this->info("Journal entries fixed: {$this->fixedEntries}");

        if ($isDryRun && !empty($this->changes)) {
            $this->newLine();
            $this->info('=== Changes that would be made ===');
            $this->table(
                ['Type', 'Reference', 'Entry', 'Old', 'New', 'Diff'],
                collect($this->changes)->map(fn($c) => [
                    $c['type'],
                    $c['reference'],
                    $c['entry_type'],
                    number_format($c['old'], 3),
                    number_format($c['new'], 3),
                    number_format($c['diff'], 3),
                ])->toArray()
            );

            $this->newLine();
            $this->warn('Run without --dry-run to apply changes.');
        }

        Log::info('FixPaymentGatewayCOA completed', [
            'dry_run' => $isDryRun,
            'invoices_fixed' => $this->fixedInvoices,
            'payments_fixed' => $this->fixedPayments,
            'entries_fixed' => $this->fixedEntries,
        ]);
    }
}
