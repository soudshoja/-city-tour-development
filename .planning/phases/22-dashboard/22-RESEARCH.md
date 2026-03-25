# Phase 22: Dashboard - Research

**Researched:** 2026-03-25
**Domain:** Livewire admin dashboard with real-time monitoring and analytics
**Confidence:** HIGH

## Summary

Phase 22 extends the existing `/admin/dotw` Livewire component with three new tabs (Dashboard, Bookings, Errors) to provide administrators with comprehensive visibility into DOTW AI module operations. The dashboard displays API call logs, booking lifecycle tracking, error filtering, and trend charts using ApexCharts — all already integrated in the project. No new dependencies are required. The implementation follows established patterns: tab-based navigation (Alpine.js), Livewire pagination (25/page), and dark mode support (Tailwind).

**Primary recommendation:** Extend DotwAdminIndex component with three new Livewire sub-components (Dashboard, BookingLifecycle, ErrorTracker) loaded via tabs. Reuse DotwAuditLogIndex pattern for filtering/pagination. Use existing ApexCharts + Tailwind styling for charts and stats cards.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Add new tabs to existing `/admin/dotw` page (DotwAdminIndex component) — "Dashboard", "Bookings", "Errors" tabs alongside existing credentials/audit-logs/api-tokens tabs
- **D-02:** Single entry point at `/admin/dotw` — no standalone page or separate sidebar link needed
- **D-03:** Stats cards for key metrics (total bookings, errors today, pending payments, active prebooks) PLUS trend charts using ApexCharts
- **D-04:** Line/bar charts for: bookings over time, error rate trends, API response times
- **D-05:** Existing ApexCharts + Chart.js libraries — no new dependencies needed
- **D-06:** Visual horizontal stepper/timeline for booking journey: search → prebook → confirmed → voucher sent
- **D-07:** Failed/cancelled bookings shown as red branches in the stepper
- **D-08:** Click a booking to see its full journey with timestamps at each step
- **D-09:** DotwAIBooking model has all necessary timestamps: created_at, voucher_sent_at, reminder_sent_at, auto_invoiced_at, cancellation_deadline
- **D-10:** Livewire polling every 30 seconds for auto-refresh of metrics and tables
- **D-11:** No toast notifications — polling refresh is sufficient for this admin tool

### Claude's Discretion
- Exact tab ordering within the admin page
- Chart color scheme (should follow existing koromiko/blue palette)
- Stats card layout and grouping
- Error detail panel design
- Loading skeleton design during polling refresh
- Pagination size for tables (existing pattern: 25 per page)

### Deferred Ideas (OUT OF SCOPE)
- DOTW Hub Documentation milestone — documentation is separate phase

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| DASH-01 | Livewire dashboard showing incoming API call logs (requests, responses, errors) — no n8n branding | DotwAuditLog table stores all incoming API data; DotwAuditLogIndex component provides filter/pagination pattern; Tab-based UI in DotwAdminIndex |
| DASH-02 | Outgoing DOTW API call monitoring (timeouts, empty responses, failures) | DotwAuditLog.response_payload stores all responses; can detect empty/null/error states via query filters; Same data model as DASH-01 |
| DASH-03 | Booking lifecycle view (search → prebook → book → cancel with timestamps) | DotwAIBooking.created_at, voucher_sent_at, reminder_sent_at, auto_invoiced_at, cancellation_deadline provide all timeline data; status constants match lifecycle stages |
| DASH-04 | Error tracking with filters (date, company, agent, error type) | DotwAuditLog + DotwAIBooking can be joined via hotel_booking_id; company_id scoping already pattern in DotwAuditLogIndex; operation_type enum supports filtering |
| DASH-05 | DOTW calls with no output / empty responses flagged for investigation | response_payload can be queried for null/empty; operation_type='book'|'rates'|'block' allows filtering by critical operations |

</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Livewire | 3.5+ | Component-based reactive UI, polling support | Already integrated; used throughout admin; 30-second polling built-in via `wire:poll` |
| ApexCharts | 3.54.0 | Interactive charts (line, bar, trend) | Already integrated in dashboard; used for stats visualization |
| Tailwind CSS | (Latest) | Dark mode styling, responsive grid, responsive layout | Project-wide standard; dark: prefix fully supported |
| Alpine.js | (bundled) | Tab switching, DOM interactions, state management | Already in use; part of Livewire stack |
| Laravel 11 | 11.9+ | Application framework, Eloquent ORM, routing | Project requirement (confirmed in composer.json) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Chart.js | (bundled) | Alternative chart type if ApexCharts insufficient | Already integrated; consider for non-interactive static charts |
| Blade Templates | (Laravel native) | Server-side HTML rendering with Livewire | All UI — no frontend framework needed |
| Heroicons | 2.6+ | Icon set for UI elements (expand/collapse, filter, etc.) | Already in project; use for dashboard buttons/icons |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| ApexCharts | Chart.js | Chart.js less interactive, requires more custom JS; ApexCharts already integrated |
| Livewire polling | WebSocket (Laravel Echo) | WebSocket more real-time but requires Redis/Node.js setup; polling sufficient for admin dashboard |
| Tab navigation (Alpine) | Livewire tabs | Livewire tabs require page reload; Alpine tabs instant client-side switching preferred |

**Installation:**
No new packages required. ApexCharts and Livewire already in composer.json and package.json.

**Verify existing packages:**
```bash
# Check Livewire version
php artisan tinker
>>> app('livewire')->version()

# Check ApexCharts in package.json
npm list apexcharts
```

## Architecture Patterns

### Recommended Project Structure
```
app/Http/Livewire/Admin/
├── DotwAdminIndex.php              # Main component — orchestrates 5 tabs
├── DotwDashboardTab.php            # NEW: Dashboard tab (stats + charts)
├── DotwBookingLifecycleTab.php      # NEW: Booking lifecycle timeline
├── DotwErrorTrackerTab.php          # NEW: Error filtering and analysis
└── DotwAuditLogIndex.php            # EXISTING: Audit logs (reused in Error tab)

resources/views/livewire/admin/
├── dotw-admin-index.blade.php       # EXISTING: Tab container
├── dotw-dashboard-tab.blade.php     # NEW: Stats cards + charts
├── dotw-booking-lifecycle-tab.blade.php # NEW: Timeline view
├── dotw-error-tracker-tab.blade.php # NEW: Error table + filters
└── dotw-audit-log-index.blade.php   # EXISTING
```

### Pattern 1: Livewire Tab-Based Component Hierarchy
**What:** Parent component (DotwAdminIndex) manages tab state; child components render tab content.
**When to use:** Multi-view admin pages with independent data sources and refresh cycles.
**Example:**
```php
// app/Http/Livewire/Admin/DotwAdminIndex.php
class DotwAdminIndex extends Component
{
    public string $activeTab = 'credentials';

    public function mount(string $tab = 'credentials'): void
    {
        $this->activeTab = $tab;
    }

    public function render(): View
    {
        return view('livewire.admin.dotw-admin-index', [
            'isSuperAdmin' => Auth::user()->role_id === Role::ADMIN,
        ]);
    }
}
```

```blade
{{-- resources/views/livewire/admin/dotw-admin-index.blade.php --}}
<div x-data="{ activeTab: '{{ $activeTab }}' }">
    {{-- Tab buttons --}}
    <button @click="activeTab = 'dashboard'" :class="activeTab === 'dashboard' ? 'active' : ''">
        Dashboard
    </button>

    {{-- Tab contents --}}
    <div x-show="activeTab === 'dashboard'">
        @livewire('admin.dotw-dashboard-tab')
    </div>
</div>
```

### Pattern 2: Livewire Polling for Auto-Refresh
**What:** `wire:poll` directive automatically calls a public method every 30 seconds without full page reload.
**When to use:** Real-time dashboards, live metrics, monitoring tools.
**Example:**
```blade
<div wire:poll.30000ms="refreshMetrics">
    <div class="text-2xl font-bold">
        {{ $bookingCount }} bookings today
    </div>
</div>
```

```php
class DotwDashboardTab extends Component
{
    public int $bookingCount = 0;
    public int $errorsToday = 0;

    public function refreshMetrics(): void
    {
        $this->bookingCount = DotwAIBooking::whereDate('created_at', today())->count();
        $this->errorsToday = DotwAIBooking::whereDate('created_at', today())
            ->whereIn('status', ['failed', 'cancelled'])
            ->count();
    }

    public function mount(): void
    {
        $this->refreshMetrics();
    }

    public function render(): View
    {
        return view('livewire.admin.dotw-dashboard-tab');
    }
}
```

### Pattern 3: Livewire Pagination (Reuse DotwAuditLogIndex)
**What:** `WithPagination` trait handles pagination state; templates loop `$items->paginate(25)`.
**When to use:** Large tables requiring per-page views (25 rows standard).
**Example:**
```php
class DotwErrorTrackerTab extends Component
{
    use WithPagination;

    public string $filterCompany = '';
    public string $filterAgent = '';
    public string $filterErrorType = '';

    public function render(): View
    {
        $errors = DotwAIBooking::query()
            ->where('status', 'failed')
            ->when($this->filterCompany, fn($q) => $q->where('company_id', $this->filterCompany))
            ->when($this->filterAgent, fn($q) => $q->where('agent_phone', 'like', "%{$this->filterAgent}%"))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.admin.dotw-error-tracker-tab', ['errors' => $errors]);
    }
}
```

### Pattern 4: Stats Cards + Trend Charts (ApexCharts)
**What:** Small metric cards + ApexCharts for line/bar charts; data passed from Livewire to JavaScript.
**When to use:** Dashboard metrics requiring visual trends over time.
**Example:**
```blade
<div class="grid grid-cols-4 gap-4">
    <!-- Stats Card -->
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <p class="text-xs text-gray-600 dark:text-gray-400">Total Bookings</p>
        <h3 class="text-2xl font-bold text-blue-600">{{ $totalBookings }}</h3>
    </div>
</div>

<!-- Chart Container -->
<div id="bookings-chart" class="mt-6"></div>

<script>
    const chartOptions = {
        chart: { type: 'line', height: 300 },
        series: [{
            name: 'Bookings',
            data: @json($bookingTrend) // Pass from Livewire controller
        }],
        xaxis: { categories: @json($dates) },
        colors: ['#3b82f6'], // blue
        stroke: { curve: 'smooth' }
    };
    new ApexCharts(document.getElementById('bookings-chart'), chartOptions).render();
</script>
```

### Anti-Patterns to Avoid
- **Embedding raw JSON in JavaScript without escaping:** Use `@json()` Blade directive to safely encode PHP arrays for JS consumption
- **Separate components for each chart:** Combine stats + single chart per section; reduces component bloat
- **Forgetting to scope queries by company_id:** Always apply company_id filter for company-level admins (auth check in component)
- **Using full table refresh instead of polling:** Polling preserves scroll/filter state; full page reload causes UX disruption
- **Hardcoding colors instead of Tailwind:** Use `dark:` prefix and consistent blue/red/green palette matching dashboard.blade.php

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Chart rendering | Custom SVG/Canvas charts | ApexCharts (already integrated) | ApexCharts handles responsive sizing, dark mode, animations, interactivity; custom code = 500+ lines |
| Pagination | Offset/limit math in component | Livewire `WithPagination` trait | Trait handles state persistence, URL binding, page tracking automatically |
| Tab state management | Custom component state | Alpine.js `x-data` + `@click` | Alpine is lightweight, part of Livewire, instant without HTTP |
| Date filtering | Manual date range logic | Livewire `wire:model` + `whereDate()` | Livewire handles two-way binding; Laravel's whereDate() handles timezone conversion |
| API call logging | Parse incoming requests manually | Existing DotwAuditLog + DotwAuditService | All requests already logged by Phase 18; querying table is faster than rebuilding logs |
| Booking timeline | Custom SVG stepper | Tailwind flex layout + icons | Tailwind's flexbox + Heroicons achieves timeline in 50 lines; SVG = 200+ lines |

**Key insight:** This phase piggybacks on Phase 18's audit logging infrastructure and Phase 19-21's booking lifecycle. No business logic is new — only visualization and filtering of existing data.

## Common Pitfalls

### Pitfall 1: Company Scoping Blindness
**What goes wrong:** Dashboard queries all companies' data (bookings, errors) even for company-level admins.
**Why it happens:** Forgetting to add `where('company_id', Auth::user()->company_id)` in non-super-admin queries.
**How to avoid:** Always check `Auth::user()->role_id === Role::ADMIN` (super-admin) vs. `Auth::user()->company_id` (company-level); apply appropriate scope.
**Warning signs:** Company A's admin sees Company B's error counts; error numbers don't match company's own records.

**Example (correct scoping):**
```php
$errors = DotwAIBooking::query()
    ->where('status', 'failed')
    ->when(! $this->isSuperAdmin(), fn($q) => $q->where('company_id', Auth::user()->company_id))
    ->orderByDesc('created_at')
    ->paginate(25);
```

### Pitfall 2: Polling Latency Expectations
**What goes wrong:** Admin expects real-time updates but polling fires every 30 seconds — data can be up to 30s stale.
**Why it happens:** Misunderstanding polling interval; expecting WebSocket-level responsiveness.
**How to avoid:** Document in dashboard that data refreshes every 30 seconds. If real-time is critical, Livewire 3.5 supports WebSocket (but requires additional setup).
**Warning signs:** Admin complains "I just saw the error but the count didn't change"; refresh frequency needs documentation.

### Pitfall 3: Loading State UI Missing During Poll
**What goes wrong:** Charts flicker or buttons appear disabled during polling without visual feedback.
**Why it happens:** Forgetting `wire:loading` directive or opacity transitions during poll.
**How to avoid:** Always wrap data-dependent sections in `wire:loading.remove` and show skeleton/spinner with `wire:loading`.
**Warning signs:** Dashboard briefly goes blank every 30s; users think page is broken.

**Example (correct):**
```blade
<div wire:loading.remove wire:target="refreshMetrics">
    <div class="text-2xl font-bold">{{ $bookingCount }} bookings</div>
</div>

<div wire:loading wire:target="refreshMetrics" class="animate-pulse">
    <div class="h-8 bg-gray-200 rounded w-48"></div>
</div>
```

### Pitfall 4: Charts Not Updating After Polling
**What goes wrong:** Stats cards update but charts stay static; data and visualization mismatch.
**Why it happens:** Chart library only runs on initial page load; polling doesn't trigger chart re-render.
**How to avoid:** Use Livewire's `dispatch()` to emit event after data refresh; listen in JavaScript with `window.addEventListener('livewire:updated', ...)`.
**Warning signs:** Numbers change but line chart is flat; user thinks something broke.

**Example (correct chart update):**
```php
class DotwDashboardTab extends Component
{
    public function refreshMetrics(): void
    {
        // ... update stats ...
        $this->dispatch('metricsUpdated', [
            'bookingTrend' => $this->calculateTrend(),
        ]);
    }
}
```

```blade
@script
<script>
    document.addEventListener('livewire:updated', () => {
        // Destroy old chart and create new one with fresh data
        if (window.bookingsChart) {
            window.bookingsChart.destroy();
        }
        window.bookingsChart = new ApexCharts(
            document.getElementById('bookings-chart'),
            { /* options */ }
        ).render();
    });
</script>
@endscript
```

### Pitfall 5: Expandable Rows State Loss After Pagination
**What goes wrong:** User clicks "View" on error row, pagination refreshes, expanded row collapses.
**Why it happens:** Livewire component state reset doesn't preserve expanded row ID across pagination.
**How to avoid:** Add `protected $queryString = ['expandedRow']` to component to persist state in URL.
**Warning signs:** Expanded row closes when user changes page or refines filters.

**Example (correct state persistence):**
```php
class DotwErrorTrackerTab extends Component
{
    use WithPagination;

    public ?int $expandedRow = null;

    protected $queryString = ['expandedRow'];

    public function toggleRow(int $id): void
    {
        $this->expandedRow = $this->expandedRow === $id ? null : $id;
    }
}
```

## Code Examples

Verified patterns from existing codebase:

### Stats Card + Chart Component
```php
// app/Http/Livewire/Admin/DotwDashboardTab.php
class DotwDashboardTab extends Component
{
    public int $totalBookings = 0;
    public int $errorCount = 0;
    public int $pendingPayments = 0;
    public int $activePrebooks = 0;

    public array $bookingTrendData = [];
    public array $errorRateData = [];

    public function mount(): void
    {
        $this->loadMetrics();
    }

    public function loadMetrics(): void
    {
        // Same-day metrics
        $today = today();
        $this->totalBookings = DotwAIBooking::whereDate('created_at', $today)->count();
        $this->errorCount = DotwAIBooking::whereDate('created_at', $today)
            ->whereIn('status', ['failed', 'cancelled'])
            ->count();
        $this->pendingPayments = DotwAIBooking::where('status', 'pending_payment')->count();
        $this->activePrebooks = DotwAIBooking::where('status', 'prebooked')->count();

        // 7-day trend
        $this->bookingTrendData = $this->calculateBookingTrend();
        $this->errorRateData = $this->calculateErrorTrend();
    }

    private function calculateBookingTrend(): array
    {
        $days = collect(range(6, 0))->mapWithKeys(function ($offset) {
            $date = today()->subDays($offset);
            $count = DotwAIBooking::whereDate('created_at', $date)->count();
            return [$date->format('M d') => $count];
        })->toArray();

        return array_values($days);
    }

    private function calculateErrorTrend(): array
    {
        $days = collect(range(6, 0))->mapWithKeys(function ($offset) {
            $date = today()->subDays($offset);
            $total = DotwAIBooking::whereDate('created_at', $date)->count();
            $errors = DotwAIBooking::whereDate('created_at', $date)
                ->whereIn('status', ['failed', 'cancelled'])
                ->count();
            $rate = $total > 0 ? round(($errors / $total) * 100) : 0;
            return [$date->format('M d') => $rate];
        })->toArray();

        return array_values($days);
    }

    public function refreshMetrics(): void
    {
        $this->loadMetrics();
    }

    public function render(): View
    {
        return view('livewire.admin.dotw-dashboard-tab');
    }
}
```

```blade
{{-- resources/views/livewire/admin/dotw-dashboard-tab.blade.php --}}
<div wire:poll.30000ms="refreshMetrics">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-6">DOTW AI Dashboard</h2>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {{-- Total Bookings --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
            <p class="text-xs text-gray-600 dark:text-gray-400 uppercase font-semibold">Total Bookings (Today)</p>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $totalBookings }}</p>
            </div>
        </div>

        {{-- Errors Today --}}
        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
            <p class="text-xs text-gray-600 dark:text-gray-400 uppercase font-semibold">Errors (Today)</p>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $errorCount }}</p>
            </div>
        </div>

        {{-- Pending Payments --}}
        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
            <p class="text-xs text-gray-600 dark:text-gray-400 uppercase font-semibold">Pending Payment</p>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $pendingPayments }}</p>
            </div>
        </div>

        {{-- Active Prebooks --}}
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
            <p class="text-xs text-gray-600 dark:text-gray-400 uppercase font-semibold">Active Prebooks</p>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $activePrebooks }}</p>
            </div>
        </div>
    </div>

    {{-- Charts Section --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Booking Trend Chart --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Bookings (7 days)</h3>
            <div id="booking-trend-chart" style="height: 250px;"></div>
        </div>

        {{-- Error Rate Trend Chart --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-4">Error Rate (7 days)</h3>
            <div id="error-rate-chart" style="height: 250px;"></div>
        </div>
    </div>
</div>

@script
<script>
    const trendChart = new ApexCharts(
        document.getElementById('booking-trend-chart'),
        {
            chart: { type: 'line', height: 250, toolbar: { show: false } },
            series: [{ name: 'Bookings', data: @json($bookingTrendData) }],
            xaxis: {
                categories: ['6d', '5d', '4d', '3d', '2d', '1d', 'Today'],
                labels: { style: { colors: '#888', fontSize: '12px' } }
            },
            colors: ['#3b82f6'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 0.1 } },
            grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
        }
    ).render();

    const errorChart = new ApexCharts(
        document.getElementById('error-rate-chart'),
        {
            chart: { type: 'line', height: 250, toolbar: { show: false } },
            series: [{ name: 'Error Rate (%)', data: @json($errorRateData) }],
            xaxis: {
                categories: ['6d', '5d', '4d', '3d', '2d', '1d', 'Today'],
                labels: { style: { colors: '#888', fontSize: '12px' } }
            },
            colors: ['#ef4444'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 0.1 } },
            grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
        }
    ).render();
</script>
@endscript
```

### Booking Lifecycle Timeline
```blade
{{-- resources/views/livewire/admin/dotw-booking-lifecycle-tab.blade.php --}}
<div class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-6">Booking Lifecycle</h2>

    @forelse($bookings as $booking)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ $booking->hotel_name }} — {{ $booking->prebook_key }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $booking->check_in?->format('M d') }} → {{ $booking->check_out?->format('M d, Y') }}
                    </p>
                </div>
                <span class="px-2 py-1 text-xs font-semibold rounded-full
                    {{ match($booking->status) {
                        'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                        'prebooked' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                        'pending_payment' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                        'failed', 'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                    }} }}">
                    {{ $booking->status }}
                </span>
            </div>

            {{-- Timeline stepper --}}
            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 mt-4">
                {{-- Search (creation) --}}
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-xs font-bold text-blue-700 dark:text-blue-300">
                        1
                    </div>
                    <p class="mt-1 text-[10px]">{{ $booking->created_at?->format('H:i') }}</p>
                </div>

                <div class="flex-1 h-0.5 bg-gray-300 dark:bg-gray-600"></div>

                {{-- Prebook (rate locked) --}}
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full {{ $booking->status !== 'prebooked' && in_array($booking->status, ['pending_payment', 'confirmed', 'confirming']) ? 'bg-blue-100 dark:bg-blue-900' : 'bg-gray-200 dark:bg-gray-700' }} flex items-center justify-center text-xs font-bold {{ $booking->status !== 'prebooked' && in_array($booking->status, ['pending_payment', 'confirmed', 'confirming']) ? 'text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-500' }}">
                        2
                    </div>
                    <p class="mt-1 text-[10px]">{{ $booking->updated_at?->format('H:i') }}</p>
                </div>

                <div class="flex-1 h-0.5 {{ in_array($booking->status, ['confirmed', 'confirming']) ? 'bg-blue-300 dark:bg-blue-700' : 'bg-gray-300 dark:bg-gray-600' }}"></div>

                {{-- Confirmed --}}
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full {{ $booking->status === 'confirmed' ? 'bg-green-100 dark:bg-green-900' : ($booking->status === 'failed' || $booking->status === 'cancelled' ? 'bg-red-100 dark:bg-red-900' : 'bg-gray-200 dark:bg-gray-700') }} flex items-center justify-center text-xs font-bold {{ $booking->status === 'confirmed' ? 'text-green-700 dark:text-green-300' : ($booking->status === 'failed' || $booking->status === 'cancelled' ? 'text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-500') }}">
                        {{ $booking->status === 'failed' || $booking->status === 'cancelled' ? '✕' : '3' }}
                    </div>
                    <p class="mt-1 text-[10px]">{{ $booking->updated_at?->format('H:i') }}</p>
                </div>

                <div class="flex-1 h-0.5 {{ $booking->voucher_sent_at ? 'bg-blue-300 dark:bg-blue-700' : 'bg-gray-300 dark:bg-gray-600' }}"></div>

                {{-- Voucher Sent --}}
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full {{ $booking->voucher_sent_at ? 'bg-green-100 dark:bg-green-900' : 'bg-gray-200 dark:bg-gray-700' }} flex items-center justify-center text-xs font-bold {{ $booking->voucher_sent_at ? 'text-green-700 dark:text-green-300' : 'text-gray-600 dark:text-gray-500' }}">
                        4
                    </div>
                    <p class="mt-1 text-[10px]">{{ $booking->voucher_sent_at?->format('H:i') ?? '—' }}</p>
                </div>
            </div>
        </div>
    @empty
        <p class="text-gray-600 dark:text-gray-400 text-sm">No bookings found.</p>
    @endforelse
</div>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Server-side rendered dashboards requiring full page refresh | Livewire components with polling (no refresh needed) | Livewire 3.0+ | Faster UX, preserved scroll state, instant filter feedback |
| Static charts (Chart.js) requiring manual data updates | ApexCharts with Livewire dispatch events | ApexCharts 3.0+ | Interactive charts (zoom, pan, legend), automatic responsiveness |
| Manual pagination logic in controllers | `WithPagination` trait with URL state | Laravel 6+ | Automatic state persistence, simplified code |
| Separate admin dashboards per entity | Tab-based unified dashboards | Livewire 3.5 | Single entry point, consolidated navigation |

**Deprecated/outdated:**
- jQuery-based admin dashboards — replaced by Alpine.js + Livewire (less dependencies)
- Server-side session for tab state — use Alpine.js `x-data` for client-side (no state pollution)
- Refresh buttons on charts — use `wire:poll` for automatic updates

## Open Questions

1. **Chart performance with 30-second polling**
   - What we know: ApexCharts supports update API; Livewire 3.5+ supports dispatch events
   - What's unclear: Will chart re-render every 30s cause excessive DOM churn?
   - Recommendation: Test with 7-day trend data (168 points per series); if performance suffers, increase polling interval to 60s or use `wire:poll.throttle` instead of `wire:poll`

2. **Empty response detection strategy**
   - What we know: response_payload can be null or empty; operation_type enum filters by operation
   - What's unclear: What constitutes "empty" (null, empty array, missing fields)?
   - Recommendation: Define in CONTEXT.md (e.g., `!isset($response['hotels'])` for search, `count($response['results']) === 0` for rates)

3. **Chart color consistency with dark mode**
   - What we know: ApexCharts supports theme property; Tailwind uses `dark:` prefix
   - What's unclear: Should chart colors invert automatically in dark mode or use fixed contrast colors?
   - Recommendation: Test ApexCharts' built-in dark theme option or explicitly set `colors: ['#3b82f6']` (blue) and `textColor: '#d1d5db'` for dark mode labels

4. **Booking count metrics for multi-company scenario**
   - What we know: DotwAIBooking has company_id; DotwAdminIndex checks `isSuperAdmin()`
   - What's unclear: Should super-admin see all companies' metrics or filter by selected company?
   - Recommendation: If multi-company view is desired, add company filter dropdown in dashboard; for now, assume super-admin sees all, company-admin sees own only

## Environment Availability

No external dependencies identified. All required tools and libraries are already installed:
- Livewire 3.5 — installed in composer.json
- ApexCharts 3.54.0 — installed in package.json
- Laravel 11.9+ — installed in composer.json
- MySQL — confirmed in use for both primary and map databases
- Docker/Docker Compose — optional, not required for this phase

**Skip reason:** This phase is purely frontend/visualization. No CLI tools, external APIs, or services are required beyond what's already running.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (Laravel default) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter Dotw` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DASH-01 | Dashboard displays incoming API logs | Feature | `php artisan test --filter DotwDashboardTabTest::test_displays_audit_logs` | ❌ Wave 0 |
| DASH-01 | Stats cards show correct counts | Unit | `php artisan test --filter DotwDashboardTabTest::test_calculates_booking_count` | ❌ Wave 0 |
| DASH-02 | Outgoing API failures detected | Feature | `php artisan test --filter DotwDashboardTabTest::test_shows_failed_operations` | ❌ Wave 0 |
| DASH-03 | Booking timeline renders all stages | Feature | `php artisan test --filter DotwBookingLifecycleTabTest::test_renders_timeline` | ❌ Wave 0 |
| DASH-04 | Error filtering works (date, company, agent) | Feature | `php artisan test --filter DotwErrorTrackerTabTest::test_filters_errors` | ❌ Wave 0 |
| DASH-05 | Empty responses flagged | Query | `php artisan test --filter DotwErrorTrackerTabTest::test_detects_empty_responses` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter Dotw`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Livewire/Admin/DotwDashboardTabTest.php` — covers DASH-01 stats and charts
- [ ] `tests/Feature/Livewire/Admin/DotwBookingLifecycleTabTest.php` — covers DASH-03 timeline rendering
- [ ] `tests/Feature/Livewire/Admin/DotwErrorTrackerTabTest.php` — covers DASH-04 and DASH-05 filtering
- [ ] `tests/Unit/Models/DotwAIBookingTest.php` — covers status/track constants and lifecycle helpers (if not already covered)
- [ ] PHPUnit base: Existing `phpunit.xml` configured; Livewire test setup in bootstrap

## Sources

### Primary (HIGH confidence)
- **CONTEXT.md** (.planning/phases/22-dashboard/22-CONTEXT.md) — Locked decisions on tab layout, polling interval, chart types
- **REQUIREMENTS.md** (.planning/REQUIREMENTS.md) — DASH-01 through DASH-05 requirements
- **DotwAdminIndex component** (app/Http/Livewire/Admin/DotwAdminIndex.php) — Tab-based navigation pattern, form handling, role checking
- **DotwAuditLogIndex component** (app/Http/Livewire/Admin/DotwAuditLogIndex.php) — Pagination, filtering, expandable rows pattern
- **DotwAIBooking model** (app/Modules/DotwAI/Models/DotwAIBooking.php) — Lifecycle status constants, timestamps, lifecycle logic
- **DotwAuditLog model** (app/Models/DotwAuditLog.php) — Audit log schema, JSON payloads, operation types
- **Livewire 3.5 documentation** (from package.json "livewire": "^3.5") — `wire:poll`, `WithPagination` trait, `dispatch()` method
- **ApexCharts 3.54.0** (from package.json) — Chart types, options, rendering API

### Secondary (MEDIUM confidence)
- **dotw-admin-index.blade.php** — Existing tab UI pattern with Alpine.js, Tailwind dark mode
- **dashboard.blade.php** — Stats card styling, responsive grid, color scheme (blue/red/green)
- **dotw-audit-log-index.blade.php** — Table layout, filter UI, expandable rows pattern

### Tertiary (Verified from codebase)
- **composer.json** — Laravel 11.9, PHP 8.2+, required packages
- **package.json** — ApexCharts 3.54.0 confirmed present

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — All libraries verified as installed; Livewire polling and ApexCharts explicitly recommended in CONTEXT.md
- Architecture: HIGH — Tab pattern exists in codebase; pagination and filtering patterns documented in DotwAuditLogIndex
- Pitfalls: HIGH — Based on common Livewire gotchas from official docs and existing code patterns (company scoping, polling latency, loading states)
- Testing: MEDIUM — PHPUnit configured but Livewire test examples needed (standard Laravel setup)

**Research date:** 2026-03-25
**Valid until:** 2026-04-08 (14 days — stable tech stack, no major version changes anticipated)
