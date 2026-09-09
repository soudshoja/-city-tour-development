<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceReceiptStatus;
use App\Enums\InvoiceReceiptType;
use App\Exceptions\Accounting\PostingException;
use App\Exceptions\Accounting\UnresolvedReceiptCompanyException;
use App\Models\Account;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\ChequeImageStore;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ReceiptPostingRule;
use App\Services\Accounting\ReconciliationService;
use App\Services\Accounting\SequenceService;
use App\Services\Accounting\VoucherOptions;
use App\Services\Accounting\VoucherSubTypeGuard;
use App\Services\PaymentReceiptService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * W5.R (w5-brief.md §W5.R). Every posting action in this controller (`store()`'s auto-approve
 * fast path, `approve()`, `update()`'s reverse+repost, `delete()`'s reverse, `clear()`, `bounce()`)
 * builds a {@see DocumentDraft} and enters {@see PostingSeam::post()} -- never a bare
 * `JournalEntry::create()` -- so engine-OFF and engine-ON both go through the SAME account
 * resolution (purpose codes / explicit, tenant-checked account ids via {@see AccountResolver}),
 * satisfying "Kill name-LIKE lookups ('Accounts Receivable','Clients','Receipt Voucher Cash','Bank
 * Accounts')" unconditionally rather than only on the ON path.
 *
 * ── Why that is a documented deviation from literal OFF-path byte-parity ─────────────────────────
 * The brief's own two requirements for this sub-wave are in direct tension for one specific case:
 * "kill name-LIKE lookups" (unconditional) vs. "OFF path: legacy behaviour preserved through seam
 * (parity tests vs HEAD)". HEAD's OFF-path `approve()` resolved its target accounts BY the name-LIKE
 * lookups this brief orders killed -- and HEAD's 'account'-type branch posted only ONE journal leg
 * (an unbalanced single-sided entry, w5-state.md's own "Not enforced" row) which the engine's
 * `PostingService::post()` structurally cannot accept even as a legacy closure's own internal shape,
 * since {@see PostingSeam::post()} on the OFF path still calls that closure directly with no
 * balance check of its own, but a permanently-unbalanced document is not something a correctness
 * fix should reproduce. Preserving HEAD's byte-for-byte SQL here would require re-introducing the
 * exact defect this brief separately orders removed. Resolved in favour of the mandatory, named fix
 * item: "OFF-path parity" in this controller means the SAME real accounts move, in the SAME
 * direction, for the SAME business event as HEAD intended -- not literal reproduction of HEAD's own
 * account-resolution bugs. See {@see self::writeLegacyTransaction()}.
 *
 * ── Lifecycle (mirrors RefundController's already-shipped draft -> approve -> post pattern, W4.R) ─
 * `store()` always creates an `invoice_receipts` row in `status=pending` with `transaction_id=NULL`
 * -- nothing is posted yet, matching HEAD's own existing split (HEAD's `store()` never wrote the
 * RV's own payment journal entries either; only `approve()` did). If the voucher's amount is
 * `<=` the company's `voucher_approval_threshold` (see {@see VoucherOptions::approvalThreshold()}),
 * `store()` immediately calls the SAME posting routine `approve()` calls -- "auto-approve on post"
 * (w5-brief.md §W5.R). Otherwise the row stays `pending` until a human calls `approve()`.
 *
 * ── `$id` is `invoice_receipts.id` everywhere in this file (edit/update/approve/delete/clear/
 *    bounce) ─────────────────────────────────────────────────────────────────────────────────────
 * HEAD's `edit()`/`approve()` keyed `$id` off `transactions.id` (RV had no row of its own that could
 * exist before a `Transaction` did). Once a still-pending voucher has no `Transaction` row at all,
 * that convention cannot survive -- `invoice_receipts.id` is the one id that exists for a voucher in
 * EVERY lifecycle state. The pre-existing Blade screens (`resources/views/receipt-voucher/*`) link
 * these actions using the transaction id, per HEAD's convention, and are NOT rewired in this
 * sub-wave -- w5-brief.md's own §W5.U is the dedicated, later sub-wave that reworks those exact
 * screens against this new backend/id contract; until it lands, the pre-existing screens' action
 * links will not resolve to the intended row. This is the documented, intentional sequencing the
 * brief itself lays out ("every wave that introduces ... new documents must also ship a MINIMAL UI"
 * is W5.U's own decision note, not W5.R's).
 */
class ReceiptVoucherController extends Controller
{
    public function __construct(
        private readonly PostingSeam $seam,
        private readonly PostingService $postingService,
        private readonly AccountResolver $accountResolver,
        private readonly ReconciliationService $reconciliation,
        private readonly ChequeImageStore $chequeImageStore,
        // CT-A3 wave 2 (W2-2), R-CT3: WHICH receipt statuses post, which reverse, and WHICH
        // cash/bank leaf the instrument leg debits -- all from configured master data, never from
        // a constant in this controller. See ReceiptPostingRule's own docblock.
        private readonly ReceiptPostingRule $receiptRule,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', InvoiceReceipt::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $query = InvoiceReceipt::with(['transaction', 'invoice', 'client', 'account'])->latest('id');

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('voucher_number', 'like', "%{$search}%")
                    ->orWhere('cheque_no', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('transaction', fn ($t) => $t->where('reference_number', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($c) => $c->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $query->where('company_id', $companyId);
        } elseif ($user->role_id == Role::AGENT) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $query->where('company_id', $companyId);
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        $totalRecords = (clone $query)->count();
        $invoicereceiptvouchers = $query->paginate(10)->withQueryString();

        return view('receipt-voucher.index', compact(
            'invoicereceiptvouchers',
            'totalRecords',
        ));
    }

    public function create(Request $request)
    {
        Gate::authorize('create', InvoiceReceipt::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $company = Company::with('branches.agents')->find($companyId);
                $accounts = $company->branches->flatMap->accounts;
                $branches = $company->branches;
                $companies = $company;
            } else {
                $accounts = Account::all();
                $companies = Company::all();
                $branches = Branch::all();
            }

            $clients = $companyId
                ? \App\Models\Client::where('company_id', $companyId)->get()
                : \App\Models\Client::all();

            $refundNumbers = $companyId
                ? Refund::where('company_id', $companyId)->select('refund_number')->get()
                : Refund::select('refund_number')->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $company = Company::with('branches.agents')->find($companyId);
            $accounts = $company->branches->flatMap->accounts;
            $branches = $company->branches;
            $companies = $company;
            $clients = \App\Models\Client::all();
            $refundNumbers = Refund::where('company_id', $companyId)
                ->select('refund_number')
                ->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $company = Company::with('branches.agents')->find($companyId);
            $accounts = $company->branches->flatMap->accounts;
            $branches = $company->branches;
            $companies = $company;
            $clients = \App\Models\Client::all();
            $refundNumbers = Refund::where('company_id', $companyId)
                ->where('branch_id', $user->accountant->branch->id)
                ->select('refund_number')
                ->get();
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
        $rootIds = Account::whereIn('name', $rootNames)->pluck('id');

        $accpayreceives = Account::doesntHave('children')
            ->with('root')
            ->whereHas('parent', fn ($q) => $q->whereIn('root_id', $rootIds))
            ->get();

        $lastLevelAccounts = Account::doesntHave('children')
            ->with('root')
            ->whereHas('parent', fn ($q) => $q->whereIn('root_id', $rootIds))
            ->get();

        $rootIds = Account::where('name', 'Liabilities')->pluck('id');
        $suppliers = Account::doesntHave('children')
            ->with('root')
            ->whereIn('root_id', $rootIds)
            ->get();

        // W5.U fix: this used to be `Invoice::where('status', 'unpaid')->get()` with NO company
        // scoping at all -- every unpaid invoice across every tenant leaked into the allocation
        // picker this sub-wave now actually renders (HEAD never surfaced this list in a
        // cross-tenant-visible control, so the gap was latent). `invoices` has no `company_id`
        // column of its own (verified against the live schema) -- company is reached only via
        // `agent.branch.company`, same fix as edit()'s identical query just above.
        $unpaidInvoices = $companyId
            ? Invoice::whereHas('agent.branch', fn ($q) => $q->where('company_id', $companyId))->where('status', 'unpaid')->get()
            : Invoice::where('status', 'unpaid')->get();
        $oldItems = old('items') ?? [];

        // W5.U: the allocation-lines editor needs a real invoice picker (client + outstanding
        // amount), the instrument section needs a bank-leaf picker (mirrors
        // BankPaymentController::create()'s own identical "Bank Accounts" group walk), and the
        // remainder-disposition note needs the company's actual configured policy -- otherwise the
        // screen would either have nothing to bind these new fields to, or would show a hardcoded
        // string that silently drifts from what ReceiptVoucherController::buildVoucherDraft()
        // actually does. $companyId is null for an unscoped ADMIN -- VoucherOptions::overpayPolicy()
        // returns the config default in that case, same as it does for a company_id of 0.
        $bankAccounts = $this->bankLeavesFor($companyId);
        $overpayPolicy = VoucherOptions::overpayPolicy((int) ($companyId ?? 0));
        $approvalThreshold = VoucherOptions::approvalThreshold((int) ($companyId ?? 0));

        // CT-A3 wave 2 (W2-2), R-CT3. The payment methods this company has configured, each with
        // whether it actually names a bank account (`charges.acc_bank_id`). Rendered as the
        // "Received through" picker, and the ones with no account are labelled as such on the
        // screen rather than silently falling back to cash -- which is how a card receipt ended
        // up in CASH_IN_HAND in the first place.
        $settlementChannels = $this->settlementChannelsFor($companyId);

        return view('receipt-voucher.create', compact(
            'settlementChannels',
            'accounts',
            'companies',
            'branches',
            'suppliers',
            'accpayreceives',
            'lastLevelAccounts',
            'refundNumbers',
            'clients',
            'unpaidInvoices',
            'oldItems',
            'bankAccounts',
            'overpayPolicy',
            'approvalThreshold',
        ));
    }

    /**
     * W5.R store(). Always creates a `pending`, unposted `invoice_receipts` row -- see class
     * docblock's "Lifecycle" section. Auto-approves (posts immediately) only when
     * `voucher_approval_threshold` is honoured.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', InvoiceReceipt::class);

        $data = $this->validateVoucherRequest($request);

        if ($data['remainder_amount'] > 0.0005 && $data['remainder_policy'] === 'block') {
            return back()->with('error', sprintf(
                'This receipt would leave KWD %s unapplied, and this company blocks overpayment (invoice_overpay_cancel_policy=block). '
                .'Reduce the amount, add another allocation, or change the company setting.',
                number_format($data['remainder_amount'], 3)
            ))->withInput();
        }

        $invoiceReceipt = new InvoiceReceipt;
        $this->fillVoucherRow($invoiceReceipt, $data);
        $invoiceReceipt->status = InvoiceReceipt::STATUS_PENDING;
        $invoiceReceipt->save();

        $invoiceReceipt->voucher_number = 'RV-DRAFT-'.$invoiceReceipt->id;
        $invoiceReceipt->save();

        $threshold = VoucherOptions::approvalThreshold($data['company_id']);
        $autoApprove = $threshold !== null && $data['amount'] <= $threshold;

        if (! $autoApprove) {
            return redirect()->route('receipt-voucher.index')
                ->with('success', 'Receipt Voucher created and awaiting approval.');
        }

        try {
            $this->postVoucher($invoiceReceipt);
        } catch (PostingException $e) {
            Log::critical('accounting.rv_auto_approve_failed', [
                'invoice_receipt_id' => $invoiceReceipt->id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('receipt-voucher.index')
                ->with('error', 'Receipt Voucher saved as a draft; auto-approval failed: '.$e->getMessage());
        }

        $this->sendReceiptPdfIfRequested($invoiceReceipt);

        return redirect()->route('receipt-voucher.index')->with('success', 'Receipt Voucher Successfully Recorded and Approved.');
    }

    public function edit(Request $request, $id)
    {
        $user = Auth::user();

        $invoiceReceipt = InvoiceReceipt::with(['transaction', 'invoice', 'client', 'account'])->findOrFail($id);
        Gate::authorize('update', $invoiceReceipt);

        $journalEntries = $invoiceReceipt->transaction_id
            ? JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get()
            : collect();

        $companyId = $invoiceReceipt->company_id;
        $company = Company::with('branches.account', 'branches.agents')->find($companyId);
        if (! $company) {
            return redirect()->route('receipt-voucher.index')->with('error', 'Company not found.');
        }

        if (! in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)) {
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
            ->with('root')
            ->where('company_id', $companyId)
            ->whereHas('parent', fn ($q) => $q->whereIn('root_id', $rootIds))
            ->get();

        $liabilitiesRootIds = Account::where('name', 'Liabilities')
            ->where('company_id', $companyId)
            ->pluck('id');

        $suppliers = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereIn('root_id', $liabilitiesRootIds)
            ->get();

        // `invoices` has no `company_id` column of its own -- company is reached only via
        // `agent.branch.company` (verified against the live schema; the controller's own
        // create() action already scopes its `$unpaidInvoices` query the SAME way indirectly, via
        // `Invoice::where('status', 'unpaid')` with no company filter at all today). Also includes
        // this voucher's own already-applied invoice (if any) so the allocation editor can still
        // show/select it even after it flips to 'paid'.
        $unpaidInvoices = Invoice::whereHas('agent.branch', fn ($q) => $q->where('company_id', $companyId))
            ->where(fn ($q) => $q->where('status', 'unpaid')->orWhere('id', $invoiceReceipt->invoice_id))
            ->get();

        $clients = Client::where('company_id', $companyId)->get();

        // W5.U: same three additions as create() (bank-leaf picker, live policy text, threshold
        // display) plus the lock/reconcile READ state the brief's own verify criterion 3 requires
        // ("reconciled/locked vouchers cannot be edited or reversed through the screen") -- computed
        // here, once, from the SAME two checks the controller's own update()/destroy() actions
        // already enforce ({@see self::checkVoucherLocked()}/{@see self::hasReconciledLines()}), so
        // the view can grey out (never merely hide, per the brief) the Edit/Reverse controls without
        // duplicating that logic, and a Gate::denies() call per action so the buttons the view shows
        // actually match what a real re-submission would be allowed to do (not just role_id name-
        // matching against the front end's own guess).
        $bankAccounts = $this->bankLeavesFor($companyId);
        $overpayPolicy = VoucherOptions::overpayPolicy($companyId);
        $approvalThreshold = VoucherOptions::approvalThreshold($companyId);
        // W5.U fix (found by this sub-wave's own new edit()-page test coverage): $isLocked is a
        // pure DISPLAY flag ("is this record physically locked", never "can the CURRENT viewer
        // modify it") -- it must read the transaction's own `is_locked` column directly, never
        // through {@see self::checkVoucherLocked()}. That method exists to gate a MUTATING action
        // (update()/destroy()) and, via {@see \App\Http\Traits\Lockable::canModify()}, calls
        // `Gate::authorize('manageLocks', ...)` internally -- which THROWS an
        // AuthorizationException on denial rather than returning false (the exact same documented
        // quirk `InvoiceControllerW40Test`/`InvoiceControllerW3eTest` already rely on for
        // Invoice::canModify()). Calling it here to render a read-only badge on an ordinary GET
        // would 403 the WHOLE edit page for any accountant without the separate 'manageLocks'
        // ability the instant a voucher is locked -- exactly backwards from "show the badge so
        // they can see why", which is this field's entire purpose.
        $lockedTransaction = $invoiceReceipt->transaction_id
            ? Transaction::withoutGlobalScopes()->find($invoiceReceipt->transaction_id)
            : null;
        $isLocked = (bool) ($lockedTransaction?->isLocked());
        $isReconciled = $this->hasReconciledLines($invoiceReceipt);
        $canApprove = Gate::allows('approve', $invoiceReceipt);
        $canReverse = Gate::allows('delete', $invoiceReceipt) && ! $isLocked && ! $isReconciled;
        $canReconcile = Gate::allows('reconcile', $invoiceReceipt);
        $canEditFields = Gate::allows('update', $invoiceReceipt) && ($invoiceReceipt->isPending() || (! $isLocked && ! $isReconciled));

        // CT-A3 wave 2 (W2-2): same "Received through" picker as create(), so a channel recorded
        // at creation stays visible and editable rather than becoming an invisible column.
        $settlementChannels = $this->settlementChannelsFor($companyId);

        return view('receipt-voucher.edit', compact(
            'settlementChannels',
            'companies',
            'invoiceReceipt',
            'accounts',
            'branches',
            'suppliers',
            'accpayreceives',
            'journalEntries',
            'unpaidInvoices',
            'clients',
            'bankAccounts',
            'overpayPolicy',
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
     * W5.R update(). Pending row -> plain field update, no engine interaction (nothing was ever
     * posted). Posted (`approved`) row -> reverse+repost: blocked when any of the voucher's own
     * journal lines is `reconciled` (Layer 3) or the posted transaction's own `is_locked` flag is
     * set ({@see \App\Http\Traits\Lockable::isLocked()}, Layer 1, per-record). Period close
     * (Layer 2) is NOT checked here -- `PeriodGuard::assertOpen()` inside
     * `PostingService::post()`/`repost()`/`reverse()` enforces it automatically (w5-brief.md's own
     * "Period model" note).
     */
    public function update(Request $request, $id)
    {
        $invoiceReceipt = InvoiceReceipt::findOrFail($id);
        Gate::authorize('update', $invoiceReceipt);

        $data = $this->validateVoucherRequest($request, $invoiceReceipt);

        if ($data['remainder_amount'] > 0.0005 && $data['remainder_policy'] === 'block') {
            return back()->with('error', sprintf(
                'This receipt would leave KWD %s unapplied, and this company blocks overpayment.',
                number_format($data['remainder_amount'], 3)
            ))->withInput();
        }

        if ($invoiceReceipt->isPending()) {
            $this->fillVoucherRow($invoiceReceipt, $data);
            $invoiceReceipt->save();

            return redirect()->route('receipt-voucher.edit', $id)->with('success', 'Receipt Voucher Updated Successfully.');
        }

        if ($blocked = $this->checkVoucherLocked($invoiceReceipt)) {
            return $blocked;
        }

        if ($this->hasReconciledLines($invoiceReceipt)) {
            return redirect()->back()->with('error', 'This receipt voucher has a reconciled line and cannot be edited. Un-reconcile it first.');
        }

        // CT-A3 R2-3 SERVER FINDING: resolved, never the raw column — see companyIdFor().
        // `$this->seam->isEnabledFor(0)` is false for every legacy row, which silently routed
        // the whole edit/delete/bounce path down the LEGACY branch (delete the journal rows and
        // stamp the header) instead of posting a dated reversal.
        $companyId = self::companyIdFor($invoiceReceipt);
        $oldTransaction = $this->resolvePostedDocumentFor($invoiceReceipt);

        if ($oldTransaction === null) {
            // CT-A3 R2-3: was `findOrFail($invoiceReceipt->transaction_id)`, which threw a raw
            // ModelNotFoundException (a 404) on any row whose linkage column is missing -- the
            // entire `accounting:replay` backfill population before this fix. Named, not raw.
            Log::critical('accounting.rv_update_no_document', [
                'invoice_receipt_id' => $invoiceReceipt->id,
                'company_id' => $companyId,
                'idempotency_key' => 'rv:'.$invoiceReceipt->id,
            ]);

            return redirect()->back()->with('error', 'This receipt voucher has no posted document to edit.');
        }

        $engineOn = $this->seam->isEnabledFor($companyId);

        DB::beginTransaction();
        try {
            $this->undoAllocationsForVoucher($invoiceReceipt);

            $this->fillVoucherRow($invoiceReceipt, $data);
            $invoiceReceipt->save();

            $newDraft = $this->buildVoucherDraft($invoiceReceipt);

            if ($engineOn) {
                // R3 convention: once the engine is confirmed ON for this company, reverse()/
                // repost() are called on PostingService DIRECTLY, not through PostingSeam -- the
                // seam's own reason to exist (a legacy fallback) does not apply to a sub-step of an
                // already-ON call, matching RefundPostingService's own established precedent (see
                // that class's "Why PostingSeam is not used here" docblock).
                $posted = $this->postingService->repost($oldTransaction, $newDraft, $newDraft->docDate, Auth::id());
                $newTransactionId = $posted->transaction->id;
            } else {
                JournalEntry::where('transaction_id', $oldTransaction->id)->delete();
                $this->markTransactionReversed($oldTransaction);
                // KEY CONVENTION (mirrors PostingService::repost()'s own, undocumented-for-the-
                // legacy-path-until-now rule): buildVoucherDraft() always derives 'rv:'.$r->id --
                // the SAME key the row was originally posted under, since the row's own id never
                // changes across an update(). The just-reversed $oldTransaction still occupies
                // that key (this method never clears idempotency_key -- only PostingService's own
                // reverse() decides that, and this OFF-path branch intentionally does not touch
                // it, matching PostingService::reverse()'s own documented behaviour), so writing a
                // SECOND transaction under the identical key here would either collide on the real
                // unique index (ON-path parity) or, worse, silently make a LATER
                // findByIdempotencyKey()-style lookup return the wrong row. Suffixed exactly like
                // PostingService::repost()'s own convention so both paths' replacement keys are
                // derived identically.
                // CT-A3 R2-2: the replacement key comes from PostingService's ONE revision
                // convention ({base}:rev{n}, monotonic and derived from the ledger), not from a
                // second hard-coded ':repost:{id}' rule living here. Before R2-2 the ON path's
                // suffix was conditional and silently stopped applying from the second edit
                // onwards (verify R1 D6); this OFF path was safe by accident, because its suffix
                // was unconditional. Both now ask the same method, so the two paths cannot drift
                // and a company that flips the engine on mid-life keeps one key family per
                // document.
                $repostDraft = $this->withReplacementIdempotencyKey(
                    $newDraft,
                    $this->postingService->nextRepostIdempotencyKey(
                        (int) $newDraft->companyId,
                        (string) $oldTransaction->idempotency_key
                    )
                );
                $newTransactionId = $this->writeLegacyTransaction($repostDraft, $invoiceReceipt)->id;
            }

            $invoiceReceipt->transaction_id = $newTransactionId;
            $invoiceReceipt->save();

            if ($invoiceReceipt->type === InvoiceReceiptType::INVOICE->value) {
                $this->applyAllocationsToInvoices($invoiceReceipt, $this->resolveAllocations($invoiceReceipt));
            }

            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.rv_update_failed', [
                'invoice_receipt_id' => $id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to update receipt voucher: '.$e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to update receipt voucher: '.$e->getMessage());
        }

        return redirect()->route('receipt-voucher.edit', $id)->with('success', 'Receipt Voucher Updated Successfully (reversed and reposted).');
    }

    /**
     * W5.R approve(). The one place (besides store()'s auto-approve fast path, which calls the
     * SAME {@see self::postVoucher()}) that turns a `pending` voucher into a real, balanced,
     * posted document.
     */
    public function approve($id)
    {
        $invoiceReceipt = InvoiceReceipt::findOrFail($id);
        Gate::authorize('approve', $invoiceReceipt);

        if (! $invoiceReceipt->isPending()) {
            return redirect()->back()->with('error', 'This receipt voucher has already been actioned.');
        }

        try {
            $this->postVoucher($invoiceReceipt);
        } catch (PostingException $e) {
            Log::critical('accounting.rv_approve_failed', [
                'invoice_receipt_id' => $id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to approve: '.$e->getMessage());
        }

        $this->sendReceiptPdfIfRequested($invoiceReceipt);

        Log::info('Receipt Voucher for ID: '.$invoiceReceipt->id.' has been successfully approved');

        return redirect()->route('receipt-voucher.index')->with('success', 'Receipt Voucher has been marked as paid');
    }

    /**
     * W5.R delete() (NEW -- HEAD has no delete action for RV at all). Pending row -> hard delete
     * (nothing was ever posted). Posted row -> reverse(), same locked/reconciled guards as
     * update().
     */
    public function destroy($id)
    {
        $invoiceReceipt = InvoiceReceipt::findOrFail($id);
        Gate::authorize('delete', $invoiceReceipt);

        if ($invoiceReceipt->isPending()) {
            $invoiceReceipt->delete();

            return redirect()->route('receipt-voucher.index')->with('success', 'Draft receipt voucher deleted.');
        }

        if ($blocked = $this->checkVoucherLocked($invoiceReceipt)) {
            return $blocked;
        }

        if ($this->hasReconciledLines($invoiceReceipt)) {
            return redirect()->back()->with('error', 'This receipt voucher has a reconciled line and cannot be deleted. Un-reconcile it first.');
        }

        // CT-A3 R2-3 SERVER FINDING: resolved, never the raw column — see companyIdFor().
        // `$this->seam->isEnabledFor(0)` is false for every legacy row, which silently routed
        // the whole edit/delete/bounce path down the LEGACY branch (delete the journal rows and
        // stamp the header) instead of posting a dated reversal.
        $companyId = self::companyIdFor($invoiceReceipt);
        $oldTransaction = $this->resolvePostedDocumentFor($invoiceReceipt);

        if ($oldTransaction === null) {
            // CT-A3 R2-3, D7's mirror: `findOrFail($invoiceReceipt->transaction_id)` threw on
            // every replay-backfilled row, so those vouchers could not be reversed at all.
            Log::critical('accounting.rv_delete_no_document', [
                'invoice_receipt_id' => $invoiceReceipt->id,
                'company_id' => $companyId,
                'idempotency_key' => 'rv:'.$invoiceReceipt->id,
            ]);

            return redirect()->back()->with('error', 'This receipt voucher has no posted document to reverse.');
        }

        $engineOn = $this->seam->isEnabledFor($companyId);

        DB::beginTransaction();
        try {
            $this->undoAllocationsForVoucher($invoiceReceipt);

            if ($engineOn) {
                $this->postingService->reverse($oldTransaction, now(), Auth::id());
            } else {
                JournalEntry::where('transaction_id', $oldTransaction->id)->delete();
                $this->markTransactionReversed($oldTransaction);
            }

            $invoiceReceipt->status = InvoiceReceipt::STATUS_REVERSED;
            $invoiceReceipt->save();

            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.rv_delete_failed', [
                'invoice_receipt_id' => $id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to delete receipt voucher: '.$e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to delete receipt voucher: '.$e->getMessage());
        }

        return redirect()->route('receipt-voucher.index')->with('success', 'Receipt voucher reversed.');
    }

    /**
     * W5.R cheque clearance (w5-brief.md §W5.R "cheque with cheque_date > voucher date -> Dr 1215
     * / Cr AR; clearance (manual action, sets cheque_clearance_date) -> Dr bank / Cr 1215"). A
     * plain JV -- no RV sub_type is minted for this (see class docblock); it is not itself a new
     * receipt event, only a bank reclassification of money the RV already recorded.
     */
    public function clear(Request $request, $id)
    {
        $invoiceReceipt = InvoiceReceipt::findOrFail($id);
        Gate::authorize('reconcile', $invoiceReceipt);

        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'clearance_date' => ['nullable', 'date'],
        ]);

        if ($invoiceReceipt->cheque_no === null || $invoiceReceipt->cheque_clearance_date !== null) {
            return back()->with('error', 'This receipt voucher has no outstanding cheque to clear.');
        }

        // CT-A3 R2-3 SERVER FINDING: resolved, never the raw column — see companyIdFor().
        // `$this->seam->isEnabledFor(0)` is false for every legacy row, which silently routed
        // the whole edit/delete/bounce path down the LEGACY branch (delete the journal rows and
        // stamp the header) instead of posting a dated reversal.
        $companyId = self::companyIdFor($invoiceReceipt);
        $branchId = (int) $invoiceReceipt->branch_id;
        $clearanceDate = isset($data['clearance_date']) ? Carbon::parse($data['clearance_date']) : Carbon::now();
        $amount = round((float) $invoiceReceipt->amount, 3);

        $chequesInHand = $this->accountResolver->resolve('CHEQUES_IN_HAND', $companyId);

        // ── CT-A3 R2-7 — VERIFY-CT-A3-STACK-R1 §3.3 D9 and D9b ───────────────────────────────
        // This method credits CHEQUES_IN_HAND and debits a bank leaf UNCONDITIONALLY, on the
        // assumption that the receipt document put the money in the cheque float. It only ever
        // did for a genuinely POST-DATED cheque: {@see ReceiptPostingRule::instrumentAccountFor()}
        // debits CHEQUES_IN_HAND only when `cheque_date > docDate`, and a same-day, past-dated or
        // NULL-dated cheque takes the bank / channel / CASH_IN_HAND branch instead. Nothing
        // requires post-dating (`cheque_date` validates as `nullable|date`, with no `after:`) and
        // the UI offers "clear" for any cheque — so clearing one of those DEBITED THE BANK A SECOND
        // TIME and left the cheque float permanently negative by the cheque amount (D9).
        //
        // D9b is the same method with no status guard at all: it read neither `status` nor
        // `transaction_id`, so a PENDING (unposted) receipt could be "cleared", posting
        // `rv-clear:{id}` with no `rv:{id}` behind it — bank overstated, float negative, nothing on
        // AR, and the voucher then permanently unpostable via bounce().
        //
        // Both are closed by asking the LEDGER what this receipt actually did, structurally, rather
        // than assuming: find the live receipt document, and clear only if its instrument leg is
        // the cheque float. Never by description, and never by re-deriving the post-dating test —
        // re-deriving it would just be a second copy of the rule that produced the mismatch.
        $receiptDocument = $this->resolvePostedDocumentFor($invoiceReceipt);

        if ($receiptDocument === null || $receiptDocument->posting_status !== 'posted') {
            return back()->with('error',
                'This receipt voucher has no posted document, so there is nothing in the cheque float to clear. '
                .'Approve the voucher first.');
        }

        $instrumentLegOnFloat = JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('transaction_id', $receiptDocument->id)
            ->where('account_id', $chequesInHand->id)
            ->where('debit', '>', 0)
            ->exists();

        if (! $instrumentLegOnFloat) {
            Log::warning('accounting.rv_clear_refused_not_on_float', [
                'invoice_receipt_id' => $invoiceReceipt->id,
                'company_id' => $companyId,
                'transaction_id' => $receiptDocument->id,
                'cheques_in_hand_account_id' => $chequesInHand->id,
            ]);

            return back()->with('error',
                'This cheque was never held in the cheque float — the receipt already debited a bank or cash '
                .'account directly (it was not post-dated), so there is nothing to reclassify. Clearing it '
                .'would debit the bank a second time.');
        }
        $bankAccount = $this->accountResolver->assertUnderBankGroup((int) $data['bank_account_id'], $companyId);

        $narration = "Cheque clearance for Receipt Voucher #{$invoiceReceipt->id}";

        $lines = [
            // CT-A3 wave 2 (W2-2): both clearance legs carry the paying client as their party
            // and a canonical ledger type, for the same reason the receipt's own legs do -- a
            // cheque that moves from float to bank is still that client's money.
            new LineDraft(
                purposeCode: '', accountId: $bankAccount->id, side: 'debit', amount: $amount,
                currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                transactionType: 'CHEQUE_CLEARED', description: $narration,
                partyAccountRef: $invoiceReceipt->client_id,
                ledgerType: 'bank',
                chequeNo: $invoiceReceipt->cheque_no,
                chequeDate: $invoiceReceipt->cheque_date ? Carbon::parse($invoiceReceipt->cheque_date) : null,
                chequeClearanceDate: $clearanceDate,
            ),
            new LineDraft(
                purposeCode: '', accountId: $chequesInHand->id, side: 'credit', amount: $amount,
                currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                transactionType: 'CHEQUE_CLEARED', description: $narration,
                partyAccountRef: $invoiceReceipt->client_id,
                ledgerType: 'bank',
                chequeNo: $invoiceReceipt->cheque_no,
                chequeDate: $invoiceReceipt->cheque_date ? Carbon::parse($invoiceReceipt->cheque_date) : null,
                chequeClearanceDate: $clearanceDate,
            ),
        ];

        $draft = new DocumentDraft(
            companyId: $companyId, branchId: $branchId, docType: 'JV', subType: null,
            docDate: $clearanceDate, narration: $narration, lines: $lines,
            idempotencyKey: 'rv-clear:'.$invoiceReceipt->id, userId: Auth::id(),
        );

        try {
            $legacy = fn () => $this->writeLegacyTransaction($draft, null);
            $this->seam->post($draft, $legacy, 'receipt-voucher.clear');
        } catch (PostingException $e) {
            Log::critical('accounting.rv_clear_failed', ['invoice_receipt_id' => $id, 'message' => $e->getMessage()]);

            return back()->with('error', 'Failed to clear cheque: '.$e->getMessage());
        }

        $invoiceReceipt->update([
            'cheque_clearance_date' => $clearanceDate,
            'bank_account_id' => $bankAccount->id,
        ]);

        return back()->with('success', 'Cheque cleared.');
    }

    /**
     * W5.R cheque bounce (w5-brief.md §W5.R "bounce = reverse clearance + bounce-fee DBN to
     * client"). Reverses the clear() JV (found by its deterministic idempotency key, never by
     * description LIKE), then optionally drafts a client-recharge DBN for the bank's own bounce
     * fee.
     */
    public function bounce(Request $request, $id)
    {
        $invoiceReceipt = InvoiceReceipt::findOrFail($id);
        Gate::authorize('reconcile', $invoiceReceipt);

        $data = $request->validate([
            'bounce_fee_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($invoiceReceipt->cheque_clearance_date === null) {
            return back()->with('error', 'This receipt voucher has no cleared cheque to bounce.');
        }

        // CT-A3 R2-3 SERVER FINDING: resolved, never the raw column — see companyIdFor().
        // `$this->seam->isEnabledFor(0)` is false for every legacy row, which silently routed
        // the whole edit/delete/bounce path down the LEGACY branch (delete the journal rows and
        // stamp the header) instead of posting a dated reversal.
        $companyId = self::companyIdFor($invoiceReceipt);
        $branchId = (int) $invoiceReceipt->branch_id;

        $clearanceTransaction = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', 'rv-clear:'.$invoiceReceipt->id)
            ->first();

        DB::beginTransaction();
        try {
            $engineOn = $this->seam->isEnabledFor($companyId);

            if ($clearanceTransaction !== null) {
                if ($engineOn) {
                    $this->postingService->reverse($clearanceTransaction, now(), Auth::id());
                } else {
                    JournalEntry::where('transaction_id', $clearanceTransaction->id)->delete();
                    $this->markTransactionReversed($clearanceTransaction);
                }
            }

            // ── CT-A3 wave 2 (W2-2), R-CT3 ────────────────────────────────────────────────────
            // THE DEFECT THIS CLOSES. Until wave 2 this method reversed only the CLEARANCE
            // journal above -- the cheque came back out of the bank and into the cheques-in-hand
            // float -- and then stopped. The receipt document itself (`rv:{id}`: Dr cheque-in-hand
            // / Cr RECEIVABLE_CONTROL) stayed on the ledger and the invoice stayed `paid`. So a
            // bounced cheque left the agency showing a collected receivable, a settled invoice
            // and a permanent debit sitting in the cheque float for money it never received.
            //
            // A bounce is not a bank reclassification: the money never arrived. Under R-CT3 the
            // statuses that take a receipt back OFF the ledger are CONFIGURED
            // (`accounting.receipt.reversing_statuses`, which lists `bounced`), and the receipt
            // document is reversed through PostingService::reverse() -- a dated REV document,
            // never an UPDATE and never a delete -- with the invoice allocations undone so the
            // client owes the money again.
            $bounceDecision = $this->receiptRule->decide(InvoiceReceipt::STATUS_BOUNCED);

            // CT-A3 R2-3 — VERIFY-CT-A3-STACK-R1 §3.2 D7 (BLOCKER). This used to read
            // `&& $invoiceReceipt->transaction_id !== null`, while the status flip below sat
            // OUTSIDE the guard. `ReceiptReplaySource` never wrote that column back, so every
            // receipt the cutover backfill posted had a LIVE `rv:{id}` document and a NULL
            // transaction_id: the bounce marked the row `bounced`, left the receipt document on
            // the ledger and the invoice allocations `paid`, and reported success -- the exact
            // defect W2-2 exists to close, still open for the entire replay population.
            //
            // The replay now writes the linkage back (half 1 of the fix), but that only helps
            // FUTURE backfills; every row an earlier run already left behind still carries a NULL.
            // So the document is resolved by the receipt's own IDEMPOTENCY KEY FAMILY when the
            // column is missing or stale -- the fix shape the report itself names, and the same
            // structural lookup the clearance leg a few lines above already uses.
            if ($bounceDecision->shouldReverse) {
                $receiptTransaction = $this->resolvePostedDocumentFor($invoiceReceipt);

                if ($receiptTransaction !== null && $receiptTransaction->posting_status !== 'reversed') {
                    $this->undoAllocationsForVoucher($invoiceReceipt);

                    if ($engineOn) {
                        $this->postingService->reverse($receiptTransaction, now(), Auth::id());
                    } else {
                        JournalEntry::where('transaction_id', $receiptTransaction->id)->delete();
                        $this->markTransactionReversed($receiptTransaction);
                    }

                    Log::info('accounting.receipt.reversed_on_bounce', array_merge(
                        $bounceDecision->toLogContext(),
                        [
                            'invoice_receipt_id' => $invoiceReceipt->id,
                            'company_id' => $companyId,
                            'transaction_id' => $receiptTransaction->id,
                        ]
                    ));
                }
            }

            $invoiceReceipt->cheque_clearance_date = null;
            $invoiceReceipt->status = InvoiceReceipt::STATUS_BOUNCED;
            $invoiceReceipt->is_used = false;
            $invoiceReceipt->save();

            $bounceFee = round((float) ($data['bounce_fee_amount'] ?? 0), 3);

            if ($bounceFee > 0.0005) {
                $client = $invoiceReceipt->client_id ? Client::find($invoiceReceipt->client_id) : null;

                $narration = "Bounce fee recharge for Receipt Voucher #{$invoiceReceipt->id}";
                $lines = [
                    new LineDraft(
                        purposeCode: 'RECEIVABLE_CONTROL', accountId: null, side: 'debit', amount: $bounceFee,
                        currency: 'KWD', originalAmount: $bounceFee, exchangeRate: 1.0,
                        transactionType: 'CUSTOMERDEBITED', description: $narration,
                        partyAccountRef: $invoiceReceipt->client_id,
                    ),
                    new LineDraft(
                        purposeCode: 'BANK_CHARGES_EXPENSE', accountId: null, side: 'credit', amount: $bounceFee,
                        currency: 'KWD', originalAmount: $bounceFee, exchangeRate: 1.0,
                        transactionType: 'BOUNCE_FEE_RECOVERY', description: $narration,
                        partyAccountRef: $invoiceReceipt->client_id,
                        ledgerType: 'expense',
                    ),
                ];

                $draft = new DocumentDraft(
                    companyId: $companyId, branchId: $branchId, docType: 'DBN', subType: null,
                    docDate: Carbon::now(), narration: $narration, lines: $lines,
                    idempotencyKey: 'rv-bounce-fee:'.$invoiceReceipt->id, userId: Auth::id(),
                );

                $legacy = fn () => $this->writeLegacyTransaction($draft, $invoiceReceipt);
                $this->seam->post($draft, $legacy, 'receipt-voucher.bounce-fee');
            }

            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.rv_bounce_failed', ['invoice_receipt_id' => $id, 'message' => $e->getMessage()]);

            return back()->with('error', 'Failed to record bounce: '.$e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to record bounce: '.$e->getMessage());
        }

        return back()->with('success', 'Cheque bounce recorded.');
    }

    /**
     * IDOR fix (routes/web.php's own comment above the 'receipt-voucher' group has the full
     * history): this used to be a bare firstOrFail() on a guessable {companyId}/{voucherNumber}
     * pair reachable with NO auth and NO signature check at all, so an anonymous caller could
     * enumerate every receipt voucher in the system. Mirrors InvoiceController's own
     * isPublicInvoiceRequest()/authorizeStaffInvoiceAccess() split and RefundController's
     * identical isPublicRefundRequest() split exactly: the SIGNED '.public' route (validated by
     * the `signed` route middleware before this method ever runs) needs no further authorization
     * check here; the authenticated internal route is gated by ReceiptVoucherPolicy::view() PLUS
     * this controller's own assertSameCompanyOrUnscopedAdmin() (already used by chequeImage()) --
     * ReceiptVoucherPolicy::view() falls through to viewAny() for every non-agent role, which is
     * role-only and does NOT by itself confirm the voucher belongs to the caller's own company,
     * exactly the gap a cross-company leak needs.
     */
    public function show($companyId, $voucherNumber)
    {
        $invoiceReceipt = InvoiceReceipt::with([
            'invoice.client',
            'transaction',
        ])
            ->whereHas('transaction', function ($q) use ($companyId, $voucherNumber) {
                $q->where('company_id', $companyId)
                    ->where('reference_number', $voucherNumber);
            })
            ->firstOrFail();

        if (! $this->isPublicVoucherRequest()) {
            Gate::authorize('view', $invoiceReceipt);
            $this->assertSameCompanyOrUnscopedAdmin(Auth::user(), (int) $invoiceReceipt->company_id);
        }

        return view('receipt-voucher.show', compact('invoiceReceipt'));
    }

    /**
     * True when the current request came in through the signed 'receipt-voucher.show.public'
     * route rather than the authenticated 'receipt-voucher.show' route. Mirrors
     * InvoiceController::isPublicInvoiceRequest() / RefundController::isPublicRefundRequest()
     * exactly.
     */
    private function isPublicVoucherRequest(): bool
    {
        return str_ends_with((string) request()->route()?->getName(), '.public');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W5.R posting core
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The one place a `pending` voucher becomes `approved` and posted. Shared by `store()`'s
     * auto-approve fast path and `approve()`.
     */
    private function postVoucher(InvoiceReceipt $invoiceReceipt): Transaction
    {
        // CT-A3 wave 2 (W2-2), R-CT3. Which statuses put a receipt document on the ledger is
        // CONFIGURED (`accounting.receipt.posting_statuses` / `reversing_statuses`), not decided
        // by an `if` here. Two things this buys that the old hard-coded `isPending()` check did
        // not: a receipt that has already reached a REVERSING status (bounced/reversed/rejected)
        // can never be posted by a stray approve() -- the old code would happily have re-posted a
        // bounced cheque's receipt -- and a company that wants a different approval vocabulary
        // changes config rather than this controller.
        $reversing = $this->receiptRule->decideFor($invoiceReceipt);

        if ($reversing->shouldReverse) {
            throw new \RuntimeException(sprintf(
                'ReceiptVoucherController::postVoucher(): receipt voucher #%d is at status "%s", which '
                .'config("accounting.receipt.reversing_statuses") lists as a REVERSING status -- a '
                .'receipt at that status must not be posted.',
                $invoiceReceipt->id,
                $invoiceReceipt->status
            ));
        }

        $draft = $this->buildVoucherDraft($invoiceReceipt);

        $legacy = fn () => $this->writeLegacyTransaction($draft, $invoiceReceipt);

        $posted = $this->seam->post($draft, $legacy, 'receipt-voucher.'.strtolower((string) $draft->subType));

        $transaction = match (true) {
            $posted instanceof PostedDocument => $posted->transaction,
            $posted instanceof Transaction => $posted,
            // S1 short-circuit (PostingSeam docblock) -- the engine already posted this exact
            // (company_id, idempotency_key) before a kill-switch flip; find it instead of
            // double-posting via the legacy closure.
            $posted === null => Transaction::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('company_id', $draft->companyId)
                ->where('idempotency_key', $draft->idempotencyKey)
                ->firstOrFail(),
            default => throw new \RuntimeException('Unexpected PostingSeam::post() return type: '.get_debug_type($posted)),
        };

        $invoiceReceipt->transaction_id = $transaction->id;
        $invoiceReceipt->status = InvoiceReceipt::STATUS_APPROVED;
        $invoiceReceipt->is_used = true;
        $invoiceReceipt->save();

        if ($invoiceReceipt->type === InvoiceReceiptType::INVOICE->value) {
            $this->applyAllocationsToInvoices($invoiceReceipt, $this->resolveAllocations($invoiceReceipt));
        }

        return $transaction;
    }

    /**
     * Pure draft builder -- no side effects, no writes. Used for the engine path, for the
     * legacy closure ({@see self::writeLegacyTransaction()}), and for `update()`'s repost.
     *
     * RV always debits the instrument leg (cash/bank/cheque-float) and credits the target -- a
     * Receipt Voucher, by definition, is money coming IN, so the instrument side of the document
     * is unconditionally the debit leg regardless of which named account or purpose code it
     * credits. This resolves HEAD's own ambiguous debit/credit branching (w5-state.md's "Not
     * enforced" row) into one unambiguous rule.
     */
    public function buildVoucherDraft(InvoiceReceipt $r): DocumentDraft
    {
        // CT-A3 E2 fix (CT-F35): `invoice_receipts.company_id` is NULL on every legacy-imported
        // row (109 of 109, per CT-A2 §3.2) -- the old `(int) $r->company_id` cast turned that NULL
        // into the sentinel `0`, which the very next line's AccountResolver call always threw
        // UnmappedPurposeException for (no system_accounts mapping is ever seeded for company 0).
        // A non-positive company_id on the row itself now falls back to the resolvable chain
        // (invoice -> client/agent -> task -> account -> branch, see
        // self::resolveReceiptCompanyId()'s own docblock for the exact precedence and why); a row
        // that chain still cannot resolve throws the named UnresolvedReceiptCompanyException
        // rather than silently posting under company 0.
        $companyId = (int) $r->company_id;
        if ($companyId <= 0) {
            $resolvedCompanyId = self::resolveReceiptCompanyId($r);

            if ($resolvedCompanyId === null || $resolvedCompanyId <= 0) {
                throw new UnresolvedReceiptCompanyException((int) $r->id);
            }

            $companyId = $resolvedCompanyId;
        }
        $branchId = (int) $r->branch_id;
        $docDate = $r->doc_date ? Carbon::parse($r->doc_date) : Carbon::now();
        $amount = round((float) $r->amount, 3);

        $subType = match ($r->type) {
            InvoiceReceiptType::ACCOUNT->value => 'ACCOUNT',
            InvoiceReceiptType::INVOICE->value => 'INVOICE',
            InvoiceReceiptType::CREDIT->value => 'TOPUP',
            InvoiceReceiptType::IMPORT->value => 'IMPORT',
            default => throw new \InvalidArgumentException("Unsupported receipt voucher type for posting: {$r->type}"),
        };
        VoucherSubTypeGuard::assertValid('RV', $subType);

        // $companyId here is the RESOLVED value from above -- never re-derive company_id from $r
        // directly (see ReceiptPostingRule::instrumentAccountFor()'s own signature): $r->company_id
        // itself may still be NULL/0 on a legacy row that was only resolved via the fallback chain
        // above, and this row is deliberately never mutated/persisted as a side effect of posting
        // (that is accounting:repair-receipt-company's job, not this method's).
        // CT-A3 wave 2 (W2-2). The instrument leg is resolved by {@see ReceiptPostingRule::
        // instrumentAccountFor()} -- post-dated cheque -> CHEQUES_IN_HAND, else the operator's
        // explicit bank_account_id, else THE CONFIGURED PAYMENT-METHOD ACCOUNT for this receipt's
        // settlement_channel (charges.acc_bank_id), and only then the configured fallback purpose.
        // Before wave 2 the third step did not exist and every card/gateway/transfer receipt
        // without an explicit bank account landed in CASH_IN_HAND -- the hard-coded constant
        // owner ruling R-CT3 forbids.
        $instrumentAccount = $this->receiptRule->instrumentAccountFor($r, $docDate, $companyId);
        $chequeDate = $r->cheque_date ? Carbon::parse($r->cheque_date) : null;

        $lines = [];
        $narration = '';

        $debitLine = function (string $desc) use ($r, $instrumentAccount, $amount, $chequeDate): LineDraft {
            return new LineDraft(
                purposeCode: '',
                accountId: $instrumentAccount->id,
                side: 'debit',
                amount: $amount,
                currency: 'KWD',
                originalAmount: $amount,
                exchangeRate: 1.0,
                transactionType: 'RECEIPT',
                description: $desc,
                partyAccountRef: $r->client_id,
                chequeNo: $r->cheque_no,
                chequeDate: $chequeDate,
                bankInfo: $r->bank_info,
                authNo: $r->auth_no,
            );
        };

        switch ($r->type) {
            case InvoiceReceiptType::ACCOUNT->value:
                // Tenant isolation (security fix): validateVoucherRequest() only checks
                // 'exists:accounts,id' on account_id, never that the account belongs to THIS
                // voucher's own company -- so without this, a Receipt Voucher could be pointed
                // at another tenant's account and post real money (a debit/credit journal-entry
                // pair) into that other company's chart of accounts. Mirrors
                // BankPaymentController::validateVoucherRequest()'s identical
                // company-scoped account lookup; scoped to $companyId (this voucher's own,
                // resolved above from $r->company_id, itself already locked to the caller's
                // company by validateVoucherRequest()'s own fix).
                $account = Account::withoutGlobalScopes()
                    ->where('id', $r->account_id)
                    ->where('company_id', $companyId)
                    ->first();

                if (! $account) {
                    throw ValidationException::withMessages(['account_id' => 'The selected account does not belong to this company.']);
                }

                $narration = "Receipt Voucher - Account: {$account->name}";
                $lines[] = $debitLine($narration);
                $lines[] = new LineDraft(
                    purposeCode: '', accountId: $account->id, side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'RECEIPT', description: $narration,
                    // CT-A3 wave 2 (W2-2): party on EVERY receipt line, not only the instrument
                    // leg. CT-A1 CT-F26 measured party attribution missing across the legacy
                    // ledger and CT-A2 §0 item 2 records the engine closing it "on 100% of AR and
                    // AP lines" -- this leg was the one receipt line still carrying none, so an
                    // account-type receipt could not be attributed to the client who paid it and
                    // the AR-control-vs-party reconciliation (CT-A3-WAVE1 §4.4) had a blind spot.
                    partyAccountRef: $r->client_id,
                    ledgerType: 'receivable',
                );
                break;

            case InvoiceReceiptType::INVOICE->value:
                $allocations = $this->resolveAllocations($r);
                $narration = 'Receipt Voucher - Invoice payment';
                $lines[] = $debitLine($narration);

                foreach ($allocations as $alloc) {
                    $invoice = Invoice::find($alloc['invoice_id']);
                    $allocAmount = round((float) $alloc['amount'], 3);

                    $lines[] = new LineDraft(
                        purposeCode: 'RECEIVABLE_CONTROL', accountId: null, side: 'credit',
                        amount: $allocAmount, currency: 'KWD', originalAmount: $allocAmount, exchangeRate: 1.0,
                        transactionType: 'CUSTOMERCREDITED',
                        description: 'Payment for invoice '.($invoice->invoice_number ?? $alloc['invoice_id']),
                        partyAccountRef: $r->client_id ?? $invoice?->client_id,
                        invoiceId: (int) $alloc['invoice_id'],
                    );
                }

                $remainder = round((float) $r->remainder_amount, 3);
                if ($remainder > 0.0005) {
                    $lines[] = new LineDraft(
                        purposeCode: 'CLIENT_ADVANCE', accountId: null, side: 'credit',
                        amount: $remainder, currency: 'KWD', originalAmount: $remainder, exchangeRate: 1.0,
                        transactionType: 'CLIENT_ADVANCE',
                        description: 'Unapplied receipt amount held as client credit ('.$r->remainder_policy.')',
                        partyAccountRef: $r->client_id,
                    );
                }
                break;

            case InvoiceReceiptType::CREDIT->value:
                // W6.S fix round: a `task_id`-tagged credit receipt is a deposit against an
                // on-hold/confirmed task (w6-brief.md "Hold/confirmed follow-up lifecycle" item 3)
                // -- same Dr instrument / Cr CLIENT_ADVANCE (2632) shape as a plain client credit
                // top-up, just carrying the task's own reference in the narration and
                // `journal_entries.task_id` (LineDraft's pre-existing `$taskId` field) so it can be
                // (a) summed for the W6.U follow-up tab's "deposit held" column and (b)
                // auto-applied to the task's invoice by TaskStatusService::issue() in W6.I.
                $task = $r->task_id ? Task::find($r->task_id) : null;
                $narration = $task
                    ? 'Receipt Voucher - Deposit against task '.($task->reference ?? $task->id)
                    : 'Receipt Voucher - Client credit top-up';
                $lines[] = $debitLine($narration);
                $lines[] = new LineDraft(
                    purposeCode: 'CLIENT_ADVANCE', accountId: null, side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'CLIENT_ADVANCE', description: $narration, partyAccountRef: $r->client_id,
                    taskId: $r->task_id,
                );
                break;

            case InvoiceReceiptType::IMPORT->value:
                $narration = 'Receipt Voucher - Imported historical receipt';
                $lines[] = $debitLine($narration);
                $lines[] = new LineDraft(
                    purposeCode: 'CLIENT_ADVANCE', accountId: null, side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'CLIENT_ADVANCE', description: $narration, partyAccountRef: $r->client_id,
                );
                break;
        }

        $allocations = $r->type === InvoiceReceiptType::INVOICE->value ? $this->resolveAllocations($r) : [];

        return new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: 'RV',
            subType: $subType,
            docDate: $docDate,
            narration: $narration,
            lines: $lines,
            idempotencyKey: 'rv:'.$r->id,
            sourceType: 'Receipt',
            sourceId: $r->id,
            invoiceId: $allocations !== [] ? (int) $allocations[0]['invoice_id'] : null,
            userId: Auth::id(),
        );
    }

    /**
     * `transactions.reference_type` is the closed 4-value legacy ENUM (`Receipt|Invoice|Payment|
     * Refund` -- {@see DocumentDraft}'s own docblock, {@see PostingService}'s
     * `DOC_TYPE_REFERENCE_TYPE` const). Mirrors `PostingService::resolveReferenceType()`'s exact
     * precedence -- an explicit, already-valid `$draft->sourceType` wins; otherwise fall back to
     * this docType map -- so the OFF path writes the SAME value the ON path would (true OFF/ON
     * parity, not just for RV). Fix (post-verify, discovered via this fix's own new INV/JV OFF-path
     * test coverage): this used to be a bare `$draft->docType === 'RV' ? 'Receipt' : $draft->docType`
     * ternary, which wrote the RAW docType string (e.g. 'INV', 'JV', 'DBN') straight into a strict
     * MySQL ENUM column for every OTHER docType -- silently correct only for the one docType
     * (`RV`) every pre-existing caller of this method ever exercised on the OFF path; `clear()`'s
     * `JV` and `bounce()`'s `DBN` legacy-fee document carried the identical latent defect, simply
     * never covered by a test that ran them with the engine OFF until now.
     */
    private function resolveLegacyReferenceType(DocumentDraft $draft): string
    {
        static $validReferenceTypes = ['Receipt', 'Invoice', 'Payment', 'Refund'];
        static $docTypeMap = [
            'INV' => 'Invoice',
            'RV' => 'Receipt',
            'PV' => 'Payment',
            'CRN' => 'Refund',
            'DBN' => 'Payment',
            'JV' => 'Invoice',
            'OJV' => 'Invoice',
            'REV' => 'Invoice',
            'AST' => 'Payment',
        ];

        if (is_string($draft->sourceType) && in_array($draft->sourceType, $validReferenceTypes, true)) {
            return $draft->sourceType;
        }

        return $docTypeMap[$draft->docType] ?? 'Receipt';
    }

    /**
     * OFF-path writer AND the shape both `clear()`/`bounce()` use for their own new, no-legacy-
     * precedent documents. Resolves every line the SAME way the engine would ({@see
     * AccountResolver}, never `Account::where('name', ...)`) -- see class docblock's "Why that is
     * a documented deviation" note for why this is correct rather than a compromise.
     */
    private function writeLegacyTransaction(DocumentDraft $draft, ?InvoiceReceipt $r): Transaction
    {
        return DB::transaction(function () use ($draft, $r) {
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

            $displayName = $r?->client?->full_name ?? $r?->account?->name ?? $number;

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
                    'branch_id' => $draft->branchId,
                    'account_id' => $accountId,
                    'invoice_id' => $line->invoiceId,
                    'transaction_date' => $draft->docDate,
                    'description' => $line->description,
                    'debit' => $line->side === 'debit' ? $line->amount : 0,
                    'credit' => $line->side === 'credit' ? $line->amount : 0,
                    'name' => $displayName,
                    'type' => $line->transactionType,
                    'type_reference_id' => $line->partyAccountRef,
                    'currency' => $line->currency,
                    'exchange_rate' => $line->exchangeRate,
                    'amount' => $line->amount,
                    'voucher_number' => $number,
                    'cheque_no' => $line->chequeNo,
                    // Timezone-safety fix (accounting-builds, 2026-09-02): journal_entries.cheque_date
                    // lost its DB-level useCurrent() default (migration
                    // 2026_09_02_000009_drop_db_clock_defaults_in_accounting_tables.php -- UTC DB
                    // clock vs Asia/Kuwait APP_TIMEZONE). Same OFF/ON parity fallback as
                    // PostingService::post() step 8 / BankPaymentController::writeLegacyTransaction():
                    // a real cheque line (chequeNo set) with no explicit date gets the app clock's
                    // now(); a non-cheque line still gets NULL.
                    'cheque_date' => $line->chequeDate ?? ($line->chequeNo !== null ? now() : null),
                    'cheque_clearance_date' => $line->chequeClearanceDate,
                    'bank_info' => $line->bankInfo,
                    'auth_no' => $line->authNo,
                ]);
            }

            return $txn;
        });
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W5.R helpers
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * CT-A3 E2 (CT-F35) fix vehicle, half (a): derives a positive company id for an
     * `invoice_receipts` row whose own `company_id` column is NULL/non-positive (every one of the
     * 109 legacy-imported rows CT-A2 §3.2 found). PUBLIC and STATIC deliberately -- this is the
     * ONE implementation of the resolution chain; {@see \App\Console\Commands::class} 's sibling
     * data-repair command (`accounting:repair-receipt-company`, half (b) of this same fix) calls
     * this exact method rather than re-implementing the chain a second time, so the two halves of
     * the fix can never drift out of agreement on what a given row resolves to. Static because it
     * needs no controller-instance state (no injected service) -- it is a pure function of the row
     * and the database.
     *
     * Precedence (owner-specified order: "invoice -> client/agent -> task -> account -> branch"):
     *
     *  1. Invoice -- `invoice_id` if populated, else the first allocation's `invoice_id` (mirrors
     *     {@see self::resolveAllocations()}'s own fallback for an INVOICE-type receipt whose own
     *     `invoice_id` column was never populated). `Invoice` has no `company_id` column of its
     *     own -- resolved via `agent->branch->company_id`, the SAME chain {@see \App\Models\
     *     Invoice}'s own methods already use (e.g. its `toArray()`-adjacent `'company_id' =>
     *     $this->agent?->branch?->company_id`).
     *  2. Client -- `client_id`'s own `company_id` column first (the common case), then, if that
     *     is itself null/non-positive, `client->agent->branch->company_id` (a client whose own
     *     `company_id` was never backfilled can still be reachable through its agent).
     *  3. Task -- `task_id`'s own `company_id` column (Task carries one directly).
     *  4. Account -- `account_id`, then `bank_account_id`, each via `accounts.company_id`.
     *     Queried `withoutGlobalScopes()` -- Account is the one model in this chain carrying a
     *     `BelongsToCompany` global scope (see {@see \App\Traits\BelongsToCompany}), and this
     *     method must behave identically with or without an authenticated request context, same
     *     requirement {@see \App\Services\Accounting\AccountResolver}'s own class docblock states
     *     for exactly this reason.
     *  5. Branch -- `branch_id`'s own `company_id` column, last: every `invoice_receipts` row
     *     carries a `branch_id`, but a branch alone says only "which office", not necessarily
     *     which of possibly-several companies that office serves in a chart shared across a
     *     migration -- the four links above are more specific to the money itself and are
     *     preferred whenever any of them resolves.
     *
     * Returns null (never `0`) when none of the above resolves to a positive company id --
     * callers must treat null as "unresolved" and refuse to post/backfill under the `0` sentinel
     * (see {@see UnresolvedReceiptCompanyException} and `accounting:repair-receipt-company`'s own
     * UNRESOLVED reporting).
     */
    public static function resolveReceiptCompanyId(InvoiceReceipt $r): ?int
    {
        $invoiceId = $r->invoice_id;
        if (! $invoiceId && is_array($r->allocations) && $r->allocations !== []) {
            $invoiceId = (int) ($r->allocations[0]['invoice_id'] ?? 0) ?: null;
        }

        if ($invoiceId) {
            $invoice = Invoice::with('agent.branch')->find($invoiceId);
            $companyId = (int) ($invoice?->agent?->branch?->company_id ?? 0);
            if ($companyId > 0) {
                return $companyId;
            }
        }

        if ($r->client_id) {
            $client = Client::with('agent.branch')->find($r->client_id);

            $companyId = (int) ($client?->company_id ?? 0);
            if ($companyId > 0) {
                return $companyId;
            }

            $companyId = (int) ($client?->agent?->branch?->company_id ?? 0);
            if ($companyId > 0) {
                return $companyId;
            }
        }

        if ($r->task_id) {
            $companyId = (int) (Task::where('id', $r->task_id)->value('company_id') ?? 0);
            if ($companyId > 0) {
                return $companyId;
            }
        }

        foreach ([$r->account_id, $r->bank_account_id] as $accountId) {
            if ($accountId) {
                $companyId = (int) (Account::withoutGlobalScopes()->where('id', $accountId)->value('company_id') ?? 0);
                if ($companyId > 0) {
                    return $companyId;
                }
            }
        }

        if ($r->branch_id) {
            $companyId = (int) (Branch::where('id', $r->branch_id)->value('company_id') ?? 0);
            if ($companyId > 0) {
                return $companyId;
            }
        }

        return null;
    }

    /**
     * W5.U helper -- the instrument section's bank-leaf `<select>` needs the same "leaves under the
     * 'Bank Accounts' group" list {@see BankPaymentController::create()}/`edit()` already build for
     * PV; RV's own `create()`/`edit()` never needed it before this sub-wave since HEAD's RV screen
     * had no bank-leaf picker at all (a bare account/invoice/client datalist only). Deliberately a
     * plain parent-name walk, never a name-LIKE lookup on the LEAF itself -- matches
     * {@see AccountResolver::assertUnderBankGroup()}'s own structural convention.
     *
     * @return \Illuminate\Support\Collection<int, Account>
     */
    /**
     * CT-A3 wave 2 (W2-2). The company's configured payment methods, for the "Received through"
     * picker. Reads `charges` -- the master data that already carries `acc_bank_id`, the account
     * an operator sets per payment method -- rather than inventing a second channel vocabulary.
     * `has_account` is surfaced so the screen can say "no account configured" out loud.
     *
     * @return \Illuminate\Support\Collection<int, object{name: string, has_account: bool}>
     */
    private function settlementChannelsFor(?int $companyId): \Illuminate\Support\Collection
    {
        if (! $companyId) {
            return collect();
        }

        // `charges` carries no `deleted_at` column (2024_10_29_032151_create_charges_table and
        // its four follow-ups) -- no soft-delete filter here, deliberately, rather than a
        // whereNull() that would throw on a table that has no such column.
        return \App\Models\Charge::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'acc_bank_id', 'is_active'])
            ->map(fn ($charge) => (object) [
                'name' => (string) $charge->name,
                'has_account' => $charge->acc_bank_id !== null,
                'is_active' => (bool) $charge->is_active,
            ]);
    }

    private function bankLeavesFor(?int $companyId): \Illuminate\Support\Collection
    {
        if (! $companyId) {
            return collect();
        }

        $assetsRoot = Account::where('name', 'Assets')->where('company_id', $companyId)->first();
        if (! $assetsRoot) {
            return collect();
        }

        $bankParent = Account::where('parent_id', $assetsRoot->id)
            ->where('name', 'Bank Accounts')
            ->where('company_id', $companyId)
            ->first();

        if (! $bankParent) {
            return collect();
        }

        return Account::where('parent_id', $bankParent->id)->where('company_id', $companyId)->get();
    }

    /**
     * Security fix (post-W5.U review): this used to trust `getClientOriginalExtension()` for the
     * stored filename and write to the PUBLIC disk (`uploads/cheques`, reachable unauthenticated
     * via `/storage`) -- an unrestricted upload with a client-controlled extension under the
     * public webroot. Delegates to {@see ChequeImageStore}, the ONE shared implementation this
     * controller and {@see BankPaymentController} now both use: server-sniffed MIME whitelist,
     * UUID filename, PRIVATE `local` disk, served only through {@see self::chequeImage()}. Returns
     * null when no file was submitted -- callers fall back to the existing stored path (an
     * update() that doesn't replace the cheque image must not silently erase it).
     */
    private function storeChequeImage(Request $request, int $companyId, string $field = 'cheque_image'): ?string
    {
        return $this->chequeImageStore->storeFromRequest($request, $companyId, $field);
    }

    /**
     * W5 cheque-image download hardening (NEW). The old blade view linked straight to
     * `Storage::url($r->cheque_image_path)` on the public disk -- anyone with the URL, no
     * authentication, no company check. This route requires 'auth' + 'module:accounting'
     * (route middleware) plus the same `view` ability {@see \App\Policies\ReceiptVoucherPolicy}
     * already exposes, PLUS an explicit company-tenant check below: `ReceiptVoucherPolicy::view()`
     * falls through to `viewAny()` for every non-agent role, which is role-only and does NOT by
     * itself confirm the voucher belongs to the caller's own company -- exactly the gap a
     * cross-company image leak needs. Streams via {@see ChequeImageStore::streamResponse()}, never
     * a raw redirect to a storage URL.
     */
    public function chequeImage($id)
    {
        $invoiceReceipt = InvoiceReceipt::findOrFail($id);
        Gate::authorize('view', $invoiceReceipt);
        $this->assertSameCompanyOrUnscopedAdmin(Auth::user(), (int) $invoiceReceipt->company_id);

        if (! $invoiceReceipt->cheque_image_path) {
            abort(404, 'No cheque image on file for this receipt voucher.');
        }

        return $this->chequeImageStore->streamResponse($invoiceReceipt->cheque_image_path);
    }

    /**
     * Same "only scope when a company is actually selected" convention `index()` already uses for
     * Role::ADMIN (an unscoped admin browsing every company is intentional there); every other
     * role must match the record's own `company_id` exactly. Aborts 403 rather than returning a
     * bool -- the one caller ({@see self::chequeImage()}) always wants an immediate stop, and mirrors
     * this controller's own `Gate::authorize()` calls, which do the same.
     */
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
     * @return array<int, array{invoice_id:int, amount:float}>
     */
    private function resolveAllocations(InvoiceReceipt $r): array
    {
        if (is_array($r->allocations) && $r->allocations !== []) {
            return array_map(
                static fn ($row) => ['invoice_id' => (int) $row['invoice_id'], 'amount' => (float) $row['amount']],
                $r->allocations
            );
        }

        // Defensive fallback only -- both createReceiptVoucher() and autoGenerate() already
        // populate `allocations` themselves (see those methods), so this branch exists purely for
        // any row that somehow predates that column (e.g. a row created before the W5.R
        // migration ran) and only ever had a bare `invoice_id`.
        if ($r->invoice_id) {
            return [['invoice_id' => (int) $r->invoice_id, 'amount' => (float) $r->amount]];
        }

        return [];
    }

    private function applyAllocationsToInvoices(InvoiceReceipt $r, array $allocations): void
    {
        foreach ($allocations as $alloc) {
            $invoice = Invoice::find($alloc['invoice_id']);
            if (! $invoice) {
                continue;
            }

            $partial = InvoicePartial::where('receipt_voucher_id', $r->id)->where('invoice_id', $invoice->id)->first();
            if ($partial) {
                $partial->update(['amount' => $alloc['amount'], 'status' => 'paid']);
            } else {
                InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'service_charge' => 0,
                    'amount' => $alloc['amount'],
                    'status' => 'paid',
                    'type' => 'full',
                    'payment_gateway' => 'Cash',
                    'receipt_voucher_id' => $r->id,
                ]);
            }

            $totalPaid = (float) InvoicePartial::where('invoice_id', $invoice->id)->where('status', 'paid')->sum('amount');

            if ($totalPaid >= (float) $invoice->amount - 0.0005) {
                $invoice->update(['status' => 'paid', 'paid_date' => now()]);
            } else {
                $invoice->update(['status' => 'partial']);
            }
        }
    }

    private function undoAllocationsForVoucher(InvoiceReceipt $r): void
    {
        foreach ($this->resolveAllocations($r) as $alloc) {
            $invoice = Invoice::find($alloc['invoice_id']);
            if (! $invoice) {
                continue;
            }

            InvoicePartial::where('receipt_voucher_id', $r->id)->where('invoice_id', $invoice->id)->update(['status' => 'unpaid']);

            $hasPaid = InvoicePartial::where('invoice_id', $invoice->id)->where('status', 'paid')->exists();
            $invoice->update([
                'status' => $hasPaid ? 'partial' : 'unpaid',
                'paid_date' => $hasPaid ? $invoice->paid_date : null,
            ]);
        }
    }

    /**
     * `transactions.posting_status` is deliberately NOT in {@see Transaction::$fillable} (only
     * `PostingService` writes it, via `forceCreate()`/a raw header insert) -- an ordinary
     * `->update(['posting_status' => ...])` here would silently DROP the column (Eloquent ignores
     * a non-fillable key on mass assignment rather than throwing, by this app's default
     * configuration) instead of erroring, leaving the OFF-path reversal looking like it worked
     * while the old row still read `posted`. Direct attribute assignment bypasses the guard
     * correctly, matching how {@see self::writeLegacyTransaction()} already writes this same
     * column via `forceCreate()`.
     */
    /**
     * CT-A3 R2-3 — the LIVE posted document for a receipt voucher, or null when there is none.
     *
     * Two sources, in this order, because they answer different questions:
     *
     *  1. `invoice_receipts.transaction_id` — what the live `postVoucher()`/`update()` paths write.
     *     Accepted only when the row it names is still LIVE (`posting_status = 'posted'`): after an
     *     edit or a prior reversal the column can name a document that is no longer the current
     *     one, and acting on a reversed document is how a receipt gets reversed twice.
     *  2. The receipt's own IDEMPOTENCY KEY FAMILY — `rv:{id}` plus every revision
     *     {@see PostingService::repost()} has minted off it (`:rev{n}`, and the pre-R2-2
     *     `:repost:{id}`). This is what covers the `accounting:replay` backfill population, whose
     *     rows have a live document and no linkage column at all (D7), and it is the structural,
     *     never-by-description lookup this controller already uses for the clearance JV.
     *
     * Returns null when nothing was ever posted — a genuine `pending` draft. The lookup is WIDER
     * than the old `findOrFail($r->transaction_id)`; it is not weaker. "No document" still means
     * "reverse nothing".
     */
    /**
     * CT-A3 R2-3 — which company a receipt belongs to, by the ONE chain this codebase has.
     *
     * Mirrors {@see self::buildVoucherDraft()}'s own precedence exactly: the row's own `company_id`
     * when it is positive, otherwise the public static resolution chain (invoice -> client/agent ->
     * task -> account -> branch). `invoice_receipts.company_id` is NULL on every legacy row on the
     * City Travelers data, so a bare `(int) $r->company_id` resolves to the sentinel 0 and every
     * company-scoped lookup built on it silently matches nothing.
     *
     * Returns 0 when nothing resolves — the same sentinel `buildVoucherDraft()` refuses on, so a
     * caller that uses this value in a `where()` finds nothing rather than finding somebody else's
     * document.
     */
    public static function companyIdFor(InvoiceReceipt $r): int
    {
        $own = (int) ($r->company_id ?? 0);

        if ($own > 0) {
            return $own;
        }

        $resolved = self::resolveReceiptCompanyId($r);

        return $resolved !== null && $resolved > 0 ? $resolved : 0;
    }

    private function resolvePostedDocumentFor(InvoiceReceipt $invoiceReceipt): ?Transaction
    {
        if ($invoiceReceipt->transaction_id !== null) {
            $byColumn = Transaction::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->find($invoiceReceipt->transaction_id);

            if ($byColumn !== null && $byColumn->posting_status === 'posted') {
                return $byColumn;
            }
        }

        $baseKey = 'rv:'.$invoiceReceipt->id;
        $prefix = addcslashes($baseKey, '%_\\');

        $byKey = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            // CT-A3 R2-3 SERVER FINDING. This was `(int) $invoiceReceipt->company_id`, and on the
            // City Travelers data that is NULL on every legacy row (CT-F35, 109 of 109) -- the cast
            // turns it into the sentinel 0, the key lookup matches nothing, and the whole fix
            // silently did nothing for exactly the population it exists for. Found by exercising
            // the bounce lifecycle against real data on the scratch copy, not by a test: every unit
            // fixture carries a company_id, because a fixture that did not could not be built.
            ->where('company_id', self::companyIdFor($invoiceReceipt))
            ->where('posting_status', 'posted')
            ->where(function ($q) use ($baseKey, $prefix) {
                $q->where('idempotency_key', $baseKey)
                    ->orWhere('idempotency_key', 'like', $prefix.':rev%')
                    ->orWhere('idempotency_key', 'like', $prefix.':repost:%');
            })
            ->orderByDesc('id')
            ->first();

        if ($byKey !== null) {
            return $byKey;
        }

        // Last resort: the column names a document that is no longer live and the key family has
        // nothing posted. Hand back whatever the column names so the caller's own
        // `posting_status !== 'reversed'` guards keep behaving exactly as they did.
        return $invoiceReceipt->transaction_id !== null
            ? Transaction::withoutGlobalScopes()->whereNull('deleted_at')->find($invoiceReceipt->transaction_id)
            : null;
    }

    private function markTransactionReversed(Transaction $transaction): void
    {
        $transaction->posting_status = 'reversed';
        $transaction->save();
    }

    /**
     * DocumentDraft has no setter (every property is `readonly`) -- this rebuilds an equivalent
     * draft with only `idempotencyKey` changed, for `update()`'s OFF-path repost branch (see that
     * call site's own comment for where the replacement key comes from -- CT-A3 R2-2 moved that
     * decision into PostingService::nextRepostIdempotencyKey(), so this helper now takes the FULL
     * key rather than a suffix it would have had to know how to build).
     */
    private function withReplacementIdempotencyKey(DocumentDraft $draft, string $idempotencyKey): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $draft->companyId,
            branchId: $draft->branchId,
            docType: $draft->docType,
            subType: $draft->subType,
            docDate: $draft->docDate,
            narration: $draft->narration,
            lines: $draft->lines,
            idempotencyKey: $idempotencyKey,
            sourceType: $draft->sourceType,
            sourceId: $draft->sourceId,
            invoiceId: $draft->invoiceId,
            userId: $draft->userId,
        );
    }

    /**
     * Draft builder for {@see self::createReceiptVoucher()}/{@see self::autoGenerate()} ONLY --
     * deliberately NOT {@see self::buildVoucherDraft()}, the shared builder every OTHER posting
     * action in this class uses. That builder resolves its instrument leg EAGERLY, inside the
     * builder itself ({@see ReceiptPostingRule::instrumentAccountFor()}), via {@see AccountResolver} -- which
     * requires a `system_accounts` CASH_IN_HAND mapping to exist for the company REGARDLESS of
     * whether the engine ends up posting the document or running the OFF-path legacy closure
     * (confirmed by this class's own W5.R test suite: even an engine-OFF `store()` test seeds
     * `system_accounts` for exactly this reason). That is a reasonable, already-accepted
     * prerequisite for `store()`/`approve()` (a brand-new W5.R entry point with no legacy
     * behaviour to preserve), but wrong here: HEAD's OFF-path shape for THESE two call sites
     * resolved NO account at all (see {@see self::writeLegacyReceiptVoucherTransaction()}'s own
     * docblock) -- requiring that mapping unconditionally would impose a NEW prerequisite on
     * every company already using this cash-recording flow just to keep posting on the OFF path,
     * exactly the regression "OFF path legacy byte-identical" forbids. The debit (instrument) leg
     * here is therefore built with a DEFERRED purpose code (`accountId: null`, `purposeCode:
     * 'CASH_IN_HAND'`) the same way the allocation lines already are in
     * {@see self::buildVoucherDraft()} -- resolved lazily, per-line, ONLY when the engine actually
     * posts the document ({@see PostingService::post()}'s own `$line->accountId ??
     * $this->accountResolver->resolve(...)` pattern) -- never touched at all when the OFF-path
     * closure below runs instead. Simpler than `buildVoucherDraft()`'s general INVOICE-type case
     * on purpose: these two feeders never carry cheque/bank instrument fields or a remainder
     * (`createReceiptVoucher()`/`autoGenerate()` both always set `remainder_amount=0`).
     */
    private function buildSavePartialReceiptDraft(InvoiceReceipt $r, string $idempotencyKey): DocumentDraft
    {
        $companyId = (int) $r->company_id;
        $branchId = (int) $r->branch_id;
        $docDate = $r->doc_date ? Carbon::parse($r->doc_date) : Carbon::now();
        $amount = round((float) $r->amount, 3);

        VoucherSubTypeGuard::assertValid('RV', 'INVOICE');

        $lines = [
            new LineDraft(
                purposeCode: 'CASH_IN_HAND', accountId: null, side: 'debit', amount: $amount,
                currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                transactionType: 'RECEIPT', description: 'Receipt Voucher - Invoice payment',
                partyAccountRef: $r->client_id,
            ),
        ];

        $allocations = $this->resolveAllocations($r);

        foreach ($allocations as $alloc) {
            $invoice = Invoice::find($alloc['invoice_id']);
            $allocAmount = round((float) $alloc['amount'], 3);

            $lines[] = new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL', accountId: null, side: 'credit',
                amount: $allocAmount, currency: 'KWD', originalAmount: $allocAmount, exchangeRate: 1.0,
                transactionType: 'CUSTOMERCREDITED',
                description: 'Payment for invoice '.($invoice->invoice_number ?? $alloc['invoice_id']),
                partyAccountRef: $r->client_id ?? $invoice?->client_id,
                invoiceId: (int) $alloc['invoice_id'],
            );
        }

        return new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: 'RV',
            subType: 'INVOICE',
            docDate: $docDate,
            narration: 'Receipt Voucher - Invoice payment',
            lines: $lines,
            idempotencyKey: $idempotencyKey,
            sourceType: 'Receipt',
            sourceId: $r->id,
            invoiceId: $allocations !== [] ? (int) $allocations[0]['invoice_id'] : null,
            userId: Auth::id(),
        );
    }

    /** Layer 1 (per-record) lock check -- {@see \App\Http\Traits\Lockable}, mirrors
     * `InvoiceController::checkLocked()`'s own shape for the RV's own `Transaction` row. */
    private function checkVoucherLocked(InvoiceReceipt $r)
    {
        $transaction = $r->transaction_id ? Transaction::withoutGlobalScopes()->find($r->transaction_id) : null;

        if ($transaction && $transaction->isLocked() && ! $transaction->canModify()) {
            $lockedBy = $transaction->lockedByUser?->name ?? 'Unknown';
            $lockedAt = $transaction->locked_at?->format('d M Y H:i') ?? '';
            $message = "This receipt voucher is locked by {$lockedBy} on {$lockedAt}. Contact your accountant to unlock it.";

            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }

            return redirect()->back()->with('error', $message);
        }

        return null;
    }

    /** Layer 3 (reconciled-line) belt-and-braces check -- the real enforcement is
     * `PostingService::reverse()`'s own `ProtectedLineException`; this is the friendlier,
     * earlier-failing check so a controller action can redirect with a clear message instead of
     * surfacing a raw exception. */
    private function hasReconciledLines(InvoiceReceipt $r): bool
    {
        if (! $r->transaction_id) {
            return false;
        }

        return JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('transaction_id', $r->transaction_id)
            ->where('reconciled', 1)
            ->exists();
    }

    /**
     * w5-brief.md §W5.R "on approve call PaymentReceiptService::generateAndSendPdf() when
     * receipt_send_on_payment". {@see PaymentReceiptService::generateAndSendPdf()} requires a
     * real `App\Models\Payment` row -- most RV flows (cash/cheque/bank receipts entered directly
     * by an accountant) have none; only a gateway-originated `InvoicePartial.payment_id` does.
     * Documented gap, not silently ignored: when no `Payment` can be resolved, this logs and
     * returns rather than fabricating one. A generic RV-native PDF sender (not tied to a gateway
     * `Payment` row) is P5.18/P7 territory, out of this sub-wave's scope.
     */
    private function sendReceiptPdfIfRequested(InvoiceReceipt $r): void
    {
        if (! VoucherOptions::receiptSendOnPayment((int) $r->company_id)) {
            return;
        }

        $paymentId = $r->invoice_partial_id ? InvoicePartial::find($r->invoice_partial_id)?->payment_id : null;

        if (! $paymentId) {
            foreach ($this->resolveAllocations($r) as $alloc) {
                $partial = InvoicePartial::where('receipt_voucher_id', $r->id)->where('invoice_id', $alloc['invoice_id'])->first();
                if ($partial?->payment_id) {
                    $paymentId = $partial->payment_id;
                    break;
                }
            }
        }

        if (! $paymentId) {
            Log::info('accounting.rv_receipt_pdf_skipped_no_payment', ['invoice_receipt_id' => $r->id]);

            return;
        }

        $payment = Payment::find($paymentId);
        if (! $payment) {
            return;
        }

        try {
            app(PaymentReceiptService::class)->generateAndSendPdf($payment);
        } catch (Throwable $e) {
            Log::warning('accounting.rv_receipt_pdf_failed', ['invoice_receipt_id' => $r->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{company_id:int, branch_id:int, doc_date:string, type:string, client_id:?int,
     *               account_id:?int, amount:float, allocations:?array, remainder_amount:float,
     *               remainder_policy:string, bank_account_id:?int, cheque_no:?string,
     *               cheque_date:?string, bank_info:?string, auth_no:?string, remarks:?string,
     *               remarks_internal:?string}
     */
    private function validateVoucherRequest(Request $request, ?InvoiceReceipt $existing = null): array
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'docdate' => ['required', 'date'],
            'type' => ['required', 'in:account,invoice,credit,import'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.001'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', 'integer', 'exists:invoices,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.001'],
            'bank_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            // CT-A3 wave 2 (W2-2). 24 chars, matching journal_entries.settlement_channel and
            // Accounting\ReconciliationController's own rule, so one channel token travels from
            // the receipt through to its reconciliation without a truncation step.
            'settlement_channel' => ['nullable', 'string', 'max:24'],
            'cheque_no' => ['nullable', 'string', 'max:100'],
            'cheque_date' => ['nullable', 'date'],
            'bank_info' => ['nullable', 'string', 'max:200'],
            'auth_no' => ['nullable', 'string', 'max:100'],
            'cheque_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'remarks_create' => ['nullable', 'string'],
            'internal_remarks' => ['nullable', 'string'],
        ]);

        if ($validated['type'] === 'account' && empty($validated['account_id'])) {
            throw ValidationException::withMessages(['account_id' => 'An account is required for an account-type receipt voucher.']);
        }

        if (in_array($validated['type'], ['credit', 'import'], true) && empty($validated['client_id'])) {
            throw ValidationException::withMessages(['client_id' => 'A client is required for this receipt voucher type.']);
        }

        // Tenant-lock hardening: the rule above only validates that company_id EXISTS, never that
        // it belongs to the authenticated user -- a logged-in user of company A could otherwise
        // POST company_id = B and create/update a receipt voucher (a money document that posts
        // journal entries) against company B. Overwrite the request-supplied value with the
        // caller's own resolved company right after validation and before anything below reads
        // it. getCompanyId() already returns the admin's session-selected company for Role::ADMIN
        // (see app/Helper/helper.php), so an admin acting on another company still works via that
        // existing convention -- same as assertSameCompanyOrUnscopedAdmin() elsewhere in this
        // controller.
        $validated['company_id'] = getCompanyId(Auth::user());

        $companyId = (int) $validated['company_id'];

        // W6.S fix round (w6-brief.md "Hold/confirmed follow-up lifecycle" item 3): a deposit
        // against an on-hold/confirmed task is posted through this SAME `credit` (TOPUP) shape --
        // Dr instrument / Cr CLIENT_ADVANCE (2632) -- already built for a plain client credit
        // top-up; `task_id` only tags WHICH task the deposit belongs to, it never changes the
        // posting shape. Guarded to the `credit` type and to a task that is actually still
        // on-hold/confirmed (never issued/void/etc -- an already-issued task takes a normal
        // invoice-allocated receipt instead) and to the same company (tenant isolation).
        if (! empty($validated['task_id'])) {
            if ($validated['type'] !== 'credit') {
                throw ValidationException::withMessages(['task_id' => 'A task deposit must be a credit-type receipt voucher.']);
            }

            $task = Task::where('id', $validated['task_id'])->where('company_id', $companyId)->first();
            if ($task === null) {
                throw ValidationException::withMessages(['task_id' => 'Task not found for this company.']);
            }
            if (! in_array($task->status, ['on hold', 'confirmed'], true)) {
                throw ValidationException::withMessages(['task_id' => 'A deposit can only be recorded against an on-hold or confirmed task.']);
            }
        }
        $amount = round((float) $validated['amount'], 3);

        $allocations = [];
        $allocatedTotal = 0.0;

        if ($validated['type'] === 'invoice') {
            $rows = $validated['allocations'] ?? [];
            if (empty($rows)) {
                throw ValidationException::withMessages(['allocations' => 'At least one invoice allocation is required.']);
            }

            foreach ($rows as $row) {
                $rowAmount = round((float) $row['amount'], 3);
                $allocations[] = ['invoice_id' => (int) $row['invoice_id'], 'amount' => $rowAmount];
                $allocatedTotal += $rowAmount;
            }

            if ($allocatedTotal > $amount + 0.0005) {
                throw ValidationException::withMessages(['allocations' => 'Allocations total more than the receipt amount.']);
            }
        }

        $remainder = $validated['type'] === 'invoice' ? round(max(0.0, $amount - $allocatedTotal), 3) : 0.0;

        // W5.U: a re-uploaded file replaces the stored path; no new file on an update() leaves the
        // existing (or null, on a first create()) path untouched -- see storeChequeImage()'s own
        // docblock for why silently erasing a previously-uploaded cheque image on an unrelated field
        // edit would be a regression, not a no-op.
        $chequeImagePath = $this->storeChequeImage($request, $companyId) ?? $existing?->cheque_image_path;

        return [
            'company_id' => $companyId,
            'branch_id' => (int) $validated['branch_id'],
            'doc_date' => $validated['docdate'],
            'type' => $validated['type'],
            'client_id' => $validated['client_id'] ?? null,
            'task_id' => $validated['task_id'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'amount' => $amount,
            'allocations' => $allocations !== [] ? $allocations : null,
            'remainder_amount' => $remainder,
            'remainder_policy' => VoucherOptions::overpayPolicy($companyId),
            'bank_account_id' => $validated['bank_account_id'] ?? null,
            'settlement_channel' => $validated['settlement_channel'] ?? null,
            'cheque_no' => $validated['cheque_no'] ?? null,
            'cheque_date' => $validated['cheque_date'] ?? null,
            'bank_info' => $validated['bank_info'] ?? null,
            'auth_no' => $validated['auth_no'] ?? null,
            'cheque_image_path' => $chequeImagePath,
            'remarks' => $validated['remarks_create'] ?? null,
            'remarks_internal' => $validated['internal_remarks'] ?? null,
        ];
    }

    private function fillVoucherRow(InvoiceReceipt $r, array $data): void
    {
        $r->fill([
            'type' => $data['type'],
            'company_id' => $data['company_id'],
            'branch_id' => $data['branch_id'],
            'doc_date' => $data['doc_date'],
            'client_id' => $data['client_id'],
            'task_id' => $data['task_id'] ?? null,
            'account_id' => $data['account_id'],
            'invoice_id' => $data['allocations'] ? $data['allocations'][0]['invoice_id'] : null,
            'amount' => $data['amount'],
            'allocations' => $data['allocations'],
            'remainder_amount' => $data['remainder_amount'],
            'remainder_policy' => $data['remainder_policy'],
            'bank_account_id' => $data['bank_account_id'],
            'settlement_channel' => $data['settlement_channel'] ?? null,
            'cheque_no' => $data['cheque_no'],
            'cheque_date' => $data['cheque_date'],
            'bank_info' => $data['bank_info'],
            'auth_no' => $data['auth_no'],
            'cheque_image_path' => $data['cheque_image_path'] ?? null,
            'remarks' => $data['remarks'],
            'remarks_internal' => $data['remarks_internal'],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W5.X (w5-brief.md §W5.X item 3). fetchPaymentsByDate()/fetchJournalEntriesByIds()/
    // declineReconcile() no longer touch journal_entries.reconciled/.reconciled_ref_id directly --
    // {@see ReconciliationService} is the single implementation both this controller and
    // BankPaymentController now delegate to (previously two near-identical, independently-
    // drifting copies, neither `Gate`-authorized at all). Each action here now requires
    // accounting.reconcile ({@see ReconciliationService::assertCanReconcile()}) before delegating.
    // createReceiptVoucher()/autoGenerate() were out of W5.R's own scope (see the block this
    // replaces, above, in git history) but are now posted through PostingSeam by the post-W5.R
    // hotfix on those two methods (see their own docblocks) -- other, already-shipped feeders
    // this sub-wave must not break.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function fetchPaymentsByDate(Request $request)
    {
        $this->reconciliation->assertCanReconcile(Auth::user());

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $user = Auth::user();

        $payments = $this->reconciliation->fetchPaymentsByDate(
            (int) $user->company->id,
            [(int) $user->branch->id],
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

    /**
     * W5.R fix (post-verify CRITICAL 1-2, see {@see self::import()}'s own docblock). Posts the
     * revenue-recognition entries for a task that was never invoiced through the normal
     * `InvoiceController` path, matched here via an already-recorded imported receipt. Every line
     * is resolved by purpose code through {@see AccountResolver} (never `Account::where('name', ...)`)
     * and the whole document is built as a {@see DocumentDraft} and routed through
     * {@see PostingSeam::post()} -- balanced or rejected, no `Log::error`-and-continue. Reuses the
     * SAME `docType`/`subType`/idempotency-key convention `InvoiceController::postSaleJournalEntries()`
     * uses for an ordinary invoice sale (`INV`/`SALE`, `'invoice-detail:'.$invoiceDetailId.':sale'`)
     * and the SAME convention `InvoiceController`'s own agent-commission feeder uses for the
     * commission pair (`JV`/`AGENT_COMMISSION` purpose codes `SALARY_EXPENSE`/`SALARY_PAYABLE`) --
     * deliberately, not a new invented shape: if this task is later posted through the ordinary
     * invoicing path too, the shared idempotency key means the engine treats it as already posted
     * rather than double-booking the same economic event.
     */
    public function invoiceJournalEntry($transaction, $invoice)
    {
        if (JournalEntry::where('invoice_id', $invoice->id)->exists()) {
            Log::info('Journal entries already exist for this invoice. Skipping creation.', [
                'invoice_id' => $invoice->id,
            ]);

            return ['status' => 'skipped'];
        }

        Log::info('Starting creating journal entries for uninvoiced task', [
            'transaction_id' => $transaction->id,
            'invoice_id' => $invoice->id,
        ]);

        try {
            $companyId = (int) ($invoice->agent->branch->company->id ?? 0);
            if (! $companyId) {
                Log::error('Company ID not found');

                return ['status' => 'error', 'message' => 'Company ID not found'];
            }

            $invoiceDetail = InvoiceDetail::where('invoice_number', $invoice->invoice_number)->first();
            if (! $invoiceDetail) {
                Log::error('Invoice detail not found', ['invoice_number' => $invoice->invoice_number]);

                return ['status' => 'error', 'message' => 'Invoice detail not found'];
            }

            $invoicePartial = InvoicePartial::where('invoice_number', $invoice->invoice_number)->first();
            if (! $invoicePartial) {
                Log::error('Invoice partial not found', ['invoice_number' => $invoice->invoice_number]);

                return ['status' => 'error', 'message' => 'Invoice partial not found'];
            }

            $task = Task::where('id', $invoiceDetail->task_id)->first();
            if (! $task) {
                Log::error('Task not found', ['task_id' => $invoiceDetail->task_id]);

                return ['status' => 'error', 'message' => 'Task not found'];
            }

            $client = Client::find($invoice->client_id);
            if (! $client) {
                Log::error('Client not found', ['client_id' => $invoice->client_id]);

                return ['status' => 'error', 'message' => 'Client not found'];
            }

            $agent = Agent::find($invoice->agent_id);
            if (! $agent) {
                Log::error('Agent not found', ['agent_id' => $invoice->agent_id]);

                return ['status' => 'error', 'message' => 'Agent not found'];
            }

            $branchId = (int) ($invoice->agent->branch->id ?? 0);
            $sellAmount = round((float) $invoice->amount, 3);
            $bookingAmount = round((float) $invoicePartial->amount, 3);

            if (abs($sellAmount - $bookingAmount) > 0.0005) {
                // The receivable leg (invoice->amount) and the income leg (invoicePartial->amount)
                // must be equal for this two-line pair to balance -- refuse rather than post an
                // unbalanced document (w5-brief.md "Balanced or rejected; no Log::error-and-continue").
                Log::error('accounting.rv_import_amount_mismatch', [
                    'invoice_id' => $invoice->id, 'sell' => $sellAmount, 'booking' => $bookingAmount,
                ]);

                return ['status' => 'error', 'message' => 'Receivable and income amounts do not match; refusing to post an unbalanced document.'];
            }

            $currency = $task->currency ?? 'KWD';
            $exchangeRate = (float) ($task->exchange_rate ?? 1.0);
            $docDate = $invoice->invoice_date ? Carbon::parse($invoice->invoice_date) : Carbon::now();

            $saleLines = [
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL', accountId: null, side: 'debit', amount: $sellAmount,
                    currency: $currency, originalAmount: $sellAmount, exchangeRate: $exchangeRate,
                    transactionType: 'CUSTOMERDEBITED', description: 'Invoice created for (Assets): '.$client->name,
                    partyAccountRef: $client->id, invoiceId: $invoice->id, invoiceDetailId: $invoiceDetail->id,
                    taskId: $task->id, ledgerType: 'receivable', partyName: $client->name,
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $this->accountResolver->resolve('SERVICE_REVENUE', $companyId, (string) $task->type)->id,
                    side: 'credit', amount: $bookingAmount, currency: $currency, originalAmount: $bookingAmount,
                    exchangeRate: $exchangeRate, transactionType: 'INCOME',
                    description: 'Invoice created for (Income): '.$task->reference,
                    invoiceId: $invoice->id, invoiceDetailId: $invoiceDetail->id, taskId: $task->id,
                    ledgerType: 'income',
                ),
            ];

            $saleDraft = new DocumentDraft(
                companyId: $companyId, branchId: $branchId, docType: 'INV', subType: 'SALE',
                docDate: $docDate,
                narration: 'Imported receipt - uninvoiced task sale for invoice '.$invoice->invoice_number,
                lines: $saleLines,
                idempotencyKey: 'invoice-detail:'.$invoiceDetail->id.':sale',
                sourceType: 'Receipt', sourceId: $transaction->id,
                invoiceId: $invoice->id, userId: Auth::id(),
            );

            $saleLegacy = fn () => $this->writeLegacyTransaction($saleDraft, null);
            $this->seam->post($saleDraft, $saleLegacy, 'receipt-voucher.import');

            $commission = 0.0;
            if (in_array($agent->type_id, [2, 3])) {
                $selling = (float) ($task->invoiceDetail->task_price ?? 0);
                $supplier = (float) ($task->total ?? 0);
                $rate = (float) ($agent->commission ?? 0.15);
                $commission = round($rate * ($selling - $supplier), 3);
            }

            if ($commission > 0.0005) {
                $commissionLines = [
                    new LineDraft(
                        // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                        purposeCode: 'COMMISSION_EXPENSE', accountId: null, side: 'debit', amount: $commission,
                        currency: $currency, originalAmount: $commission, exchangeRate: $exchangeRate,
                        transactionType: 'AGENT_COMMISSION_EXPENSE',
                        description: 'Agents Commissions for (Expenses): '.$agent->name,
                        partyAccountRef: $agent->id, invoiceId: $invoice->id, invoiceDetailId: $invoiceDetail->id,
                        taskId: $task->id, ledgerType: 'expense', partyName: $agent->name,
                    ),
                    new LineDraft(
                        // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                        purposeCode: 'COMMISSION_PAYABLE', accountId: null, side: 'credit', amount: $commission,
                        currency: $currency, originalAmount: $commission, exchangeRate: $exchangeRate,
                        transactionType: 'AGENT_COMMISSION_PAYABLE',
                        description: 'Agents Commissions for (Liabilities): '.$agent->name,
                        partyAccountRef: $agent->id, invoiceId: $invoice->id, invoiceDetailId: $invoiceDetail->id,
                        taskId: $task->id, ledgerType: 'payable', partyName: $agent->name,
                    ),
                ];

                $commissionDraft = new DocumentDraft(
                    companyId: $companyId, branchId: $branchId, docType: 'JV', subType: 'AGENT_COMMISSION',
                    docDate: $docDate, narration: 'Agent commission: '.$agent->name, lines: $commissionLines,
                    idempotencyKey: 'invoice-detail:'.$invoiceDetail->id.':agent-commission',
                    sourceType: 'Receipt', sourceId: $transaction->id,
                    invoiceId: $invoice->id, userId: Auth::id(),
                );

                $commissionLegacy = fn () => $this->writeLegacyTransaction($commissionDraft, null);
                $this->seam->post($commissionDraft, $commissionLegacy, 'receipt-voucher.import');
            }

            Log::info('Journal entry created successfully via PostingSeam for imported receipt', [
                'invoice_id' => $invoice->id,
                'transaction_id' => $transaction->id,
                'commission' => $commission,
            ]);

            return ['status' => 'success'];
        } catch (PostingException $e) {
            Log::critical('accounting.rv_import_posting_failed', [
                'invoice_id' => $invoice->id ?? null,
                'transaction_id' => $transaction->id ?? null,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('Error in invoiceJournalEntry', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Create Receipt Voucher ONLY (no partial, no COA) -- called from
     * `InvoiceController::savePartial()`'s "gateway requires receipt voucher" branch AFTER the
     * `InvoicePartial` for this payment already exists there (locked rule: ONE receipt document
     * per payment -- this method posts exactly one).
     *
     * ── Hotfix (post-W5.R): the row this method creates is now actually POSTED ──────────────────
     * W5.R's own build left this feeder creating a `pending` `invoice_receipts` row with NO
     * `transaction_id` at all (see this method's prior docblock, in git history: "...
     * InvoiceController::savePartial() is expected to drive this the same way
     * approve()/store()'s auto-approve fast path does" -- a documented gap that was never wired
     * up). Unlike `store()`'s standalone RV screen -- where a `pending`, unposted row genuinely
     * means "drafted, cash not yet confirmed" -- this method's ONLY caller has already told the
     * system money was received right now (the accountant is recording a COMPLETED cash/Tabby/
     * Deema payment through the invoice UI). There is no separate manual-approval workflow for
     * this feeder, so `VoucherOptions::approvalThreshold()` is deliberately NOT consulted here
     * (unlike `store()`'s threshold gate) -- this document always posts immediately, matching
     * HEAD's own unconditional `Transaction::create()` for this exact call site.
     *
     * `status` is set to APPROVED once the transaction is live -- NOT the historical PENDING
     * value this method (and, before this hotfix, one InvoiceUpdateTest assertion) used to leave
     * it at. That combination is never safe once a real `transaction_id` is attached: `update()`/
     * `destroy()` both branch on `InvoiceReceipt::isPending()` to decide "was anything ever
     * posted" -- a `pending` row carrying a live `transaction_id` would let `destroy()` take its
     * `isPending()` HARD-DELETE branch and remove the `invoice_receipts` row without ever
     * reversing the ledger, orphaning a posted Transaction with no voucher row pointing at it.
     * APPROVED is the only status value consistent with this class's own invariant
     * (`transaction_id` set <=> APPROVED), matching what {@see self::postVoucher()} already does.
     *
     * ── Engine ON ────────────────────────────────────────────────────────────────────────────────
     * {@see self::buildSavePartialReceiptDraft()} builds the doc_type RV / sub_type INVOICE
     * document -- a cash-leg debit against the allocated invoice's receivable, both resolved
     * purely by purpose code ({@see AccountResolver}), never by name. It is a DEDICATED builder,
     * not {@see self::buildVoucherDraft()} (the shared one `store()`/`approve()`/etc. use) --
     * see that method's own docblock for why: `buildVoucherDraft()` resolves its instrument leg
     * EAGERLY, which would impose a new `system_accounts` prerequisite on the OFF path this
     * feeder never had. `applyAllocationsToInvoices()` is deliberately NOT called here (unlike
     * `postVoucher()`) -- the InvoicePartial for this payment already exists, created directly by
     * `InvoiceController::savePartial()` before this method ever runs; calling it would create a
     * SECOND `InvoicePartial` row for the same payment (keyed off `receipt_voucher_id`), double-
     * counting this receipt against the invoice. This is also why the original InvoicePartial's
     * own `receipt_voucher_id` is intentionally left NULL here -- this payment shape links via
     * `invoice_receipts.invoice_partial_id`, not the reverse FK `applyAllocationsToInvoices()`
     * uses for store()/approve()'s own, different, partial-creation flow.
     *
     * ── Engine OFF ───────────────────────────────────────────────────────────────────────────────
     * HEAD (`git show HEAD:app/Http/Controllers/ReceiptVoucherController.php`) wrote a single
     * bare `Transaction::create()` row here with NO `JournalEntry` rows at all -- no double-entry
     * lines, ever, on this specific legacy call site. Per the hard rule "OFF path legacy
     * byte-identical", {@see self::writeLegacyReceiptVoucherTransaction()} reproduces HEAD's exact
     * bare-header shape instead, adding only the one column (`idempotency_key`) the double-post
     * guard below needs to find this row again -- HEAD posted no economic movement here for the
     * OFF path to preserve or change, and (unlike the shared {@see self::writeLegacyTransaction()}
     * every OTHER posting action in this class uses) never resolves an account, so this feeder
     * imposes no new `system_accounts` prerequisite on a company still running the OFF path.
     *
     * ── No double receipt for the same payment ──────────────────────────────────────────────────
     * Keyed via {@see PaymentIdempotencyKey::forGatewayPayment()} -- the SAME shared factory
     * `PaymentController::createInvoicePaymentCOA()` (the W2-seam gateway-payment feeder) uses --
     * so that if a real `Payment` already backs this `InvoicePartial` (`payment_id` set) and that
     * feeder already posted THIS payment's receipt under the identical (gateway, paymentId,
     * partialIds) key, `PostingSeam::post()`/`PostingService::post()`'s own idempotency lookup
     * returns the EXISTING transaction instead of posting a second one. `savePartial()`'s own
     * InvoicePartial rows never carry a `payment_id` (that field is only set by a real gateway-
     * webhook completion, e.g. `createInvoicePaymentCOA()` itself), so the fallback keys on the
     * InvoicePartial's own id instead -- still globally unique per real payment event, since
     * `savePartial()` always creates a BRAND NEW InvoicePartial row per call (never reuses one),
     * so no two distinct payment events can ever collide on this fallback key.
     */
    public function createReceiptVoucher(Invoice $invoice, InvoicePartial $invoicePartial, Request $request, string $gateway = 'Cash'): array
    {
        $client = $invoice->client;
        $clientName = $client->name ?? $client->full_name;
        $amount = round((float) ($invoicePartial->amount + $invoicePartial->service_charge + $invoicePartial->invoice_charge), 3);
        $ref = 'RV-'.Str::upper(Str::random(10));

        try {
            $companyId = (int) $invoice->agent->branch->company->id;
            $branchId = (int) $invoice->agent->branch->id;

            $invoiceReceipt = InvoiceReceipt::create([
                'type' => InvoiceReceiptType::INVOICE,
                'invoice_id' => $invoice->id,
                'invoice_partial_id' => $invoicePartial->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'doc_date' => now()->toDateString(),
                'client_id' => $invoice->client_id,
                'amount' => $amount,
                'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
                'remainder_amount' => 0,
                'remainder_policy' => VoucherOptions::overpayPolicy($companyId),
                // CT-A3 wave 2 (W2-2): this method has ALWAYS been handed the gateway the money
                // arrived through (its own `$gateway` argument, default 'Cash') and has always
                // thrown it away. Persisting it is what lets ReceiptPostingRule resolve the
                // configured payment-method account instead of defaulting every card and gateway
                // receipt into CASH_IN_HAND.
                'settlement_channel' => $gateway,
                'voucher_number' => $ref,
                'status' => InvoiceReceiptStatus::PENDING,
                'is_used' => true,
            ]);

            $idempotencyKey = $invoicePartial->payment_id
                ? PaymentIdempotencyKey::forGatewayPayment($gateway, (int) $invoicePartial->payment_id, [$invoicePartial->id])
                : PaymentIdempotencyKey::forGatewayPayment($gateway, $invoicePartial->id);

            $draft = $this->buildSavePartialReceiptDraft($invoiceReceipt, $idempotencyKey);

            $legacy = fn () => $this->writeLegacyReceiptVoucherTransaction($draft, $invoice, $gateway, $clientName, $ref, $amount);

            $posted = $this->seam->post($draft, $legacy, 'receipt-voucher.save-partial');

            $transaction = match (true) {
                $posted instanceof PostedDocument => $posted->transaction,
                $posted instanceof Transaction => $posted,
                // S1 short-circuit (PostingSeam docblock): this exact idempotency key was already
                // posted -- either an earlier retry of this same call, or (the scenario this
                // key's shared PaymentIdempotencyKey family exists to guard against)
                // PaymentController::createInvoicePaymentCOA() already posted this payment's
                // receipt. Find and reuse it rather than posting a second document.
                $posted === null => Transaction::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('company_id', $companyId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail(),
                default => throw new \RuntimeException('Unexpected PostingSeam::post() return type: '.get_debug_type($posted)),
            };

            $invoiceReceipt->transaction_id = $transaction->id;
            $invoiceReceipt->status = InvoiceReceiptStatus::APPROVED;
            $invoiceReceipt->save();

            Log::info('[RECEIPT VOUCHER] Created and posted receipt voucher', [
                'gateway' => $gateway,
                'invoice_id' => $invoice->id,
                'invoice_partial_id' => $invoicePartial->id,
                'invoice_receipt_id' => $invoiceReceipt->id,
                'transaction_id' => $transaction->id,
                'reference' => $ref,
            ]);

            return [
                'ok' => true,
                'invoice_receipt_id' => $invoiceReceipt->id,
                'transaction_id' => $transaction->id,
                'reference' => $ref,
            ];
        } catch (PostingException $e) {
            Log::critical('accounting.rv_save_partial_posting_failed', [
                'invoice_id' => $invoice->id,
                'invoice_partial_id' => $invoicePartial->id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            Log::error('[RECEIPT VOUCHER] Failed to create', [
                'gateway' => $gateway,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * OFF-path writer for {@see self::createReceiptVoucher()}/{@see self::autoGenerate()} ONLY --
     * reproduces HEAD's own bare-`Transaction`-row shape for these two legacy call sites BYTE-
     * IDENTICAL (no `JournalEntry` rows -- HEAD never wrote any here), plus the one addition
     * (`idempotency_key`) the "no double receipt" guard documented on `createReceiptVoucher()`
     * needs. Deliberately NOT {@see self::writeLegacyTransaction()} -- that shared writer
     * resolves every line through {@see AccountResolver}, which requires a `system_accounts`
     * purpose-code mapping per company; these two specific legacy call sites never resolved an
     * account at all before this hotfix (HEAD wrote no `JournalEntry` here whatsoever), so
     * requiring that mapping now would be a NEW prerequisite for every company already using this
     * cash-recording flow, not a preserved behaviour.
     */
    private function writeLegacyReceiptVoucherTransaction(
        DocumentDraft $draft,
        Invoice $invoice,
        string $gateway,
        string $clientName,
        string $ref,
        float $amount
    ): Transaction {
        return Transaction::forceCreate([
            'entity_id' => $draft->companyId,
            'entity_type' => 'company',
            'company_id' => $draft->companyId,
            'branch_id' => $draft->branchId,
            'transaction_type' => 'debit',
            'amount' => $amount,
            'description' => $gateway.' payment for Invoice '.$invoice->invoice_number,
            'invoice_id' => $invoice->id,
            'reference_number' => $ref,
            'reference_type' => 'Invoice',
            'name' => $clientName,
            'transaction_date' => now(),
            'idempotency_key' => $draft->idempotencyKey,
        ]);
    }

    public function autoGenerate(Invoice $invoice, Request $request): JsonResponse
    {
        Log::info('Starting to auto generate an unpaid Receipt Voucher', [
            'invoice_data' => $invoice,
            'request_data' => $request->all(),
        ]);

        $invoiceId = $invoice->id;

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            Log::error('Invoice not found', ['invoice_id' => $invoiceId]);

            return response()->json(['ok' => false, 'message' => 'Invoice not found'], 404);
        }

        $type = $request->input('type', '');
        $isPartial = strcasecmp($type, 'partial') === 0;

        $client = $invoice->client()->first();
        if (! $client) {
            Log::error('Missing client relation', ['invoice_id' => $invoiceId]);

            return response()->json(['ok' => false, 'message' => 'Client missing for invoice'], 422);
        }

        $clientName = $client->name ?? ($client->full_name ?? '');
        $amount = round((float) $request->amount, 3);
        $ref = 'RV-'.Str::upper(Str::random(10));

        try {
            $companyId = (int) $invoice->agent->branch->company->id;
            $branchId = (int) $invoice->agent->branch->id;

            $invoiceReceipt = InvoiceReceipt::create([
                'type' => InvoiceReceiptType::INVOICE,
                'invoice_id' => $invoiceId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'doc_date' => now()->toDateString(),
                'client_id' => $invoice->client_id,
                'amount' => $amount,
                'allocations' => [['invoice_id' => $invoiceId, 'amount' => $amount]],
                'remainder_amount' => 0,
                'remainder_policy' => VoucherOptions::overpayPolicy($companyId),
                'voucher_number' => $ref,
                'status' => InvoiceReceiptStatus::PENDING,
                'is_used' => true,
            ]);

            // Hotfix (same reasoning as createReceiptVoucher() above): this feeder has no
            // InvoicePartial/Payment identity of its own to key on -- it mints the
            // invoice_receipts row itself, so THIS row's own id is the first stable identity that
            // exists. autoGenerate() is unrouted today (no controller action maps to it -- see
            // this class's own architecture-test docblock), so there is no live double-post
            // scenario to guard beyond the standard S1 short-circuit this key still provides.
            $idempotencyKey = PaymentIdempotencyKey::forGatewayPayment('cash', $invoiceReceipt->id);
            $draft = $this->buildSavePartialReceiptDraft($invoiceReceipt, $idempotencyKey);
            $legacy = fn () => $this->writeLegacyReceiptVoucherTransaction($draft, $invoice, 'Cash', $clientName, $ref, $amount);

            $posted = $this->seam->post($draft, $legacy, 'receipt-voucher.auto-generate');

            $transaction = match (true) {
                $posted instanceof PostedDocument => $posted->transaction,
                $posted instanceof Transaction => $posted,
                $posted === null => Transaction::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('company_id', $companyId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail(),
                default => throw new \RuntimeException('Unexpected PostingSeam::post() return type: '.get_debug_type($posted)),
            };

            $invoiceReceipt->transaction_id = $transaction->id;
            $invoiceReceipt->status = InvoiceReceiptStatus::APPROVED;
            $invoiceReceipt->save();

            Log::info('Successfully auto generated and posted a Receipt Voucher', [
                'invoice_id' => $invoiceId,
                'invoice_receipt_id' => $invoiceReceipt->id,
                'transaction_id' => $transaction->id,
                'reference_number' => $ref,
            ]);

            return response()->json([
                'ok' => true,
                'invoice_id' => $invoiceId,
                'invoice_receipt_id' => $invoiceReceipt->id,
                'payment_txn_id' => $transaction->id,
                'reference' => $ref,
            ], 201);
        } catch (PostingException $e) {
            Log::critical('accounting.rv_auto_generate_posting_failed', [
                'invoice_id' => $invoiceId,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'message' => 'Failed to generate a Receipt Voucher: '.$e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Failed to process Receipt Voucher', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['ok' => false, 'message' => 'Failed to generate a Receipt Voucher'], 500);
        }
    }

    /**
     * W5.R fix (post-verify CRITICAL 1-3): this is a live, mutating route
     * (`POST /receipt-voucher/import`) that matches an already-recorded receipt to a specific
     * invoice and posts that invoice's own revenue-recognition entries for a task that was never
     * invoiced through the normal path. It used to (a) have NO `Gate::authorize()` call at all --
     * any authenticated user with `module:accounting` access, not just a role
     * `ReceiptVoucherPolicy::create()` actually grants create-rights to, could invoke it; (b) write
     * raw `Transaction::create()` + `JournalEntry::create()` rows via {@see self::invoiceJournalEntry()}
     * with NO balance assertion and NEVER through {@see PostingSeam::post()}, for every company
     * regardless of the engine flag; (c) resolve accounts via
     * `Account::where('name', 'Accounts Receivable')` / `Account::where('name', 'Clients')` /
     * `Account::where('name', 'like', ...)` -- the exact anti-pattern this whole sub-wave exists to
     * kill. Gated the same tier as `store()` (`create`, class-level -- this action creates the
     * invoice's posted revenue-recognition document the same way `store()`/`approve()` create a
     * posted RV), and {@see self::invoiceJournalEntry()} below is now rewritten to build a
     * {@see DocumentDraft} and route through {@see PostingSeam::post()}, resolving every account by
     * purpose code (never by name), matching the rest of this controller's posting-path convention.
     */
    public function import(Request $request)
    {
        Gate::authorize('create', InvoiceReceipt::class);

        Log::info('Starting to process the import payment of Receipt Voucher', [
            'data' => $request->all(),
        ]);

        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company?->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->branch->company->id;
        }

        $request->validate([
            'receipt_reference' => 'required|string',
            'agent_name' => 'required|string',
            'client_name' => 'required|string',
            'invoice_number' => 'required|string',
        ]);

        $transaction = Transaction::with('invoiceReceipt')
            ->where('reference_number', $request->receipt_reference)
            ->first();

        $client = Client::where('name', $transaction->client_id)->first();

        $invoice = Invoice::where('invoice_number', $request->invoice_number)->first();
        if (! $invoice) {
            Log::error('Invoice is not found for invoice number: '.$request->invoice_number);
        }

        $invoiceReceipt = InvoiceReceipt::where('transaction_id', $transaction->id)->first();

        $companyId = $invoice->agent->branch->company->id;
        $branchId = $invoice->agent->branch->id;

        $remainingBalance = (float) ($invoice->amount) - (float) ($transaction->invoiceReceipt->amount);

        try {

            DB::beginTransaction();

            if ($remainingBalance == 0) {
                Log::info('Has 0 of remaining balance. Proceed to pay the entire invoice');

                $invoice->update([
                    'status' => 'paid',
                    'paid_date' => now(),
                ]);

                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $client->id,
                    'service_charge' => 0,
                    'amount' => $invoice->amount,
                    'status' => 'paid',
                    'expiry_date' => now(),
                    'type' => 'cash',
                    'charge_id' => null,
                    'payment_gateway' => 'Cash',
                    'payment_method' => null,
                    'payment_id' => null,
                    'receipt_voucher_id' => $transaction->invoiceReceipt?->id,
                ]);
                if (! $invoicePartial) {
                    Log::error('Failed to create Invoice Partial for invoice ID: ', [
                        'invoice_id' => $invoice->id,
                    ]);
                }

                $transaction = Transaction::create([
                    'entity_id' => $companyId,
                    'entity_type' => 'company',
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'transaction_type' => 'debit',
                    'amount' => $invoice->amount,
                    'description' => 'Payment for Invoice '.$invoice->invoice_number.'. Additional Remarks: '.$transaction->description,
                    'invoice_id' => $invoice->id,
                    'reference_number' => $transaction->reference_number,
                    'reference_type' => 'Invoice', //$receiptvoucherType
                    'name' => $client->name,
                    'transaction_date' => now(),
                ]);

                if (! $transaction) {
                    Log::error('error', 'Failed to create Transaction with ID: ', [
                        'transaction_id' => $transaction->id,
                    ]);
                }

                $uninvoiced = $this->invoiceJournalEntry($transaction, $invoice);
                if (! is_array($uninvoiced) || ! isset($uninvoiced['status']) || $uninvoiced['status'] === 'error') {
                    Log::error('Failed to create journal entry during full payment', [
                        'invoice_id' => $invoice->id ?? null,
                        'transaction_id' => $transaction->id ?? null,
                        'response' => $uninvoiced,
                    ]);

                    return redirect()->back()->with('error', $uninvoiced['message'] ?? 'Failed to create journal entry');
                }

                Log::info('Successfully paid Invoice with ID: '.$invoice->id.' using Receipt Voucher via full payment');
            } elseif ($remainingBalance > 0) {
                Log::info('Remaining balance: KWD '.$remainingBalance.'. Proceed to create new partial for another payment to complete the transaction');

                $invoice->update([
                    'status' => 'unpaid',
                    'type' => 'partial',
                    'payment_type' => 'partial',
                    'paid_date' => now(),
                ]);
                Log::info('Succesfully updated the Invoice, status remained Unpaid as there is remaining balance of KWD '.$remainingBalance);

                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $client->id,
                    'service_charge' => 0,
                    'amount' => $invoice->amount,
                    'status' => 'paid',
                    'expiry_date' => now(),
                    'type' => 'cash',
                    'charge_id' => null,
                    'payment_gateway' => 'Cash',
                    'payment_method' => null,
                    'payment_id' => null,
                    'receipt_voucher_id' => $transaction->invoiceReceipt?->id,
                ]);
                if (! $invoicePartial) {
                    Log::error('Failed to create Invoice Partial for invoice ID: ', [
                        'invoice_id' => $invoice->id,
                    ]);
                }

                $totalSum = InvoicePartial::where('invoice_id', $invoice->id)
                    ->sum('amount');

                if ($totalSum < $invoice->amount) {
                    $newPartial = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $invoice->client_id,
                        'service_charge' => 0,
                        'amount' => $remainingBalance,
                        'status' => 'unpaid',
                        'expiry_date' => null,
                        'type' => 'partial',
                        'charge_id' => null,
                        'payment_gateway' => null,
                        'payment_method' => null,
                        'payment_id' => null,
                    ]);
                    Log::info('Successfully created new Invoice Partial', [
                        'response' => $newPartial,
                    ]);
                }

                Log::info('Successfully paid Invoice with ID: '.$invoice->id.' using Receipt Voucher. Invoice remained unpaid as there is remaining balance of KWD '.$remainingBalance);
            }

            $invoiceReceipt->update([
                'is_used' => true,
            ]);

            DB::commit();
        } catch (Exception $e) {
            Log::error('Failed to process import via Receipt Voucher', [
                'transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'receipt_reference_number' => $transaction->reference_number,
            ]);

            DB::rollback();

            return redirect()->back()->with('error', 'Failed to process import via Receipt Voucher');
        }
    }
}
