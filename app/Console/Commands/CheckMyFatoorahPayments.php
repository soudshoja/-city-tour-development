<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Credit;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\MyFatoorahPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\ClientController;
use App\Services\GatewayConfigService;

class CheckMyFatoorahPayments extends Command
{
    protected $signature = 'app:myfatoorah-check-status {invoiceId?}';
    protected $description = 'Check MyFatoorah payment status for initiated payments (or a specific invoice).';

    public function handle(): int
    {
        $invoiceId = $this->argument('invoiceId');

        $query = Payment::query()
            ->where('payment_gateway', 'MyFatoorah')
            ->where('status', 'initiate');
        if ($invoiceId) {
            $query->where('payment_reference', $invoiceId);
        }
        $payments = $query->get();

        if ($payments->isEmpty()) {
            $this->info('No initiated MyFatoorah payments to check.');
            return self::SUCCESS;
        }

        $updatedPayments = [];
        $bar = $this->output->createProgressBar($payments->count());
        $bar->start();

        foreach ($payments as $payment) {
            $result = $this->getMFPaymentStatus($payment->payment_reference);

            if (empty($result['success'])) {
                Log::warning('MyFatoorah status check skipped', [
                    'payment_id' => $payment->id,
                    'invoice'    => $payment->payment_reference,
                    'message'    => $result['message'] ?? 'Unknown error',
                ]);
                $bar->advance();
                continue;
            }

            $status = $result['invoice_status'] ?? null;
            $map = [
                'Paid'     => 'completed',
                'Pending'  => 'pending',
                'Canceled' => 'canceled',
                'Expired'  => 'expired',
            ];
            $newStatus = $map[$status] ?? strtolower($status);

            if ($newStatus !== 'completed') {
                $bar->advance();
                continue;
            }

            try {
                DB::beginTransaction();

                $data = $result['raw'] ?? [];
                $transaction = $data['InvoiceTransactions'][0] ?? [];
                $invoiceRef = $data['InvoiceReference'] ?? null;
                $authCode = $transaction['AuthorizationId'] ?? null;

                $payment->invoice_reference = $invoiceRef ?? $payment->invoice_reference;
                $payment->auth_code = $authCode ?? $payment->auth_code;
                if ($result['amount'] !== null) {
                    $payment->amount = (float)$result['amount'];
                }

                $ud = [];
                $udRaw = $data['UserDefinedField'] ?? null;
                if (is_string($udRaw) && $udRaw !== '') {
                    $ud = json_decode($udRaw, true) ?: [];
                }

                $process = $ud['process'] ?? null;

                if ($process === 'topup') {
                    $alreadyCredited = Credit::where('client_id', $payment->client_id)
                        ->where('description', 'Topup Credit via ' . $payment->voucher_number)
                        ->exists();
            
                    if (!$alreadyCredited) {
                        $clientController = app(ClientController::class);
                        $resp = $clientController->addCredit($payment);
                        if (is_array($resp) && ($resp['status'] ?? '') !== 'success') {
                            throw new \RuntimeException('addCredit failed: ' . (($resp['message'] ?? 'unknown')));
                        }
                    }
                }

                $alreadyPosted = Transaction::where('payment_id', $payment->id)
                    ->where('reference_type', 'Payment')
                    ->exists();

                if (!$alreadyPosted) {
                    $companyId  = $payment->agent->branch->company->id;
                    $branchId   = $payment->agent->branch->id;

                    $liabilitiesAccount = Account::where('name', 'like', '%Liabilities%')
                        ->where('company_id', $companyId)
                        ->first();
                    if (!$liabilitiesAccount) {
                        throw new \RuntimeException('Liabilities account not found.');
                    }

                    $clientAdvance = Account::where('name', 'Client')
                        ->where('company_id', $companyId)
                        ->where('root_id', $liabilitiesAccount->id)
                        ->first();
                    if (!$clientAdvance) {
                        throw new \RuntimeException('Client advance account not found.');
                    }

                    $paymentGateway = Account::where('name', 'Payment Gateway')
                            ->where('company_id', $companyId)
                            ->where('parent_id', $clientAdvance->id)
                            ->first();
                    if (!$paymentGateway) {
                        throw new \RuntimeException('Payment Gateway account not found');
                    }

                    $transaction = Transaction::create([
                        'branch_id'         => $branchId,
                        'company_id'        => $companyId,
                        'entity_id'         => $companyId,
                        'entity_type'       => 'company',
                        'transaction_type'  => 'debit',
                        'amount'            => $payment->amount,
                        'description'       => 'Topup success by ' . $payment->client->full_name,
                        'payment_id'        => $payment->id,
                        'invoice_id'        => $payment->invoice_id,
                        'payment_reference' => $invoiceRef,
                        'reference_type'    => 'Payment',
                        'transaction_date' => now(),
                    ]);

                    JournalEntry::create([
                        'transaction_id'     => $transaction->id,
                        'branch_id'          => $branchId,
                        'company_id'         => $companyId,
                        'invoice_id'         => $payment->invoice_id,
                        'account_id'         => $paymentGateway->id,
                        'transaction_date'   => now(),
                        'description'        => 'Advance Payment in voucher number: ' . $payment->voucher_number,
                        'debit'              => 0,
                        'credit'             => $payment->amount,
                        'balance'            => ($paymentGateway->actual_balance ?? 0) - $payment->amount,
                        'name'               => $payment->client->full_name,
                        'type'               => 'receivable',
                        'voucher_number'     => $payment->voucher_number,
                        'type_reference_id'  => $paymentGateway->id,
                    ]);

                    $paymentGateway->actual_balance = ($paymentGateway->actual_balance ?? 0) - $payment->amount;
                    $paymentGateway->save();
                }

                $existingMF = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();
                if (!$existingMF) {
                    MyFatoorahPayment::create([
                        'payment_int_id'     => $payment->id,
                        'payment_id'         => $transaction['PaymentId'] ?? null,
                        'invoice_id'         => $data['InvoiceId'] ?? null,
                        'invoice_ref'        => $invoiceRef,
                        'invoice_status'     => $data['InvoiceStatus'] ?? null,
                        'customer_reference' => $process === 'invoice' ? ($payment->invoice?->invoice_number ?? null) : $payment->voucher_number,
                        'payload'            => $data,
                    ]);
                }

                $payment->status = 'completed';
                $payment->save();

                $updatedPayments[] = [
                    'id' => $payment->id,
                    'voucher' => $payment->voucher_number,
                    'reference' => $invoiceRef,
                    'client' => $payment->client->full_name ?? 'Not Set',
                ];

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Failed to finalize MyFatoorah payment', [
                    'payment_id' => $payment->id,
                    'invoice'    => $payment->payment_reference,
                    'error'      => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('MyFatoorah status check complete.');
        $this->info('-----------------------------------------');
        $this->info('Payments updated to status "completed": ' . count($updatedPayments));
        $this->info('-----------------------------------------');

        if (!empty($updatedPayments)) {
            foreach ($updatedPayments as $p) {
                $this->line("- Voucher: {$p['voucher']} | Client: {$p['client']} | InvoiceRef: {$p['reference']}");
            }
        } else {
            $this->line('No payments were updated.');
        }
        return self::SUCCESS;
    }

    private function getMFPaymentStatus($invoiceId): array
    {
        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        if($myfatoorahConfig['status'] === 'error') {
            return $myfatoorahConfig;
        }

        $myfatoorahConfig = $myfatoorahConfig['data'];

        $apiKey  = $myfatoorahConfig['api_key'];
        $baseUrl = $myfatoorahConfig['base_url'];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->post("{$baseUrl}/getPaymentStatus", [
            'Key'     => $invoiceId,
            'KeyType' => 'InvoiceId',
        ]);

        Log::info('getPaymentStatusMyFatoorah Response: ' . json_encode($response->json()));

        if (!$response->successful() || !$response->json('IsSuccess')) {
            return [
                'success' => false,
                'message' => $response->json('Message') ?? 'Failed API response',
            ];
        }

        return [
            'success'        => true,
            'invoice_status' => $response->json('Data.InvoiceStatus'),
            'amount'         => $response->json('Data.InvoiceValue'),
            'invoice_id'     => $response->json('Data.InvoiceId'),
            'raw'            => $response->json('Data'),
        ];
    }
}
