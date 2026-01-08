<?php

namespace App\Http\Traits;

use App\Enums\NotificationEmailTypeEnum;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NotificationMail;
use App\Mail\AutoBillMail;
use App\Mail\PaymentMail;

trait EmailNotificationTrait
{
    use NotificationTrait;

    public function storeNotificationWithEmail(array $data)
    {
        $notification = $this->storeNotification($data);

        try {
            if (!empty($data['email'])) {
                $toEmail = $data['email'];
            } elseif (app()->environment('local')) {
                $toEmail = env('EMAIL_LOCAL', 'it@alphia.net');
            } else {
                $toEmail = 'shoja@citytravelers.co';
            }

            $company = $data['company'] ?? null;

            if ($toEmail) {
                $type = $data['type'] ?? 'default';
                switch ($type) {
                    case NotificationEmailTypeEnum::AUTOBILL:

                        Log::info('[Notification] Sending AutoBill email.');

                        $mailable = new AutoBillMail($data, $company);
                        break;
                    case NotificationEmailTypeEnum::PAYMENT:

                        Log::info('[Notification] Sending Payment email.');

                        $paymentId = $data['payment']['id'] ?? null;
                        $paymentMailType = $data['payment']['type'] ?? null;

                        if(!$paymentId || !$paymentMailType) {
                            throw new \Exception('Payment ID or Payment Mail Type is missing for payment email.');
                        }

                        $mailable = new PaymentMail($paymentId, $paymentMailType);
                        break;

                    default:
                        $mailable = new NotificationMail($data, $company);
                        break;
                }

                Mail::to($toEmail)->send($mailable);
                Log::info("[Notification] Email sent to {$toEmail} for '{$data['title']}' ({$type})");
            }
        } catch (\Exception $e) {
            Log::error('[Notification] Failed to send email: ' . $e->getMessage());
        }

        return $notification;
    }
}
