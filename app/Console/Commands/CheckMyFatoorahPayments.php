<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Credit;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\JournalEntry;
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

                $ud = [];
                $udRaw = $result['raw']['UserDefinedField'] ?? null;
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
                    $invoiceRef = $result['raw']['InvoiceReference'] ?? null;

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

                    $transaction = Transaction::create([
                        'branch_id'         => $branchId,
                        'company_id'        => $companyId,
                        'entity_id'         => $companyId,
                        'entity_type'       => 'company',
                        'transaction_type'  => 'debit',
                        'amount'            => $payment->amount,
                        'description'       => 'Topup success by ' . $payment->client->first_name,
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
                        'account_id'         => $clientAdvance->id,
                        'transaction_date'   => now(),
                        'description'        => 'Advance Payment in voucher number: ' . $payment->voucher_number,
                        'debit'              => 0,
                        'credit'             => $payment->amount,
                        'balance'            => ($clientAdvance->actual_balance ?? 0) - $payment->amount,
                        'name'               => $payment->client->first_name,
                        'type'               => 'receivable',
                        'voucher_number'     => $payment->voucher_number,
                        'type_reference_id'  => $clientAdvance->id,
                    ]);

                    $clientAdvance->actual_balance = ($clientAdvance->actual_balance ?? 0) - $payment->amount;
                    $clientAdvance->save();
                }

                if ($result['amount'] !== null) $payment->amount = (float)$result['amount'];
                $payment->status = 'completed';
                $payment->save();

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Failed to complete accounting; payment left uncompleted', [
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
            'KeyType' => "InvoiceId",
        ]);

        Log::info('getPaymentStatusMyFatoorah Response: ', $response->json());

        if (!$response->successful() || !($response->json('IsSuccess'))) {
            $message = $response->json()['Message'] ?? 'Unknown error';

            Log::error('Failed to fetch payment status from MyFatoorah', [
                'invoiceId' => $invoiceId,
                'response' => $response->body()
            ]);

            return [
                'status' => 'error',
                'message' => $message
            ];
        }

        $data = $response->json('Data') ?? [];
        return [
            'success'             => true,
            'invoice_status' => $data['InvoiceStatus'] ?? null,
            'amount'         => $data['InvoiceValue'] ?? null,
            'invoice_id'     => $data['InvoiceId'] ?? null,
            'raw'            => $data,
        ];
    }
}
