<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResailaiCredential;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResailAIAdminController extends Controller
{
    /**
     * List all active API keys.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $credentials = ResailaiCredential::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($cred) {
                return [
                    'id' => $cred->id,
                    'name' => $cred->name,
                    'api_key' => $this->maskApiKey($cred->api_key),
                    'is_active' => $cred->is_active,
                    'last_used_at' => $cred->last_used_at ? $cred->last_used_at->toIso8601String() : null,
                    'expires_at' => $cred->expires_at ? $cred->expires_at->toIso8601String() : null,
                    'created_at' => $cred->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $credentials,
            'count' => $credentials->count(),
        ]);
    }

    /**
     * Generate a new API key.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Generate unique API key
        $apiKey = 'resailai_' . Str::random(32);
        $apiSecret = Str::random(32);

        try {
            $credential = ResailaiCredential::create([
                'user_id' => $request->user()?->id,
                'name' => $validated['name'],
                'api_key' => Crypt::encryptString($apiKey),
                'api_secret' => Crypt::encryptString($apiSecret),
                'expires_at' => $validated['expires_in_days'] ? now()->addDays($validated['expires_in_days']) : null,
            ]);

            Log::info('ResailAI API key generated', [
                'credential_id' => $credential->id,
                'name' => $credential->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'API key generated successfully',
                'data' => [
                    'id' => $credential->id,
                    'name' => $credential->name,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                    'expires_at' => $credential->expires_at ? $credential->expires_at->toIso8601String() : null,
                    'created_at' => $credential->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('ResailAI API key generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate API key',
            ], 500);
        }
    }

    /**
     * Revoke an API key.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function revoke(int $id): JsonResponse
    {
        $credential = ResailaiCredential::find($id);

        if (!$credential) {
            return response()->json([
                'success' => false,
                'error' => 'API key not found',
            ], 404);
        }

        $credential->update([
            'is_active' => false,
        ]);

        Log::info('ResailAI API key revoked', [
            'credential_id' => $credential->id,
            'name' => $credential->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key revoked successfully',
            'data' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'is_active' => false,
            ],
        ]);
    }

    /**
     * Mask API key for display (show only first 8 chars).
     *
     * @param string $encryptedKey
     * @return string
     */
    private function maskApiKey(string $encryptedKey): string
    {
        try {
            $decrypted = Crypt::decryptString($encryptedKey);
            $len = strlen($decrypted);

            return substr($decrypted, 0, 8) . str_repeat('*', $len - 8);
        } catch (\Exception $e) {
            Log::warning('Failed to decrypt API key for masking', [
                'error' => $e->getMessage(),
            ]);
            return '************';
        }
    }
}
