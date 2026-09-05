<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use App\Services\Accounting\Reports\EquityChangesReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * accounting-builds T6 (L10): Statement of Changes in Equity screen. One route, one read-layer
 * service call, one plain server-rendered view — no Livewire, no new UI framework, same "report
 * table" shape as {@see \App\Http\Controllers\ReportController::trialBalance()} and
 * {@see \App\Http\Controllers\Accounting\StatementController}.
 *
 * Authorization mirrors ReportController::trialBalance()'s own existing gate for the accounting
 * report family (role_id in ADMIN/COMPANY/ACCOUNTANT) rather than inventing a new Policy for a
 * single report — every other financial report in this codebase (trial balance, P&L, creditors)
 * gates the identical way; a Gate::authorize('view', ...) against a dedicated model would be
 * inconsistent with its own siblings for no reason. Company resolution follows the SAME
 * `resolveCompanyId()` convention {@see PeriodController} already established: `getCompanyId(Auth
 * ::user())`, with an optional `?company_id=` override for Role::ADMIN switching context.
 */
class EquityStatementController extends Controller
{
    public function __construct(private readonly EquityChangesReportService $equityChanges) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        if (! in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)) {
            abort(403, 'Unauthorized action.');
        }

        $companyId = $this->resolveCompanyId($request);
        if ($companyId === null) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $year = (int) $request->input('year', (int) now()->format('Y'));

        $statement = $this->equityChanges->generate($companyId, $year);

        return view('accounting.reports.equity-changes', [
            'company' => Company::find($companyId),
            'year' => $year,
            'statement' => $statement,
        ]);
    }

    public function export(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)) {
            abort(403, 'Unauthorized action.');
        }

        $companyId = $this->resolveCompanyId($request);
        if ($companyId === null) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $year = (int) $request->input('year', (int) now()->format('Y'));
        $company = Company::find($companyId);
        $statement = $this->equityChanges->generate($companyId, $year);

        // Same shape as ReportController::trialBalanceExport() — a hand-built CSV string, no
        // package dependency, matching this codebase's existing convention for report exports.
        $csv = "Statement of Changes in Equity\n";
        $csv .= 'Company: '.($company->name ?? '')."\n";
        $csv .= 'Fiscal Year: '.$year."\n";
        $csv .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n\n";
        $csv .= "Component,Code,Opening,Movement,Closing\n";

        foreach ($statement['components'] as $component) {
            $csv .= '"'.addslashes($component['name']).'","'.$component['code'].'","'
                .number_format($component['opening'], 3).'","'
                .number_format($component['movement'], 3).'","'
                .number_format($component['closing'], 3)."\"\n";
        }

        $csv .= "\n";
        $csv .= 'Net profit for the year,,,,"'.number_format($statement['net_profit'], 3)."\"\n";
        $csv .= 'Dividends paid this year,,,,"'.number_format($statement['dividends_paid_this_year'], 3)."\"\n";
        $csv .= 'Opening equity total,,"'.number_format($statement['opening_equity_total'], 3)."\",,\n";
        $csv .= 'Closing equity total,,,,"'.number_format($statement['closing_equity_total'], 3)."\"\n";
        $csv .= 'Ties to next-year opening,,,,'.($statement['checks']['ties_to_next_year_opening'] ? 'YES' : 'NO')."\n";

        $filename = 'equity-changes-'.$year.'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
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
