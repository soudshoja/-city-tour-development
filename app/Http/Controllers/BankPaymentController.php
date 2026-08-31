<?php

namespace App\Http\Controllers;

use App\Exceptions\Accounting\InsufficientBankBalanceException;
use App\Exceptions\Accounting\PostingException;
use App\Models\Account;
use App\Models\Agent;
use App\Models\BankPayment;
use App\Models\BonusAgent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Transaction;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\ChequeImageStore;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ReconciliationService;
use App\Services\Accounting\SequenceService;
use App\Services\Accounting\VoucherOptions;
use App\Services\Accounting\VoucherSubTypeGuard;
use App\Services\TrialBalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * W5.P (w5-brief.md §W5.P). Every posting action in this controller (`store()`'s auto-approve fast
 * path, `approve()`, `update()`'s reverse+repost, `destroy()`'s reverse, `clear()`) builds a
 * {@see DocumentDraft} and enters {@see PostingSeam::post()} -- never a bare `JournalEntry::create()`
 * -- so engine-OFF and engine-ON both go through the SAME account resolution (explicit, tenant-
 * checked account ids via {@see AccountResolver}, plus purpose-code anchors for the
 * CHEQUES_ISSUED_NOT_CLEARED/BANK_CHARGES_EXPENSE legs), same discipline W5.R already established
 * for RV one wave earlier.
 *
 * ── One row, one document (the shape change from HEAD) ──────────────────────────────────────────
 * HEAD's `store()` loops over a submitted `items[]` array and creates a SEPARATE `Transaction` per
 * item, sharing one user-typed `bankpaymentref` string across all of them (w5-state.md §1 "PV
 * document" / "Numbering" rows). {@see BankPayment} (this wave's new table -- HEAD had no dedicated
 * PV row at all, see that migration's own docblock) keeps that exact "one item -> one document"
 * shape: each submitted item becomes its own `bank_payments` row, its own `DocumentDraft`, its own
 * engine-minted serial number. What changes is that the row now exists BEFORE posting (a real
 * `pending` draft, mirroring RV's already-shipped draft -> approve -> post lifecycle, W4.R/W5.R) and
 * `store()` posts the whole batch atomically -- one failing item rolls the entire submission back,
 * never "log and skip" (w5-state.md's own defect: "several branches log an error and continue
 * instead of aborting").
 *
 * ── `$id` is `bank_payments.id` everywhere in this file (edit/update/approve/destroy/clear) ───────
 * HEAD's `edit()`/`update()` keyed `$id` off `transactions.id`. Once a still-pending voucher has no
 * `Transaction` row at all, that convention cannot survive -- same documented deviation W5.R already
 * took for `invoice_receipts.id`. The pre-existing Blade screens (`resources/views/bank-payments/*`)
 * are NOT rewired in this sub-wave -- w5-brief.md's own §W5.U is the dedicated, later sub-wave that
 * reworks those screens against this new backend/id contract.
 *
 * ── SUPPLIER vs ACCOUNT sub_type ────────────────────────────────────────────────────────────────
 * HEAD's own `items[].type_selector` UI only ever offers `account`/`bonus` (`refund` is commented
 * out disabled in `create.blade.php`) -- there is no user-facing SUPPLIER/ACCOUNT distinction today.
 * {@see self::resolveSubType()} derives it structurally instead: a target account whose ROOT is
 * `Liabilities` is a SUPPLIER payment (paying down a payable); anything else is a generic ACCOUNT
 * cash-out. Never a name-LIKE lookup -- the root walk uses `withoutGlobalScopes()->find()`, the same
 * convention {@see AccountResolver} uses throughout.
 *
 * ── Cheque-issued vs direct-bank credit leg, and why the overdraft check treats them differently ──
 * A cheque_no on the item means "issued, not yet cleared" (w5-brief.md §W5.P) -- the credit leg goes
 * to CHEQUES_ISSUED_NOT_CLEARED (2215), NOT the bank leaf, because the bank's own balance is
 * unaffected until the cheque is manually cleared (real-world fact: the bank does not debit the
 * account the moment a cheque is written). The `pv_allow_overdraft` pre-check therefore only guards
 * the REAL bank outflow for this document: direct-bank payments (no cheque) and any manual bank-
 * charge amount (which the brief treats as a same-day bank movement regardless of how the main leg
 * is paid). Cheque clearance itself ({@see self::clear()}) re-runs the same check at the moment money
 * actually leaves the bank.
 */
class BankPaymentController extends Controller
{
    public function __construct(
        private readonly PostingSeam $seam,
        private readonly PostingService $postingService,
        private readonly AccountResolver $accountResolver,
        private readonly TrialBalanceService $trialBalance,
        private readonly ReconciliationService $reconciliation,
        private readonly ChequeImageStore $chequeImageStore,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', BankPayment::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        // Read side unchanged from HEAD (Transaction-backed, 'PV-%' reference_number filter --
        // SequenceService's DEFAULT_MASK still renders 'PV-2026-00001'/'PV-0007-2026-00005', both
        // matching this LIKE, so posted vouchers remain listed here unchanged). Out of this
        // sub-wave's scope: W5.U reworks this to list `bank_payments` (including still-pending
        // drafts) the same way ReceiptVoucherController::index() already lists `invoice_receipts`.
        $bankPaymentsQuery = Transaction::with(['journalEntries' => function ($query) {
            $query->where('type', 'payable');
        }])
            ->whereNotNull('name')
            ->where('reference_number', 'like', 'PV-%')
            ->latest();

        if ($request->filled('q')) {
            $search = $request->q;
            $bankPaymentsQuery->where(function ($query) use ($search) {
                $query->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('journalEntries', function ($q) use ($search) {
                        $q->where('type', 'payable')
                            ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $bankPaymentsQuery->where('company_id', $companyId);
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $branchIds = Branch::where('company_id', $companyId)->pluck('id')->toArray();
            $bankPaymentsQuery->whereIn('branch_id', $branchIds);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $bankPaymentsQuery->where('company_id', $companyId);
        } elseif ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        $totalRecords = (clone $bankPaymentsQuery)->count();
        $bankPayments = $bankPaymentsQuery->paginate(10)->withQueryString();

        return view('bank-payments.index', compact(
            'bankPayments',
            'totalRecords',
        ));
    }

    public function create(Request $request)
    {
        // NEW (HEAD had no authorize() call in create() at all -- w5-brief.md §W5.X "full abilities
        // ... enforced on every route").
        Gate::authorize('create', BankPayment::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (! $companyId) {
            return redirect()->route('bank-payments.index')->with('error', 'Please select a company first.');
        }

        $company = Company::with('branches')->find($companyId);

        if (! $company) {
            return redirect()->route('bank-payments.index')->with('error', 'Company not found.');
        }

        $companies = $company;
        $branches = $company->branches;

        $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
        $rootIds = Account::whereIn('name', $rootNames)
            ->where('company_id', $companyId)
            ->pluck('id');

        $accpayreceives = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereHas('parent', function ($query) use ($rootIds) {
                $query->whereIn('root_id', $rootIds);
            })
            ->get();

        $lastLevelAccounts = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereHas('parent', function ($query) use ($rootIds) {
                $query->whereIn('root_id', $rootIds);
            })
            ->get();

        $supplierRootIds = Account::whereIn('name', ['Liabilities', 'Expenses'])
            ->where('company_id', $companyId)
            ->pluck('id');
        $suppliers = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereIn('root_id', $supplierRootIds)
            ->get();

        $accounts = Account::where('company_id', $companyId)->get();

        $refundNumbers = Refund::where('company_id', $companyId)
            ->select('refund_number')
            ->get();

        $bonusAccounts = Account::where('company_id', $companyId)
            ->with('root')
            ->where('label', 'like', '%bonus%')
            ->get();

        $agents = Agent::whereHas('branch', fn ($q) => $q->where('company_id', $companyId))->get();

        $assetsRoot = Account::where('name', 'Assets')
            ->where('company_id', $companyId)
            ->first();

        $bankAccounts = collect();
        if ($assetsRoot) {
            $bankParent = Account::where('parent_id', $assetsRoot->id)
                ->where('name', 'Bank Accounts')
                ->where('company_id', $companyId)
                ->first();

            if ($bankParent) {
                $bankAccounts = Account::where('parent_id', $bankParent->id)
                    ->where('company_id', $companyId)
                    ->get()
                    ->map(function ($account) {
                        $debitSum = JournalEntry::where('account_id', $account->id)->sum('debit');
                        $creditSum = JournalEntry::where('account_id', $account->id)->sum('credit');
                        $account->current_balance = $debitSum - $creditSum;

                        return $account;
                    });
            }
        }

        // W5.U: the instrument section's overdraft note needs the company's actual
        // `pv_allow_overdraft` setting -- otherwise the screen would show a hardcoded claim that
        // silently drifts from what {@see self::assertOverdraftAllowed()} actually enforces.
        $pvAllowOverdraft = VoucherOptions::pvAllowOverdraft($companyId);
        $approvalThreshold = VoucherOptions::approvalThreshold($companyId);

        return view('bank-payments.create', compact(
            'accounts',
            'companies',
            'branches',
            'suppliers',
            'accpayreceives',
            'lastLevelAccounts',
            'refundNumbers',
            'bonusAccounts',
            'agents',
            'bankAccounts',
            'pvAllowOverdraft',
            'approvalThreshold',
        ));
    }

    /**
     * W5.P store(). Validates the whole batch up front (every item requires a real, same-company
     * target account -- HEAD's own "post only the bank leg when no target account resolves" gap,
     * w5-state.md §1, is closed rather than reproduced: {@see self::validateVoucherRequest()} throws
     * before any row is created, so a bad item never leaves a partial batch behind), then creates
     * one `pending` `bank_payments` row per item and auto-approves (posts) each at or under
     * {@see VoucherOptions::approvalThreshold()}.
     *
     * Each item's row-creation and its own auto-approve attempt are handled INDEPENDENTLY (matching
     * ReceiptVoucherController::store()'s own established convention): a `pending` row is always
     * saved first, then `postVoucher()` is attempted; if posting fails (e.g. the overdraft
     * pre-check refuses it), the row stays `pending` and the user is told exactly which items
     * failed auto-approval -- this is NOT "log and continue" (w5-state.md's own defect, which
     * SILENTLY skipped a broken item mid-post): nothing is ever half-posted, and every failure is
     * surfaced to the caller, per-item, in the response.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', BankPayment::class);

        $rows = $this->validateVoucherRequest($request);

        $created = [];
        $autoApproveErrors = [];

        foreach ($rows as $data) {
            $bankPayment = new BankPayment;
            $this->fillVoucherRow($bankPayment, $data);
            $bankPayment->status = BankPayment::STATUS_PENDING;
            $bankPayment->created_by = Auth::id();
            $bankPayment->save();

            $bankPayment->voucher_number = 'PV-DRAFT-'.$bankPayment->id;
            $bankPayment->save();

            $threshold = VoucherOptions::approvalThreshold($data['company_id']);
            $autoApprove = $threshold !== null && $data['amount'] <= $threshold;

            if ($autoApprove) {
                try {
                    $this->postVoucher($bankPayment);
                } catch (PostingException $e) {
                    Log::critical('accounting.pv_auto_approve_failed', [
                        'bank_payment_id' => $bankPayment->id,
                        'exception_class' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);

                    $autoApproveErrors[] = "#{$bankPayment->id}: {$e->getMessage()}";
                }
            }

            $created[] = $bankPayment;
        }

        if ($autoApproveErrors !== []) {
            return redirect()->route('bank-payments.index')->with(
                'error',
                'Payment Voucher(s) saved as drafts; auto-approval failed for: '.implode(' | ', $autoApproveErrors)
            );
        }

        $pendingCount = collect($created)->filter(fn (BankPayment $bp) => $bp->isPending())->count();
        $message = $pendingCount > 0
            ? sprintf('Payment Voucher(s) created; %d awaiting approval.', $pendingCount)
            : 'Payment Voucher Successfully Recorded with Double-Entry.';

        return redirect()->route('bank-payments.index')->with('success', $message);
    }

    public function edit($id)
    {
        $bankPayment = BankPayment::with(['payFromAccount', 'targetAccount', 'agent'])->findOrFail($id);
        Gate::authorize('update', $bankPayment);

        $JournalEntrys = $bankPayment->transaction_id
            ? JournalEntry::where('transaction_id', $bankPayment->transaction_id)->get()
            : collect();

        $payeeName = $bankPayment->targetAccount?->name ?? '';
        $payFromName = $bankPayment->payFromAccount?->name ?? '';

        $user = Auth::user();
        $companyId = $bankPayment->company_id;
        $company = Company::with('branches.account', 'branches.agents')->find($companyId);

        if (! $company) {
            return redirect()->route('bank-payments.index')->with('error', 'Company not found.');
        }

        if (! in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])) {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        $companies = $company;
        $branches = $company->branches;
        $accounts = $branches->pluck('account')->filter();

        $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
        $rootIds = Account::whereIn('name', $rootNames)
            ->where('company_id', $companyId)
            ->pluck('id');

        $accpayreceives = Account::doesntHave('children')
            ->where('company_id', $companyId)
            ->whereHas('parent', fn ($q) => $q->whereIn('root_id', $rootIds))
            ->get();

        $suppliers = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereHas('parent', fn ($q) => $q->whereIn('root_id', $rootIds))
            ->get();

        $assetsRoot = Account::where('name', 'Assets')->where('company_id', $companyId)->first();
        $bankAccounts = collect();

        if ($assetsRoot) {
            $bankParent = Account::where('parent_id', $assetsRoot->id)
                ->where('name', 'Bank Accounts')
                ->where('company_id', $companyId)
                ->first();

            if ($bankParent) {
                $bankAccounts = Account::where('parent_id', $bankParent->id)
                    ->where('company_id', $companyId)
                    ->get()
                    ->map(function ($account) {
                        $debitSum = JournalEntry::where('account_id', $account->id)->sum('debit');
                        $creditSum = JournalEntry::where('account_id', $account->id)->sum('credit');
                        $account->current_balance = $debitSum - $creditSum;

                        return $account;
                    });
            }
        }

        // W5.U: same lock/reconcile READ state and ability set ReceiptVoucherController::edit()
        // exposes -- see that method's own docblock for why this is computed here rather than
        // re-derived in the view.
        $pvAllowOverdraft = VoucherOptions::pvAllowOverdraft($companyId);
        $approvalThreshold = VoucherOptions::approvalThreshold($companyId);
        // W5.U fix -- see ReceiptVoucherController::edit()'s identical, more fully-commented fix
        // for why this reads the transaction's own `is_locked` column directly rather than going
        // through {@see self::checkVoucherLocked()} (which can THROW via
        // {@see \App\Http\Traits\Lockable::canModify()}'s internal `Gate::authorize('manageLocks',
        // ...)` -- fine for a mutating action, wrong for rendering a read-only badge).
        $lockedTransaction = $bankPayment->transaction_id
            ? Transaction::withoutGlobalScopes()->find($bankPayment->transaction_id)
            : null;
        $isLocked = (bool) ($lockedTransaction?->isLocked());
        $isReconciled = $this->hasReconciledLines($bankPayment);
        $canApprove = Gate::allows('approve', $bankPayment);
        $canReverse = Gate::allows('delete', $bankPayment) && ! $isLocked && ! $isReconciled;
        $canReconcile = Gate::allows('reconcile', $bankPayment);
        $canEditFields = Gate::allows('update', $bankPayment) && ($bankPayment->isPending() || (! $isLocked && ! $isReconciled));

        return view('bank-payments.edit', compact(
            'companies',
            'bankPayment',
            'accounts',
            'branches',
            'suppliers',
            'accpayreceives',
            'JournalEntrys',
            'bankAccounts',
            'payeeName',
            'payFromName',
            'pvAllowOverdraft',
            'approvalThreshold',
            'isLocked',
            'isReconciled',
            'canApprove',
            'canReverse',
            'canReconcile',
            'canEditFields',
        ));
    }

    /**
     * W5.P update(). Pending row -> plain field update, no engine interaction (mirrors
     * ReceiptVoucherController::update()). Posted row -> reverse+repost: blocked when any of the
     * voucher's own journal lines is `reconciled` (Layer 3) or the posted transaction's own
     * `is_locked` flag is set (Layer 1, per-record, `checkLocked`). Period close (Layer 2) is NOT
     * checked here -- `PeriodGuard::assertOpen()` inside `PostingService::post()`/`repost()`
     * enforces it automatically (w5-brief.md's own "Period model" note).
     */
    public function update(Request $request, $id)
    {
        $bankPayment = BankPayment::findOrFail($id);
        Gate::authorize('update', $bankPayment);

        $data = $this->validateSingleVoucherRequest($request, $bankPayment);

        if ($bankPayment->isPending()) {
            $this->fillVoucherRow($bankPayment, $data);
            $bankPayment->save();

            return redirect()->route('bank-payments.edit', $id)->with('success', 'Payment Voucher Updated Successfully.');
        }

        if ($blocked = $this->checkVoucherLocked($bankPayment)) {
            return $blocked;
        }

        if ($this->hasReconciledLines($bankPayment)) {
            return redirect()->back()->with('error', 'This payment voucher has a reconciled line and cannot be edited. Un-reconcile it first.');
        }

        $companyId = (int) $bankPayment->company_id;
        $oldTransaction = Transaction::withoutGlobalScopes()->findOrFail($bankPayment->transaction_id);
        $engineOn = $this->seam->isEnabledFor($companyId);

        DB::beginTransaction();
        try {
            $this->fillVoucherRow($bankPayment, $data);
            $bankPayment->save();

            $newDraft = $this->buildVoucherDraft($bankPayment);

            // NOTE (documented simplification): unlike a fresh post, this outflow is a REPLACEMENT
            // for one the bank balance already reflects (the old document is reversed in the same
            // breath). Checking against the CURRENT balance (still including the old document's own
            // outflow, since it hasn't been reversed yet at this point) is deliberately conservative
            // -- it can occasionally over-refuse an edit that merely swaps which invoice a payment
            // targets without changing the amount, but it can never let an actual overdraft through.
            $this->assertOverdraftAllowed($companyId, (int) $bankPayment->pay_from_account_id, $this->bankOutflowFor($bankPayment));

            if ($engineOn) {
                $posted = $this->postingService->repost($oldTransaction, $newDraft, $newDraft->docDate, Auth::id());
                $newTransactionId = $posted->transaction->id;
            } else {
                JournalEntry::where('transaction_id', $oldTransaction->id)->delete();
                $this->markTransactionReversed($oldTransaction);
                $repostDraft = $this->withIdempotencyKeySuffix($newDraft, ':repost:'.$oldTransaction->id);
                $newTransactionId = $this->writeLegacyTransaction($repostDraft, $bankPayment)->id;
            }

            $bankPayment->transaction_id = $newTransactionId;
            $bankPayment->save();

            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.pv_update_failed', [
                'bank_payment_id' => $id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to update payment voucher: '.$e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to update payment voucher: '.$e->getMessage());
        }

        return redirect()->route('bank-payments.edit', $id)->with('success', 'Payment Voucher Updated Successfully (reversed and reposted).');
    }

    /**
     * W5.P approve(). The one place (besides store()'s auto-approve fast path, which calls the SAME
     * {@see self::postVoucher()}) that turns a `pending` voucher into a real, balanced, posted
     * document.
     */
    public function approve($id)
    {
        $bankPayment = BankPayment::findOrFail($id);
        Gate::authorize('approve', $bankPayment);

        if (! $bankPayment->isPending()) {
            return redirect()->back()->with('error', 'This payment voucher has already been actioned.');
        }

        try {
            $this->postVoucher($bankPayment);
        } catch (PostingException $e) {
            Log::critical('accounting.pv_approve_failed', [
                'bank_payment_id' => $id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to approve: '.$e->getMessage());
        }

        return redirect()->route('bank-payments.index')->with('success', 'Payment Voucher has been approved and posted.');
    }

    /**
     * W5.P destroy() (NEW -- HEAD has no delete action for PV at all). Pending row -> hard delete
     * (nothing was ever posted). Posted row -> reverse(), same locked/reconciled guards as update().
     */
    public function destroy($id)
    {
        $bankPayment = BankPayment::findOrFail($id);
        Gate::authorize('delete', $bankPayment);

        if ($bankPayment->isPending()) {
            $bankPayment->delete();

            return redirect()->route('bank-payments.index')->with('success', 'Draft payment voucher deleted.');
        }

        if ($blocked = $this->checkVoucherLocked($bankPayment)) {
            return $blocked;
        }

        if ($this->hasReconciledLines($bankPayment)) {
            return redirect()->back()->with('error', 'This payment voucher has a reconciled line and cannot be deleted. Un-reconcile it first.');
        }

        $companyId = (int) $bankPayment->company_id;
        $oldTransaction = Transaction::withoutGlobalScopes()->findOrFail($bankPayment->transaction_id);
        $engineOn = $this->seam->isEnabledFor($companyId);

        DB::beginTransaction();
        try {
            if ($engineOn) {
                $this->postingService->reverse($oldTransaction, now(), Auth::id());
            } else {
                JournalEntry::where('transaction_id', $oldTransaction->id)->delete();
                $this->markTransactionReversed($oldTransaction);
            }

            $bankPayment->status = BankPayment::STATUS_REVERSED;
            $bankPayment->save();

            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.pv_delete_failed', [
                'bank_payment_id' => $id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to delete payment voucher: '.$e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to delete payment voucher: '.$e->getMessage());
        }

        return redirect()->route('bank-payments.index')->with('success', 'Payment voucher reversed.');
    }

    /**
     * W5.P cheque clearance (w5-brief.md §W5.P "Cheque issued not cleared: Cr 2215 until manual
     * clear -> Dr 2215 / Cr bank"). A plain JV -- no PV sub_type is minted for this, mirroring
     * ReceiptVoucherController::clear()'s own convention; it is not itself a new payment event, only
     * a bank reclassification of money this voucher already committed to pay.
     */
    public function clear(Request $request, $id)
    {
        $bankPayment = BankPayment::findOrFail($id);
        Gate::authorize('reconcile', $bankPayment);

        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'clearance_date' => ['nullable', 'date'],
        ]);

        if ($bankPayment->cheque_no === null || $bankPayment->cheque_clearance_date !== null) {
            return back()->with('error', 'This payment voucher has no outstanding cheque to clear.');
        }

        $companyId = (int) $bankPayment->company_id;
        $branchId = (int) $bankPayment->branch_id;
        $clearanceDate = isset($data['clearance_date']) ? Carbon::parse($data['clearance_date']) : Carbon::now();
        $amount = round((float) $bankPayment->amount, 3);

        $chequesIssued = $this->accountResolver->resolve('CHEQUES_ISSUED_NOT_CLEARED', $companyId);
        $bankAccount = $this->accountResolver->assertUnderBankGroup((int) $data['bank_account_id'], $companyId);

        $narration = "Cheque clearance for Payment Voucher #{$bankPayment->id}";
        $chequeDate = $bankPayment->cheque_date ? Carbon::parse($bankPayment->cheque_date) : null;

        $lines = [
            new LineDraft(
                purposeCode: '', accountId: $chequesIssued->id, side: 'debit', amount: $amount,
                currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                transactionType: 'CHEQUE_CLEARED', description: $narration,
                chequeNo: $bankPayment->cheque_no, chequeDate: $chequeDate, chequeClearanceDate: $clearanceDate,
            ),
            new LineDraft(
                purposeCode: '', accountId: $bankAccount->id, side: 'credit', amount: $amount,
                currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                transactionType: 'CHEQUE_CLEARED', description: $narration,
                chequeNo: $bankPayment->cheque_no, chequeDate: $chequeDate, chequeClearanceDate: $clearanceDate,
            ),
        ];

        $draft = new DocumentDraft(
            companyId: $companyId, branchId: $branchId, docType: 'JV', subType: null,
            docDate: $clearanceDate, narration: $narration, lines: $lines,
            idempotencyKey: 'pv-clear:'.$bankPayment->id, userId: Auth::id(),
        );

        DB::beginTransaction();
        try {
            // Real money leaves the bank NOW -- see class docblock's "Cheque-issued vs direct-bank"
            // note for why this was skipped at issuance time.
            $this->assertOverdraftAllowed($companyId, $bankAccount->id, $amount);

            $legacy = fn () => $this->writeLegacyTransaction($draft, null);
            $this->seam->post($draft, $legacy, 'bank-payment.clear');

            $bankPayment->update(['cheque_clearance_date' => $clearanceDate]);

            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.pv_clear_failed', ['bank_payment_id' => $id, 'message' => $e->getMessage()]);

            return back()->with('error', 'Failed to clear cheque: '.$e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to clear cheque: '.$e->getMessage());
        }

        return back()->with('success', 'Cheque cleared.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W5.P posting core
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The one place a `pending` voucher becomes `approved` and posted. Shared by `store()`'s
     * auto-approve fast path and `approve()`. Wraps the overdraft pre-check and the post itself in
     * ONE DB transaction so the row lock {@see self::assertOverdraftAllowed()} takes on the bank
     * leaf is held until the whole document (including {@see PostingSeam::post()}'s own nested
     * transaction/SAVEPOINT) commits -- closing the TOCTOU race w5-state.md's as-is table names.
     */
    private function postVoucher(BankPayment $bankPayment): Transaction
    {
        $draft = $this->buildVoucherDraft($bankPayment);
        $companyId = (int) $bankPayment->company_id;
        $bankOutflow = $this->bankOutflowFor($bankPayment);

        return DB::transaction(function () use ($bankPayment, $draft, $companyId, $bankOutflow) {
            $this->assertOverdraftAllowed($companyId, (int) $bankPayment->pay_from_account_id, $bankOutflow);

            $legacy = fn () => $this->writeLegacyTransaction($draft, $bankPayment);
            $posted = $this->seam->post($draft, $legacy, 'bank-payment.'.strtolower((string) $draft->subType));

            $transaction = match (true) {
                $posted instanceof PostedDocument => $posted->transaction,
                $posted instanceof Transaction => $posted,
                // S1 short-circuit (PostingSeam docblock) -- the engine already posted this exact
                // (company_id, idempotency_key) before a kill-switch flip.
                $posted === null => Transaction::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('company_id', $draft->companyId)
                    ->where('idempotency_key', $draft->idempotencyKey)
                    ->firstOrFail(),
                default => throw new \RuntimeException('Unexpected PostingSeam::post() return type: '.get_debug_type($posted)),
            };

            $bankPayment->transaction_id = $transaction->id;
            $bankPayment->status = BankPayment::STATUS_APPROVED;
            $bankPayment->save();

            if ($bankPayment->sub_type === 'BONUS' && $bankPayment->agent_id) {
                // NOTE: `bonus_agents` (migration 2025_10_23_121621_create_table_bonus_for_agents.php)
                // has NO `company_id`/`branch_id`/`description` columns despite
                // BonusAgent::$fillable listing them (a pre-existing model/schema drift, out of this
                // sub-wave's scope to fix) -- only the four columns the table actually has are
                // written here, matching HEAD's own BonusAgent::create() call exactly.
                BonusAgent::create([
                    'transaction_id' => $transaction->id,
                    'agent_id' => $bankPayment->agent_id,
                    'amount' => $bankPayment->amount,
                    'created_by' => Auth::id(),
                ]);
            }

            if ($bankPayment->sub_type === 'BY_DATE') {
                $this->applyByDateReconciliation($bankPayment, $transaction);
            }

            return $transaction;
        });
    }

    /**
     * Pure draft builder -- no side effects, no writes. Used for the engine path, for the legacy
     * closure ({@see self::writeLegacyTransaction()}), and for `update()`'s repost.
     *
     * PV always debits the target (payee) leg and credits the instrument leg -- a Payment Voucher,
     * by definition, is money going OUT, mirroring ReceiptVoucherController::buildVoucherDraft()'s
     * identical unambiguous-direction rule for RV.
     */
    private function buildVoucherDraft(BankPayment $bp): DocumentDraft
    {
        $companyId = (int) $bp->company_id;
        $branchId = (int) $bp->branch_id;
        $docDate = $bp->doc_date ? Carbon::parse($bp->doc_date) : Carbon::now();
        $amount = round((float) $bp->amount, 3);
        $bankCharge = round((float) ($bp->bank_charge_amount ?? 0), 3);

        VoucherSubTypeGuard::assertValid('PV', $bp->sub_type);

        $payFromAccount = Account::withoutGlobalScopes()->findOrFail($bp->pay_from_account_id);
        $targetAccount = Account::withoutGlobalScopes()->findOrFail($bp->target_account_id);

        $chequeDate = $bp->cheque_date ? Carbon::parse($bp->cheque_date) : null;
        $isChequeIssued = $bp->cheque_no !== null && $bp->cheque_no !== '';
        $reconciledFlag = $bp->sub_type === 'BY_DATE' ? 2 : null;

        $narration = match ($bp->sub_type) {
            'BONUS' => "Bonus Payment - {$targetAccount->name}",
            'REFUND_OUT' => "Refund Payment - {$targetAccount->name}",
            'BY_DATE' => "Payment by Date - {$targetAccount->name}",
            'SUPPLIER' => "Supplier Payment - {$targetAccount->name}",
            default => "Payment Voucher - {$targetAccount->name}",
        };

        $lines = [
            new LineDraft(
                purposeCode: '', accountId: $targetAccount->id, side: 'debit', amount: $amount,
                currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                transactionType: 'SUPPLIERDEBITED', description: $narration,
                partyAccountRef: $bp->sub_type === 'BONUS' ? $bp->agent_id : null,
                ledgerType: 'payable', partyName: $targetAccount->name,
                chequeNo: $bp->cheque_no, chequeDate: $chequeDate, bankInfo: $bp->bank_info, authNo: $bp->auth_no,
                reconciled: $reconciledFlag,
            ),
        ];

        // Instrument (credit) leg: a cheque issued and not yet cleared floats on
        // CHEQUES_ISSUED_NOT_CLEARED (2215); otherwise it hits the picked bank/cash leaf directly.
        // See class docblock's "Cheque-issued vs direct-bank credit leg" note.
        $lines[] = $isChequeIssued
            ? new LineDraft(
                purposeCode: '', accountId: $this->accountResolver->resolve('CHEQUES_ISSUED_NOT_CLEARED', $companyId)->id,
                side: 'credit', amount: $amount, currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                transactionType: 'CHEQUE_ISSUED', description: $narration, ledgerType: 'bank',
                chequeNo: $bp->cheque_no, chequeDate: $chequeDate, bankInfo: $bp->bank_info, authNo: $bp->auth_no,
                reconciled: $reconciledFlag,
            )
            : new LineDraft(
                purposeCode: '', accountId: $payFromAccount->id, side: 'credit', amount: $amount,
                currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                transactionType: 'PAYMENT', description: $narration, ledgerType: 'bank', partyName: $payFromAccount->name,
                chequeNo: $bp->cheque_no, chequeDate: $chequeDate, bankInfo: $bp->bank_info, authNo: $bp->auth_no,
                reconciled: $reconciledFlag,
            );

        // Optional manual bank-charge line (w5-brief.md §W5.P): Dr BANK_CHARGES_EXPENSE / Cr bank --
        // always the real bank leaf directly, regardless of whether the main leg is by cheque (a
        // transfer/handling fee is its own, immediate, separate bank movement).
        if ($bankCharge > 0.0005) {
            $chargeNarration = "Bank charge for {$narration}";
            $lines[] = new LineDraft(
                purposeCode: '', accountId: $this->accountResolver->resolve('BANK_CHARGES_EXPENSE', $companyId)->id,
                side: 'debit', amount: $bankCharge, currency: 'KWD', originalAmount: $bankCharge, exchangeRate: 1.0,
                transactionType: 'BANK_CHARGE', description: $chargeNarration,
            );
            $lines[] = new LineDraft(
                purposeCode: '', accountId: $payFromAccount->id, side: 'credit', amount: $bankCharge,
                currency: 'KWD', originalAmount: $bankCharge, exchangeRate: 1.0,
                transactionType: 'BANK_CHARGE', description: $chargeNarration, ledgerType: 'bank',
            );
        }

        return new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: 'PV',
            subType: $bp->sub_type,
            docDate: $docDate,
            narration: $narration,
            lines: $lines,
            idempotencyKey: 'pv:'.$bp->id,
            sourceType: 'Payment',
            sourceId: $bp->id,
            userId: Auth::id(),
        );
    }

    /** The real, immediate bank outflow this document represents -- see class docblock's
     * "Cheque-issued vs direct-bank" note for why a cheque-instrument payment contributes 0 here. */
    private function bankOutflowFor(BankPayment $bp): float
    {
        $isChequeIssued = $bp->cheque_no !== null && $bp->cheque_no !== '';

        return round(($isChequeIssued ? 0.0 : (float) $bp->amount) + (float) ($bp->bank_charge_amount ?? 0), 3);
    }

    /**
     * w5-brief.md §W5.P "Bank balance pre-check -> TrialBalanceService read INSIDE the posting
     * transaction, refusing when pv_allow_overdraft=false and balance would go negative" -- replaces
     * HEAD's raw, unlocked `SUM(debit)-SUM(credit)` TOCTOU pre-check (w5-state.md §1 "PV document"
     * row). The row lock MUST be taken by the caller's own enclosing `DB::transaction()` (this
     * method itself does not open one) so the lock is held across {@see PostingSeam::post()}'s own
     * nested write.
     */
    private function assertOverdraftAllowed(int $companyId, int $bankAccountId, float $bankOutflow): void
    {
        if ($bankOutflow <= 0.0005) {
            return;
        }

        if (VoucherOptions::pvAllowOverdraft($companyId)) {
            return;
        }

        // Locks the bank leaf's row before reading its balance -- a concurrent payment against the
        // SAME leaf, inside its OWN enclosing transaction, blocks here until this one commits or
        // rolls back, closing the TOCTOU race a plain unlocked SUM could not.
        Account::withoutGlobalScopes()->where('id', $bankAccountId)->lockForUpdate()->first();

        $currentBalance = $this->trialBalance->getCurrentAccountBalance($companyId, $bankAccountId);
        $projected = round($currentBalance - $bankOutflow, 3);

        if ($projected < -0.0005) {
            throw new InsufficientBankBalanceException($bankAccountId, $currentBalance, $bankOutflow, $projected);
        }
    }

    /**
     * `transactions.reference_type` is the closed 4-value legacy ENUM (`Receipt|Invoice|Payment|
     * Refund`) -- mirrors PostingService::resolveReferenceType()'s exact precedence (an explicit,
     * already-valid `$draft->sourceType` wins; otherwise fall back to this docType map), matching
     * ReceiptVoucherController's own identical private copy for the OFF path. See PostingService's
     * own DOC_TYPE_REFERENCE_TYPE const docblock for the full rationale.
     */
    private function resolveLegacyReferenceType(DocumentDraft $draft): string
    {
        static $validReferenceTypes = ['Receipt', 'Invoice', 'Payment', 'Refund'];
        static $docTypeMap = [
            'INV' => 'Invoice', 'RV' => 'Receipt', 'PV' => 'Payment', 'CRN' => 'Refund',
            'DBN' => 'Payment', 'JV' => 'Invoice', 'OJV' => 'Invoice', 'REV' => 'Invoice', 'AST' => 'Payment',
        ];

        if (is_string($draft->sourceType) && in_array($draft->sourceType, $validReferenceTypes, true)) {
            return $draft->sourceType;
        }

        return $docTypeMap[$draft->docType] ?? 'Payment';
    }

    /**
     * OFF-path writer AND the shape `clear()` uses for its own no-legacy-precedent JV. Resolves
     * every line the SAME way the engine would ({@see AccountResolver}, never
     * `Account::where('name', ...)`) -- true OFF/ON parity, matching
     * ReceiptVoucherController::writeLegacyTransaction()'s own established convention.
     *
     * `branch_id` IS written here (unlike ReceiptVoucherController's own copy of this method, whose
     * legacy code never set it) -- HEAD's `BankPaymentController::storeJournalEntryEntries()`/
     * `store()` always wrote `journal_entries.branch_id` explicitly; dropping it here would be a
     * real OFF-path regression, not parity.
     */
    private function writeLegacyTransaction(DocumentDraft $draft, ?BankPayment $bp): Transaction
    {
        return DB::transaction(function () use ($draft, $bp) {
            [$number] = app(SequenceService::class)->next($draft->docType, $draft->companyId, $draft->branchId, $draft->docDate);

            $totalDebit = 0.0;
            $totalCredit = 0.0;
            foreach ($draft->lines as $line) {
                if ($line->side === 'debit') {
                    $totalDebit += $line->amount;
                } else {
                    $totalCredit += $line->amount;
                }
            }

            $displayName = $bp?->targetAccount?->name ?? $bp?->payFromAccount?->name ?? $number;

            $txn = Transaction::forceCreate([
                'company_id' => $draft->companyId,
                'branch_id' => $draft->branchId,
                'entity_id' => $draft->companyId,
                'entity_type' => 'company',
                'transaction_type' => $draft->docType,
                'amount' => $totalDebit,
                'description' => $draft->narration,
                'invoice_id' => $draft->invoiceId,
                'reference_type' => $this->resolveLegacyReferenceType($draft),
                'reference_number' => $number,
                'name' => $displayName,
                'transaction_date' => $draft->docDate,
                'doc_type' => $draft->docType,
                'sub_type' => $draft->subType,
                'doc_year' => (int) $draft->docDate->format('Y'),
                'posting_status' => 'posted',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'idempotency_key' => $draft->idempotencyKey,
                'created_by' => Auth::id(),
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ]);

            foreach ($draft->lines as $line) {
                $accountId = $line->accountId ?? $this->accountResolver->resolve($line->purposeCode, $draft->companyId)->id;

                JournalEntry::create([
                    'transaction_id' => $txn->id,
                    'company_id' => $draft->companyId,
                    'branch_id' => $draft->branchId > 0 ? $draft->branchId : null,
                    'account_id' => $accountId,
                    'invoice_id' => $line->invoiceId,
                    'transaction_date' => $draft->docDate,
                    'description' => $line->description,
                    'debit' => $line->side === 'debit' ? $line->amount : 0,
                    'credit' => $line->side === 'credit' ? $line->amount : 0,
                    'name' => $line->partyName ?? $displayName,
                    'type' => $line->ledgerType ?? $line->transactionType,
                    'type_reference_id' => $line->partyAccountRef,
                    'currency' => $line->currency,
                    'exchange_rate' => $line->exchangeRate,
                    'amount' => $line->amount,
                    'reconciled' => $line->reconciled ?? 0,
                    'voucher_number' => $number,
                    'cheque_no' => $line->chequeNo,
                    'cheque_date' => $line->chequeDate,
                    'cheque_clearance_date' => $line->chequeClearanceDate,
                    'bank_info' => $line->bankInfo,
                    'auth_no' => $line->authNo,
                ]);
            }

            return $txn;
        });
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W5.P helpers
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * SUPPLIER vs ACCOUNT (w5-brief.md §W5.L sub_type list) -- see class docblock's own note. Never
     * a name-LIKE lookup: walks `root_id` -> `withoutGlobalScopes()->find()`, one level, the same
     * primitive {@see AccountResolver::assertUnderBankGroup()} uses for its own ancestor walk.
     */
    private function resolveSubType(string $bankPaymentType, string $typeSelector, Account $targetAccount): string
    {
        if ($bankPaymentType === 'PaymentByDate') {
            return 'BY_DATE';
        }

        if ($typeSelector === 'bonus') {
            return 'BONUS';
        }

        if ($bankPaymentType === 'Refund') {
            return 'REFUND_OUT';
        }

        $rootName = $targetAccount->root_id
            ? Account::withoutGlobalScopes()->where('id', $targetAccount->root_id)->value('name')
            : null;

        return $rootName === 'Liabilities' ? 'SUPPLIER' : 'ACCOUNT';
    }

    /**
     * w5-brief.md §W5.P "PaymentByDate fast path: keep, but reconciled=2 set via engine line flag,
     * not raw column write" -- that half is done in {@see self::buildVoucherDraft()} via
     * `LineDraft::$reconciled`. This is the OTHER half HEAD's `store()` already did: marking the
     * PRE-EXISTING, already-posted liability journal_entries the user selected (HEAD's
     * `items[].transaction_id`, a misleadingly-named list of `journal_entries.id` values, persisted
     * here on `bank_payments.reconcile_journal_entry_ids`) as `reconciled = 1` /
     * `reconciled_ref_id = <this voucher's own new line>`. Deliberately kept as a raw column write
     * against those OTHER documents -- w5-brief.md's own Traps section: "reconciled/
     * reconciled_ref_id ... move behind a service method; P5.10 replaces" names this a future
     * concern, not this sub-wave's to build.
     */
    private function applyByDateReconciliation(BankPayment $bp, Transaction $transaction): void
    {
        $ids = collect($bp->reconcile_journal_entry_ids ?? [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $targetLine = JournalEntry::where('transaction_id', $transaction->id)
            ->where('account_id', $bp->target_account_id)
            ->first();

        if (! $targetLine) {
            return;
        }

        // W5.X item 3: the write itself now lives in ReconciliationService::reconcile() --
        // see that method's own docblock.
        $this->reconciliation->reconcile((int) $bp->company_id, (int) $bp->branch_id, $ids->all(), $targetLine->id);
    }

    /** Layer 1 (per-record) lock check -- {@see \App\Http\Traits\Lockable}, mirrors
     * ReceiptVoucherController::checkVoucherLocked()'s identical shape. */
    private function checkVoucherLocked(BankPayment $bp)
    {
        $transaction = $bp->transaction_id ? Transaction::withoutGlobalScopes()->find($bp->transaction_id) : null;

        if ($transaction && $transaction->isLocked() && ! $transaction->canModify()) {
            $lockedBy = $transaction->lockedByUser?->name ?? 'Unknown';
            $lockedAt = $transaction->locked_at?->format('d M Y H:i') ?? '';
            $message = "This payment voucher is locked by {$lockedBy} on {$lockedAt}. Contact your accountant to unlock it.";

            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }

            return redirect()->back()->with('error', $message);
        }

        return null;
    }

    /** Layer 3 (reconciled-line) belt-and-braces check -- the real enforcement is
     * `PostingService::reverse()`'s own `ProtectedLineException`; mirrors
     * ReceiptVoucherController::hasReconciledLines()'s identical shape. */
    private function hasReconciledLines(BankPayment $bp): bool
    {
        if (! $bp->transaction_id) {
            return false;
        }

        return JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('transaction_id', $bp->transaction_id)
            ->where('reconciled', 1)
            ->exists();
    }

    /** See ReceiptVoucherController::markTransactionReversed()'s own docblock -- identical
     * non-fillable-column rationale, `posting_status` is not mass-assignable. */
    private function markTransactionReversed(Transaction $transaction): void
    {
        $transaction->posting_status = 'reversed';
        $transaction->save();
    }

    /** DocumentDraft has no setter (every property is `readonly`) -- rebuilds an equivalent draft
     * with only `idempotencyKey` changed, for `update()`'s OFF-path repost branch. Mirrors
     * ReceiptVoucherController::withIdempotencyKeySuffix()'s identical convention. */
    private function withIdempotencyKeySuffix(DocumentDraft $draft, string $suffix): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $draft->companyId,
            branchId: $draft->branchId,
            docType: $draft->docType,
            subType: $draft->subType,
            docDate: $draft->docDate,
            narration: $draft->narration,
            lines: $draft->lines,
            idempotencyKey: $draft->idempotencyKey.$suffix,
            sourceType: $draft->sourceType,
            sourceId: $draft->sourceId,
            userId: $draft->userId,
        );
    }

    private function fillVoucherRow(BankPayment $bp, array $data): void
    {
        $bp->fill($data);
    }

    /**
     * store()'s per-item batch validator. Every item requires a real, same-company target account
     * (w5-state.md's own "post only the bank leg when no target resolves" gap is closed here rather
     * than reproduced -- see class docblock). Returns one normalized row array per submitted item,
     * each directly `BankPayment::$fillable`-shaped.
     *
     * @return array<int, array<string, mixed>>
     */
    private function validateVoucherRequest(Request $request): array
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'docdate' => ['required', 'date'],
            'bankpaymentref' => ['nullable', 'string', 'max:100'],
            'bankpaymenttype' => ['required', 'in:Payment,PaymentByDate,Refund'],
            'pay_from_account' => ['required', 'integer', 'exists:accounts,id'],
            'remarks_create' => ['nullable', 'string'],
            'internal_remarks' => ['nullable', 'string'],
            'remarks_fl' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type_selector' => ['nullable', 'string', 'in:none,account,bonus,refund,supplier'],
            'items.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'items.*.agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.credit' => ['required', 'numeric', 'min:0.001'],
            'items.*.cheque_no' => ['nullable', 'string', 'max:100'],
            'items.*.cheque_date' => ['nullable', 'date'],
            'items.*.bank_name' => ['nullable', 'string', 'max:200'],
            'items.*.auth_no' => ['nullable', 'string', 'max:100'],
            'items.*.bank_charge_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.cheque_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'items.*.transaction_id' => ['nullable'],
        ], [
            'items.*.account_id.required' => 'Every payment line requires a target account.',
            'items.*.account_id.exists' => 'The selected account code does not exist.',
            'pay_from_account.required' => 'Please select a bank account to pay from.',
        ]);

        // Tenant-lock hardening: the rule above only validates that company_id EXISTS, never that
        // it belongs to the authenticated user -- a logged-in user of company A could otherwise
        // POST company_id = B and create/update a bank payment (a money document that posts
        // journal entries) against company B, and every Account::withoutGlobalScopes() lookup
        // below trusts $companyId completely once tenant scoping is bypassed like that. Overwrite
        // the request-supplied value with the caller's own resolved company right after
        // validation and before anything below reads it. getCompanyId() already returns the
        // admin's session-selected company for Role::ADMIN (see app/Helper/helper.php), so an
        // admin acting on another company still works via that existing convention -- same as
        // assertSameCompanyOrUnscopedAdmin() elsewhere in this controller. Mirrors
        // ReceiptVoucherController::validateVoucherRequest()'s identical fix.
        $validated['company_id'] = getCompanyId(Auth::user());

        $companyId = (int) $validated['company_id'];
        $branchId = (int) $validated['branch_id'];

        $payFromAccount = Account::withoutGlobalScopes()
            ->where('id', $validated['pay_from_account'])
            ->where('company_id', $companyId)
            ->first();

        if (! $payFromAccount) {
            throw ValidationException::withMessages(['pay_from_account' => 'Invalid bank account for this company.']);
        }

        $this->accountResolver->assertUnderBankGroup($payFromAccount->id, $companyId);

        $bankPaymentType = $validated['bankpaymenttype'];
        $reconciledFastPath = $bankPaymentType === 'PaymentByDate';

        $rows = [];
        foreach ($validated['items'] as $item) {
            $amount = round((float) $item['credit'], 3);
            if ($amount <= 0.0005) {
                continue;
            }

            $targetAccount = Account::withoutGlobalScopes()
                ->where('id', $item['account_id'])
                ->where('company_id', $companyId)
                ->first();

            if (! $targetAccount) {
                throw ValidationException::withMessages(['items' => "Target account #{$item['account_id']} does not belong to this company."]);
            }

            $typeSelector = $item['type_selector'] ?? 'account';

            if ($typeSelector === 'bonus' && empty($item['agent_id'])) {
                throw ValidationException::withMessages(['items' => 'A bonus payment line requires an agent.']);
            }

            $rows[] = $this->buildRowData(
                $companyId, $branchId, $validated['docdate'], $bankPaymentType, $typeSelector,
                $payFromAccount, $targetAccount, $amount, $item, $reconciledFastPath,
                $validated['bankpaymentref'] ?? null,
                $item['remarks'] ?? ($validated['remarks_create'] ?? null),
                $validated['internal_remarks'] ?? null,
                $validated['remarks_fl'] ?? null,
                null,
            );
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['items' => 'At least one payment line with a positive amount is required.']);
        }

        return $rows;
    }

    /**
     * update()'s single-row validator. HEAD's `update()` accepted a multi-line `items[]` array
     * consolidated under one `transactions` row -- a structurally DIFFERENT shape from `store()`'s
     * own "one item = one document" convention (see class docblock), and one already inconsistent
     * with it at HEAD. Since `$id` now names exactly ONE `bank_payments` row (one document), this
     * takes flat fields for that one row instead, the same convention
     * ReceiptVoucherController::validateVoucherRequest() already uses for RV's own single-document
     * update(). W5.U reworks the edit screen to match.
     *
     * @return array<string, mixed>
     */
    private function validateSingleVoucherRequest(Request $request, BankPayment $existing): array
    {
        $validated = $request->validate([
            'docdate' => ['required', 'date'],
            'bankpaymenttype' => ['required', 'in:Payment,PaymentByDate,Refund'],
            'type_selector' => ['nullable', 'string', 'in:none,account,bonus,refund,supplier'],
            'pay_from_account' => ['required', 'integer', 'exists:accounts,id'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'amount' => ['required', 'numeric', 'min:0.001'],
            'cheque_no' => ['nullable', 'string', 'max:100'],
            'cheque_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'auth_no' => ['nullable', 'string', 'max:100'],
            'bank_charge_amount' => ['nullable', 'numeric', 'min:0'],
            'cheque_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'remarks_create' => ['nullable', 'string'],
            'internal_remarks' => ['nullable', 'string'],
            'remarks_fl' => ['nullable', 'string'],
        ]);

        $companyId = (int) $existing->company_id;

        $payFromAccount = Account::withoutGlobalScopes()
            ->where('id', $validated['pay_from_account'])
            ->where('company_id', $companyId)
            ->first();

        if (! $payFromAccount) {
            throw ValidationException::withMessages(['pay_from_account' => 'Invalid bank account for this company.']);
        }

        $this->accountResolver->assertUnderBankGroup($payFromAccount->id, $companyId);

        $targetAccount = Account::withoutGlobalScopes()
            ->where('id', $validated['account_id'])
            ->where('company_id', $companyId)
            ->first();

        if (! $targetAccount) {
            throw ValidationException::withMessages(['account_id' => 'Invalid target account for this company.']);
        }

        $typeSelector = $validated['type_selector'] ?? 'account';

        if ($typeSelector === 'bonus' && empty($validated['agent_id'])) {
            throw ValidationException::withMessages(['agent_id' => 'A bonus payment line requires an agent.']);
        }

        $amount = round((float) $validated['amount'], 3);

        return $this->buildRowData(
            $companyId, (int) $existing->branch_id, $validated['docdate'], $validated['bankpaymenttype'],
            $typeSelector, $payFromAccount, $targetAccount, $amount, [
                'agent_id' => $validated['agent_id'] ?? null,
                'cheque_no' => $validated['cheque_no'] ?? null,
                'cheque_date' => $validated['cheque_date'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'auth_no' => $validated['auth_no'] ?? null,
                'bank_charge_amount' => $validated['bank_charge_amount'] ?? null,
                'cheque_image' => $validated['cheque_image'] ?? null,
            ],
            $validated['bankpaymenttype'] === 'PaymentByDate',
            $existing->reference_ref,
            $validated['remarks_create'] ?? $existing->remarks,
            $validated['internal_remarks'] ?? $existing->remarks_internal,
            $validated['remarks_fl'] ?? $existing->remarks_fl,
            $existing->cheque_image_path,
        );
    }

    /**
     * Security fix (post-W5.U review): this used to trust `getClientOriginalExtension()` for the
     * stored filename and write to the PUBLIC disk (`uploads/cheques`, reachable unauthenticated
     * via `/storage`) -- an unrestricted upload with a client-controlled extension under the public
     * webroot. Delegates to {@see ChequeImageStore}, the ONE shared implementation this controller
     * and {@see ReceiptVoucherController} now both use: server-sniffed MIME whitelist, UUID
     * filename, PRIVATE `local` disk, served only through {@see self::chequeImage()}. Still takes
     * the already-resolved `UploadedFile` directly rather than pulling it off the request by field
     * name -- store()'s batch shape means each item's file lives at `items.{index}.cheque_image`, a
     * path this method's callers have already navigated to.
     */
    private function storeChequeImageFile(?\Illuminate\Http\UploadedFile $file, int $companyId): ?string
    {
        return $this->chequeImageStore->storeUploadedFile($file, $companyId);
    }

    /**
     * W5 cheque-image download hardening (NEW). See ReceiptVoucherController::chequeImage()'s own,
     * more fully-commented copy of this method for why the explicit tenant check below is required
     * IN ADDITION to `Gate::authorize('view', ...)` -- {@see \App\Policies\BankPaymentPolicy::view()}
     * falls through to the role-only `viewAny()` for every non-agent role.
     */
    public function chequeImage($id)
    {
        $bankPayment = BankPayment::findOrFail($id);
        Gate::authorize('view', $bankPayment);
        $this->assertSameCompanyOrUnscopedAdmin(Auth::user(), (int) $bankPayment->company_id);

        if (! $bankPayment->cheque_image_path) {
            abort(404, 'No cheque image on file for this payment voucher.');
        }

        return $this->chequeImageStore->streamResponse($bankPayment->cheque_image_path);
    }

    /** Mirrors ReceiptVoucherController::assertSameCompanyOrUnscopedAdmin()'s identical shape. */
    private function assertSameCompanyOrUnscopedAdmin($user, int $recordCompanyId): void
    {
        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ADMIN) {
            if ($companyId && (int) $companyId !== $recordCompanyId) {
                abort(403, 'Unauthorized action.');
            }

            return;
        }

        if ((int) $companyId !== $recordCompanyId) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Shared row-shaping logic for both validators above -- one `BankPayment::$fillable`-shaped
     * array per document.
     *
     * `$existingChequeImagePath` (W5.U): the row's already-stored path to fall back to when this
     * submission carries no new file -- same "don't silently erase a prior upload on an unrelated
     * edit" rule as ReceiptVoucherController::validateVoucherRequest()'s identical fallback.
     */
    private function buildRowData(
        int $companyId,
        int $branchId,
        string $docDate,
        string $bankPaymentType,
        string $typeSelector,
        Account $payFromAccount,
        Account $targetAccount,
        float $amount,
        array $item,
        bool $reconciledFastPath,
        ?string $referenceRef,
        ?string $remarks,
        ?string $remarksInternal,
        ?string $remarksFl,
        ?string $existingChequeImagePath = null,
    ): array {
        $subType = $this->resolveSubType($bankPaymentType, $typeSelector, $targetAccount);

        $uploadedChequeImage = $item['cheque_image'] ?? null;
        $chequeImagePath = $uploadedChequeImage instanceof \Illuminate\Http\UploadedFile
            ? $this->storeChequeImageFile($uploadedChequeImage, $companyId)
            : $existingChequeImagePath;

        $reconcileIds = [];
        if ($reconciledFastPath && ! empty($item['transaction_id'])) {
            $raw = $item['transaction_id'];
            $ids = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', (string) $raw)));
            $reconcileIds = array_values(array_unique(array_map('intval', $ids)));
        }

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'doc_date' => $docDate,
            'sub_type' => $subType,
            'pay_from_account_id' => $payFromAccount->id,
            'target_account_id' => $targetAccount->id,
            'agent_id' => $typeSelector === 'bonus' ? (int) ($item['agent_id'] ?? 0) : null,
            'amount' => $amount,
            'bank_charge_amount' => isset($item['bank_charge_amount']) && $item['bank_charge_amount'] !== null
                ? round((float) $item['bank_charge_amount'], 3) : null,
            'cheque_no' => $item['cheque_no'] ?? null,
            'cheque_date' => $item['cheque_date'] ?? null,
            'bank_info' => $item['bank_name'] ?? null,
            'auth_no' => $item['auth_no'] ?? null,
            'cheque_image_path' => $chequeImagePath,
            'reconcile_journal_entry_ids' => $reconcileIds !== [] ? $reconcileIds : null,
            'reference_ref' => $referenceRef,
            'remarks' => $remarks,
            'remarks_internal' => $remarksInternal,
            'remarks_fl' => $remarksFl,
        ];
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W5.X (w5-brief.md §W5.X item 3). See ReceiptVoucherController's identical block comment --
    // {@see ReconciliationService} is now the single implementation both controllers delegate to.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function fetchPaymentsByDate(Request $request)
    {
        $this->reconciliation->assertCanReconcile(Auth::user());

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (! $companyId) {
            return response()->json(['error' => 'Please select a company first.'], 400);
        }

        $branchIds = Branch::where('company_id', $companyId)->pluck('id')->toArray();

        $payments = $this->reconciliation->fetchPaymentsByDate(
            $companyId,
            $branchIds,
            $request->from,
            $request->to,
            $request->get('supplier'),
        );

        return response()->json($payments->values());
    }

    public function fetchJournalEntriesByIds(Request $request)
    {
        $this->reconciliation->assertCanReconcile(Auth::user());

        $id = $request->input('id');

        if (! $id) {
            return response()->json(['error' => 'Invalid or missing ID.'], 400);
        }

        return response()->json($this->reconciliation->fetchJournalEntriesByIds((int) $id));
    }

    public function declineReconcile($transactionId)
    {
        $this->reconciliation->assertCanReconcile(Auth::user());

        $this->reconciliation->declineReconcile((int) $transactionId);

        return response()->json(['success' => true]);
    }
}
