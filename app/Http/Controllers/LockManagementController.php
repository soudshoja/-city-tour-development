<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Invoice;
use App\Models\Company;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class LockManagementController extends Controller
{
    /**
     * Record type configuration — single source of truth.
     * Each model MUST use the Lockable trait and define getLockCascadeMap().
     * Cascade is handled automatically by the trait.
     */
    private function getRecordTypes(): array
    {
        return [
            'invoices' => [
                'label' => 'Invoices',
                'model' => Invoice::class,
                'date_column' => 'invoice_date',
                'number_column' => 'invoice_number',
                'scope_column' => 'agent_id',
                'scope_type' => 'agent',
                'icon' => 'invoice',
                'color' => 'blue',
                'has_status' => true,
                'status_column' => 'status',
                'statuses' => ['paid', 'unpaid', 'partial'],
                'detail_route' => 'invoice.details',
            ],
            // 'payments' => [
            //     'label' => 'Payments',
            //     'model' => Payment::class,
            //     'date_column' => 'payment_date',
            //     'number_column' => 'voucher_number',
            //     'scope_column' => 'agent_id',
            //     'scope_type' => 'agent',
            //     'icon' => 'payment',
            //     'color' => 'green',
            //     'has_status' => false,
            //     'detail_route' => null,
            // ],
        ];
    }

    /**
     * Get scoped query for a record type
     */
    private function scopedQuery(array $config, int $companyId, array $agentIds)
    {
        $query = $config['model']::query();

        if ($config['scope_type'] === 'agent') {
            $query->whereIn($config['scope_column'], $agentIds);
        } else {
            $query->where($config['scope_column'], $companyId);
        }

        return $query;
    }

    public function index(Request $request)
    {
        Gate::authorize('manageLocks', User::class);
        $user = Auth::user();

        $companyId = getCompanyId($user);
        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company from the sidebar first.');
        }

        $company = Company::find($companyId);
        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id')->toArray();

        $recordTypes = $this->getRecordTypes();

        // Build stats for each record type
        $stats = [];
        foreach ($recordTypes as $key => $config) {
            $baseQuery = $this->scopedQuery($config, $companyId, $agentIds);
            $total = (clone $baseQuery)->count();
            $locked = (clone $baseQuery)->where('is_locked', true)->count();

            $stats[$key] = [
                'label' => $config['label'],
                'color' => $config['color'],
                'icon' => $config['icon'],
                'total' => $total,
                'locked' => $locked,
                'unlocked' => $total - $locked,
                'percentage' => $total > 0 ? round(($locked / $total) * 100) : 0,
            ];
        }

        // Monthly summary across all record types
        $monthlySummary = [];
        foreach ($recordTypes as $key => $config) {
            $dateCol = $config['date_column'];
            $rows = $this->scopedQuery($config, $companyId, $agentIds)
                ->select(
                    DB::raw("DATE_FORMAT({$dateCol}, '%Y-%m') as month"),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked'),
                    DB::raw('SUM(CASE WHEN is_locked = 0 THEN 1 ELSE 0 END) as unlocked'),
                )
                ->whereNotNull($dateCol)
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            foreach ($rows as $month => $data) {
                if (!isset($monthlySummary[$month])) {
                    $monthlySummary[$month] = [
                        'month' => $month,
                        'types' => [],
                        'total' => 0,
                        'locked' => 0,
                        'unlocked' => 0,
                    ];
                }
                $monthlySummary[$month]['types'][$key] = [
                    'label' => $config['label'],
                    'color' => $config['color'],
                    'total' => $data->total,
                    'locked' => $data->locked,
                    'unlocked' => $data->unlocked,
                ];
                $monthlySummary[$month]['total'] += $data->total;
                $monthlySummary[$month]['locked'] += $data->locked;
                $monthlySummary[$month]['unlocked'] += $data->unlocked;
            }
        }

        // Sort by month descending
        krsort($monthlySummary);

        // Paginate — 10 months per page
        $page = $request->input('page', 1);
        $perPage = 10;
        $allMonths = collect($monthlySummary);
        $paginatedMonths = new LengthAwarePaginator(
            $allMonths->forPage($page, $perPage),
            $allMonths->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('lock-management.index', compact(
            'company',
            'companyId',
            'stats',
            'paginatedMonths',
            'recordTypes',
        ));
    }

    /**
     * Lock by period — supports multiple record types (Bulk Lock modal).
     * Cascade is handled automatically by each model's Lockable trait.
     */
    public function lockByPeriod(Request $request)
    {
        Gate::authorize('manageLocks', User::class);
        $user = Auth::user();

        $request->validate([
            'lock_from_date' => 'required|date',
            'lock_to_date' => 'required|date|after_or_equal:lock_from_date',
            'record_types' => 'required|array|min:1',
            'record_types.*' => 'string',
            'lock_status' => 'nullable|array',
            'lock_status.*' => 'in:paid,unpaid,partial',
        ]);

        $companyId = getCompanyId($user);
        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id')->toArray();
        $lockFromDate = Carbon::parse($request->lock_from_date)->startOfDay();
        $lockToDate = Carbon::parse($request->lock_to_date)->endOfDay();
        $allTypes = $this->getRecordTypes();
        $results = [];

        foreach ($request->record_types as $typeKey) {
            if (!isset($allTypes[$typeKey])) continue;

            $config = $allTypes[$typeKey];
            $query = $this->scopedQuery($config, $companyId, $agentIds)
                ->where('is_locked', false)
                ->whereBetween($config['date_column'], [$lockFromDate, $lockToDate]);

            if (!empty($config['has_status']) && $request->has('lock_status')) {
                $query->whereIn($config['status_column'], $request->lock_status);
            }

            $count = $config['model']::bulkLock($query, $user->id);
            $results[$typeKey] = $count;
        }

        $totalLocked = array_sum($results);
        $summary = collect($results)
            ->filter(fn($c) => $c > 0)
            ->map(fn($c, $k) => $allTypes[$k]['label'] . ": {$c}")
            ->implode(', ');

        Log::info('Period lock applied', [
            'locked_by' => $user->id,
            'company_id' => $companyId,
            'lock_from_date' => $lockFromDate,
            'lock_to_date' => $lockToDate,
            'results' => $results,
        ]);

        return redirect()->back()->with('success', "{$totalLocked} record(s) locked. {$summary}");
    }

    /**
     * Lock records for a specific month + specific record type.
     * Cascade handled by Lockable trait.
     */
    public function lockByMonth(Request $request)
    {
        Gate::authorize('manageLocks', User::class);
        $user = Auth::user();

        $request->validate([
            'month' => 'required|date_format:Y-m',
            'record_type' => 'required|string',
        ]);

        $companyId = getCompanyId($user);
        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id')->toArray();
        $startOfMonth = Carbon::parse($request->month . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($request->month . '-01')->endOfMonth();

        $allTypes = $this->getRecordTypes();
        $typeKey = $request->record_type;

        if (!isset($allTypes[$typeKey])) {
            return redirect()->back()->with('error', 'Invalid record type.');
        }

        $config = $allTypes[$typeKey];
        $query = $this->scopedQuery($config, $companyId, $agentIds)
            ->where('is_locked', false)
            ->whereBetween($config['date_column'], [$startOfMonth, $endOfMonth]);

        // Uses Lockable::bulkLock() — cascades automatically
        $count = $config['model']::bulkLock($query, $user->id);

        Log::info('Month type lock applied', [
            'locked_by' => $user->id,
            'company_id' => $companyId,
            'month' => $request->month,
            'record_type' => $typeKey,
            'count' => $count,
        ]);

        return redirect()->back()->with('success', "{$count} {$config['label']} locked for " . $startOfMonth->format('F Y') . ".");
    }

    /**
     * Unlock records for a specific month + specific record type.
     *
     * R7 fix (P2-EXIT-REPORT.md §7 residual register; `p2_5/p25e-verify.md` finding 1): this
     * method used to call {@see \App\Http\Traits\Lockable::bulkUnlock()} directly — a raw
     * `is_locked=false` mass-update with NO dependency-chain check
     * ({@see \App\Http\Traits\Lockable::unlockBlockers()}) and NO `accounting.record.unlock`
     * permission check, gated only by the pre-existing `Gate::authorize('manageLocks', ...)`
     * above and a free-text `reason` that was logged but never validated against anything. That
     * routed around every gate P2.5.E built (the same dependency-aware-unlock guarantee
     * {@see \App\Http\Controllers\InvoiceController}'s single-record `invoice.unlock` action
     * already enforces) for every record locked in the chosen month, in one click — including
     * invoices whose receipts are bank-reconciled.
     *
     * Now walks each matching locked record through {@see \App\Http\Traits\Lockable::unlock()}
     * individually instead: the SAME per-record authorization
     * ({@see \App\Http\Traits\Lockable::assertUnlockAuthorized()}) and dependency-chain check the
     * single-record path uses apply uniformly here too. `assertUnlockAuthorized()` is also
     * checked once up front (on a throwaway model instance — the method reads only `$user`, never
     * `$this`) so an unauthorized caller fails fast with one clear error instead of a
     * per-record exception storm. A record whose chain is not clear (e.g. a reconciled receipt,
     * a closed period) is left locked and reported back by number, never unlocked
     * unconditionally; cascade to related tables still runs per record via `unlock()`'s own
     * `applyCascade()` call, matching the single-record path exactly.
     */
    public function unlockByMonth(Request $request)
    {
        Gate::authorize('manageLocks', User::class);
        $user = Auth::user();

        $request->validate([
            'month' => 'required|date_format:Y-m',
            'record_type' => 'required|string',
            'reason' => 'required|string|max:255',
        ]);

        $companyId = getCompanyId($user);
        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id')->toArray();
        $startOfMonth = Carbon::parse($request->month . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($request->month . '-01')->endOfMonth();

        $allTypes = $this->getRecordTypes();
        $typeKey = $request->record_type;

        if (!isset($allTypes[$typeKey])) {
            return redirect()->back()->with('error', 'Invalid record type.');
        }

        $config = $allTypes[$typeKey];
        $modelClass = $config['model'];

        // Fail fast, once, before touching any record -- mirrors Lockable::unlock()'s own
        // ordering rationale ("an unauthorized caller should not learn anything about the
        // record's dependency chain before reaching it").
        (new $modelClass)->assertUnlockAuthorized($user);

        $query = $this->scopedQuery($config, $companyId, $agentIds)
            ->where('is_locked', true)
            ->whereBetween($config['date_column'], [$startOfMonth, $endOfMonth]);

        $numberColumn = $config['number_column'] ?? null;
        $unlockedCount = 0;
        $blockedIdentifiers = [];

        foreach ($query->get() as $record) {
            try {
                $record->unlock($request->reason, $user->id);
                $unlockedCount++;
            } catch (\App\Exceptions\Accounting\UnlockDependencyBlockedException $e) {
                $blockedIdentifiers[] = $numberColumn !== null
                    ? ($record->{$numberColumn} ?? ('#' . $record->getKey()))
                    : ('#' . $record->getKey());
            }
        }

        Log::info('Month type unlock applied', [
            'unlocked_by' => $user->id,
            'company_id' => $companyId,
            'month' => $request->month,
            'record_type' => $typeKey,
            'reason' => $request->reason,
            'count' => $unlockedCount,
            'blocked_count' => count($blockedIdentifiers),
            'blocked' => $blockedIdentifiers,
        ]);

        $message = "{$unlockedCount} {$config['label']} unlocked for " . $startOfMonth->format('F Y') . ".";

        if ($blockedIdentifiers !== []) {
            $message .= ' ' . count($blockedIdentifiers)
                . ' could not be unlocked (dependency not clear -- resolve individually): '
                . implode(', ', $blockedIdentifiers) . '.';

            return redirect()->back()->with('warning', $message);
        }

        return redirect()->back()->with('success', $message);
    }
}
