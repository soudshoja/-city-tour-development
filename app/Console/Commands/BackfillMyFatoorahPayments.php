<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\MyFatoorahPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\GatewayConfigService;

class BackfillMyFatoorahPayments extends Command
{
    protected $signature = 'app:myfatoorah-backfill {invoiceId?}';
    protected $description = 'Backfill missing MyFatoorahPayment records for completed payments and update invoice details.';

    public function handle(): int
    {
        $invoiceId = $this->argument('invoiceId');

        $query = Payment::query()
            ->where('payment_gateway', 'MyFatoorah')
            ->where('status', 'completed');

        if ($invoiceId) {
            $query->where('payment_reference', $invoiceId);
        }

        $payments = $query->get();

        if ($payments->isEmpty()) {
            $this->info('No completed MyFatoorah payments found.');
            return self::SUCCESS;
        }

        $createdRecords = [];
        $updatedRecords = [];

        $bar = $this->output->createProgressBar($payments->count());
        $bar->start();

        foreach ($payments as $payment) {
            $mfPayment = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();

            $needsFetch = false;

            if (!$mfPayment || empty($payment->invoice_reference) || empty($payment->auth_code)) {
                $needsFetch = true;
            }

            if (!$needsFetch) {
                $bar->advance();
                continue;
            }

            $result = $this->getMFPaymentStatus($payment->payment_reference);

            if (empty($result['success'])) {
                Log::warning('Skipping MyFatoorah backfill due to failed status fetch', [
                    'payment_id' => $payment->id,
                    'invoice' => $payment->payment_reference,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
                $bar->advance();
                continue;
            }

            try {
                $data = $result['raw'] ?? [];
                $transaction = $data['InvoiceTransactions'][0] ?? [];
                $process = null;

                if (!empty($data['UserDefinedField'])) {
                    $ud = json_decode($data['UserDefinedField'], true);
                    $process = $ud['process'] ?? null;
                }

                $customerReference = $process === 'invoice' ? ($payment->invoice?->invoice_number ?? null) : $payment->voucher_number;

                $oldRef = $payment->invoice_reference;
                $oldAuth = $payment->auth_code;

                $payment->invoice_reference = $data['InvoiceReference'] ?? $payment->invoice_reference;
                $payment->auth_code = $transaction['AuthorizationId'] ?? $payment->auth_code;
                $payment->save();

                if ($oldRef !== $payment->invoice_reference || $oldAuth !== $payment->auth_code) {
                    $updatedRecords[] = [
                        'voucher' => $payment->voucher_number,
                        'client' => $payment->client->full_name ?? 'N/A',
                        'invoice_ref' => $payment->invoice_reference,
                    ];
                }

                if (!$mfPayment) {
                    MyFatoorahPayment::create([
                        'payment_int_id' => $payment->id,
                        'payment_id' => $transaction['PaymentId'] ?? null,
                        'invoice_id' => $data['InvoiceId'] ?? null,
                        'invoice_ref' => $data['InvoiceReference'] ?? null,
                        'invoice_status' => $data['InvoiceStatus'] ?? null,
                        'customer_reference' => $customerReference,
                        'payload' => $data,
                    ]);

                    $createdRecords[] = [
                        'voucher' => $payment->voucher_number,
                        'client' => $payment->client->full_name ?? 'N/A',
                        'invoice_ref' => $payment->invoice_reference,
                    ];

                    Log::info("MyFatoorahPayment created for payment ID {$payment->id}");
                } else {
                    Log::info("MyFatoorahPayment already exists for payment ID {$payment->id} — only updated payment fields.");
                }

            } catch (\Throwable $e) {
                Log::error("Failed to backfill/update MyFatoorahPayment for payment ID {$payment->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('MyFatoorah backfill complete (invoice_reference & auth_code updated).');
        $this->info('--------------------------------------------');
        $this->info('Created new MyFatoorahPayment records: ' . count($createdRecords));
        $this->info('Updated existing Payment fields: ' . count($updatedRecords));
        $this->info('--------------------------------------------');

        if (!empty($createdRecords)) {
            $this->newLine();
            $this->info('🆕 Created Records:');
            $this->table(['Voucher', 'Client', 'Invoice Reference'], $createdRecords);
        }

        if (!empty($updatedRecords)) {
            $this->newLine();
            $this->info('✏️ Updated Payments:');
            $this->table(['Voucher', 'Client', 'Invoice Reference'], $updatedRecords);
        }

        if (empty($createdRecords) && empty($updatedRecords)) {
            $this->info('No records needed update or creation.');
        }

        return self::SUCCESS;
    }

    private function getMFPaymentStatus($invoiceId): array
    {
        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        if ($myfatoorahConfig['status'] === 'error') {
            return $myfatoorahConfig;
        }

        $config = $myfatoorahConfig['data'];
        $apiKey = $config['api_key'];
        $baseUrl = $config['base_url'];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/getPaymentStatus", [
            'Key' => $invoiceId,
            'KeyType' => 'InvoiceId',
        ]);

        Log::info('Backfill MyFatoorah API Response: ' . json_encode($response->json()));

        if (!$response->successful() || !$response->json('IsSuccess')) {
            return [
                'success' => false,
                'message' => $response->json('Message') ?? 'Failed API response',
            ];
        }

        return [
            'success' => true,
            'invoice_status' => $response->json('Data.InvoiceStatus'),
            'amount' => $response->json('Data.InvoiceValue'),
            'invoice_id' => $response->json('Data.InvoiceId'),
            'raw' => $response->json('Data'),
        ];
    }
}
