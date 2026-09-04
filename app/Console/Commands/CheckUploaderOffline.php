<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Detects AIR uploader hosts that have gone silent — no heartbeat for
 * AIR_OFFLINE_ALERT_MINUTES (default 5) — and sends a one-time "OFFLINE"
 * WhatsApp to opt-in recipients (agent_notification_settings.notification_type
 * = 'uploader_alert', channel whatsapp/both, is_active). Sets alert_sent_at so
 * the alert fires once per outage; AirIngestController::heartbeat() clears
 * alert_sent_at and sends the matching "back ONLINE" recovery message.
 *
 * Run every minute via cron. Built 2026-06-03 to close the gap where the
 * FAST_COMO uploader sat offline ~3h with nobody notified — the recovery
 * half existed but nothing ever raised the initial OFFLINE alert.
 */
class CheckUploaderOffline extends Command
{
    protected $signature = 'app:check-uploader-offline';

    protected $description = 'WhatsApp opt-in admins when an AIR uploader host has been offline past the threshold';

    public function handle(): int
    {
        $mins = (int) env('AIR_OFFLINE_ALERT_MINUTES', 5);
        if ($mins < 1) {
            $mins = 5;
        }

        // Newly-offline = stale heartbeat AND we haven't already alerted for this outage.
        $stale = DB::table('uploader_heartbeats')
            ->whereNull('alert_sent_at')
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<=', now()->subMinutes($mins))
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No newly-offline hosts.');
            return self::SUCCESS;
        }

        $recipients = DB::table('agents as a')
            ->join('agent_notification_settings as s', 's.agent_id', '=', 'a.id')
            ->where('s.notification_type', 'uploader_alert')
            ->where('s.is_active', 1)
            ->whereIn('s.channel', ['whatsapp', 'both'])
            ->whereNotNull('a.phone_number')
            ->where('a.phone_number', '!=', '')
            ->select('a.id', 'a.phone_number', 'a.country_code')
            ->get();

        foreach ($stale as $h) {
            // Atomically claim this outage first, so two overlapping cron ticks
            // can't both send. Whoever flips alert_sent_at from NULL wins.
            $claimed = DB::table('uploader_heartbeats')
                ->where('host_id', $h->host_id)
                ->whereNull('alert_sent_at')
                ->update(['alert_sent_at' => now()]);
            if ($claimed === 0) {
                continue;
            }

            $offlineMin = (int) abs(now()->diffInMinutes($h->last_seen_at));
            $msg = "ALERT: AIR uploader '{$h->host_id}' is OFFLINE.\n"
                 . "No heartbeat for {$offlineMin} min (since {$h->last_seen_at}).\n"
                 . "Last status: " . ($h->status ?? 'unknown') . ".\n"
                 . "Please check the PC and restart the City Travelers uploader.\n"
                 . "Dashboard: " . config('app.url');

            if ($recipients->isEmpty()) {
                Log::warning('[CheckUploaderOffline] host offline but no opt-in recipients', ['host' => $h->host_id]);
            }

            foreach ($recipients as $r) {
                try {
                    (new \App\Http\Controllers\ResayilController())->message(
                        phone: $r->phone_number,
                        country_code: $r->country_code ?? '',
                        message: $msg,
                        isDummyNumber: false,
                    );
                } catch (Throwable $e) {
                    Log::error('[CheckUploaderOffline] send failed', [
                        'host' => $h->host_id, 'agent_id' => $r->id, 'err' => $e->getMessage(),
                    ]);
                }
            }

            $this->warn("Alerted OFFLINE host {$h->host_id} ({$offlineMin}m) to {$recipients->count()} recipient(s).");
        }

        return self::SUCCESS;
    }
}
