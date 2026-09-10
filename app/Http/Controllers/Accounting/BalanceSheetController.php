<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Report;
use App\Models\Role;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\LedgerSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CT-A6-4 — Balance Sheet screen ("Assets = Liabilities + Equity" as of a date). Did not exist
 * before this lane (CT-A2 §7.5 / CT-D1B §5.7, re-confirmed a third time: "no route containing
 * 'balance' beyond the trial balance"). One route, one read-layer service call
 * ({@see BalanceSheetService}), one plain server-rendered view — same shape as
 * {@see \App\Http\Controllers\Accounting\GeneralLedgerController} and
 * {@see \App\Http\Controllers\Accounting\EquityStatementController}.
 *
 * Authorization is `Gate::authorize('viewBalanceSheet', Report::class)` —
 * {@see \App\Policies\ReportPolicy::viewBalanceSheet()}.
 */
class BalanceSheetController extends Controller
{
    public function __construct(private readonly BalanceSheetService $balanceSheet, private readonly LedgerSource $ledgerSource) {}

    public function show(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewBalanceSheet', Report::class);

        $companyId = $this->resolveCompanyId($request);
        if ($companyId === null) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $asOf = Carbon::parse($request->input('as_of', now()->toDateString()));

        $sheet = $this->balanceSheet->generate($companyId, $asOf);

        return view('reports.balance-sheet', [
            'company' => Company::find($companyId),
            'sheet' => $sheet,
            'asOf' => $asOf->toDateString(),
            'transitionBanner' => $this->ledgerSource->transitionBanner($companyId),
        ]);
    }

    private function resolveCompanyId(Request $request): ?int
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $queryCompanyId = $request->input('company_id');
        if ($queryCompanyId !== null && ($user->hasRole('admin') || $user->role_id === Role::ADMIN)) {
            return (int) $queryCompanyId;
        }

        return getCompanyId($user);
    }
}
