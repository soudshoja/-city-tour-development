<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Jobs\RunReconciliationAutoMatchJob;
use App\Models\Account;
use App\Models\Branch;
use App\Models\GatewaySettlement;
use App\Models\ReconciliationFixDraft;
use App\Models\ReconciliationProposal;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierStatementImport;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\GatewaySettlementService;
use App\Services\Accounting\ReconciliationCenterService;
use App\Services\Accounting\ReconciliationFixDraftService;
use App\Services\Accounting\ReconciliationProposalService;
use App\Services\Accounting\Reconciliation\SupplierStatementImportInput;
use App\Services\Accounting\Reconciliation\SupplierStatementImportRejected;
use App\Services\Accounting\Reconciliation\SupplierStatementImporter;
use App\Services\Accounting\Reconciliation\SupplierStatementMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G) — the Reconciliation Center v0 screen's HTTP surface. Every
 * action delegates to the service layer ({@see ReconciliationCenterService},
 * {@see ReconciliationProposalService}, {@see ReconciliationFixDraftService}) — no ledger/audit
 * logic lives here. Company resolution mirrors {@see PeriodController}'s own convention
 * (`getCompanyId($user)`, with an admin-only `?company_id=` override).
 */
class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('view', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        return view('accounting.reconciliation.index', [
            'companyId' => $companyId,
            'canManage' => Gate::allows('manage', ReconciliationProposal::class),
            'gateways' => (array) config('accounting.purpose_codes.gateways', []),
        ]);
    }

    public function grid(Request $request, ReconciliationCenterService $center): JsonResponse
    {
        Gate::authorize('view', ReconciliationProposal::class);

        [$companyId, $asOf, $mode] = $this->resolveGridInput($request);

        return response()->json(['success' => true, 'grid' => $center->grid($companyId, $asOf, $mode)]);
    }

    public function rowDetail(Request $request, string $rowKey, ReconciliationCenterService $center): JsonResponse
    {
        Gate::authorize('view', ReconciliationProposal::class);

        [$companyId, $asOf, $mode] = $this->resolveGridInput($request);

        $grid = $center->grid($companyId, $asOf, $mode);
        $row = collect($grid['rows'])->firstWhere('key', $rowKey);
        if ($row === null) {
            return response()->json(['success' => false, 'message' => 'Unknown row.'], 404);
        }

        $accountIds = $row['account_ids'];

        $isReviewOnly = $row['group'] === ReconciliationCenterService::GROUP_REVIEW_ONLY;

        return response()->json([
            'success' => true,
            'row' => $row,
            'proposals' => $center->proposalsFor($companyId, $accountIds),
            // Recently-APPROVED proposals (most-recent-first, capped) — this is the only place a
            // reconciled line's `book_journal_entry_id` is exposed to the Unmatch action, since
            // drill-down panel (2) only ever lists still-UNMATCHED lines by design.
            'recently_matched' => $isReviewOnly ? collect() : $center->proposalsFor($companyId, $accountIds, ReconciliationProposal::STATUS_APPROVED)->take(20)->values(),
            'unmatched' => $isReviewOnly ? ['items' => [], 'buckets' => []] : $center->unmatchedFor($companyId, $accountIds, $row['group'], $asOf),
            'history' => $center->historyFor($companyId, $accountIds),
            'gap_explanation' => $isReviewOnly ? null : $center->explainGap($companyId, $row, $asOf),
        ]);
    }

    public function approveProposal(Request $request, ReconciliationProposal $proposal, ReconciliationProposalService $service): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $proposal = $service->approve($proposal, Auth::user());

        return response()->json(['success' => true, 'proposal' => $proposal]);
    }

    public function rejectProposal(Request $request, ReconciliationProposal $proposal, ReconciliationProposalService $service): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);
        $proposal = $service->reject($proposal, $data['reason'], Auth::user());

        return response()->json(['success' => true, 'proposal' => $proposal]);
    }

    public function manualMatch(Request $request, ReconciliationProposalService $service): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'journal_entry_id' => ['required', 'integer'],
            'matched_journal_entry_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'min:1'],
        ]);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $proposal = $service->manualMatch(
            $companyId,
            (int) $data['account_id'],
            (int) $data['journal_entry_id'],
            isset($data['matched_journal_entry_id']) ? (int) $data['matched_journal_entry_id'] : null,
            $data['reason'],
            Auth::user(),
        );

        return response()->json(['success' => true, 'proposal' => $proposal]);
    }

    public function manualUnmatch(Request $request, ReconciliationProposalService $service): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $data = $request->validate([
            'journal_entry_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:1'],
        ]);

        try {
            $line = $service->manualUnmatch((int) $data['journal_entry_id'], $data['reason'], Auth::user());
        } catch (\App\Exceptions\Accounting\ReconciliationPeriodLockedException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json(['success' => true, 'journal_entry' => $line]);
    }

    public function createFixDraft(Request $request, ReconciliationFixDraftService $service): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'kind' => ['required', 'string', 'in:'.implode(',', \App\Services\Accounting\ReconciliationFixDraftService::KNOWN_KINDS)],
            'amount' => ['required', 'numeric'],
            'narration' => ['required', 'string', 'min:1'],
            'proposal_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $branchId = $data['branch_id'] ?? $this->resolveBranchId($companyId);
        abort_if($branchId === null, 422, 'No branch available to attach this draft to.');

        $draft = $service->create(
            $companyId,
            (int) $branchId,
            (int) $data['account_id'],
            $data['kind'],
            (float) $data['amount'],
            $data['narration'],
            isset($data['proposal_id']) ? (int) $data['proposal_id'] : null,
            Auth::user(),
        );

        return response()->json(['success' => true, 'fix_draft' => $draft], 201);
    }

    public function postFixDraft(Request $request, ReconciliationFixDraft $fixDraft, ReconciliationFixDraftService $service): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        try {
            $draft = $service->post($fixDraft, Auth::user());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'fix_draft' => $draft]);
    }

    public function discardFixDraft(Request $request, ReconciliationFixDraft $fixDraft, ReconciliationFixDraftService $service): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);
        $draft = $service->discard($fixDraft, $data['reason'], Auth::user());

        return response()->json(['success' => true, 'fix_draft' => $draft]);
    }

    public function runNow(Request $request): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        RunReconciliationAutoMatchJob::dispatch($companyId, Auth::id());

        return response()->json(['success' => true, 'message' => 'Reconciliation run queued.']);
    }

    public function runStatus(Request $request, ReconciliationCenterService $center): JsonResponse
    {
        Gate::authorize('view', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        return response()->json(['success' => true, 'run_status' => $center->runStatus($companyId)]);
    }

    /**
     * accounting-builds T8 (Lane E) — DOTW supplier-statement reconciliation tab. Import form +
     * past-imports list on its own page (reachable from the Reconciliation Center screen).
     */
    public function supplierStatements(Request $request): View
    {
        Gate::authorize('view', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $imports = SupplierStatementImport::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->latest('id')
            ->limit(50)
            ->get();

        $suppliers = Supplier::query()
            ->activeForCompany($companyId)
            ->orderBy('name')
            ->get(['suppliers.id', 'suppliers.name']);

        return view('accounting.reconciliation.supplier-statements', [
            'companyId' => $companyId,
            'canManage' => Gate::allows('manage', ReconciliationProposal::class),
            'imports' => $imports,
            'suppliers' => $suppliers,
            'defaultColumns' => (array) config('accounting.supplier_statements.dotw.columns', []),
        ]);
    }

    public function importSupplierStatement(Request $request, SupplierStatementImporter $importer): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'supplier_id' => ['required', 'integer'],
            'statement_currency' => ['required', 'string', 'size:3'],
            'statement_reference' => ['nullable', 'string', 'max:160'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
            'column_map' => ['nullable', 'array'],
        ], [
            'file.mimes' => 'The statement must be a CSV or Excel (.xlsx/.xls) file.',
            'file.max' => 'The file must not exceed 10MB.',
        ]);

        $file = $request->file('file');

        try {
            $import = $importer->import(new SupplierStatementImportInput(
                companyId: $companyId,
                supplierId: (int) $data['supplier_id'],
                absoluteFilePath: $file->getRealPath(),
                fileName: $file->getClientOriginalName(),
                statementCurrency: $data['statement_currency'],
                columnMapOverride: $data['column_map'] ?? null,
                statementReference: $data['statement_reference'] ?? null,
                periodFrom: $data['period_from'] ?? null,
                periodTo: $data['period_to'] ?? null,
                importedBy: Auth::id(),
            ));
        } catch (SupplierStatementImportRejected $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'import' => $import->fresh(['lines'])], 201);
    }

    public function matchSupplierStatement(Request $request, SupplierStatementImport $supplierStatementImport, SupplierStatementMatcher $matcher): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');
        abort_if((int) $supplierStatementImport->company_id !== $companyId, 403, 'Statement import belongs to a different company.');

        $result = $matcher->match($supplierStatementImport);

        return response()->json(['success' => true, 'result' => $result->toArray(), 'import' => $supplierStatementImport->fresh()]);
    }

    public function supplierStatementExceptions(Request $request, SupplierStatementImport $supplierStatementImport, SupplierStatementMatcher $matcher): JsonResponse
    {
        Gate::authorize('view', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');
        abort_if((int) $supplierStatementImport->company_id !== $companyId, 403, 'Statement import belongs to a different company.');

        $exceptions = $matcher->exceptionsFor($supplierStatementImport);

        return response()->json([
            'success' => true,
            'import' => $supplierStatementImport,
            'matched' => $supplierStatementImport->lines()->where('state', 'matched')->orderBy('row_no')->get(),
            'unmatched_statement' => $exceptions['unmatched_statement']->values(),
            'disputed' => $exceptions['disputed']->values(),
            'unmatched_ledger' => $exceptions['unmatched_ledger']->values(),
        ]);
    }

    /**
     * accounting-builds T7 (Lane D): the "Record settlement" panel's bank-account picker — every
     * leaf a settlement's `bank_account_id` could actually pass
     * {@see \App\Services\Accounting\AccountResolver::assertUnderBankGroup()} for.
     */
    public function bankAccounts(Request $request, AccountResolver $resolver): JsonResponse
    {
        Gate::authorize('view', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $accounts = Account::withoutGlobalScopes()
            ->whereIn('id', $resolver->bankLeafIds($companyId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json(['success' => true, 'bank_accounts' => $accounts]);
    }

    /**
     * accounting-builds T7 (Lane D): the settlements list feeding the "Record settlement" panel's
     * own table — most-recent-first, capped, same shape convention as
     * {@see self::rowDetail()}'s own `recently_matched` slice.
     */
    public function settlements(Request $request): JsonResponse
    {
        Gate::authorize('view', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $settlements = GatewaySettlement::forCompany($companyId)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'settlements' => $settlements]);
    }

    /**
     * accounting-builds T7 (Lane D): records a real gateway payout and (engine permitting) posts
     * its ledger movement — see {@see \App\Services\Accounting\GatewaySettlementService::record()}
     * for the full contract. A validation/idempotency-conflict failure comes back as a 422 with
     * the exception's own message (never a 500 — this is caller-fixable input, not a server
     * fault), matching {@see self::postFixDraft()}'s own error-shape convention.
     */
    public function recordSettlement(Request $request, GatewaySettlementService $service): JsonResponse
    {
        Gate::authorize('manage', ReconciliationProposal::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $data = $request->validate([
            'gateway' => ['required', 'string'],
            'payout_reference' => ['required', 'string', 'max:100'],
            'payout_date' => ['required', 'date'],
            'gross' => ['required', 'numeric'],
            'fee' => ['required', 'numeric'],
            'net' => ['required', 'numeric'],
            'bank_account_id' => ['required', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'settlement_channel' => ['nullable', 'string', 'max:24'],
            'recognised_fee' => ['nullable', 'numeric'],
        ]);

        try {
            $settlement = $service->record(
                companyId: $companyId,
                gateway: $data['gateway'],
                payoutReference: $data['payout_reference'],
                payoutDate: Carbon::parse($data['payout_date']),
                gross: (float) $data['gross'],
                fee: (float) $data['fee'],
                net: (float) $data['net'],
                bankAccountId: (int) $data['bank_account_id'],
                currency: $data['currency'] ?? null,
                settlementChannel: $data['settlement_channel'] ?? null,
                recognisedFee: (float) ($data['recognised_fee'] ?? 0),
                source: GatewaySettlement::SOURCE_MANUAL,
                actor: Auth::user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'settlement' => $settlement], 201);
    }

    /** @return array{0:int,1:Carbon,2:string} */
    private function resolveGridInput(Request $request): array
    {
        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $asOf = $request->filled('date') ? Carbon::parse((string) $request->input('date')) : now();
        $mode = $request->input('mode', 'day') === 'month' ? 'month' : 'day';

        return [$companyId, $asOf, $mode];
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

    private function resolveBranchId(int $companyId): ?int
    {
        $user = Auth::user();
        $userBranchId = $user?->branch?->id;
        if ($userBranchId !== null) {
            return (int) $userBranchId;
        }

        return Branch::where('company_id', $companyId)->value('id');
    }
}
