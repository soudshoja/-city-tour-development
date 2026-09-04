<?php

namespace App\Console\Commands;

use App\Services\InboundMailIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

/**
 * Mode A (IMAP poll) for cPanel per-agent email ingestion. Polls each
 * configured mailbox for unseen mail and hands every message to
 * InboundMailIngestService. dev1 validation transport — see
 * docs/design_cpanel_per_agent_email_ingestion.md (Mode B = cPanel mail-pipe
 * is the go-live push transport and reuses the same service).
 */
class IngestInboundMail extends Command
{
    protected $signature = 'mail:ingest-inbound {--limit=50 : Max unseen messages per mailbox} {--keep-unseen : Do not mark messages as read}';
    protected $description = 'Poll cPanel agent mailboxes (IMAP) and ingest supplier PDF attachments into the parser pipeline.';

    /**
     * Skip (and mark read) anything bigger than this. A single oversized mail
     * used to exhaust memory mid-fetch — an uncatchable fatal that killed the
     * whole run before the later mailboxes were ever polled.
     */
    private const MAX_MESSAGE_BYTES = 12582912; // 12 MB

    public function handle(InboundMailIngestService $service): int
    {
        // Piggyback (citycomm-only): this host cannot register a dedicated cron
        // (exec disabled, cPanel UAPI Cron module absent, crond ignores spool
        // edits), so poll the IATA wallet here at most once every ~15 min. Runs
        // before the mail-config early-returns; fully guarded so it can never
        // affect mail ingestion. See app:check-iata-wallet.
        try {
            $iataMarker = storage_path('app/iata_wallet_last_poll');
            if (!is_file($iataMarker) || (time() - filemtime($iataMarker)) >= 870) {
                @touch($iataMarker);
                \Illuminate\Support\Facades\Artisan::call('app:check-iata-wallet');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[iata-wallet piggyback] ' . $e->getMessage());
        }

        if (!config('mail_ingest.enabled')) {
            $this->warn('mail_ingest disabled (set MAIL_INGEST_ENABLED=true).');
            return self::SUCCESS;
        }
        if (config('mail_ingest.mode') !== 'imap') {
            $this->info('mail_ingest.mode != imap — IMAP poll skipped (pipe mode handles delivery).');
            return self::SUCCESS;
        }

        $limit    = (int) $this->option('limit');
        $markSeen = !$this->option('keep-unseen');
        $totalSaved = 0;
        // Headroom for one message at a time; the old batch fetch died at 128M.
        if (($cur = ini_get('memory_limit')) !== '-1' && (int) $cur < 512) {
            @ini_set('memory_limit', '512M');
        }

        foreach (config('mail_ingest.mailboxes') as $mb) {
            if (empty($mb['username']) || empty($mb['password'])) {
                $this->warn('Mailbox credentials missing — skipping an entry.');
                continue;
            }
            $address = strtolower((string) ($mb['address'] ?: $mb['username']));
            $this->info("Polling {$address} ...");

            try {
                $client = Client::make([
                    'host'          => $mb['host'],
                    'port'          => $mb['port'],
                    'encryption'    => $mb['encryption'],
                    'validate_cert' => $mb['validate_cert'],
                    'username'      => $mb['username'],
                    'password'      => $mb['password'],
                    'protocol'      => 'imap',
                ]);
                $client->connect();

                $folder = $client->getFolder('INBOX');

                // Pass 1: headers only — cheap, and gives us the size so an
                // oversized mail can be skipped instead of killing the run.
                $headers = $folder->query()->unseen()->limit($limit)
                    ->setFetchBody(false)->leaveUnread()->get();
                $this->info("  {$headers->count()} unseen message(s)");
                $queue = [];
                foreach ($headers as $h) {
                    $queue[] = ['uid' => $h->getUid(), 'size' => (int) $h->getSize()];
                }
                unset($headers);

                // Pass 2: one message at a time, freed between each.
                foreach ($queue as $item) {
                    $uid = $item['uid'];
                    if ($item['size'] > self::MAX_MESSAGE_BYTES) {
                        $this->warn("  uid {$uid}: {$item['size']} bytes — too large, skipped");
                        Log::warning('mail:ingest-inbound oversized message skipped', [
                            'mailbox' => $address, 'uid' => $uid, 'size' => $item['size'],
                        ]);
                        if ($markSeen) {
                            $folder->query()->getMessageByUid($uid)?->setFlag('Seen');
                        }
                        continue;
                    }
                    try {
                        $message = $folder->query()->leaveUnread()->getMessageByUid($uid);
                        if (!$message) {
                            continue;
                        }
                        $res = $service->ingestWebklexMessage($message, $address);
                        $totalSaved += (int) ($res['saved'] ?? 0);
                        $this->line('  ' . ($message->getMessageId() ?: '(no-id)') . ': ' . json_encode($res));
                        if ($markSeen) {
                            $message->setFlag('Seen');
                        }
                        unset($message, $res);
                    } catch (\Throwable $e) {
                        // One bad message must not stall the mailbox forever:
                        // log it and mark read so the queue keeps draining.
                        $this->error("  uid {$uid}: " . $e->getMessage());
                        Log::warning('mail:ingest-inbound message failed', [
                            'mailbox' => $address, 'uid' => $uid, 'error' => $e->getMessage(),
                        ]);
                        if ($markSeen) {
                            try {
                                $folder->query()->getMessageByUid($uid)?->setFlag('Seen');
                            } catch (\Throwable $ignored) {
                            }
                        }
                    }
                    gc_collect_cycles();
                }

                $client->disconnect();
            } catch (\Throwable $e) {
                $this->error("  {$address}: " . $e->getMessage());
                Log::warning('mail:ingest-inbound error', ['mailbox' => $address, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Done. saved={$totalSaved}");
        return self::SUCCESS;
    }
}
