<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\Company;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LockManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::ACCOUNTANT])) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company from the sidebar first.');
        }

        $company = Company::find($companyId);
        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))
            ->pluck('id');

        // Stats
        $totalInvoices = Invoice::whereIn('agent_id', $agentIds)->count();
        $lockedInvoices = Invoice::whereIn('agent_id', $agentIds)->where('is_locked', true)->count();
        $unlockedInvoices = $totalInvoices - $lockedInvoices;
        $paidUnlocked = Invoice::whereIn('agent_id', $agentIds)
            ->where('is_locked', false)
            ->where('status', 'paid')
            ->count();

        // Get locked invoices list
        $filter = $request->input('filter', 'locked'); // locked, unlocked, all
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $invoicesQuery = Invoice::with(['agent.branch', 'client', 'lockedByUser'])
            ->whereIn('agent_id', $agentIds);

        if ($filter === 'locked') {
            $invoicesQuery->where('is_locked', true);
        } elseif ($filter === 'unlocked') {
            $invoicesQuery->where('is_locked', false);
        }

        if ($search) {
            $invoicesQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($dateFrom) {
            $invoicesQuery->where('invoice_date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $invoicesQuery->where('invoice_date', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $invoices = $invoicesQuery->orderBy('invoice_date', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Monthly summary for period locking
        $monthlySummary = Invoice::whereIn('agent_id', $agentIds)
            ->select(
                DB::raw("DATE_FORMAT(invoice_date, '%Y-%m') as month"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked'),
                DB::raw('SUM(CASE WHEN is_locked = 0 THEN 1 ELSE 0 END) as unlocked'),
                DB::raw('SUM(CASE WHEN status = "paid" AND is_locked = 0 THEN 1 ELSE 0 END) as paid_unlocked'),
                DB::raw('SUM(amount) as total_amount'),
            )
            ->whereNotNull('invoice_date')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return view('lock-management.index', compact(
            'company',
            'companyId',
            'totalInvoices',
            'lockedInvoices',
            'unlockedInvoices',
            'paidUnlocked',
            'invoices',
            'monthlySummary',
            'filter',
        ));
    }

    /**
     * Lock all invoices before a specific date
     */
    public function lockByPeriod(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::ACCOUNTANT])) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'lock_before_date' => 'required|date',
            'lock_status' => 'nullable|array',
            'lock_status.*' => 'in:paid,unpaid,partial',
        ]);

        $companyId = getCompanyId($user);
        $lockBeforeDate = Carbon::parse($request->lock_before_date)->endOfDay();
        $statuses = $request->input('lock_status', ['paid']); // default lock only paid

        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))
            ->pluck('id');

        $count = Invoice::whereIn('agent_id', $agentIds)
            ->where('is_locked', false)
            ->where('invoice_date', '<=', $lockBeforeDate)
            ->whereIn('status', $statuses)
            ->update([
                'is_locked' => true,
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]);

        Log::info('Period lock applied', [
            'locked_by' => $user->id,
            'company_id' => $companyId,
            'lock_before_date' => $lockBeforeDate,
            'statuses' => $statuses,
            'invoices_locked' => $count,
        ]);

        return redirect()->back()->with('success', "{$count} invoice(s) locked successfully (before {$request->lock_before_date}).");
    }

    /**
     * Lock all invoices in a specific month
     */
    public function lockByMonth(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::ACCOUNTANT])) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $companyId = getCompanyId($user);
        $startOfMonth = Carbon::parse($request->month . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($request->month . '-01')->endOfMonth();

        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))
            ->pluck('id');

        $count = Invoice::whereIn('agent_id', $agentIds)
            ->where('is_locked', false)
            ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
            ->update([
                'is_locked' => true,
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]);

        Log::info('Month lock applied', [
            'locked_by' => $user->id,
            'company_id' => $companyId,
            'month' => $request->month,
            'invoices_locked' => $count,
        ]);

        return redirect()->back()->with('success', "{$count} invoice(s) locked for {$startOfMonth->format('F Y')}.");
    }

    /**
     * Unlock all invoices in a specific month
     */
    public function unlockByMonth(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::ACCOUNTANT])) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'month' => 'required|date_format:Y-m',
            'reason' => 'required|string|max:255',
        ]);

        $companyId = getCompanyId($user);
        $startOfMonth = Carbon::parse($request->month . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($request->month . '-01')->endOfMonth();

        $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))
            ->pluck('id');

        $count = Invoice::whereIn('agent_id', $agentIds)
            ->where('is_locked', true)
            ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
            ->update([
                'is_locked' => false,
                'locked_by' => null,
                'locked_at' => null,
            ]);

        Log::info('Month unlock applied', [
            'unlocked_by' => $user->id,
            'company_id' => $companyId,
            'month' => $request->month,
            'reason' => $request->reason,
            'invoices_unlocked' => $count,
        ]);

        return redirect()->back()->with('success', "{$count} invoice(s) unlocked for " . $startOfMonth->format('F Y') . ".");
    }

    /**
     * Bulk lock selected invoices
     */
    public function bulkLock(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::ACCOUNTANT])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:invoices,id',
        ]);

        $count = Invoice::whereIn('id', $request->invoice_ids)
            ->where('is_locked', false)
            ->update([
                'is_locked' => true,
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]);

        Log::info('Bulk lock applied', [
            'locked_by' => $user->id,
            'invoice_ids' => $request->invoice_ids,
            'invoices_locked' => $count,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$count} invoice(s) locked successfully.",
        ]);
    }

    /**
     * Bulk unlock selected invoices
     */
    public function bulkUnlock(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::ACCOUNTANT])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:invoices,id',
            'reason' => 'required|string|max:255',
        ]);

        $count = Invoice::whereIn('id', $request->invoice_ids)
            ->where('is_locked', true)
            ->update([
                'is_locked' => false,
                'locked_by' => null,
                'locked_at' => null,
            ]);

        Log::info('Bulk unlock applied', [
            'unlocked_by' => $user->id,
            'invoice_ids' => $request->invoice_ids,
            'reason' => $request->reason,
            'invoices_unlocked' => $count,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$count} invoice(s) unlocked successfully.",
        ]);
    }
}
