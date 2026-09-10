<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Company;
use App\Models\Report;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\LedgerSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CT-A6-3 — per-account general ledger screen: opening balance, every posted line inside a period
 * with a running balance, closing balance, and (per CT-A6-2) a transition banner when this
 * company's engine ledger and legacy ledger both still carry rows. One route, one read-layer
 * service call ({@see GeneralLedgerService}), one plain server-rendered view — same "report table"
 * shape as {@see \App\Http\Controllers\Accounting\EquityStatementController}.
 *
 * Authorization is `Gate::authorize('viewGeneralLedger', Report::class)` —
 * {@see \App\Policies\ReportPolicy::viewGeneralLedger()} — the same Spatie-permission-string
 * convention `viewProfitLoss()`/`viewCreditors()` already use on this exact Policy, rather than the
 * older inline role_id check some other report actions on {@see \App\Http\Controllers\
 * ReportController} still carry.
 */
class GeneralLedgerController extends Controller
{
    public function __construct(private readonly GeneralLedgerService $generalLedger, private readonly LedgerSource $ledgerSource) {}

    public function show(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewGeneralLedger', Report::class);

        $companyId = $this->resolveCompanyId($request);
        if ($companyId === null) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $accountId = (int) $request->input('account_id');
        if ($accountId <= 0) {
            return redirect()->back()->with('error', 'Please select an account.');
        }

        $account = Account::withoutGlobalScopes()->where('company_id', $companyId)->find($accountId);
        if ($account === null) {
            return redirect()->back()->with('error', 'Account not found for this company.');
        }

        $dateFrom = Carbon::parse($request->input('date_from', now()->startOfMonth()->toDateString()));
        $dateTo = Carbon::parse($request->input('date_to', now()->toDateString()));

        $ledger = $this->generalLedger->generate($companyId, $accountId, $dateFrom, $dateTo);

        return view('reports.general-ledger', [
            'company' => Company::find($companyId),
            'ledger' => $ledger,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'accounts' => Account::withoutGlobalScopes()->where('company_id', $companyId)->whereDoesntHave('children')->orderBy('code')->get(),
            'transitionBanner' => $this->ledgerSource->transitionBanner($companyId),
        ]);
    }

    /**
     * Same convention {@see EquityStatementController::resolveCompanyId()} already established:
     * `getCompanyId(Auth::user())`, with an optional `?company_id=` override for Role::ADMIN
     * switching context.
     */
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
