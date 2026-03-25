<?php

namespace App\Http\Livewire\Admin;

use App\Models\DotwAuditLog;
use App\Models\Role;
use App\Modules\DotwAI\Models\DotwAIBooking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DotwErrorTrackerTab — Livewire component for unified error tracking.
 *
 * Merges errors from two sources:
 * 1. Failed/expired bookings (booking_failed errors)
 * 2. Empty DOTW API responses (empty_response errors)
 *
 * Provides filtering by:
 * - Error type (all, booking_failed, empty_response)
 * - Company (super-admin only)
 * - Agent phone (partial match)
 * - Date range
 *
 * Auto-refreshes via wire:poll every 30 seconds.
 * Satisfies DASH-04 (error filtering) and DASH-05 (empty response flagging).
 */
class DotwErrorTrackerTab extends Component
{
    use WithPagination;

    /**
     * Filter properties.
     */
    public string $filterErrorType = '';

    public string $filterCompanyId = '';

    public string $filterAgent = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    /**
     * Expanded row state for detail inspection.
     */
    public ?int $expandedRow = null;

    /**
     * Query string bindings for URL persistence.
     */
    protected $queryString = [
        'filterErrorType' => ['except' => ''],
        'filterCompanyId' => ['except' => ''],
        'filterAgent'     => ['except' => ''],
        'filterDateFrom'  => ['except' => ''],
        'filterDateTo'    => ['except' => ''],
    ];

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
     * Toggle expansion state for a specific error row.
     *
     * @param int $id
     * @return void
     */
    public function toggleRow(int $id): void
    {
        $this->expandedRow = $this->expandedRow === $id ? null : $id;
    }

    /**
     * Reset all filters and pagination state.
     *
     * @return void
     */
    public function resetFilters(): void
    {
        $this->filterErrorType = '';
        $this->filterCompanyId = '';
        $this->filterAgent = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->expandedRow = null;
        $this->resetPage();
    }

    /**
     * Render the component with paginated, filtered errors.
     *
     * Merges booking failures and empty DOTW responses into a unified error list,
     * sorted by created_at descending, paginated to 25 per page.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        // Source 1: Failed/expired bookings
        if ($this->filterErrorType !== 'empty_response') {
            $bookingErrors = DotwAIBooking::query()
                ->whereIn('status', ['failed', 'expired'])
                ->when(! $this->isSuperAdmin(), fn ($q) => $q->where('company_id', Auth::user()->company_id))
                ->when($this->isSuperAdmin() && $this->filterCompanyId, fn ($q) => $q->where('company_id', $this->filterCompanyId))
                ->when($this->filterAgent, fn ($q) => $q->where('agent_phone', 'like', "%{$this->filterAgent}%"))
                ->when($this->filterDateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->filterDateTo))
                ->get()
                ->map(fn ($b) => (object) [
                    'id'             => $b->id,
                    'error_type'     => 'booking_failed',
                    'company_id'     => $b->company_id,
                    'agent_phone'    => $b->agent_phone,
                    'operation_type' => null,
                    'detail'         => $b->hotel_name ?? $b->prebook_key,
                    'status'         => $b->status,
                    'created_at'     => $b->created_at,
                ]);
        } else {
            $bookingErrors = collect();
        }

        // Source 2: Audit log rows with empty response
        if ($this->filterErrorType !== 'booking_failed') {
            $emptyErrors = DotwAuditLog::query()
                ->whereNull('response_payload')
                ->when(! $this->isSuperAdmin(), fn ($q) => $q->where('company_id', Auth::user()->company_id))
                ->when($this->isSuperAdmin() && $this->filterCompanyId, fn ($q) => $q->where('company_id', $this->filterCompanyId))
                ->when($this->filterDateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->filterDateTo))
                ->get()
                ->map(fn ($l) => (object) [
                    'id'             => $l->id,
                    'error_type'     => 'empty_response',
                    'company_id'     => $l->company_id,
                    'agent_phone'    => null,
                    'operation_type' => $l->operation_type,
                    'detail'         => $l->resayil_message_id ?? "Log #{$l->id}",
                    'status'         => null,
                    'created_at'     => $l->created_at,
                ]);
        } else {
            $emptyErrors = collect();
        }

        // Merge and sort
        $merged = $bookingErrors->merge($emptyErrors)
            ->sortByDesc('created_at')
            ->values();

        // Manual pagination
        $page = $this->getPage();
        $perPage = 25;
        $items = $merged->forPage($page, $perPage);

        $errors = new LengthAwarePaginator(
            $items,
            $merged->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        return view('livewire.admin.dotw-error-tracker-tab', [
            'errors'       => $errors,
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }
}
