<?php

namespace App\Http\Traits;

use App\Enums\NotificationEmailTypeEnum;
use App\Http\Controllers\ResayilController;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Mail\NotificationMail;
use App\Mail\AutoBillMail;
use App\Mail\PaymentMail;

trait EmailNotificationTrait
{
    use NotificationTrait;

    public function storeNotificationWithEmail(array $data)
    {
        $notification = $this->storeNotification($data);

        $type = $data['type'] ?? 'default';
        $company = $data['company'] ?? null;
        $companyId = $data['company_id'] ?? ($company->id ?? null);

        // For autobill: check channel setting to determine email/whatsapp/both/none
        if ($type === NotificationEmailTypeEnum::AUTOBILL || $type === 'autobill') {
            $channel = $companyId
                ? Setting::getByKey($companyId, 'notification.autobill.channel', 'none')
                : 'none';

            if (in_array($channel, ['email', 'both'])) {
                $this->sendAutoBillEmail($data, $companyId, $company);
            }

            if (in_array($channel, ['whatsapp', 'both'])) {
                $this->sendAutoBillWhatsApp($data, $companyId, $company);
            }

            return $notification;
        }

        // For all other types: keep existing email-only behavior
        try {
            $toEmail = $this->resolveEmail($data, $companyId);

            if ($toEmail) {
                $mailable = $this->resolveMailable($type, $data, $company);
                Mail::to($toEmail)->send($mailable);
                Log::info("[Notification] Email sent to {$toEmail} for '{$data['title']}' ({$type})");
            }
        } catch (\Exception $e) {
            Log::error('[Notification] Failed to send email: ' . $e->getMessage());
        }

        return $notification;
    }

    private function sendAutoBillEmail(array $data, ?int $companyId, $company): void
    {
        try {
            $toEmail = $this->resolveEmail($data, $companyId, 'notification.autobill.email');

            if (empty($toEmail)) {
                Log::warning('[AutoBill] No email configured for company ' . ($companyId ?? 'unknown'));
                return;
            }

            Mail::to($toEmail)->send(new AutoBillMail($data, $company));
            Log::info("[AutoBill] Email sent to {$toEmail} for '{$data['title']}'");
        } catch (\Exception $e) {
            Log::error('[AutoBill] Failed to send email: ' . $e->getMessage());
        }
    }

    private function sendAutoBillWhatsApp(array $data, ?int $companyId, $company): void
    {
        try {
            $phone = $this->resolvePhone($companyId, 'notification.autobill.phone');

            if (empty($phone)) {
                Log::warning('[AutoBill] No phone configured for company ' . ($companyId ?? 'unknown'));
                return;
            }

            $companyName = $company->name ?? config('app.name', 'City Tour');
            $status = $data['status'] ?? 'success';

            if ($status === 'warning') {
                $taskList = collect($data['ineligibleTasks'] ?? [])->map(fn ($t) =>
                    "- {$t['reference']}: {$t['issues']}"
                )->implode("\n");

                $nextRun = $data['nextRunAt'] ?? 'N/A';

                $caption = "*AutoBill: Tasks Need Attention*\n\n"
                    . "The following tasks for *{$data['clientName']}* could not be invoiced:\n\n"
                    . "{$taskList}\n\n"
                    . "Please fix them before the next AutoBill run on *{$nextRun}*.\n\n"
                    . "_{$companyName}_";
            } elseif ($status === 'failed') {
                $caption = "*AutoBill Failed*\n\n"
                    . "AutoBilling failed for *{$data['clientName']}*.\n\n"
                    . "Error: {$data['errorMessage']}\n\n"
                    . "Please review the AutoBilling logs.\n\n"
                    . "_{$companyName}_";
            } else {
                $caption = "*AutoBill Invoice Generated*\n\n"
                    . "Invoice *#{$data['invoiceNumber']}* has been created for *{$data['clientName']}*.\n\n"
                    . "Amount: *{$data['amount']} {$data['currency']}*\n"
                    . "Tasks: {$data['taskCount']}\n\n"
                    . "_{$companyName}_";
            }

            $pdfPath = $this->generateAutoBillPdf($data, $company);

            $resayil = new ResayilController();
            $response = $resayil->document(
                phone: $phone,
                country_code: '',
                filePath: $pdfPath,
                caption: $caption,
            );

            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }

            if ($response['success'] ?? false) {
                Log::info("[AutoBill] WhatsApp sent to {$phone} for '{$data['title']}'");
            } else {
                Log::error("[AutoBill] WhatsApp failed for company {$companyId}: " . json_encode($response));
            }
        } catch (\Exception $e) {
            Log::error('[AutoBill] Failed to send WhatsApp: ' . $e->getMessage());
        }
    }

    private function generateAutoBillPdf(array $data, $company): string
    {
        $pdf = Pdf::loadView('email.autobill-notification', array_merge($data, [
            'company' => $company,
            'isPdf' => true,
        ]));

        $filename = 'autobill_' . ($company->id ?? 0) . '_' . now()->format('Ymd_His') . '.pdf';
        $path = "temp/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        return storage_path("app/public/{$path}");
    }

    private function resolveEmail(array $data, ?int $companyId, ?string $settingKey = null): ?string
    {
        if (!empty($data['email'])) {
            return $data['email'];
        }

        if (app()->environment('local')) {
            return env('EMAIL_LOCAL', 'it@alphia.net');
        }

        if ($companyId && $settingKey) {
            return Setting::getByKey($companyId, $settingKey);
        }

        return null;
    }

    private function resolvePhone(?int $companyId, string $settingKey): ?string
    {
        if (app()->environment('local')) {
            return env('PHONE_LOCAL', null);
        }

        return $companyId ? Setting::getByKey($companyId, $settingKey) : null;
    }

    private function resolveMailable(string $type, array $data, $company)
    {
        if ($type === NotificationEmailTypeEnum::PAYMENT) {
            $paymentId = $data['payment']['id'] ?? null;
            $paymentMailType = $data['payment']['type'] ?? null;

            if (!$paymentId || !$paymentMailType) {
                throw new \Exception('Payment ID or Payment Mail Type is missing for payment email.');
            }

            return new PaymentMail($paymentId, $paymentMailType);
        }

        return new NotificationMail($data, $company);
    }
}
