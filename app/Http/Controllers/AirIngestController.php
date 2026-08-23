<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AIR file source-capture ingestion endpoint (ported from dev1's Dev1AirController,
 * 2026-06-02). The agent-side uploader (City Travelers.exe) FTPs an Amadeus .air
 * file into AIR_INBOX_BASE/UPLOADED, then POSTs the filename here. We move it into
 * the standard parser path, run `app:process-files --single`, detect success/error,
 * and move the file to AIR_INBOX_BASE/LOADED/<DDMMYYYY>/ or AIR_INBOX_BASE/NOT LOADED/
 * so nothing is ever silently lost (recoverable). Auth: bearer AIR_INGEST_SECRET.
 *
 * Paths are env-driven (AIR_INBOX_BASE) so the same controller runs on iamshoja
 * (main) and citycomm. No agent-gate on prod, so failures land in NOT LOADED/.
 */
class AirIngestController extends Controller
{
    private function base(): string
    {
        return rtrim((string) env('AIR_INBOX_BASE', storage_path('app/air_inbox')), '/');
    }
    private function uploadedDir(): string { return $this->base() . '/UPLOADED'; }
    private function loadedDir(): string   { return $this->base() . '/LOADED'; }
    private function notLoadedDir(): string { return $this->base() . '/NOT LOADED'; }

    public function processAirFile(Request $request): JsonResponse
    {
        // 1. Auth
        $expected = env('AIR_INGEST_SECRET');
        if (empty($expected) || $request->bearerToken() !== $expected) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        // 2. Validate filename — no path traversal, basic chars only
        $filename = (string) $request->input('filename', '');
        if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            return response()->json(['error' => 'invalid_filename', 'filename' => $filename], 400);
        }

        $uploadedPath = $this->uploadedDir() . '/' . $filename;
        if (!file_exists($uploadedPath)) {
            return response()->json(['error' => 'file_not_found_in_uploaded', 'path' => $uploadedPath], 404);
        }

        // 3. Resolve company + Amadeus supplier
        $company  = Company::first();
        $supplier = Supplier::where('name', 'Amadeus')->first();
        if (!$company || !$supplier) {
            return response()->json([
                'success'  => false,
                'error'    => 'missing_company_or_supplier',
                'company'  => $company?->name,
                'supplier' => $supplier?->name,
            ], 500);
        }

        $companyDir  = strtolower(preg_replace('/\s+/', '_', $company->name));
        $supplierDir = strtolower(preg_replace('/\s+/', '_', $supplier->name));

        $unprocessedDir = storage_path("app/{$companyDir}/{$supplierDir}/files_unprocessed");
        $processedDir   = storage_path("app/{$companyDir}/{$supplierDir}/files_processed");
        $errorDir       = storage_path("app/{$companyDir}/{$supplierDir}/files_error");
        foreach ([$unprocessedDir, $processedDir, $errorDir] as $d) {
            if (!is_dir($d)) @mkdir($d, 0775, true);
        }

        // 4. Move from UPLOADED → unprocessed
        $stagingPath = $unprocessedDir . '/' . $filename;
        if (!@rename($uploadedPath, $stagingPath)) {
            if (!@copy($uploadedPath, $stagingPath)) {
                return response()->json([
                    'success' => false, 'error' => 'staging_move_failed',
                    'from' => $uploadedPath, 'to' => $stagingPath,
                ], 500);
            }
            @unlink($uploadedPath);
        }

        // 5. Pre-state → parse → post-state
        $maxIdBefore = (int) DB::table('tasks')->max('id');
        $artisanOutput = '';
        $artisanExit = -1;
        try {
            $artisanExit = Artisan::call('app:process-files', ['--single' => true]);
            $artisanOutput = Artisan::output();
        } catch (Throwable $e) {
            Log::error('[AirIngestController] Artisan failed', ['err' => $e->getMessage()]);
        }
        $maxIdAfter = (int) DB::table('tasks')->max('id');

        // 6. Outcome — file should now be in processed/ or error/
        $endedInProcessed = file_exists($processedDir . '/' . $filename);
        $endedInError     = file_exists($errorDir . '/' . $filename);

        $newTasks = [];
        if ($maxIdAfter > $maxIdBefore) {
            $newTasks = DB::table('tasks')
                ->select('id', 'reference', 'gds_reference', 'status', 'created_by', 'issued_by', 'total', 'type')
                ->where('id', '>', $maxIdBefore)->orderBy('id')->get()->toArray();
        }

        // 7. Move file to LOADED/<DDMMYYYY>/ or NOT LOADED/
        $dateFolder = date('dmY');
        $finalPath = null; $finalLocation = null; $rejectReason = null;

        if ($endedInProcessed) {
            $loadedDated = $this->loadedDir() . '/' . $dateFolder;
            if (!is_dir($loadedDated)) @mkdir($loadedDated, 0775, true);
            $finalPath = $loadedDated . '/' . $filename;
            @rename($processedDir . '/' . $filename, $finalPath);
            $finalLocation = 'LOADED/' . $dateFolder;
        } elseif ($endedInError) {
            // Agent-gate marker exists on dev1 only; on prod $isUnregistered is false.
            $isUnregistered = strpos($artisanOutput, 'UNREGISTERED_AGENT_REJECT') !== false;
            $destDir = $isUnregistered ? $this->notLoadedDir() . '/unregistered_agent' : $this->notLoadedDir();
            if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
            $finalPath = $destDir . '/' . $filename;
            @rename($errorDir . '/' . $filename, $finalPath);
            $finalLocation = $isUnregistered ? 'NOT LOADED/unregistered_agent' : 'NOT LOADED';
            $rejectReason = $isUnregistered ? 'unregistered_agent' : 'processing_error';
            @file_put_contents($finalPath . '.error.json', json_encode([
                'reason' => $rejectReason, 'artisan_exit' => $artisanExit,
                'artisan_output' => substr($artisanOutput, -4000), 'timestamp' => date('c'),
            ], JSON_PRETTY_PRINT));
        } else {
            if (!is_dir($this->notLoadedDir())) @mkdir($this->notLoadedDir(), 0775, true);
            if (file_exists($stagingPath)) {
                @rename($stagingPath, $this->notLoadedDir() . '/' . $filename);
                $finalPath = $this->notLoadedDir() . '/' . $filename;
                $finalLocation = 'NOT LOADED (orphaned)';
            }
        }

        $success = $endedInProcessed && !$endedInError;
        $skippedDuplicate = $endedInProcessed && count($newTasks) === 0;

        return response()->json([
            'success'             => $success,
            'skipped_duplicate'   => $skippedDuplicate,
            'reject_reason'       => $rejectReason,
            'filename'            => $filename,
            'final_location'      => $finalLocation,
            'final_path'          => $finalPath,
            'tasks_created'       => count($newTasks),
            'tasks'               => $newTasks,
            'artisan_exit'        => $artisanExit,
            'artisan_output_tail' => substr($artisanOutput, -1500),
            'ended_in_processed'  => $endedInProcessed,
            'ended_in_error'      => $endedInError,
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $expected = env('AIR_INGEST_SECRET');
        if (empty($expected) || $request->bearerToken() !== $expected) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'host_id'     => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'status'      => ['nullable', 'string', 'max:32'],
            'files_today' => ['nullable', 'integer', 'min:0'],
            'files_total' => ['nullable', 'integer', 'min:0'],
            'version'     => ['nullable', 'string', 'max:32'],
            'last_error'  => ['nullable', 'string', 'max:1000'],
            // Why the uploader was offline (crash traceback or log tail), shipped
            // by the client on its first heartbeat after a restart so the recovery
            // alert can surface client-side bugs automatically.
            'offline_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $prior = DB::table('uploader_heartbeats')->where('host_id', $data['host_id'])->first();

        DB::table('uploader_heartbeats')->updateOrInsert(
            ['host_id' => $data['host_id']],
            [
                'status'       => $data['status']      ?? 'running',
                'files_today'  => $data['files_today'] ?? 0,
                'files_total'  => $data['files_total'] ?? 0,
                'version'      => $data['version']     ?? null,
                'last_error'   => $data['last_error']  ?? null,
                'last_seen_at' => now(),
                'updated_at'   => now(),
                'created_at'   => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        // Peer offline-sweep: the dedicated `app:check-uploader-offline` cron is
        // not reliably loaded into crond on this server (direct crontab edits
        // aren't being re-read), so it never raised the initial OFFLINE alert.
        // Run the sweep here too — whenever ANY uploader checks in, stale peers
        // get alerted. Idempotent with the cron (alert_sent_at is claimed once
        // per outage). Wrapped so a sweep failure can never break a heartbeat.
        try {
            \Illuminate\Support\Facades\Artisan::call('app:check-uploader-offline');
        } catch (\Throwable $e) {
            Log::error('[AirIngestController/heartbeat] offline sweep failed', ['err' => $e->getMessage()]);
        }

        $wasAlerted = $prior && $prior->alert_sent_at !== null;
        if ($wasAlerted) {
            $skipDueToCooldown = $prior->recovered_alert_sent_at
                && (int) abs(now()->diffInMinutes($prior->recovered_alert_sent_at)) < 5;
            if (!$skipDueToCooldown) {
                $offlineMinutes = (int) abs(now()->diffInMinutes($prior->last_seen_at));
                $recipients = DB::table('agents as a')
                    ->join('agent_notification_settings as s', 's.agent_id', '=', 'a.id')
                    ->where('s.notification_type', 'uploader_alert')
                    ->where('s.is_active', 1)
                    ->whereIn('s.channel', ['whatsapp', 'both'])
                    ->whereNotNull('a.phone_number')->where('a.phone_number', '!=', '')
                    ->select('a.id', 'a.phone_number', 'a.country_code')->get();

                if ($recipients->isNotEmpty()) {
                    $reason = trim((string) ($data['offline_reason'] ?? ''));
                    $msg = "OK: AIR uploader '{$data['host_id']}' is back ONLINE.\n"
                         . "Was offline for {$offlineMinutes} min.\n"
                         . "Files today: " . ($data['files_today'] ?? 0) . "\n"
                         . ($reason !== ''
                             ? "Reason it went offline:\n" . \Illuminate\Support\Str::limit($reason, 800) . "\n"
                             : "")
                         . "Dashboard: " . config('app.url');
                    foreach ($recipients as $r) {
                        try {
                            (new \App\Http\Controllers\ResayilController())->message(
                                phone: $r->phone_number,
                                country_code: $r->country_code ?? '',
                                message: $msg,
                                isDummyNumber: false,
                            );
                        } catch (\Throwable $e) {
                            Log::error('[AirIngestController/heartbeat] recovery send failed', [
                                'host' => $data['host_id'], 'agent_id' => $r->id, 'err' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                DB::table('uploader_heartbeats')->where('host_id', $data['host_id'])
                    ->update(['alert_sent_at' => null, 'recovered_alert_sent_at' => now()]);
                return response()->json(['ok' => true, 'recovery_alert_sent' => $recipients->count()]);
            }
        }

        // Crash visibility: when the client-side watchdog auto-restarts the uploader
        // too fast to ever look "offline" (< the 5-min threshold), the recovery
        // branch above never fires — so a Python crash (a bug) would heal silently.
        // Surface its trace here so bugs get fixed. offline_reason from a real crash
        // is prefixed "Crashed"; log-tail reasons (normal reboot) are not, so those
        // stay quiet. Cooldown (15 min/host, via cache — no schema change) keeps a
        // crash-loop from spamming.
        $crashReason = trim((string) ($data['offline_reason'] ?? ''));
        if ($crashReason !== '' && \Illuminate\Support\Str::startsWith($crashReason, 'Crashed')) {
            $cooldownKey = 'air_crash_alert_' . $data['host_id'];
            if (!\Illuminate\Support\Facades\Cache::has($cooldownKey)) {
                $recipients = DB::table('agents as a')
                    ->join('agent_notification_settings as s', 's.agent_id', '=', 'a.id')
                    ->where('s.notification_type', 'uploader_alert')
                    ->where('s.is_active', 1)
                    ->whereIn('s.channel', ['whatsapp', 'both'])
                    ->whereNotNull('a.phone_number')->where('a.phone_number', '!=', '')
                    ->select('a.id', 'a.phone_number', 'a.country_code')->get();
                if ($recipients->isNotEmpty()) {
                    $msg = "WARNING: AIR uploader '{$data['host_id']}' crashed and was auto-restarted.\n"
                         . \Illuminate\Support\Str::limit($crashReason, 850) . "\n"
                         . "Dashboard: " . config('app.url');
                    foreach ($recipients as $r) {
                        try {
                            (new \App\Http\Controllers\ResayilController())->message(
                                phone: $r->phone_number,
                                country_code: $r->country_code ?? '',
                                message: $msg,
                                isDummyNumber: false,
                            );
                        } catch (\Throwable $e) {
                            Log::error('[AirIngestController/heartbeat] crash alert send failed', [
                                'host' => $data['host_id'], 'agent_id' => $r->id, 'err' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                \Illuminate\Support\Facades\Cache::put($cooldownKey, true, now()->addMinutes(15));
            }
        }

        DB::table('uploader_heartbeats')->where('host_id', $data['host_id'])
            ->whereNotNull('alert_sent_at')->update(['alert_sent_at' => null]);

        return response()->json(['ok' => true]);
    }

    /**
     * Pre-upload check for the City Travelers.exe uploader. Given an Amadeus .air
     * file's contents, report whether it WOULD load — i.e. at least one passenger
     * is either a modification (reissue/refund/void) or a NEW issue whose acting
     * agent is REGISTERED. Lets the uploader SKIP files belonging only to
     * unregistered agents instead of uploading them just to be held (NOT LOADED).
     *
     * Reuses the SAME ProcessAirFiles::findAgent() the live agent gate uses (via
     * reflection) so the uploader can never diverge from the server. Errs toward
     * should_upload=true on any ambiguity so a real ticket is never dropped at the
     * client. Auth: bearer AIR_INGEST_SECRET.
     */
    public function willLoad(Request $request): JsonResponse
    {
        $expected = env('AIR_INGEST_SECRET');
        if (empty($expected) || $request->bearerToken() !== $expected) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $content = '';
        if ($request->hasFile('file')) {
            $content = (string) @file_get_contents($request->file('file')->getRealPath());
        } elseif ($request->filled('content')) {
            $content = (string) $request->input('content');
        } else {
            $content = (string) $request->getContent();
        }
        if (trim($content) === '') {
            return response()->json(['error' => 'empty_content'], 400);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'air_') . '.air';
        @file_put_contents($tmp, $content);

        try {
            $companyId = (int) (Company::first()?->id ?? 1);

            $cmd = app(\App\Console\Commands\ProcessAirFiles::class);
            $findAgent = new \ReflectionMethod($cmd, 'findAgent');
            $findAgent->setAccessible(true);

            $tasks = (new \App\Services\AirFileParser($tmp))->parseTaskSchema();

            $anyWouldLoad = false;
            $detail = [];
            foreach ($tasks as $t) {
                $status = $t['status'] ?? null;
                $isMod  = in_array($status, ['reissued', 'refund', 'void'], true);
                if ($isMod) {
                    $anyWouldLoad = true;   // server still gates orphan modifications
                    $detail[] = ['status' => $status, 'modification' => true, 'would_load' => true];
                    continue;
                }
                $agent = $findAgent->invoke(
                    $cmd,
                    $t['agent_amadeus_id'] ?? null,
                    $t['agent_name'] ?? null,
                    $t['agent_email'] ?? null,
                    $companyId
                );
                $reg = $agent !== null;
                if ($reg) {
                    $anyWouldLoad = true;
                }
                $detail[] = [
                    'status' => $status,
                    'agent_amadeus_id' => $t['agent_amadeus_id'] ?? null,
                    'agent_registered' => $reg,
                    'would_load' => $reg,
                ];
            }

            return response()->json([
                'should_upload' => $anyWouldLoad,   // false ONLY when every pax is an unregistered new issue
                'tasks' => count($tasks),
                'detail' => $detail,
            ]);
        } catch (Throwable $e) {
            Log::warning('[AirIngestController/willLoad] check failed — defaulting to should_upload=true', ['err' => $e->getMessage()]);
            return response()->json(['should_upload' => true, 'error' => 'check_failed', 'message' => $e->getMessage()]);
        } finally {
            @unlink($tmp);
        }
    }
}
