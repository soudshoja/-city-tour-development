<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\Resayil\ResayilAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings -> WhatsApp — the Resayil Admin Center.
 *
 * Plan: .planning/specs/RESAYIL-ADMIN-CENTER.md. This is slice 1: Panel 1
 * (Overview), Panel 4 (Billing — subscription + payment history) and the
 * operator pause/resume lever (§5.5). Panels 2/3/5 land in later slices.
 *
 * The controller stays thin on purpose. Every byte of Resayil traffic goes
 * through ResayilAdminService, which is the only class that holds a
 * ResayilClient here — the browser never talks to Resayil directly and
 * never receives a token.
 *
 * ACCESS is enforced by the route group in routes/web.php:
 *   auth -> module:resayil (404s an un-entitled company) -> can:manage-resayil
 *   (403s any role outside {ADMIN, COMPANY}).
 * The two destructive actions re-check for ADMIN individually, because the
 * gate deliberately admits COMPANY for everything else.
 *
 * TENANT ISOLATION (§9.3): every action derives the company from the
 * AUTHENTICATED USER via getCompanyId(). No action accepts a company id, a
 * customer id or a device id from the request — there is no route parameter
 * naming a Resayil resource anywhere in this group, and a forged device id
 * in a request body is simply ignored because nothing ever reads one.
 */
class ResayilAdminController extends Controller
{
    /**
     * The panel shell. Renders whatever state the company is actually in —
     * including the not-yet-provisioned and platform-not-configured states,
     * which is what most companies see first. It never 404s or 500s on a
     * missing workspace, and never shows a raw error (§8).
     */
    public function index(Request $request, ResayilAdminService $service): View
    {
        $user = $request->user();
        $companyId = getCompanyId($user);

        // A user whose role resolves to no company (see the getCompanyId
        // switch) still gets a styled, explained page rather than a 500 on
        // a null id downstream.
        $overview = $companyId === null
            ? null
            : $service->overview($companyId);

        return view('resayil.admin.index', [
            'overview' => $overview,
            'companyId' => $companyId,
            'isOperator' => (int) $user->role_id === Role::ADMIN,
            'activePanel' => in_array($request->query('panel'), ['overview', 'billing', 'team', 'inbox'], true)
                ? $request->query('panel')
                : 'overview',
            // Inbox tab (redesign §1 — the WhatsApp inbox now lives inside
            // this section instead of the separate /resayil full page).
            // Same server-configured URL as ResayilEmbedController and the
            // drawer — never user input (SSRF guard, see
            // App\Http\Middleware\ResayilFrameHeaders).
            'embedUrl' => config('resayil.embed_url'),
            'notConfigured' => empty(config('resayil.embed_url')),
        ]);
    }

    /**
     * JSON feed behind the panel's refresh/poll. Same data as index(),
     * same cache — a poll costs nothing extra inside the 60 s window.
     */
    public function overviewData(Request $request, ResayilAdminService $service): JsonResponse
    {
        $user = $request->user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json(['success' => false, 'message' => 'No company selected.'], 400);
        }

        // `refresh` is an explicit user gesture (the Refresh button), not
        // something the poller sends — otherwise every 60 s poll would
        // bypass the cache it exists to populate.
        $fresh = $request->boolean('refresh');

        return response()->json([
            'success' => true,
            'overview' => $this->forAudience($service->overview($companyId, $fresh), $request),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Strip the operator-only fields from an overview payload before it is
     * serialised to a client.
     *
     * The Blade view already hides these behind @if($isOperator); this JSON
     * feed did not, and the page polls it every 60 s for EVERY user of the
     * section. operator_note is a raw diagnostic — upstream error bodies,
     * internal endpoint paths, infrastructure hostnames — written for the
     * platform operator, not for the agency owner whose browser was
     * receiving it. No credential and no cross-tenant data ever reached it,
     * but none of it is a client's business either, and wave 2 puts key
     * capture diagnostics into the same field.
     *
     * Resayil ids go the same way: a COMPANY user has nothing to do with a
     * customer id or device id, and echoing them invites someone to try
     * them somewhere.
     *
     * @param  array<string,mixed>  $overview
     * @return array<string,mixed>
     */
    protected function forAudience(array $overview, Request $request): array
    {
        if ((int) $request->user()->role_id === Role::ADMIN) {
            return $overview;
        }

        $overview['operator_note'] = null;

        if (isset($overview['workspace']) && is_array($overview['workspace'])) {
            unset($overview['workspace']['customer_id']);
        }

        if (isset($overview['device']) && is_array($overview['device'])) {
            unset($overview['device']['id']);
        }

        return $overview;
    }

    /**
     * Panel 4 payment history (§5.2). Reseller read — no company key
     * needed. `cursor` is a payment id, not a page number: this API
     * ignores `?page=` (V-3c), so the UI is a "Load more" list.
     */
    public function payments(Request $request, ResayilAdminService $service): JsonResponse
    {
        $user = $request->user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json(['success' => false, 'message' => 'No company selected.'], 400);
        }

        $cursor = $request->query('cursor');
        $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;

        $result = $service->payments($companyId, $cursor);

        return response()->json([
            'success' => true,
            'rows' => $result['rows'],
            'next' => $result['next'],
            'degraded' => $result['degraded'],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Pause this company's WhatsApp subscription — the owner's collections
     * lever (§5.5, owner decision D-2).
     *
     * OPERATOR ONLY (role 1). This is never exposed to a COMPANY user:
     * pausing takes a live number dark, and a client pausing themselves
     * would silently break receipts and reminders in other modules (U-6).
     */
    public function pauseDevice(Request $request, ResayilAdminService $service): JsonResponse
    {
        return $this->subscriptionAction($request, $service, 'pause');
    }

    /**
     * Resume a paused subscription (§5.5). Operator only, same reasoning.
     */
    public function resumeDevice(Request $request, ResayilAdminService $service): JsonResponse
    {
        return $this->subscriptionAction($request, $service, 'resume');
    }

    /**
     * Shared guard + dispatch for the two operator actions.
     */
    protected function subscriptionAction(Request $request, ResayilAdminService $service, string $action): JsonResponse
    {
        $user = $request->user();

        // Second gate, deliberately narrower than the route's
        // can:manage-resayil (which admits COMPANY too).
        abort_unless((int) $user->role_id === Role::ADMIN, 403);

        // ENVIRONMENT SAFETY. There is no Resayil sandbox: dev and production
        // share one reseller account, so disabling a device from a dev demo
        // takes a REAL customer's WhatsApp number off the air. City Travelers'
        // number is online with 8 agents and live traffic — a stray click here
        // is an outage for a trading company, not a test. Off by default; see
        // config/resayil.php subscription_control_enabled.
        if (! config('resayil.subscription_control_enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription control is switched off in this environment. It acts on the live WhatsApp platform, so it is enabled deliberately rather than left available while testing.',
            ], 409);
        }

        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json(['success' => false, 'message' => 'No company selected.'], 400);
        }

        // Confirm gate (§9.4). The UI sends this from a two-step modal; a
        // stray POST without it changes nothing.
        if (! $request->boolean('confirmed')) {
            return response()->json([
                'success' => false,
                'message' => 'This action was not confirmed. Nothing was changed.',
            ], 422);
        }

        $result = $action === 'pause'
            ? $service->pauseDevice($companyId, (int) $user->id)
            : $service->resumeDevice($companyId, (int) $user->id);

        return response()->json([
            'success' => (bool) $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 502)->header('Cache-Control', 'no-store');
    }
}
