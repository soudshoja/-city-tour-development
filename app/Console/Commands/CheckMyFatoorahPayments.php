<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\PaymentController;
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
                $data = $result['raw'] ?? [];

                if (empty($data) || !isset($data['InvoiceValue'])) {
                    Log::warning('MyFatoorah status check: Paid response missing Data payload, skipping', [
                        'payment_id' => $payment->id,
                        'invoice'    => $payment->payment_reference,
                    ]);
                    $bar->advance();
                    continue;
                }

                $ud = [];
                $udRaw = $data['UserDefinedField'] ?? null;
                if (is_string($udRaw) && $udRaw !== '') {
                    $ud = json_decode($udRaw, true) ?: [];
                }

                $process = $ud['process'] ?? ($payment->invoice_id ? 'invoice' : 'topup');
                $partialId = $ud['invoice_partial_id'] ?? null;

                // Route through the SAME completion path used by the MyFatoorah callback and
                // webhook (PaymentController::processMyFatoorahPaymentCompletion): it updates
                // the payment, dedups the myfatoorah_payments row, posts client credit with
                // balanced journal entries via ClientController::addCredit for topups, and
                // settles invoice-linked payments via createInvoicePaymentCOA — instead of the
                // hand-rolled (single-sided, topup-shaped) writes this command used to do.
                app(PaymentController::class)->processMyFatoorahPaymentCompletion(
                    $payment,
                    $data,
                    $process,
                    $partialId,
                    false
                );

                $updatedPayments[] = [
                    'id' => $payment->id,
                    'voucher' => $payment->voucher_number,
                    'reference' => $data['InvoiceReference'] ?? null,
                    'client' => $payment->client->full_name ?? 'Not Set',
                ];
            } catch (\Throwable $e) {
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
