<?php

namespace App\Http\Livewire\Admin;

use App\Models\DotwAuditLog;
use App\Models\Role;
use App\Modules\DotwAI\Models\DotwAIBooking;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * DotwDashboardTab — Livewire component for DOTW module health monitoring.
 *
 * Displays:
 * - 4 stat cards: total bookings, bookings today, errors today, active prebooks
 * - 2 ApexCharts: booking trend (last 14 days) and API operation breakdown (last 7 days)
 * - Recent API calls table (last 25 entries, flagging empty responses)
 *
 * Auto-refreshes via wire:poll every 30 seconds.
 * Company-level admins see only their own company's data.
 */
class DotwDashboardTab extends Component
{
    /**
     * Stat card data.
     */
    public int $totalBookings = 0;
    public int $bookingsToday = 0;
    public int $errorsToday = 0;
    public int $activePrebooks = 0;

    /**
     * Chart data — serialized as JSON for Blade.
     */
    public array $bookingTrendDates = [];
    public array $bookingTrendCounts = [];
    public array $operationLabels = ['search', 'rates', 'block', 'book'];
    public array $operationCounts = [0, 0, 0, 0];

    /**
     * Recent API log table — plain array representation.
     */
    public array $recentLogs = [];

    /**
     * Determine if the current user is a super-admin.
     *
     * @return bool True if role_id === Role::ADMIN
     */
    public function isSuperAdmin(): bool
    {
        return Auth::user()->role_id === Role::ADMIN;
    }

    /**
     * Initial mount — load all metrics on component load.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->refreshMetrics();
    }

    /**
     * Refresh all dashboard metrics.
     *
     * Queries both DotwAIBooking and DotwAuditLog tables to:
     * 1. Compute 4 stat cards
     * 2. Generate booking trend data (last 14 days)
     * 3. Generate API operation breakdown (last 7 days)
     * 4. Load recent API calls (last 25 entries)
     *
     * Company-level filtering is applied to all queries.
     *
     * @return void
     */
    public function refreshMetrics(): void
    {
        $companyScope = fn ($query) => $query->when(
            !$this->isSuperAdmin(),
            fn ($q) => $q->where('company_id', Auth::user()->company_id)
        );

        // 1. Total bookings (all time)
        $this->totalBookings = DotwAIBooking::query()
            ->tap($companyScope)
            ->count();

        // 2. Bookings today
        $this->bookingsToday = DotwAIBooking::query()
            ->tap($companyScope)
            ->whereDate('created_at', today())
            ->count();

        // 3. Errors today (failed or expired status)
        $this->errorsToday = DotwAIBooking::query()
            ->tap($companyScope)
            ->whereDate('created_at', today())
            ->whereIn('status', [DotwAIBooking::STATUS_FAILED, DotwAIBooking::STATUS_EXPIRED])
            ->count();

        // 4. Active prebooks (prebooked or pending_payment)
        $this->activePrebooks = DotwAIBooking::query()
            ->tap($companyScope)
            ->whereIn('status', [DotwAIBooking::STATUS_PREBOOKED, DotwAIBooking::STATUS_PENDING_PAYMENT])
            ->count();

        // 5. Booking trend (last 14 days)
        $dates = collect(range(13, 0))
            ->map(fn ($d) => now()->subDays($d)->format('Y-m-d'))
            ->toArray();

        $trendData = DotwAIBooking::query()
            ->tap($companyScope)
            ->whereDate('created_at', '>=', now()->subDays(13))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $this->bookingTrendDates = $dates;
        $this->bookingTrendCounts = collect($dates)
            ->map(fn ($d) => (int) ($trendData[$d] ?? 0))
            ->toArray();

        // 6. API operation breakdown (last 7 days)
        $opData = DotwAuditLog::query()
            ->tap($companyScope)
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->selectRaw('operation_type, COUNT(*) as count')
            ->groupBy('operation_type')
            ->pluck('count', 'operation_type');

        $this->operationCounts = collect($this->operationLabels)
            ->map(fn ($op) => (int) ($opData[$op] ?? 0))
            ->toArray();

        // 7. Recent API log (last 25 entries, most recent first)
        $this->recentLogs = DotwAuditLog::query()
            ->tap($companyScope)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn ($log) => [
                'id'               => $log->id,
                'company_id'       => $log->company_id,
                'message_id'       => $log->resayil_message_id ?? '—',
                'operation_type'   => $log->operation_type,
                'has_empty_response' => empty($log->response_payload),
                'created_at'       => $log->created_at->format('Y-m-d H:i:s'),
            ])
            ->toArray();

        // 8. Dispatch event for chart re-initialization
        $this->dispatch('dashboardMetricsUpdated');
    }

    /**
     * Render the Blade view.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.admin.dotw-dashboard-tab');
    }
}
