<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Exceptions\Accounting\PeriodDependencyBlockedException;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Services\Accounting\PeriodCloseChecklistService;
use App\Services\Accounting\PeriodCloseService;
use App\Services\Accounting\YearEndCloseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C): "UI: period list with status/closed_by/at, close / soft-close /
 * lock / reopen with reason modal, checklist results panel, audit log." This controller is the
 * screen's HTTP surface; every action delegates to the SAME services the console commands
 * ({@see \App\Console\Commands\PeriodClose}/{@see \App\Console\Commands\YearClose}) use — no logic
 * is duplicated here.
 *
 * Company resolution follows the SAME convention `SettingController::getAccountingSettings()`
 * already established for an accounting screen: `getCompanyId(Auth::user())`, with an optional
 * `?company_id=` override for a Role::ADMIN switching context (identical fallback chain).
 */
class PeriodController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('view', AccountingPeriod::class);

        $companyId = $this->resolveCompanyId($request);

        if ($companyId === null) {
            abort(400, 'No company selected.');
        }

        $year = (int) $request->query('year', (int) now()->format('Y'));
        $isAnnual = (string) config('accounting.period.length', 'monthly') === 'annual';

        $periods = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = $isAnnual
            ? [AccountingPeriod::ANNUAL_MONTH]
            : range(1, 12);

        return view('accounting.periods.index', [
            'companyId' => $companyId,
            'year' => $year,
            'isAnnual' => $isAnnual,
            'months' => $months,
            'periods' => $periods,
            'canClose' => Gate::allows('close', AccountingPeriod::class),
            'canReopen' => Gate::allows('reopen', AccountingPeriod::class),
        ]);
    }

    public function checklist(Request $request, PeriodCloseChecklistService $checklist): JsonResponse
    {
        Gate::authorize('view', AccountingPeriod::class);

        [$companyId, $year, $month] = $this->resolvePeriodInput($request);

        return response()->json(['success' => true, 'checklist' => $checklist->run($companyId, $year, $month)]);
    }

    public function close(Request $request, PeriodCloseService $service): JsonResponse
    {
        Gate::authorize('close', AccountingPeriod::class);

        [$companyId, $year, $month] = $this->resolvePeriodInput($request);

        $status = $request->input('status');
        if (! in_array($status, [AccountingPeriod::STATUS_SOFT_CLOSED, AccountingPeriod::STATUS_LOCKED], true)) {
            return response()->json(['success' => false, 'message' => "status must be 'soft_closed' or 'locked'."], 422);
        }

        $result = $service->close($companyId, $year, $month, $status, Auth::id());

        if (! $result['applied']) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist has blocking issues; period was not closed.',
                'checklist' => $result['checklist'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'period' => $result['period'],
            'checklist' => $result['checklist'],
        ]);
    }

    public function reopen(Request $request, PeriodCloseService $service): JsonResponse
    {
        Gate::authorize('reopen', AccountingPeriod::class);

        [$companyId, $year, $month] = $this->resolvePeriodInput($request);
        $reason = (string) $request->input('reason', '');

        try {
            $period = $service->reopen($companyId, $year, $month, (int) Auth::id(), $reason);
        } catch (PeriodDependencyBlockedException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'period' => $period]);
    }

    public function closeYear(Request $request, YearEndCloseService $service): JsonResponse
    {
        Gate::authorize('close', AccountingPeriod::class);

        $companyId = $this->resolveCompanyId($request);
        if ($companyId === null) {
            return response()->json(['success' => false, 'message' => 'No company selected.'], 400);
        }

        $year = (int) $request->input('year');

        $result = $service->run($companyId, $year, Auth::id());

        if (! $result['success']) {
            return response()->json(['success' => false, 'message' => 'Year-end close refused.', 'blocking' => $result['blocking']], 422);
        }

        return response()->json([
            'success' => true,
            'already_closed' => $result['already_closed'],
            'net_profit' => $result['net_profit'],
            'transaction_id' => $result['transaction']?->id,
        ]);
    }

    /** @return array{0: int, 1: int, 2: int} [companyId, year, month] */
    private function resolvePeriodInput(Request $request): array
    {
        $companyId = $this->resolveCompanyId($request);
        if ($companyId === null) {
            abort(400, 'No company selected.');
        }

        $year = (int) $request->input('year');
        $month = (int) $request->input('month', 0);

        return [$companyId, $year, $month];
    }

    private function resolveCompanyId(Request $request): ?int
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $queryCompanyId = $request->input('company_id');
        if ($queryCompanyId !== null && ($user->hasRole('admin') || $user->role_id === \App\Models\Role::ADMIN)) {
            return (int) $queryCompanyId;
        }

        return getCompanyId($user);
    }
}
