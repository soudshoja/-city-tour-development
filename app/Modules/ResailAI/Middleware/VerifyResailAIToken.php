<?php

namespace App\Modules\ResailAI\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ResailaiCredential;
use Symfony\Component\HttpFoundation\Response;

class VerifyResailAIToken
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for Bearer token in Authorization header
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            Log::warning('[ResailAI] Missing or invalid Authorization header', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $this->rejectRequest($request, 'Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix

        // Find credential with matching API key
        $credential = $this->findCredentialByToken($token);

        if (!$credential || !$credential->is_active) {
            Log::warning('[ResailAI] Invalid or inactive API key', [
                'token_hash' => hash('sha256', $token),
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $this->rejectRequest($request, 'Invalid API key');
        }

        // Update last used timestamp
        $credential->update(['last_used_at' => now()]);

        // Attach credential to request for later use
        $request->attributes->set('resailai_credential', $credential);

        Log::info('[ResailAI] Token verified successfully', [
            'credential_id' => $credential->id,
            'credential_name' => $credential->name,
            'path' => $request->path(),
        ]);

        return $next($request);
    }

    /**
     * Find credential by decrypted API key.
     *
     * @param string $token
     * @return ResailaiCredential|null
     */
    private function findCredentialByToken(string $token): ?ResailaiCredential
    {
        // Get all active credentials
        $credentials = ResailaiCredential::where('is_active', true)->get();

        foreach ($credentials as $credential) {
            try {
                $decryptedKey = \Illuminate\Support\Facades\Crypt::decryptString($credential->api_key);

                if ($decryptedKey === $token) {
                    return $credential;
                }
            } catch (\Exception $e) {
                Log::warning('[ResailAI] Failed to decrypt API key', [
                    'credential_id' => $credential->id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return null;
    }

    /**
     * Reject request with 401 Unauthorized.
     *
     * @param Request $request
     * @param string $reason
     * @return Response
     */
    private function rejectRequest(Request $request, string $reason): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => $reason,
        ], 401);
    }
}
