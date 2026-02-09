<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Credit;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Company;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class LockManagementController extends Controller
{
    /**
     * Record type configuration — single source of truth
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
            // 'credits' => [
            //     'label' => 'Credits',
            //     'model' => Credit::class,
            //     'date_column' => 'created_at',
            //     'number_column' => 'id',
            //     'scope_column' => 'company_id',
            //     'scope_type' => 'company',
            //     'icon' => 'credit',
            //     'color' => 'purple',
            //     'has_status' => false,
            //     'detail_route' => null,
            // ],
            // 'journal_entries' => [
            //     'label' => 'Journal Entries',
            //     'model' => JournalEntry::class,
            //     'date_column' => 'transaction_date',
            //     'number_column' => 'voucher_number',
            //     'scope_column' => 'company_id',
            //     'scope_type' => 'company',
            //     'icon' => 'journal',
            //     'color' => 'indigo',
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
        Gate::authorize('manage', User::class);
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

        // Sort by month descending, limit to 12
        krsort($monthlySummary);
        $monthlySummary = array_slice($monthlySummary, 0, 12, true);

        return view('lock-management.index', compact(
            'company',
            'companyId',
            'stats',
            'monthlySummary',
            'recordTypes',
        ));
    }

    /**
     * Lock by period — supports multiple record types
     */
    public function lockByPeriod(Request $request)
    {
        Gate::authorize('manage', User::class);
        $user = Auth::user();

        $request->validate([
            'lock_before_date' => 'required|date',
            'record_types' => 'required|array|min:1',
            'record_types.*' => 'string',
            'lock_status' => 'nullable|array',
            'lock_status.*' => 'in:paid,unpaid,partial',
        ]);

        $companyId = getCompanyId($user);
        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id')->toArray();
        $lockBeforeDate = Carbon::parse($request->lock_before_date)->endOfDay();
        $allTypes = $this->getRecordTypes();
        $results = [];

        foreach ($request->record_types as $typeKey) {
            if (!isset($allTypes[$typeKey])) continue;

            $config = $allTypes[$typeKey];
            $query = $this->scopedQuery($config, $companyId, $agentIds)
                ->where('is_locked', false)
                ->where($config['date_column'], '<=', $lockBeforeDate);

            // Apply status filter only for models that have status
            if (!empty($config['has_status']) && $request->has('lock_status')) {
                $query->whereIn($config['status_column'], $request->lock_status);
            }

            $count = $query->update([
                'is_locked' => true,
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]);

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
            'lock_before_date' => $lockBeforeDate,
            'results' => $results,
        ]);

        return redirect()->back()->with('success', "{$totalLocked} record(s) locked. {$summary}");
    }

    /**
     * Lock all records for a specific month
     */
    public function lockByMonth(Request $request)
    {
        Gate::authorize('manage', User::class);
        $user = Auth::user();

        $request->validate([
            'month' => 'required|date_format:Y-m',
            'record_types' => 'nullable|array',
        ]);

        $companyId = getCompanyId($user);
        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id')->toArray();
        $startOfMonth = Carbon::parse($request->month . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($request->month . '-01')->endOfMonth();

        $allTypes = $this->getRecordTypes();
        $targetTypes = $request->input('record_types', array_keys($allTypes));
        $results = [];

        foreach ($targetTypes as $typeKey) {
            if (!isset($allTypes[$typeKey])) continue;
            $config = $allTypes[$typeKey];

            $count = $this->scopedQuery($config, $companyId, $agentIds)
                ->where('is_locked', false)
                ->whereBetween($config['date_column'], [$startOfMonth, $endOfMonth])
                ->update([
                    'is_locked' => true,
                    'locked_by' => $user->id,
                    'locked_at' => now(),
                ]);

            $results[$typeKey] = $count;
        }

        $totalLocked = array_sum($results);

        Log::info('Month lock applied', [
            'locked_by' => $user->id,
            'company_id' => $companyId,
            'month' => $request->month,
            'results' => $results,
        ]);

        return redirect()->back()->with('success', "{$totalLocked} record(s) locked for " . $startOfMonth->format('F Y') . ".");
    }

    /**
     * Unlock all records for a specific month
     */
    public function unlockByMonth(Request $request)
    {
        Gate::authorize('manage', User::class);
        $user = Auth::user();

        $request->validate([
            'month' => 'required|date_format:Y-m',
            'reason' => 'required|string|max:255',
            'record_types' => 'nullable|array',
        ]);

        $companyId = getCompanyId($user);
        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id')->toArray();
        $startOfMonth = Carbon::parse($request->month . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($request->month . '-01')->endOfMonth();

        $allTypes = $this->getRecordTypes();
        $targetTypes = $request->input('record_types', array_keys($allTypes));
        $results = [];

        foreach ($targetTypes as $typeKey) {
            if (!isset($allTypes[$typeKey])) continue;
            $config = $allTypes[$typeKey];

            $count = $this->scopedQuery($config, $companyId, $agentIds)
                ->where('is_locked', true)
                ->whereBetween($config['date_column'], [$startOfMonth, $endOfMonth])
                ->update([
                    'is_locked' => false,
                    'locked_by' => null,
                    'locked_at' => null,
                ]);

            $results[$typeKey] = $count;
        }

        $totalUnlocked = array_sum($results);

        Log::info('Month unlock applied', [
            'unlocked_by' => $user->id,
            'company_id' => $companyId,
            'month' => $request->month,
            'reason' => $request->reason,
            'results' => $results,
        ]);

        return redirect()->back()->with('success', "{$totalUnlocked} record(s) unlocked for " . $startOfMonth->format('F Y') . ".");
    }
}
