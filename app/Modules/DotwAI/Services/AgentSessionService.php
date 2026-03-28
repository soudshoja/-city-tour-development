<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Manages per-phone journey state for the AI agent facade.
 *
 * Session stored in Cache under "dotwai_session_{phone}" with 60-min TTL.
 * Tracks: stage, search results reference, selected hotel/room, prebook_key,
 * search_cached_at, prebook_expires_at, last_action.
 *
 * DOTW time constraints enforced:
 * - Search cache TTL: 600 seconds (10 minutes) — per dotwai.search_cache_ttl config
 * - Prebook allocation: 30 minutes — per dotwai.prebook_expiry_minutes config
 *
 * @see AGEN-02 Per-phone session state management
 */
class AgentSessionService
{
    private const CACHE_PREFIX = 'dotwai_session_';
    private const SESSION_TTL_MINUTES = 60;

    /** Get current session for phone. Returns [] if none. */
    public function getSession(string $phone): array
    {
        return Cache::get(self::CACHE_PREFIX . $phone, []);
    }

    /** Persist session with 60-min rolling TTL. */
    public function saveSession(string $phone, array $data): void
    {
        Cache::put(self::CACHE_PREFIX . $phone, $data, now()->addMinutes(self::SESSION_TTL_MINUTES));
    }

    /** Erase session (on explicit logout or fatal expiry). */
    public function clearSession(string $phone): void
    {
        Cache::forget(self::CACHE_PREFIX . $phone);
    }

    /**
     * Returns true if search results in session are stale (> 600 seconds old).
     * 600 seconds matches dotwai.search_cache_ttl — the window DOTW search
     * results are valid. After this, AI should trigger a fresh search.
     */
    public function isSearchExpired(string $phone): bool
    {
        $session = $this->getSession($phone);
        if (empty($session['search_cached_at'])) {
            return true; // No search in session = treat as expired
        }
        $cachedAt = Carbon::parse($session['search_cached_at']);
        return $cachedAt->diffInSeconds(now()) > 600;
    }

    /**
     * Returns true if the prebook allocation has expired (past prebook_expires_at).
     * DOTW holds rate blocks for 30 minutes from prebook time.
     * After expiry, customer must re-search.
     */
    public function isPrebookExpired(string $phone): bool
    {
        $session = $this->getSession($phone);
        if (empty($session['prebook_expires_at'])) {
            return true; // No active prebook
        }
        return now()->isAfter(Carbon::parse($session['prebook_expires_at']));
    }

    /**
     * Derive session context for inclusion in every API response.
     * Gives the AI agent a snapshot of where the customer is in the journey.
     *
     * @return array{stage: string, summary: string, next_actions: array<int,string>}
     */
    public function getStageContext(array $session): array
    {
        $stage = $session['stage'] ?? 'idle';

        $nextActions = match ($stage) {
            'idle'             => ['search for a hotel'],
            'searching'        => ['select a hotel from results', 'search again with different filters'],
            'viewing_details'  => ['select a room to book', 'search for a different hotel'],
            'prebooked'        => ['get payment link', 'cancel booking'],
            'awaiting_payment' => ['check if payment is complete', 'cancel booking'],
            'confirmed'        => ['view booking status', 'resend voucher', 'cancel booking'],
            'cancelling'       => ['confirm cancellation', 'keep booking'],
            default            => ['search for a hotel'],
        };

        $summary = match ($stage) {
            'idle'             => 'No active session. Customer can start a new search.',
            'searching'        => 'Search results available: ' . ($session['search_hotel_count'] ?? 0) . ' hotels found for ' . ($session['search_city'] ?? 'unknown city') . '.',
            'viewing_details'  => 'Viewing room details for ' . ($session['selected_hotel_name'] ?? 'selected hotel') . '.',
            'prebooked'        => 'Rate locked for ' . ($session['selected_hotel_name'] ?? 'hotel') . '. Prebook key: ' . ($session['prebook_key'] ?? 'N/A') . '.',
            'awaiting_payment' => 'Payment link sent for ' . ($session['selected_hotel_name'] ?? 'hotel') . '. Awaiting payment confirmation.',
            'confirmed'        => 'Booking confirmed: ' . ($session['booking_ref'] ?? 'N/A') . ' at ' . ($session['selected_hotel_name'] ?? 'hotel') . '.',
            'cancelling'       => 'Cancellation initiated for booking ' . ($session['prebook_key'] ?? 'N/A') . '. Awaiting customer confirmation.',
            default            => 'No active session.',
        };

        return [
            'stage'        => $stage,
            'summary'      => $summary,
            'next_actions' => $nextActions,
        ];
    }
}
