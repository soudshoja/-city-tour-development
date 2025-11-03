<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NotificationMail;
use App\Mail\AutoBillMail;

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
                $toEmail = 'thclown12@gmail.com';
            } else {
                $toEmail = 'shoja@citytravelers.co';
            }

            $company = $data['company'] ?? null;

            if ($toEmail) {
                $type = $data['type'] ?? 'default';
                switch ($type) {
                    case 'autobill':
                        $mailable = new AutoBillMail($data, $company);
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
