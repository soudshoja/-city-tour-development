<?php

namespace App\Console\Commands;

use App\AI\AIManager;
use App\Http\Controllers\TaskController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Credit;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\Charge;
use App\Services\ChargeService;
use Carbon\Carbon;
use Exception;

class CreateClientCredit extends Command
{
    protected $signature = 'create:client-credit
                            {--dry-run : Show expected process without making changes}
                           ';
    protected $description = 'Create the missing client credit for existing paid payment';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Starting to process creating credit for client with existing paid payment');

        try {
            $payments = $this->PaymentWithoutCredit();

            if ($payments->isEmpty()) {
                $this->info('No payments found that need processing');
                return 0;
            }

            $this->info("Found {$payments->count()} payments to process");

            $this->table(
                [
                    'ID',
                    'Voucher Number',
                    'Payment Gateway',
                    'Payment Method ID',
                    'Status'
                ],
                $payments->map(function ($payment) {
                    return [
                        $payment->id,
                        $payment->voucher_number,
                        $payment->payment_gateway,
                        $payment->payment_method_id,
                        $payment->status,
                    ];
                })->toArray()
            );

            if ($dryRun) {
                $this->info('DRY RUN completed - no changes has been made');
                return 0;
            }

            if (!$this->confirm('Do you want to proceed with creating credits for client with existing paid payment?')) {
                $this->info('Operation cancelled');
                return 0;
            }

            $processed = 0;
            $errors = 0;

            foreach ($payments as $creditPayment) {
                try {
                    $this->processCredit($creditPayment);
                    $processed++;
                    $this->info("Processed credit: {$creditPayment->voucher_number}");
                } catch (Exception $e) {
                    $errors++;
                    $this->error("Failed to process credit {$creditPayment->voucher_number}" . $e->getMessage());
                    Log::error("Credit processing failed: {$creditPayment->voucher_number}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("\nProcessing complete:");
            $this->info("Successfully processed {$processed} credits for paid payment");
            if ($errors > 0) {
                $this->warn("Errors encoutered: {$errors} during credit processing");
            }

            return 0;
        } catch (Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Creating new credit for exisitng paid payment command failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function PaymentWithoutCredit()
    {
        $paidPayment = Payment::where('status', 'completed')
            ->whereDoesntHave('credit')
            ->get();
        return $paidPayment;
    }

    public function processCredit($creditPayment)
    {
        $client = Client::findOrFail($creditPayment->client_id);
        $agent = Agent::find($creditPayment->agent_id);

        if (!$client || !$agent) {
            return [
                'status' => 'error',
                'message' => 'Client or Agent not found',
            ];
        }

        DB::beginTransaction();

          try {
            // Insert credit table
            $topupCreditClientData = [
                'company_id'  => $agent->branch->company->id,
                'client_id'   => $client->id,
                'type'        => 'Topup',
                'payment_id'  => $creditPayment->id,
                'description' => 'Topup Credit via ' . $creditPayment->voucher_number,
                'amount'      => $creditPayment->amount,
            ];

            Log::info('Creating Credit record:', $topupCreditClientData);

            Credit::create($topupCreditClientData);

            Log::info('Credit record created successfully for client ID: ' . $client->id);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Credit record', [
                'data'  => $topupCreditClientData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        DB::commit();

        DB::beginTransaction();
        try {

            $chargeRecord = Charge::where('name', 'LIKE', '%' . $creditPayment->payment_gateway . '%')
                ->where('company_id', $agent->branch->company->id)
                ->select('amount', 'acc_bank_id', 'acc_fee_bank_id', 'acc_fee_id', 'paid_by')
                ->first();
            $paymentMethod = $creditPayment->paymentMethod;
            $paidBy = $paymentMethod?->paid_by ?? $chargeRecord?->paid_by ?? 'Company';

            if ($chargeRecord) {
                $coaBankIdRec = $chargeRecord->acc_bank_id; //COA (Assets) for Debited Bank Account
                $coaFeeIdRec = $chargeRecord->acc_fee_id; //COA (Expenses) for Payment Gateway Fee
                $coaBankFeeIdRec = $chargeRecord->acc_fee_bank_id; //COA (Assets) for Bank Account for the selected Payment Gateway

                $bankCOAFee = Account::where('id', $coaFeeIdRec)
                    ->where('company_id', $agent->branch->company->id)
                    ->first();

                $bankPaymentFee = Account::where('id', $coaBankFeeIdRec)
                    ->where('company_id', $agent->branch->company->id)
                    ->first();
            }

            if (strtolower($creditPayment->payment_gateway) === 'myfatoorah') {
                try {
                    $gatewayFee = ChargeService::FatoorahCharge($creditPayment->amount, $paymentMethod->id, $creditPayment->agent->branch->company_id)['gatewayFee'] ?? 0;
                } catch (Exception $e) {
                    Log::error('FatoorahCharge exception', [
                        'message' => $e->getMessage(),
                        'paymentMethod' => $paymentMethod->id,
                        'company_id' => $creditPayment->agent->branch->company_id,
                    ]);
                    $gatewayFee = 0;
                }
            } elseif (strtolower($creditPayment->payment_gateway) === 'tap') {
                try {
                    $gatewayFee = ChargeService::TapCharge([
                        'amount' => $creditPayment->amount,
                        'client_id' => $creditPayment->client_id,
                        'agent_id' => $creditPayment->agent_id,
                        'currency' => $creditPayment->currency
                    ], $creditPayment->payment_gateway)['gatewayFee'] ?? 0;
                } catch (Exception $e) {
                    Log::error('TapCharge exception', [
                        'message' => $e->getMessage(),
                        'amount' => $creditPayment->amount,
                        'client_id' => $creditPayment->client_id,
                        'agent_id' => $creditPayment->agent_id,
                    ]);
                    $gatewayFee = 0;
                }
            } elseif (strtolower($creditPayment->payment_gateway) === 'hesabe') {
                try {
                    $gatewayFee = ChargeService::HesabeCharge($creditPayment->amount, $paymentMethod->id, $creditPayment->agent->branch->company_id)['gatewayFee'] ?? 0;
                } catch (Exception $e) {
                    Log::error('HesabeCharge exception', [
                        'message' => $e->getMessage(),
                        'amount' => $creditPayment->amount,
                        'payment_method' => $paymentMethod->id,
                        'company_id' => $creditPayment->agent->branch->company_id,
                    ]);
                    $gatewayFee = 0;
                }
            } else if (strtolower($creditPayment->payment_gateway) === 'upayment') {
                try {
                    $gatewayFee = ChargeService::UPaymentCharge($creditPayment->amount, $paymentMethod->id, $creditPayment->agent->branch->company_id)['fee'] ?? 0;
                } catch (Exception $e) {
                    Log::error('PaypalCharge exception', [
                        'message' => $e->getMessage(),
                        'amount' => $creditPayment->amount,
                        'payment_method' => $paymentMethod->id,
                        'company_id' => $creditPayment->agent->branch->company_id,
                    ]);
                    $gatewayFee = 0;
                }
            } else {
                $gatewayFee = $chargeRecord?->amount ?? 0;
            }

            $transaction = Transaction::create([
                'branch_id' =>  $agent->branch->id,
                'company_id' =>  $agent->branch->company->id,
                'entity_id' =>  $agent->branch->company->id,
                'entity_type' => 'client',
                'transaction_type' => 'debit',
                'amount' => $creditPayment->amount,
                'description' => 'Client Credit of ' . $client->full_name,
                'invoice_id' => null,
                'reference_type' => 'Payment',
                'reference_number' => $creditPayment->voucher_number,
                'transaction_date' => now(),
            ]);

            $receivableAccount = Account::where('name', 'Clients')->first();
            $receivableAccountId = $receivableAccount->id;

            if ($bankPaymentFee) {
                // Create record to payment_gateway assets coa account (OK)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $agent->branch->company->id,
                    'branch_id' => $agent->branch->id,
                    'account_id' =>  $bankPaymentFee->id,
                    'transaction_date' => Carbon::now(),
                    'description' => 'Client Pays by ' . $client->full_name . ' via (Assets): ' . $bankPaymentFee->name,
                    'debit' => $creditPayment->amount,
                    'credit' => 0,
                    'name' =>  $bankPaymentFee->name,
                    'type' => 'bank',
                    'voucher_number' => $creditPayment->voucher_number,
                    'type_reference_id' => $bankPaymentFee->id
                ]);

                $bankPaymentFee->actual_balance += ($creditPayment->amount - $gatewayFee);
                $bankPaymentFee->save();
            }

            $bankCOAFee->actual_balance += $gatewayFee;

            if ($bankCOAFee) {
                JournalEntry::create([
                    'transaction_id'    => $transaction->id,
                    'company_id'        => $agent->branch->company->id,
                    'branch_id'         => $agent->branch->id,
                    'account_id'        => $bankCOAFee->id,
                    'voucher_number'    => $creditPayment->voucher_number,
                    'transaction_date'  => Carbon::now(),
                    'description'       => ($paidBy === 'Company' ? 'Company Pays Gateway Fee: ' : 'Client Pays Gateway Fee: ') . $bankCOAFee->name,
                    'debit'             => $gatewayFee,
                    'credit'            => 0,
                    'balance'           => $bankCOAFee->actual_balance + $gatewayFee,
                    'name'              => $bankCOAFee->name,
                    'type'              => 'charges',
                    'type_reference_id' => $bankCOAFee->id
                ]);

                $bankCOAFee->actual_balance += $gatewayFee;
                $bankCOAFee->save();
            }
        } catch (Exception $e) {
            DB::rollBack();
            logger('Error adding JournalEntry: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to add JournalEntry',
            ];
        }

        DB::commit();
        return [
            'status' => 'success',
            'message' => 'Credit added successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $creditPayment->amount,
            ],
        ];
    }
}
