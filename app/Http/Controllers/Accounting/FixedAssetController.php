<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Exceptions\Accounting\PostingException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\Role;
use App\Models\Supplier;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\FixedAssets\DepreciationRunService;
use App\Services\Accounting\FixedAssets\FixedAssetService;
use App\Services\Accounting\PeriodGuard;
use App\Services\Accounting\PostingSeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * accounting-builds T10 (Lane G): the fixed-asset register's HTTP surface — register list
 * (with filters + NBV/totals), create/edit forms, the asset detail page (derived schedule,
 * posted months + document links, disposal panel), and the depreciation-run action
 * (dry-run preview + real post). Every ledger-affecting action delegates to
 * {@see FixedAssetService} / {@see DepreciationRunService} ONLY — no `PostingSeam`/journal write
 * happens in this file.
 *
 * ── Hard constraints carried over from the Lane B sign-off (T2-T4 review packet §12, notes
 *    D/F/G) — every one of these is enforced in this controller, not merely in the service:
 *
 *   1. The `status` column is NEVER exposed as a form input and NEVER accepted from the request.
 *      {@see self::rejectStatusInput()} 422s the WHOLE request the instant a `status` key is
 *      present on store/update, rather than silently stripping it — status transitions happen
 *      ONLY through {@see FixedAssetService::capitalise()} and {@see FixedAssetService::dispose()}
 *      (note F: "T10 must not expose `status` as an editable input").
 *   2. Fields frozen once depreciation has posted (cost, salvage, useful_life_months,
 *      in_service_date, asset_class, method — {@see FixedAssetService::assertBasisNotFrozen()},
 *      rule D4) are rendered read-only in the edit form (`basisFrozen` passed to the view) AND
 *      still rejected server-side by the service itself if submitted changed — this controller
 *      does not duplicate that check, it only surfaces the service's own
 *      `\InvalidArgumentException` as a friendly validation error.
 *   3. Disposal is its own guarded action: a dedicated POST endpoint
 *      (`accounting/fixed-assets/{fixedAsset}/dispose`), never folded into `update()`. The show
 *      page's disposal panel requires a client-side confirm step before submitting.
 *   4. NBV shown anywhere on these screens is always {@see FixedAssetService::nbv()}'s live,
 *      derived value — never `$asset->` anything, never a cached figure.
 *   5. Engine OFF: register CRUD (create/edit/update) still works normally. Capitalise/dispose/
 *      depreciation-run show an honest "engine disabled — nothing will post" state rather than
 *      pretending to post — see {@see PostingSeam::isEnabledFor()} used throughout.
 *
 * Company resolution and the admin `?company_id=` override follow the exact same convention
 * {@see ReconciliationController}/{@see PeriodController} already established. Tenant isolation
 * for a route-bound {@see FixedAsset} (which carries NO global scope — see that model's own
 * docblock) is enforced explicitly by {@see self::assertBelongsToCompany()} on every action that
 * receives one; removing that check is exactly what MP-10-1 targets.
 */
class FixedAssetController extends Controller
{
    public function index(Request $request, FixedAssetService $assets): View
    {
        Gate::authorize('view', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $query = FixedAsset::query()->forCompany($companyId);

        $filterClass = $request->query('asset_class');
        if (is_string($filterClass) && $filterClass !== '') {
            $query->where('asset_class', $filterClass);
        }

        $filterStatus = $request->query('status');
        if (is_string($filterStatus) && $filterStatus !== '') {
            $query->where('status', $filterStatus);
        }

        $rows = $query->orderBy('asset_class')->orderBy('name')->get();

        $classes = (array) config('accounting.purpose_codes.fixed_asset_classes', []);

        // L8 / hard constraint 4: NBV is FixedAssetService::nbv()'s live derivation for every row
        // — never a stored/cached column. Register scale for a single-tenant deployment is small
        // enough that one nbv() call per row (each a small, indexed journal_entries sum) is cheap;
        // there is no cached shortcut to take even if there were more rows.
        $items = $rows->map(function (FixedAsset $asset) use ($assets, $classes) {
            $nbv = $assets->nbv($asset);

            return [
                'asset' => $asset,
                'class_label' => $classes[$asset->asset_class]['label'] ?? $asset->asset_class,
                'cost' => (float) $asset->cost,
                'accumulated' => round((float) $asset->cost - $nbv, 3),
                'nbv' => $nbv,
            ];
        });

        $totals = [
            'cost' => round((float) $items->sum('cost'), 3),
            'accumulated' => round((float) $items->sum('accumulated'), 3),
            'nbv' => round((float) $items->sum('nbv'), 3),
            'count' => $items->count(),
        ];

        return view('accounting.fixed-assets.index', [
            'companyId' => $companyId,
            'items' => $items,
            'totals' => $totals,
            'classes' => $classes,
            'statuses' => [
                FixedAsset::STATUS_DRAFT,
                FixedAsset::STATUS_ACTIVE,
                FixedAsset::STATUS_FULLY_DEPRECIATED,
                FixedAsset::STATUS_DISPOSED,
            ],
            'filterClass' => $filterClass,
            'filterStatus' => $filterStatus,
            'engineEnabled' => app(PostingSeam::class)->isEnabledFor($companyId),
            'canManage' => Gate::allows('manage', FixedAsset::class),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        return view('accounting.fixed-assets.create', [
            'companyId' => $companyId,
            'classes' => (array) config('accounting.purpose_codes.fixed_asset_classes', []),
            'methods' => (array) config('accounting.fixed_assets.methods', ['straight_line']),
            'branches' => Branch::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, FixedAssetService $service): RedirectResponse
    {
        Gate::authorize('manage', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $this->rejectStatusInput($request);

        $data = $this->validatedFields($request, $companyId);
        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();

        try {
            $asset = $service->create($data);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['cost' => $e->getMessage()]);
        }

        return redirect()
            ->route('accounting.fixed-assets.show', $asset)
            ->with('success', 'Fixed asset registered as a draft. Capitalise it once its cost is on the ledger.');
    }

    public function edit(Request $request, FixedAsset $fixedAsset): View
    {
        Gate::authorize('manage', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        $this->assertBelongsToCompany($fixedAsset, $companyId);

        return view('accounting.fixed-assets.edit', [
            'companyId' => $companyId,
            'asset' => $fixedAsset,
            'basisFrozen' => $this->basisFrozen($fixedAsset),
            'classes' => (array) config('accounting.purpose_codes.fixed_asset_classes', []),
            'methods' => (array) config('accounting.fixed_assets.methods', ['straight_line']),
            'branches' => Branch::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, FixedAsset $fixedAsset, FixedAssetService $service): RedirectResponse
    {
        Gate::authorize('manage', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        $this->assertBelongsToCompany($fixedAsset, $companyId);

        $this->rejectStatusInput($request);

        $data = $this->validatedFields($request, $companyId);

        try {
            $service->update($fixedAsset, $data);
        } catch (\InvalidArgumentException $e) {
            // Hard constraint 2: the service's own assertBasisNotFrozen()/validate() rejection is
            // surfaced verbatim as the reason shown to the user — never swallowed, never retried
            // with the frozen fields silently dropped.
            throw ValidationException::withMessages(['cost' => $e->getMessage()]);
        }

        return redirect()
            ->route('accounting.fixed-assets.show', $fixedAsset)
            ->with('success', 'Fixed asset updated.');
    }

    public function show(Request $request, FixedAsset $fixedAsset, FixedAssetService $service): View
    {
        Gate::authorize('view', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        $this->assertBelongsToCompany($fixedAsset, $companyId);

        $classes = (array) config('accounting.purpose_codes.fixed_asset_classes', []);
        $classLabel = $classes[$fixedAsset->asset_class]['label'] ?? $fixedAsset->asset_class;

        $scheduleRows = $this->scheduleWithPostedState($fixedAsset, $service);

        // Hard constraint 4: nbv() is called fresh here, on every request — the view never reads
        // any other value.
        $nbv = $service->nbv($fixedAsset);

        $engineEnabled = app(PostingSeam::class)->isEnabledFor($companyId);

        // Verifier fix (adversarial pass, T10, defect: N+1): bankOnlyLeaves() -> AccountResolver's
        // bankCashLeafIds()/assertUnderBankGroup() walk the company's ENTIRE account tree one
        // parent_id lookup at a time per candidate (confirmed 767 queries on a 171-account fixture
        // COA, on every single show() page load, regardless of schedule row count). Only the
        // Capitalise panel (draft) and the Dispose panel (isDisposable()) ever render this dropdown
        // — a disposed/otherwise non-actionable asset pays this cost for a dropdown nothing shows.
        // Skipping the call outright for those assets, and caching the result briefly per company
        // for the assets that DO need it, are both safe, T10-local changes: neither touches
        // AccountResolver (a shared, heavily-reused service other controllers also call) nor
        // changes what account ids are ever offered — only how often the expensive walk reruns.
        $bankLeaves = ($fixedAsset->status === FixedAsset::STATUS_DRAFT || $fixedAsset->isDisposable())
            ? \Illuminate\Support\Facades\Cache::remember(
                "fixed-assets.bank-only-leaves.{$companyId}",
                60,
                fn () => $this->bankOnlyLeaves($companyId)
            )
            : collect();

        return view('accounting.fixed-assets.show', [
            'companyId' => $companyId,
            'asset' => $fixedAsset,
            'classLabel' => $classLabel,
            'schedule' => $scheduleRows,
            'nbv' => $nbv,
            'accumulated' => round((float) $fixedAsset->cost - $nbv, 3),
            'basisFrozen' => $this->basisFrozen($fixedAsset),
            'engineEnabled' => $engineEnabled,
            'canManage' => Gate::allows('manage', FixedAsset::class),
            'bankLeaves' => $bankLeaves,
        ]);
    }

    /**
     * Hard constraint 1 / note F: the ONLY route in this controller that flips a draft asset's
     * status — and it does so exclusively via {@see FixedAssetService::capitalise()}, which
     * itself only ever moves draft→active on a SUCCESSFUL post. Deliberately narrow: the
     * counterpart is always an explicit bank/cash leaf (the same `assertUnderBankGroup()` set
     * {@see self::dispose()} already offers), never an arbitrary purpose code — a free-text
     * purpose-code field in a form is exactly the kind of "resolve by name" surface this cutover
     * forbids on the ON path. An asset whose cost was posted through an existing PV/JV before
     * being registered here has no UI path to "activate without posting" in this v1 (see the
     * review packet's Deviations section) — that gap is deliberate, not an oversight: it keeps
     * every status transition attributable to a real, balanced document.
     */
    public function capitalise(Request $request, FixedAsset $fixedAsset, FixedAssetService $service): RedirectResponse
    {
        Gate::authorize('manage', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        $this->assertBelongsToCompany($fixedAsset, $companyId);

        $validated = $request->validate([
            'bank_account_id' => ['required', 'integer'],
        ]);

        $engineEnabled = app(PostingSeam::class)->isEnabledFor($companyId);

        try {
            $result = $service->capitalise(
                $fixedAsset,
                counterpartPurposeCode: null,
                counterpartAccountId: (int) $validated['bank_account_id'],
                userId: Auth::id(),
            );
        } catch (PostingException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['bank_account_id' => $e->getMessage()]);
        }

        $message = $engineEnabled
            ? ($result !== null ? 'Fixed asset capitalised.' : 'Capitalisation already posted for this asset.')
            : 'Engine disabled — nothing was posted. The register row is saved; capitalise once the accounting engine is enabled for this company.';

        return redirect()->route('accounting.fixed-assets.show', $fixedAsset)->with('success', $message);
    }

    /**
     * Hard constraint 3: disposal's own dedicated, guarded endpoint. The confirm step lives in
     * the show page's JS (a plain `confirm()` before this form submits) — this method itself is
     * the "dedicated endpoint" side of that guard. Idempotent by construction because it only
     * ever calls {@see FixedAssetService::dispose()}, whose own idempotency short-circuit this
     * controller never bypasses.
     */
    public function dispose(Request $request, FixedAsset $fixedAsset, FixedAssetService $service): RedirectResponse
    {
        Gate::authorize('manage', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        $this->assertBelongsToCompany($fixedAsset, $companyId);

        $validated = $request->validate([
            'disposal_date' => ['required', 'date'],
            'proceeds' => ['required', 'numeric', 'gte:0'],
            'proceeds_account_id' => ['nullable', 'integer'],
        ]);

        $engineEnabled = app(PostingSeam::class)->isEnabledFor($companyId);
        $nbvBefore = $service->nbv($fixedAsset);

        try {
            $result = $service->dispose(
                $fixedAsset,
                Carbon::parse($validated['disposal_date']),
                (float) $validated['proceeds'],
                proceedsAccountId: isset($validated['proceeds_account_id']) ? (int) $validated['proceeds_account_id'] : null,
                userId: Auth::id(),
            );
        } catch (PostingException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['proceeds' => $e->getMessage()]);
        }

        if (! $engineEnabled) {
            return redirect()->route('accounting.fixed-assets.show', $fixedAsset)
                ->with('success', 'Engine disabled — nothing was posted. Disposal was not recorded; try again once the accounting engine is enabled for this company.');
        }

        if ($result === null) {
            return redirect()->route('accounting.fixed-assets.show', $fixedAsset)
                ->with('success', 'Disposal could not be posted.');
        }

        $diff = round((float) $validated['proceeds'] - $nbvBefore, 3);
        $gainLoss = $diff > 0 ? sprintf('a gain of %.3f KWD', $diff) : ($diff < 0 ? sprintf('a loss of %.3f KWD', abs($diff)) : 'no gain or loss');

        $message = "Asset disposed ({$gainLoss} vs. NBV).";
        $message .= $this->lockedPeriodShiftNote($validated['disposal_date'], $result->transaction->posting_date ?? null);

        return redirect()->route('accounting.fixed-assets.show', $fixedAsset)
            ->with('success', $message);
    }

    /**
     * Verifier fix (adversarial pass, T10): the engine silently shifts a document's posting_date
     * forward to the next open period when the requested date's own period is locked/soft-closed
     * (PostingService::post() step 5 / PeriodGuard::earliestOpenOnOrAfter() — see the T2-T4 packet
     * §12 "Locked-period shift, not refusal" note). Before this fix the dispose/run success message
     * never mentioned it, so a user who entered a disposal date in a locked month had no way to
     * know their document actually landed in the ledger a different month than the one they typed.
     * This never changes what posts — only what the flash message says.
     */
    private function lockedPeriodShiftNote(string $requestedDate, ?\DateTimeInterface $actualPostingDate): string
    {
        if ($actualPostingDate === null) {
            return '';
        }

        $requestedMonth = Carbon::parse($requestedDate)->format('Y-m');
        $postedMonth = Carbon::instance($actualPostingDate)->format('Y-m');

        if ($requestedMonth === $postedMonth) {
            return '';
        }

        return " Note: {$requestedMonth} is locked or closed, so this posted into {$postedMonth}'s books instead of the date you entered.";
    }

    /**
     * The register-wide "run depreciation for a month" screen. GET renders the picker and (when
     * `?year=&month=` are present) a dry-run preview computed via
     * {@see DepreciationRunService::runForMonth()} with `dryRun: true` — which, per that
     * service's own docblock, previews correctly EVEN WHEN the engine is off for this company, so
     * the preview is always honest about what a real run would post whenever it is enabled.
     */
    public function depreciateForm(Request $request, DepreciationRunService $runner): View
    {
        Gate::authorize('manage', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $year = (int) $request->query('year', (int) now()->format('Y'));
        $month = (int) $request->query('month', (int) now()->format('m'));

        $preview = null;
        if ($request->has('year') && $request->has('month')) {
            $preview = $runner->runForMonth($companyId, $year, $month, dryRun: true);
        }

        return view('accounting.fixed-assets.depreciate', [
            'companyId' => $companyId,
            'year' => $year,
            'month' => $month,
            'preview' => $preview,
            'engineEnabled' => app(PostingSeam::class)->isEnabledFor($companyId),
        ]);
    }

    public function depreciateRun(Request $request, DepreciationRunService $runner): RedirectResponse
    {
        Gate::authorize('manage', FixedAsset::class);

        $companyId = $this->resolveCompanyId($request);
        abort_if($companyId === null, 400, 'No company selected.');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $engineEnabled = app(PostingSeam::class)->isEnabledFor($companyId);

        $monthEnd = Carbon::create((int) $validated['year'], (int) $validated['month'], 1)->endOfMonth();
        $periodStatus = app(PeriodGuard::class)->statusFor($companyId, $monthEnd);

        $result = $runner->runForMonth($companyId, (int) $validated['year'], (int) $validated['month'], dryRun: false, userId: Auth::id());

        $message = ! $engineEnabled
            ? 'Engine disabled — nothing was posted.'
            : sprintf(
                '%d document(s) posted, %d skipped%s.',
                $result['posted'],
                $result['skipped'],
                $result['blocked'] !== [] ? ', '.count($result['blocked']).' blocked (see log)' : ''
            );

        // Verifier fix (adversarial pass, T10): same locked-period shift honesty gap as dispose() —
        // see FixedAssetController::lockedPeriodShiftNote()'s docblock. DepreciationRunService's own
        // result shape carries no per-document posting_date, so this is a pre-check on the
        // requested period's status rather than a post-hoc comparison; it only fires when
        // something was actually posted this call.
        if ($engineEnabled && $result['posted'] > 0 && in_array($periodStatus, [\App\Models\AccountingPeriod::STATUS_LOCKED, \App\Models\AccountingPeriod::STATUS_SOFT_CLOSED], true)) {
            $message .= sprintf(
                ' Note: %04d-%02d is %s, so these documents posted into the next open period\'s books instead of that month.',
                (int) $validated['year'],
                (int) $validated['month'],
                str_replace('_', ' ', $periodStatus)
            );
        }

        return redirect()
            ->route('accounting.fixed-assets.depreciate', ['year' => $validated['year'], 'month' => $validated['month']])
            ->with('success', $message)
            ->with('run_result', $result);
    }

    // ── internals ────────────────────────────────────────────────────────────────────────────

    /**
     * Hard constraint 1: reject the WHOLE request with a 422 the instant a `status` key is
     * present on the payload — never silently strip it and proceed. This is checked BEFORE any
     * other validation so an attacker cannot smuggle a status change behind an otherwise-valid
     * payload.
     */
    private function rejectStatusInput(Request $request): void
    {
        if ($request->has('status')) {
            throw ValidationException::withMessages([
                'status' => 'The asset status cannot be set directly. Status changes only through Capitalise or Dispose.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validatedFields(Request $request, int $companyId): array
    {
        $classes = array_keys((array) config('accounting.purpose_codes.fixed_asset_classes', []));
        $methods = (array) config('accounting.fixed_assets.methods', ['straight_line']);

        $validated = $request->validate([
            'asset_class' => ['required', 'string', Rule::in($classes)],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:60'],
            'cost' => ['required', 'numeric', 'gt:0'],
            'salvage' => ['nullable', 'numeric', 'gte:0'],
            'acquisition_date' => ['required', 'date'],
            'in_service_date' => ['required', 'date'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', 'string', Rule::in($methods)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['salvage'] = $validated['salvage'] ?? 0;

        return $validated;
    }

    /**
     * The bank/cash-account picker for both the capitalise and disposal forms. Deliberately NOT
     * {@see AccountResolver::bankCashLeafIds()} — that returns bank AND cash leaves together, but
     * {@see FixedAssetService::capitalise()}'s explicit-account path and
     * {@see FixedAssetService::dispose()}'s explicit-account path both validate the chosen id with
     * {@see AccountResolver::assertUnderBankGroup()}, which accepts ONLY leaves under the "Bank
     * Accounts" group — a leaf under "Cash In Hand" (e.g. Petty Cash) throws
     * `AccountNotUnderGroupException`. Offering a cash leaf in this dropdown would be a form that
     * validates client-side and then 422s every time it is used — discovered by this controller's
     * own feature tests (`test_capitalise_posts_and_activates_the_asset_when_engine_is_on` failed
     * against the unfiltered list first). The dispose form's own "Cash in hand" default option
     * already covers the cash case correctly, via {@see FixedAssetService::dispose()}'s
     * no-account-given branch (`CASH_IN_HAND` purpose code) — so this list only ever needs to
     * offer genuine bank leaves. Filters by calling `assertUnderBankGroup()` itself (never a
     * `name`/`root_id` equality check) so this stays correct if the bank/cash leaf set ever
     * changes.
     */
    private function bankOnlyLeaves(int $companyId): \Illuminate\Support\Collection
    {
        $resolver = app(AccountResolver::class);
        $candidateIds = $resolver->bankCashLeafIds($companyId);

        if ($candidateIds === []) {
            return collect();
        }

        $bankOnlyIds = [];
        foreach ($candidateIds as $id) {
            try {
                $resolver->assertUnderBankGroup($id, $companyId);
                $bankOnlyIds[] = $id;
            } catch (PostingException) {
                continue;
            }
        }

        if ($bankOnlyIds === []) {
            return collect();
        }

        return Account::withoutGlobalScopes()->whereIn('id', $bankOnlyIds)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function basisFrozen(FixedAsset $asset): bool
    {
        return FixedAssetDepreciation::where('fixed_asset_id', $asset->id)
            ->where('status', FixedAssetDepreciation::STATUS_POSTED)
            ->exists();
    }

    /**
     * @return list<array{year:int, month:int, amount:float, period:string, posted:bool, transaction_id:?int}>
     */
    private function scheduleWithPostedState(FixedAsset $asset, FixedAssetService $service): array
    {
        $posted = FixedAssetDepreciation::where('fixed_asset_id', $asset->id)
            ->orderBy('period_year')->orderBy('period_month')
            ->get()
            ->keyBy(fn (FixedAssetDepreciation $row) => sprintf('%04d-%02d', $row->period_year, $row->period_month));

        return array_map(function (array $row) use ($posted) {
            $key = sprintf('%04d-%02d', $row['year'], $row['month']);
            $postedRow = $posted->get($key);

            return $row + [
                'period' => $key,
                'posted' => $postedRow !== null,
                'transaction_id' => $postedRow?->transaction_id,
            ];
        }, $service->scheduleFor($asset));
    }

    /**
     * MP-10-1 targets removing this call: a {@see FixedAsset} route-bound by id alone carries no
     * global scope (see that model's own docblock — soft-deletes only), so without this explicit
     * check any authenticated user of ANY company could view/edit/capitalise/dispose ANY other
     * company's asset by guessing its id.
     */
    private function assertBelongsToCompany(FixedAsset $asset, ?int $companyId): void
    {
        if ($companyId === null || (int) $asset->company_id !== $companyId) {
            abort(404);
        }
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
