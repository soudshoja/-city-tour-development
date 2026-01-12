<?php

namespace App\Services;

use App\Http\Controllers\ResayilController;
use App\Models\Payment;
use App\Models\PaymentFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentReceiptService
{
    /**
     * Generate PDF receipt and send via WhatsApp
     * 
     * @param Payment $payment The payment record
     * @param string|null $phone Override phone number (if null, uses client's phone)
     * @param string|null $countryCode Override country code (if null, uses client's country_code)
     * @return array ['success' => bool, 'message' => string, 'file_id' => string|null, 'was_cached' => bool, 'error' => string|null]
     */
    public function generateAndSendPdf(Payment $payment, ?string $phone = null, ?string $countryCode = null): array
    {
        try {
            // Load relationships if not already loaded
            $payment->loadMissing(['client', 'agent.branch.company', 'paymentItems', 'paymentMethod']);

            $recipientPhone = $phone ?? $payment->client->phone ?? null;
            $recipientCountryCode = $countryCode ?? $payment->client->country_code ?? '+60';

            if (!$recipientPhone) {
                Log::warning('PaymentReceiptService: No phone number available', [
                    'payment_id' => $payment->id,
                    'client_id' => $payment->client_id,
                ]);

                return [
                    'success' => false,
                    'message' => 'No phone number available for client',
                    'file_id' => null,
                    'was_cached' => false,
                    'error' => 'Missing phone number',
                ];
            }

            // Get cached file or upload new one
            $fileResult = $this->getCachedFileOrUpload($payment);

            if (!$fileResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to prepare PDF file',
                    'file_id' => null,
                    'was_cached' => false,
                    'error' => $fileResult['error'] ?? 'Unknown error',
                ];
            }

            $fileId = $fileResult['file_id'];
            $wasCached = $fileResult['was_cached'];

            // Send via WhatsApp
            $sendResult = $this->sendWhatsAppWithPdf(
                payment: $payment,
                fileId: $fileId,
                phone: $recipientPhone,
                countryCode: $recipientCountryCode
            );

            if (!$sendResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to send PDF via WhatsApp',
                    'file_id' => $fileId,
                    'was_cached' => $wasCached,
                    'error' => $sendResult['error'] ?? 'Unknown error',
                ];
            }

            // If file was re-uploaded during send (because cached was invalid), save it
            if (isset($sendResult['new_file_id'])) {
                $this->saveFileCache(
                    payment: $payment,
                    fileId: $sendResult['new_file_id'],
                    expiresAt: $sendResult['expires_at'] ?? null
                );
            }

            Log::info('PaymentReceiptService: PDF sent successfully', [
                'payment_id' => $payment->id,
                'file_id' => $fileId,
                'was_cached' => $wasCached,
                'phone' => $recipientCountryCode . $recipientPhone,
            ]);

            return [
                'success' => true,
                'message' => "Payment receipt sent to {$recipientCountryCode}{$recipientPhone}",
                'file_id' => $fileId,
                'was_cached' => $wasCached,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('PaymentReceiptService: Exception occurred', [
                'payment_id' => $payment->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while sending receipt',
                'file_id' => null,
                'was_cached' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cached file_id or upload new PDF
     * 
     * @param Payment $payment
     * @return array ['success' => bool, 'file_id' => string|null, 'was_cached' => bool, 'error' => string|null]
     */
    private function getCachedFileOrUpload(Payment $payment): array
    {
        try {
            // Check for cached file
            $paymentFile = PaymentFile::where('payment_id', $payment->id)
                ->where('expiry_date', '>', now())
                ->orderBy('created_at', 'desc')
                ->first();

            if ($paymentFile) {
                $resayil = new ResayilController();
                $fileInfo = $resayil->getFileInfo($paymentFile->file_id);

                if (($fileInfo['success'] ?? false) && ($fileInfo['is_active'] ?? false)) {
                    Log::info('PaymentReceiptService: Using cached file', [
                        'payment_id' => $payment->id,
                        'file_id' => $paymentFile->file_id,
                    ]);

                    return [
                        'success' => true,
                        'file_id' => $paymentFile->file_id,
                        'was_cached' => true,
                        'error' => null,
                    ];
                }

                Log::info('PaymentReceiptService: Cached file no longer active, uploading new', [
                    'payment_id' => $payment->id,
                    'old_file_id' => $paymentFile->file_id,
                ]);
            }

            $pdfPath = $this->generatePdf($payment);

            $resayil = new ResayilController();
            $uploadResult = $resayil->uploadFile($pdfPath);

            // Clean up temp file
            if (Storage::disk('public')->exists(str_replace(storage_path('app/public/'), '', $pdfPath))) {
                Storage::disk('public')->delete(str_replace(storage_path('app/public/'), '', $pdfPath));
            }

            if (!($uploadResult['success'] ?? false)) {
                Log::error('PaymentReceiptService: Upload failed', [
                    'payment_id' => $payment->id,
                    'error' => $uploadResult['error'] ?? 'Unknown error',
                ]);

                return [
                    'success' => false,
                    'file_id' => null,
                    'was_cached' => false,
                    'error' => $uploadResult['error'] ?? 'Upload failed',
                ];
            }

            $this->saveFileCache(
                payment: $payment,
                fileId: $uploadResult['file_id'],
                expiresAt: $uploadResult['expires_at'] ?? null
            );

            return [
                'success' => true,
                'file_id' => $uploadResult['file_id'],
                'was_cached' => false,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('PaymentReceiptService: getCachedFileOrUpload exception', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'file_id' => null,
                'was_cached' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate PDF for payment receipt
     * 
     * @param Payment $payment
     * @return string Path to generated PDF file
     * @throws \Exception
     */
    private function generatePdf(Payment $payment): string
    {
        $pdf = Pdf::loadView('payment.pdf.success', ['payment' => $payment, 'isPdf' => true]);
        
        $filename = "payment_receipt_{$payment->voucher_number}.pdf";
        $path = "temp/{$filename}";
        
        Storage::disk('public')->put($path, $pdf->output());
        
        return storage_path("app/public/{$path}");
    }

    /**
     * Send PDF via WhatsApp using ResayilController
     * 
     * @param Payment $payment
     * @param string $fileId
     * @param string $phone
     * @param string $countryCode
     * @return array ['success' => bool, 'error' => string|null, 'new_file_id' => string|null, 'expires_at' => string|null]
     */
    private function sendWhatsAppWithPdf(Payment $payment, string $fileId, string $phone, string $countryCode): array
    {
        $resayil = new ResayilController();
        
        // ResayilController will verify file_id validity and re-upload if needed
        $response = $resayil->document(
            phone: $phone,
            country_code: $countryCode,
            caption: "Payment Receipt - {$payment->voucher_number}",
            fileId: $fileId
        );

        if (!($response['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Unknown error',
                'new_file_id' => null,
                'expires_at' => null,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'new_file_id' => $response['new_file_id'] ?? null,
            'expires_at' => $response['expires_at'] ?? null,
        ];
    }

    /**
     * Save file_id to cache
     * 
     * @param Payment $payment
     * @param string $fileId
     * @param string|null $expiresAt
     * @return void
     */
    private function saveFileCache(Payment $payment, string $fileId, ?string $expiresAt): void
    {
        try {
            if (!$expiresAt) {
                Log::warning('PaymentReceiptService: No expiry date provided, skipping cache', [
                    'payment_id' => $payment->id,
                    'file_id' => $fileId,
                ]);
                return;
            }

            PaymentFile::create([
                'payment_id' => $payment->id,
                'file_id' => $fileId,
                'expiry_date' => Carbon::parse($expiresAt),
            ]);

            Log::info('PaymentReceiptService: File cached', [
                'payment_id' => $payment->id,
                'file_id' => $fileId,
                'expires_at' => $expiresAt,
            ]);

        } catch (Exception $e) {
            Log::error('PaymentReceiptService: Failed to save cache', [
                'payment_id' => $payment->id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - caching failure shouldn't break the flow
        }
    }
}
