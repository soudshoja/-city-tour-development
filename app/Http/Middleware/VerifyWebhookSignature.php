<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\WebhookClient;
use App\Models\WebhookAuditLog;
use App\Services\WebhookSigningService;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function __construct(private WebhookSigningService $signingService) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Skip if no signature header (not a signed webhook)
        if (!$request->hasHeader(WebhookSigningService::SIGNATURE_HEADER)) {
            return $next($request);
        }

        $signatureProvided = $request->header(WebhookSigningService::SIGNATURE_HEADER);
        $timestampProvided = (int) $request->header(WebhookSigningService::TIMESTAMP_HEADER, 0);

        // Get webhook client ID from route parameter or query
        $clientId = $request->route('webhook_client_id') ?? $request->query('client_id');
        if (!$clientId) {
            return $this->rejectRequest($request, 'No webhook client identified');
        }

        // Load webhook client and valid secrets
        $client = WebhookClient::find($clientId);
        if (!$client || !$client->is_active) {
            return $this->rejectRequest($request, 'Invalid or inactive webhook client');
        }

        // Get payload as string
        $payload = $request->getContent();

        // Try verification against all valid secrets
        $verified = false;
        $computedSignature = '';
        foreach ($client->getValidSecrets() as $secret) {
            // Get plaintext secret from environment
            $plaintextSecret = $this->getPlaintextSecret($secret);

            $result = $this->signingService->verifySignature(
                $payload,
                $signatureProvided,
                $timestampProvided,
                $plaintextSecret,
                $request->method(),
                $request->path()
            );

            if ($result['valid']) {
                $verified = true;
                $computedSignature = $result['computed_signature'];
                break;
            }
            $computedSignature = $result['computed_signature'];
        }

        // Log audit entry
        $this->logAudit($client, $request, $signatureProvided, $computedSignature, $timestampProvided, $verified);

        // Reject if not verified
        if (!$verified) {
            return $this->rejectRequest($request, 'Webhook signature verification failed');
        }

        // Attach verified client to request for later use
        $request->attributes->set('webhook_client', $client);

        return $next($request);
    }

    private function rejectRequest(Request $request, string $reason): Response
    {
        Log::warning("[Webhook] Rejected: {$reason}", [
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Webhook signature verification failed',
        ], 401);
    }

    private function logAudit(
        WebhookClient $client,
        Request $request,
        string $signatureProvided,
        string $computedSignature,
        int $timestampProvided,
        bool $verified
    ): void {
        WebhookAuditLog::create([
            'webhook_client_id' => $client->id,
            'direction' => 'inbound',
            'http_method' => $request->method(),
            'endpoint' => $request->path(),
            'signature_provided' => substr($signatureProvided, 0, 64),
            'signature_computed' => substr($computedSignature, 0, 64),
            'signature_valid' => $verified,
            'timestamp_provided' => $timestampProvided,
            'timestamp_computed' => time(),
            'timestamp_valid' => abs(time() - $timestampProvided) <= WebhookSigningService::TIMESTAMP_TOLERANCE_SECONDS,
            'payload_hash' => hash('sha256', $request->getContent()),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get plaintext secret from environment
     * In production, retrieve from AWS Secrets Manager or encrypted storage
     */
    private function getPlaintextSecret($secret): string
    {
        return env('WEBHOOK_SECRET_' . $secret->id, '');
    }
}
