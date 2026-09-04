<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\EmailIngest;
use App\Services\Parsers\SupplierPdfDetector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Core of the cPanel per-agent email ingestion (see
 * docs/design_cpanel_per_agent_email_ingestion.md).
 *
 * Transport-agnostic: both the IMAP poll command (Mode A) and the future
 * cPanel mail-pipe script (Mode B) normalise their input and call handle().
 * It pulls PDF attachments, attributes the agent from the delivery mailbox,
 * dedups on Message-ID, routes to the supplier folder, and drops the PDF into
 * the existing parser pipeline (app:process-files finishes the job).
 */
class InboundMailIngestService
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('mail_ingest');
    }

    /** Ingest from a Webklex IMAP message (Mode A). $deliveredTo = the polled mailbox. */
    public function ingestWebklexMessage($message, string $deliveredTo): array
    {
        $messageId = (string) ($message->getMessageId() ?: '');
        $fromObj   = $message->getFrom()[0] ?? null;
        $from      = strtolower((string) ($fromObj->mail ?? ''));
        $subject   = (string) $message->getSubject();

        $attachments = [];
        foreach ($message->getAttachments() as $a) {
            $attachments[] = [
                'name'  => (string) $a->getName(),
                'bytes' => $a->getContent(),
            ];
        }

        // Body-only suppliers (IndiGo, Accelya NDC …) ship the whole itinerary
        // in the HTML body with no PDF attachment — capture it so handle() can
        // fall back to the body when no usable PDF is present.
        $htmlBody = (string) ($message->getHTMLBody() ?: '');

        return $this->handle($messageId, $deliveredTo, $from, $subject, $attachments, $htmlBody);
    }

    /**
     * @param array  $attachments list of ['name' => string, 'bytes' => string]
     * @param string $htmlBody    raw HTML email body (used only when no PDF saved)
     */
    public function handle(string $messageId, string $deliveredTo, string $from, string $subject, array $attachments, string $htmlBody = ''): array
    {
        $deliveredTo = strtolower(trim($deliveredTo));

        // Turkish "Shared Content Information" email = the FARE for a PNR (the
        // e-ticket mail has none). Capture it to price the matching tasks; it is
        // not a booking, so no task-file is created. See TurkishFareResolver.
        if (trim($htmlBody) !== '' && \App\Services\Parsers\TurkishNdcParser::isSharedContent($htmlBody)) {
            try {
                $r = app(\App\Services\TurkishFareResolver::class)->ingestSharedContent($htmlBody, $messageId !== '' ? $messageId : null);
                Log::info('InboundMailIngest: captured Turkish shared-content fare', (array) $r);
            } catch (\Throwable $e) {
                Log::warning('InboundMailIngest: turkish fare capture failed: ' . $e->getMessage());
            }
            return ['status' => 'turkish_fare', 'saved' => 0];
        }

        $fromLc = strtolower($from);
        foreach (($this->cfg['ignore_senders'] ?? []) as $ignore) {
            if ($ignore !== '' && str_contains($fromLc, strtolower($ignore))) {
                return ['status' => 'ignored', 'reason' => "sender ignored ({$ignore})", 'saved' => 0];
            }
        }

        if ($messageId !== '') {
            $existing = EmailIngest::where('message_id', $messageId)->first();
            if ($existing) {
                // Seen in a SECOND mailbox => this booking is broadcast to multiple
                // agents, so the mailbox is NOT a reliable signal for the booking
                // agent. Clear any agent attributed from the first mailbox (on the
                // ingest row and any task already created) — leave it unassigned.
                // Exception: when the SENDER is an internal agent the attribution
                // came from the sender, not the mailbox, so broadcasting doesn't
                // invalidate it.
                $senderIsAgent = $fromLc !== '' && Agent::whereRaw('LOWER(email) = ?', [$fromLc])->exists();
                if ($existing->agent_id !== null && !$senderIsAgent) {
                    $existing->update([
                        'agent_id' => null,
                        'note'     => trim((string) $existing->note . ' | broadcast (also @' . $deliveredTo . ') — agent cleared'),
                    ]);
                    try {
                        \App\Models\Task::where('file_name', $existing->file_name)
                            ->whereNotNull('agent_id')
                            ->update(['agent_id' => null]);
                    } catch (\Throwable $e) {
                        Log::warning('InboundMailIngest: broadcast agent-clear on task failed: ' . $e->getMessage());
                    }
                }
                return ['status' => 'skipped', 'reason' => 'duplicate message_id (broadcast — agent cleared)', 'saved' => 0];
            }
        }

        // Internal forward: when the sender is one of our own agents (e.g. Peter
        // forwards a supplier PDF to a colleague's ingest mailbox), the SENDER
        // owns the booking — the delivery mailbox is just transport.
        $senderAgent = $fromLc !== ''
            ? Agent::whereRaw('LOWER(email) = ?', [$fromLc])->first()
            : null;
        $agent     = $senderAgent
            ?: ($deliveredTo !== ''
                ? Agent::whereRaw('LOWER(ingest_email) = ?', [$deliveredTo])->first()
                : null);
        $agentId   = $agent->id ?? null;
        $companyId = $agent->company_id ?? $this->cfg['company_id'];

        $saved = 0;
        $files = [];

        // Air Arabia: the attachment PDFs carry NO price, but the HTML body has
        // the full booking (all passengers + "Total Payment KWD"). Prefer the
        // body whenever it resolves to a parser — dropping the attachments lets
        // the body-only fallback below handle it. OTP/marketing mails carry no
        // PDFs, so this only affects itinerary mails.
        if (trim($htmlBody) !== ''
            && str_contains($fromLc, 'airarabia.com')
            && $this->resolveSupplierForBody($from, $htmlBody) !== null
            && \App\Services\Parsers\SupplierPdfDetector::detectHtml($htmlBody) !== null) {
            $attachments = [];
        }

        foreach ($this->preferTaAttachments($attachments) as $att) {
            $name = (string) ($att['name'] ?? '');
            if ($name === '' || !Str::endsWith(strtolower($name), '.pdf')) {
                continue;
            }
            if ($this->isSkipped($name)) {
                continue;
            }

            $bytes = $att['bytes'] ?? null;
            if ($bytes === null || $bytes === '') {
                continue;
            }

            $supplierSlug = $this->resolveSupplier($from, $bytes);
            $companySlug  = $this->cfg['company_slug'];
            $dir          = storage_path("app/{$companySlug}/{$supplierSlug}/files_unprocessed");
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $safe   = $this->safeName($name);
            $target = $dir . DIRECTORY_SEPARATOR . $safe;
            // Generic supplier filenames (Smile's "factura.aspx.pdf") collide and
            // overwrite each other in unprocessed/processed — uniquify on clash.
            if (file_exists($target) || file_exists(dirname($dir) . '/files_processed/' . $safe)) {
                $safe   = time() . '_' . $safe;
                $target = $dir . DIRECTORY_SEPARATOR . $safe;
            }
            file_put_contents($target, $bytes);
            $saved++;
            $files[] = $safe;

            EmailIngest::create([
                'company_id'    => $companyId,
                'agent_id'      => $agentId,
                'mailbox'       => $deliveredTo,
                'message_id'    => $messageId !== '' ? $messageId : null,
                'from_address'  => $from,
                'supplier_slug' => $supplierSlug,
                'file_name'     => $safe,
                'pnr'           => strtoupper(pathinfo($safe, PATHINFO_FILENAME)),
                'status'        => $supplierSlug === $this->cfg['unrouted_slug'] ? 'unrouted' : 'dropped',
                'note'          => $agentId ? null : "no agent matched mailbox {$deliveredTo}",
                'received_at'   => now(),
            ]);

            Log::info('InboundMailIngest: dropped PDF', [
                'mailbox' => $deliveredTo, 'agent_id' => $agentId,
                'supplier' => $supplierSlug, 'file' => $safe,
            ]);
        }

        // Body-only fallback: no usable PDF was saved, but the HTML body may
        // itself be the itinerary (IndiGo / Accelya NDC). Drop it as <key>.html
        // ONLY when the supplier is recognised (known sender or a registered
        // HTML parser matches) — never persist arbitrary marketing/OTP bodies.
        // PDF always wins (guarded by $saved === 0) so PDF+body emails like
        // Jazeera don't double-create.
        if ($saved === 0 && trim($htmlBody) !== '') {
            $supplierSlug = $this->resolveSupplierForBody($from, $htmlBody);
            if ($supplierSlug !== null) {
                $companySlug = $this->cfg['company_slug'];
                $dir         = storage_path("app/{$companySlug}/{$supplierSlug}/files_unprocessed");
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }

                $safe   = $this->safeName($this->bodyKey($subject, $messageId) . '.html');
                $target = $dir . DIRECTORY_SEPARATOR . $safe;
                file_put_contents($target, $htmlBody);
                $saved++;
                $files[] = $safe;

                EmailIngest::create([
                    'company_id'    => $companyId,
                    'agent_id'      => $agentId,
                    'mailbox'       => $deliveredTo,
                    'message_id'    => $messageId !== '' ? $messageId : null,
                    'from_address'  => $from,
                    'supplier_slug' => $supplierSlug,
                    'file_name'     => $safe,
                    'pnr'           => strtoupper(pathinfo($safe, PATHINFO_FILENAME)),
                    'status'        => 'dropped',
                    'note'          => ($agentId ? null : "no agent matched mailbox {$deliveredTo}; ") . 'body-only (no PDF)',
                    'received_at'   => now(),
                ]);

                Log::info('InboundMailIngest: dropped HTML body', [
                    'mailbox' => $deliveredTo, 'agent_id' => $agentId,
                    'supplier' => $supplierSlug, 'file' => $safe,
                ]);
            } else {
                Log::info('InboundMailIngest: body-only email, no known supplier — skipped', [
                    'mailbox' => $deliveredTo, 'from' => $from, 'subject' => $subject,
                ]);
            }
        }

        return ['status' => 'ok', 'saved' => $saved, 'agent_id' => $agentId, 'files' => $files];
    }

    /**
     * Supplier resolution for a body-only email. Sender map first (fast), then
     * SupplierPdfDetector::detectHtml() on the body. Returns null when the body
     * is NOT a recognised supplier — caller then skips it (never drops junk).
     */
    private function resolveSupplierForBody(string $from, string $html): ?string
    {
        $map    = $this->cfg['sender_supplier_map'] ?? [];
        $fromLc = strtolower($from);
        if ($fromLc !== '' && isset($map[$fromLc])) {
            return $map[$fromLc];
        }
        if ($fromLc !== '') {
            $domain = Str::after($fromLc, '@');
            foreach ($map as $addr => $slug) {
                if ($domain !== '' && str_contains($addr, $domain)) {
                    return $slug;
                }
            }
        }

        try {
            $cls = SupplierPdfDetector::detectHtml($html);
            if ($cls && isset($this->cfg['parser_supplier_map'][$cls])) {
                return $this->cfg['parser_supplier_map'][$cls];
            }
        } catch (\Throwable $e) {
            Log::warning('InboundMailIngest: detectHtml failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Best-effort filename key for a body-only email. Prefer a PNR-shaped token
     * from the subject (mixed letters+digits, 5-8 chars — skips words like
     * "ITINERARY"/"LOCATOR"); fall back to a hash of the Message-ID. The parser
     * extracts the authoritative PNR from the body regardless.
     */
    private function bodyKey(string $subject, string $messageId): string
    {
        if (preg_match_all('/\b([A-Z0-9]{5,8})\b/', strtoupper($subject), $m)) {
            foreach (array_reverse($m[1]) as $cand) {
                if (preg_match('/[A-Z]/', $cand) && preg_match('/[0-9]/', $cand)) {
                    return $cand;
                }
            }
        }
        return 'BODY_' . substr(md5($messageId !== '' ? $messageId : $subject), 0, 10);
    }

    /** Sender map first (fast), then SupplierPdfDetector on the bytes, else unrouted. */
    private function resolveSupplier(string $from, string $bytes): string
    {
        $map = $this->cfg['sender_supplier_map'] ?? [];
        if ($from !== '' && isset($map[$from])) {
            return $map[$from];
        }
        if ($from !== '') {
            $domain = Str::after($from, '@');
            foreach ($map as $addr => $slug) {
                if ($domain !== '' && str_contains($addr, $domain)) {
                    return $slug;
                }
            }
        }

        try {
            $tmp = tempnam(sys_get_temp_dir(), 'ingest_');
            if ($tmp !== false) {
                file_put_contents($tmp, $bytes);
                $cls = SupplierPdfDetector::detect($tmp);
                @unlink($tmp);
                if ($cls && isset($this->cfg['parser_supplier_map'][$cls])) {
                    return $this->cfg['parser_supplier_map'][$cls];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('InboundMailIngest: detector fallback failed: ' . $e->getMessage());
        }

        return $this->cfg['unrouted_slug'];
    }

    /**
     * One PDF per PNR per email: prefer the "_TA" (agent copy) variant, and fall
     * back to the plain PDF only when no _TA exists. Avoids the duplicate
     * issued+reissued pair Jazeera causes by attaching both <PNR>.pdf and
     * <PNR>_TA.pdf to the same email.
     */
    private function preferTaAttachments(array $attachments): array
    {
        $groups = [];
        foreach ($attachments as $att) {
            $name = (string) ($att['name'] ?? '');
            if ($name === '' || !Str::endsWith(strtolower($name), '.pdf') || $this->isSkipped($name)) {
                continue;
            }
            $base = preg_replace('/\.pdf$/i', '', basename($name));
            $isTa = (bool) preg_match('/_TA$/i', $base);
            $key  = strtoupper(preg_replace('/_TA$/i', '', $base));
            if (!isset($groups[$key]) || ($isTa && empty($groups[$key]['is_ta']))) {
                $groups[$key] = ['att' => $att, 'is_ta' => $isTa];
            }
        }

        return array_map(fn ($g) => $g['att'], array_values($groups));
    }

    private function isSkipped(string $name): bool
    {
        $lower = strtolower($name);
        foreach (($this->cfg['skip_attachments'] ?? []) as $bad) {
            if (str_contains($lower, $bad)) {
                return true;
            }
        }
        return false;
    }

    private function safeName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
        return $clean !== '' ? $clean : ('mail_' . md5($name) . '.pdf');
    }
}
