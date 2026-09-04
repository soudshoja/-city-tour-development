<?php

namespace App\Services;

use App\Http\Controllers\ResayilController;
use App\Models\Agent;
use App\Models\Task;
use App\Models\WhatsappIngest;
use App\Services\Parsers\SupplierPdfDetector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsApp supplier-PDF → task ingestion.
 *
 * Invoked from IncomingMediaController::handleResayilWebhook when an agent
 * forwards a PDF document. A recognised supplier PDF is dropped into the same
 * files_unprocessed/ landing path the email/AIR channels use (app:process-files
 * finishes the job); wa:dispatch-results then attributes + enables + replies.
 *
 * Non-matching PDFs never enter the AI path: a passport (TD3 MRZ) is routed to
 * the photo flow, anything else is "saved for review".
 */
class WhatsappPdfIngestService
{
    /**
     * Handle an inbound WhatsApp document (PDF) forwarded by an agent, or posted
     * in a supplier group ($agent null when the poster is not a registered agent
     * — the task then loads unassigned; $fromPhone records the actual poster).
     * Returns ['status' => dropped|duplicate|review|passport|ignored|error, ...].
     */
    public function handleDocument(array $data, ?Agent $agent, ?string $fromPhone = null): array
    {
        $messageId = $data['message_id'] ?? null;
        $mime      = strtolower($data['mime'] ?? '');
        $url       = $data['url'] ?? null;
        $filename  = $data['filename'] ?? null;

        if ($mime !== 'application/pdf' || !$url) {
            return ['status' => 'ignored'];
        }

        // Dedup: Resayil retries the same event; one row per message_id.
        if ($messageId && WhatsappIngest::where('message_id', $messageId)->exists()) {
            return ['status' => 'duplicate'];
        }

        $bytes = $this->download($url);
        if ($bytes === null) {
            if ($agent) {
                $this->reply($agent, 'Sorry — I could not download that file. Please send it again.');
            }
            return ['status' => 'error'];
        }

        // Detect supplier from PDF content (deterministic parsers only).
        $tmp = tempnam(sys_get_temp_dir(), 'wa_');
        file_put_contents($tmp, $bytes);
        $cls = SupplierPdfDetector::detect($tmp);
        @unlink($tmp);

        $companySlug = config('wa_pdf_ingest.company_slug');

        if ($cls !== null) {
            $slug = config("wa_pdf_ingest.parser_supplier_map.$cls", $this->slugFromClass($cls));
            $dir  = storage_path("app/{$companySlug}/{$slug}/files_unprocessed");
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $safe = $this->safeName($filename ?: (($messageId ?: uniqid('wa')) . '.pdf'));
            if (file_exists($dir . DIRECTORY_SEPARATOR . $safe) || file_exists(dirname($dir) . '/files_processed/' . $safe)) {
                $safe = time() . '_' . $safe;
            }
            file_put_contents($dir . DIRECTORY_SEPARATOR . $safe, $bytes);

            WhatsappIngest::create([
                'company_id'    => $agent?->company_id ?? config('wa_pdf_ingest.company_id'),
                'agent_id'      => $agent?->id,
                'from_phone'    => $agent?->phone_number ?? $fromPhone,
                'country_code'  => $agent?->country_code,
                'message_id'    => $messageId,
                'supplier_slug' => $slug,
                'file_name'     => $safe,
                'pnr'           => strtoupper(pathinfo($safe, PATHINFO_FILENAME)),
                'confidence'    => 'deterministic',
                'status'        => 'received',  // dispatcher promotes to live/awaiting_field
                'received_at'   => now(),
            ]);

            if ($agent) {
                $this->reply($agent, '📥 Received, reading it now…');
            }
            Log::info('WaPdfIngest: dropped', ['agent' => $agent?->id, 'slug' => $slug, 'file' => $safe]);
            return ['status' => 'dropped', 'file_name' => $safe, 'supplier_slug' => $slug];
        }

        // No supplier matched. Is it a passport? (supplier-first already ruled out
        // VFS/UK-ETA, which carry passport data but match a parser.)
        $mrz = $this->detectMrz($bytes);
        if ($mrz !== null) {
            WhatsappIngest::create([
                'company_id'   => $agent?->company_id ?? config('wa_pdf_ingest.company_id'),
                'agent_id'     => $agent?->id,
                'from_phone'   => $agent?->phone_number ?? $fromPhone,
                'country_code' => $agent?->country_code,
                'message_id'   => $messageId,
                'confidence'   => 'none',
                'status'       => 'passport',
                'note'         => 'passport PDF (MRZ found)',
                'received_at'  => now(),
            ]);
            if ($agent) {
                $this->reply($agent, '📸 That looks like a passport. Please send it as a *photo* (not a PDF) so I can create the client.');
            }
            return ['status' => 'passport'];
        }

        // Genuinely unrecognised → saved for review. KEEP THE BYTES: before
        // 2026-08 only the DB row was written and the PDF itself was lost,
        // which made review rows undiagnosable (Magic group vouchers).
        $reviewDir = storage_path("app/{$companySlug}/wa_review");
        if (!is_dir($reviewDir)) { @mkdir($reviewDir, 0775, true); }
        $reviewName = $this->safeName(($messageId ?: uniqid('wa')) . '_' . ($filename ?: 'document.pdf'));
        file_put_contents($reviewDir . DIRECTORY_SEPARATOR . $reviewName, $bytes);

        WhatsappIngest::create([
            'company_id'   => $agent?->company_id ?? config('wa_pdf_ingest.company_id'),
            'agent_id'     => $agent?->id,
            'from_phone'   => $agent?->phone_number ?? $fromPhone,
            'country_code' => $agent?->country_code,
            'message_id'   => $messageId,
            'file_name'    => $reviewName,
            'confidence'   => 'none',
            'status'       => 'review',
            'note'         => 'no supplier parser matched (PDF kept in wa_review)',
            'received_at'  => now(),
        ]);
        if ($agent) {
            $this->reply($agent, "Couldn't read this one — I've saved it for the team to check.");
        }
        return ['status' => 'review'];
    }

    /**
     * Agent replied with a missing field value (Level 2). Returns true if a
     * pending field was filled (or the reply was otherwise consumed).
     */
    public function handleFieldReply(string $phone, string $text, Agent $agent): bool
    {
        $key = 'wa_pending_field_' . $phone;
        $pending = Cache::get($key);
        if (!$pending || ($pending['field'] ?? null) !== 'price') {
            return false;
        }

        $amount = (float) preg_replace('/[^0-9.]/', '', $text);
        if ($amount <= 0) {
            $this->reply($agent, "That didn't look like a price. Reply with just the amount in KWD, e.g. 145.500");
            return true; // handled (kept waiting)
        }

        $task = Task::find($pending['task_id']);
        if (!$task) { Cache::forget($key); return true; }

        $task->total = $amount;
        $task->enabled = true;
        $task->save();
        // ⚠️ VERIFY ON DEV1: ensure the linked AccountTransaction / journal entry
        // reflects the new total (CityTour may set transaction amounts at task
        // creation). If so, update the transaction here or recompute via the same
        // path TaskController uses.

        Cache::forget($key);
        WhatsappIngest::where('task_id', $task->id)
            ->update(['status' => 'live', 'note' => 'price supplied by agent']);
        $this->reply($agent, "✅ Done — task #{$task->id} updated and assigned to you.");
        return true;
    }

    private function download(string $url): ?string
    {
        if (!str_starts_with($url, 'http')) {
            $url = rtrim(config('services.resayil.base_url'), '/') . '/' . ltrim($url, '/');
        }
        try {
            $resp = Http::withHeaders(['Token' => config('services.resayil.api_token')])
                ->timeout(120)->get($url);
            return $resp->successful() ? $resp->body() : null;
        } catch (\Throwable $e) {
            Log::warning('WaPdfIngest: download failed', ['url' => $url, 'err' => $e->getMessage()]);
            return null;
        }
    }

    public function reply(Agent $agent, string $message): void
    {
        try {
            (new ResayilController())->message(
                $agent->phone_number, $agent->country_code, $message, isDummyNumber: false
            );
        } catch (\Throwable $e) {
            Log::warning('WaPdfIngest: reply failed', ['agent' => $agent->id, 'err' => $e->getMessage()]);
        }
    }

    /** Locate a TD3 MRZ in PDF text and validate via MrzParser. Returns parsed array or null. */
    private function detectMrz(string $bytes): ?array
    {
        try {
            $tmp = tempnam(sys_get_temp_dir(), 'wamrz_');
            file_put_contents($tmp, $bytes);
            $text = (new \Smalot\PdfParser\Parser())->parseFile($tmp)->getText();
            @unlink($tmp);
        } catch (\Throwable $e) {
            return null;
        }
        // TD3 = two 44-char lines; line1 starts with 'P'. Tolerate spacing.
        $lines = preg_split('/\r\n|\r|\n/', strtoupper($text));
        $cand = array_values(array_filter(
            array_map(fn($l) => preg_replace('/\s+/', '', $l), $lines),
            fn($l) => strlen($l) >= 30 && str_contains($l, '<')
        ));
        for ($i = 0; $i < count($cand) - 1; $i++) {
            if (str_starts_with($cand[$i], 'P')) {
                $parsed = \App\Services\MrzParser::parseTd3($cand[$i], $cand[$i + 1]);
                if ($parsed !== null) { return $parsed; }
            }
        }
        return null;
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        return $name ?: (uniqid('wa') . '.pdf');
    }

    private function slugFromClass(string $cls): string
    {
        return Str::of(class_basename($cls))
            ->replaceMatches('/Parser$/', '')->snake()->toString();
    }
}
