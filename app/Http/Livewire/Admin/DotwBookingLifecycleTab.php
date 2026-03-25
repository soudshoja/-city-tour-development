<?php

declare(strict_types=1);

namespace App\Http\Livewire\Admin;

use App\Models\Role;
use App\Modules\DotwAI\Models\DotwAIBooking;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DotwBookingLifecycleTab extends Component
{
    use WithPagination;

    /**
     * Filter properties.
     */
    public string $filterStatus = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    /**
     * Expanded row state.
     */
    public ?int $expandedRow = null;

    /**
     * Query string bindings for URL persistence.
     */
    protected $queryString = [
        'filterStatus'   => ['except' => ''],
        'filterDateFrom' => ['except' => ''],
        'filterDateTo'   => ['except' => ''],
    ];

    /**
     * Check if the authenticated user is a super admin.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return Auth::user()->role_id === Role::ADMIN;
    }

    /**
     * Toggle expansion state for a specific booking row.
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
        $this->filterStatus = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->expandedRow = null;
        $this->resetPage();
    }

    /**
     * Generate the lifecycle stepper stages for a booking.
     *
     * Each stage represents a point in the booking journey. Stages transition from
     * gray (not reached) → blue (reached successfully) → red (reached but failed/cancelled).
     *
     * @param DotwAIBooking $booking
     * @return array Array of stage arrays with keys: label, reached, failed, timestamp
     */
    public function lifecycleStages(DotwAIBooking $booking): array
    {
        // Stage 0: Prebooked - Always reached when a booking exists
        $stages[] = [
            'label'     => 'Prebooked',
            'reached'   => true,
            'failed'    => false,
            'timestamp' => $booking->created_at->format('Y-m-d H:i'),
        ];

        // Stage 1: Payment (or Credit for B2B)
        $label = $booking->track === DotwAIBooking::TRACK_B2B ? 'Credit' : 'Payment';
        $reached = $booking->payment_status === 'paid' || in_array($booking->track, [DotwAIBooking::TRACK_B2B]);
        $failed = $booking->status === DotwAIBooking::STATUS_FAILED && $booking->payment_status !== 'paid';
        $stages[] = [
            'label'     => $label,
            'reached'   => $reached,
            'failed'    => $failed,
            'timestamp' => null,
        ];

        // Stage 2: Confirmed
        $reached = in_array($booking->status, [
            DotwAIBooking::STATUS_CONFIRMED,
            DotwAIBooking::STATUS_CANCELLATION_PENDING,
            DotwAIBooking::STATUS_CANCELLED,
        ]);
        $failed = $booking->status === DotwAIBooking::STATUS_FAILED;
        $stages[] = [
            'label'     => 'Confirmed',
            'reached'   => $reached,
            'failed'    => $failed,
            'timestamp' => null,
        ];

        // Stage 3: Voucher Sent
        $reached = $booking->voucher_sent_at !== null;
        $stages[] = [
            'label'     => 'Voucher Sent',
            'reached'   => $reached,
            'failed'    => false,
            'timestamp' => $booking->voucher_sent_at?->format('Y-m-d H:i'),
        ];

        // Stage 4: Cancelled (only shown if actually cancelled)
        $reached = $booking->status === DotwAIBooking::STATUS_CANCELLED;
        $stages[] = [
            'label'     => 'Cancelled',
            'reached'   => $reached,
            'failed'    => $reached, // Always red when reached
            'timestamp' => null,
        ];

        return $stages;
    }

    /**
     * Render the component with paginated, filtered bookings.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        $bookings = DotwAIBooking::query()
            ->when(! $this->isSuperAdmin(), fn ($q) => $q->where('company_id', Auth::user()->company_id))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterDateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->filterDateTo))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.admin.dotw-booking-lifecycle-tab', [
            'bookings'     => $bookings,
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }
}
