<?php

namespace App\Http\Middleware;

use App\Models\ResayilAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security fix (sec/resayil-webhook): Resayil's register-webhook body
 * (`{name, device, url, events}`) has no signature/secret of its own — see
 * the resayil-whatsapp-api skill's webhooks reference. Security is
 * entirely ours: each company's webhook URL carries a random secret
 * (`/webhook/resayil/media/{secret}`, `/webhook/resayil/{secret}`), and
 * this middleware is the ONLY place that resolves a webhook request to a
 * company. It never trusts anything in the request body for that — the
 * old code resolved the sender (and therefore company) via
 * Agent::where('phone_number', $phone) taken straight from the payload,
 * which let anyone claiming to be an agent's phone number write into
 * another company's data.
 *
 * On success, the matched ResayilAccount (the admin row that owns the
 * company's Resayil workspace identity) is attached to the request as
 * `resayil_account`; controllers must derive company_id from it and never
 * from the request.
 *
 * Unknown/missing secret -> 404 (not 401/403) so a still-registered old,
 * secret-less webhook registration fails closed and indistinguishably
 * from a wrong URL, per the phase brief.
 */
class VerifyResayilWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) $request->route('secret');

        if ($secret === '') {
            return $this->notFound($request, 'missing secret');
        }

        $incomingHash = hash('sha256', $secret);

        // Small, bounded set (one row per company's admin identity) — loop
        // with hash_equals() for a constant-time compare per candidate
        // rather than a raw WHERE webhook_secret = ? equality on the
        // digest.
        $account = ResayilAccount::query()
            ->where('role', ResayilAccount::ROLE_ADMIN)
            ->whereNotNull('webhook_secret')
            ->get()
            ->first(function (ResayilAccount $candidate) use ($incomingHash) {
                return hash_equals((string) $candidate->webhook_secret, $incomingHash);
            });

        if (! $account) {
            Log::warning('[ResayilWebhook] Unknown or invalid webhook secret', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $this->notFound($request, 'unknown secret');
        }

        $request->attributes->set('resayil_account', $account);

        return $next($request);
    }

    private function notFound(Request $request, string $reason): Response
    {
        Log::info("[ResayilWebhook] Rejected ({$reason})", [
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Not Found'], 404);
    }
}
