<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Account; // Import the Account model
use App\Models\Transaction; // Import the Transaction model
use App\Models\JournalEntry; // Import the JournalEntry model
use App\Models\Payment;
use App\Models\Charge;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentReleaseToCompanyBankAccProcess extends Command
{
    // The name and signature of the console command.
    protected $signature = 'app:payment-release-to-company-bankacc-process';

    // The console command description.
    protected $description = 'Process daily payments and generate journal entries to complete pay invoice';

    // Create the command instance.
    public function __construct()
    {
        parent::__construct();
    }

    // Execute the console command.
    public function handle()
    {
        // Log the start of the task
        \Log::info('Starting daily task...');

        // Example task logic - Process all payments
        Payment::where('completed', 0)->chunk(100, function ($payments) {
            foreach ($payments as $payment) {
                try {
                    // Your existing logic here, such as creating transactions
                    $invoice = $payment->invoice;

                    if (!$invoice || !$invoice->agent || !$invoice->agent->branch || !$invoice->agent->branch->company) {
                        \Log::warning("Missing relationships for payment ID: {$payment->id}");
                        continue;
                    }

                    $paymentGateway = $payment->payment_method;
                    $chargeRecord = Charge::where('name', 'LIKE', $paymentGateway)
                    ->where('company_id', $invoice->agent->branch->company->id)
                    ->select('amount', 'acc_bank_id', 'acc_fee_bank_id', 'acc_fee_id')
                    ->first(); 
        
                    if ($chargeRecord) {
                        $defaultPaymentGatewayFee = $chargeRecord->amount;
                        $coaBankIdRec = $chargeRecord->acc_bank_id; //COA (Assets) for Debited Bank Account
                        $coaFeeIdRec = $chargeRecord->acc_fee_id; //COA (Expenses) for Payment Gateway Fee
                        $coaBankFeeIdRec = $chargeRecord->acc_fee_bank_id; //COA (Assets) for Bank Account for the selected Payment Gateway

                        $bankAccountAccRecord = Account::where('id', $coaBankIdRec)
                        ->where('company_id', $invoice->agent->branch->company->id)
                        ->first();
            
                        $tapAccount = Account::where('id', $coaFeeIdRec)
                        ->where('company_id', $invoice->agent->branch->company->id)
                        ->first();
            
                        $bankPaymentFee = Account::where('id', $coaBankFeeIdRec)
                        ->where('company_id', $invoice->agent->branch->company->id)
                        ->first();
                    }

                    $invoiceDetail = $invoice->invoiceDetails->first();

                    $journalEntries = JournalEntry::where('company_id', $invoice->agent->branch->company->id)
                        ->where('invoice_id', $payment->invoice_id)
                        ->where('type', 'charges')
                        ->first();


                    $totalPaidAmount = $payment->amount - $journalEntries->debit;

                    // Creating the transaction record
                    $transaction = Transaction::create([
                        'branch_id' =>  $invoice->agent->branch->id,
                        'company_id' =>  $invoice->agent->branch->company->id,
                        'entity_id' =>  $invoice->agent->branch->company->id,
                        'entity_type' => 'company',
                        'transaction_type' => 'payment',
                        'amount'=> $totalPaidAmount,
                        'date'=> Carbon::now(),
                        'description'=> 'Payment Released to Bank:'.$bankAccountAccRecord->name.' for Invoice:'. $invoiceDetail->invoice_number,
                        'invoice_id' => $payment->invoice_id, 
                        'reference_type' => 'Invoice',
                    ]);

                    $transactionId = $transaction->id; 
                    \Log::info("Transaction ID for Payment - {$payment->id}: Trx - {$transactionId}");

                    \Log::info('Transaction Details:', [
                        'branch_id' => $transaction->branch_id,
                        'company_id' => $transaction->company_id,
                        'entity_id' => $transaction->entity_id,
                        'entity_type' => $transaction->entity_type,
                        'transaction_type' => $transaction->transaction_type,
                        'amount' => $transaction->amount,
                        'description' => $transaction->description,
                        'invoice_id' => $transaction->invoice_id,
                        'reference_type' => $transaction->reference_type,
                        'created_at' => $transaction->created_at,
                    ]);

                    // Debit Entry (recording the payment as debit)
                    JournalEntry::create([
                        'transaction_id' => $transactionId,
                        'branch_id' => $invoice->agent->branch->id,
                        'company_id' => $invoice->agent->branch->company->id,
                        'invoice_id' => $payment->invoice_id,
                        'account_id' => $bankAccountAccRecord->id,
                        'invoice_detail_id' => $invoiceDetail ? $invoiceDetail->id : null,
                        'transaction_date' => Carbon::now(),
                        'description' => 'Payment Invoice:'. $invoiceDetail->invoice_number . ' ('.$payment->pay_to.') released from '.$bankPaymentFee->name,
                        'debit' => $totalPaidAmount,
                        'credit' => 0,
                        'balance' => 0,
                        'name' => $bankAccountAccRecord->name,
                        'type' => 'receivable',
                        'voucher_number' => $payment->voucher_number,
                        'type_reference_id' => $payment->account_id,
                    ]);

                    // Credit Entry (recording the payment as credit)
                    JournalEntry::create([
                        'transaction_id' => $transactionId,
                        'branch_id' => $invoice->agent->branch->id,
                        'company_id' => $invoice->agent->branch->company->id,
                        'invoice_id' => $payment->invoice_id,
                        'account_id' => $bankPaymentFee->id,
                        'invoice_detail_id' => $invoiceDetail ? $invoiceDetail->id : null,
                        'transaction_date' => Carbon::now(),
                        'description' => 'Payment Invoice:'. $invoiceDetail->invoice_number . ' ('.$payment->from.') released to '.$bankAccountAccRecord->name,
                        'debit' => 0,
                        'credit' => $totalPaidAmount,
                        'balance' => 0,
                        'name' => $bankPaymentFee->name,
                        'type' => 'receivable',
                        'voucher_number' => $payment->voucher_number,
                        'type_reference_id' => $payment->account_id,
                    ]);

                    // Once everything is successful, mark the payment as completed
                    $payment->completed = 1;
                    $payment->save();

                    \Log::info("Payment ID {$payment->id} marked as completed.");
                } catch (Exception $e) {
                    \Log::error("Error processing Payment ID {$payment->id}: " . $e->getMessage());
                }
            }
        });

        \Log::info('Daily task completed.');
    }
}
