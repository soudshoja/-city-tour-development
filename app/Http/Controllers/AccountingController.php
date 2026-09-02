<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Account;
use App\Models\Supplier;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\CoaCategory;
use App\Exports\LedgerExport;
use App\Services\EncryptionService;
use App\Exceptions\Accounting\PostingException;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * ── W7.A (w7-brief.md §W7.A) — the three raw-write "manual JV" screens closed ────────────────────
 * `storePayableDetail()`, `storeReceivableDetail()`, `storeBankPayment()` each used to write a
 * `Transaction` (or nothing, for the third) plus two `JournalEntry` rows directly, with zero
 * `PostingSeam` wiring -- the last reachable raw ledger writers named by the W6 final gate
 * (`.planning/accounting-waves/w6/w6-final-gate.md`) for this file.
 *
 * All three ARE the manual-journal-voucher screens: each posts a user-picked debit account against
 * a user-picked credit account for one amount -- a plain two-line `JV`. Cut over identically:
 *   - OFF (either global flag or `companies.posting_engine_enabled` false): `PostingSeam::post()`
 *     runs the EXACT legacy body (own `PY-`/`EX-`/`RV-`/`IN-` reference-number mint, raw
 *     `Transaction::create()` + two `JournalEntry::create()` calls) verbatim, in a `$legacy`
 *     closure -- byte-identical to HEAD, bugs and all (see `storeBankPayment()`'s own docblock for
 *     the one pre-existing bug this deliberately preserves on the OFF path only).
 *   - ON: a `DocumentDraft` (`doc_type='JV'`) with two explicit-`accountId` `LineDraft`s (this is
 *     the manual-entry screen -- the user picks both accounts by hand, so `purposeCode` is
 *     deliberately empty on both lines, matching the established convention
 *     `BankPaymentController::clear()` already uses for the identical shape) goes through
 *     `PostingSeam::post()`. `PostingService::post()` itself supplies every guarantee the brief
 *     asks for -- balanced-or-rejected (`UnbalancedDocumentException`), leaf-only accounts
 *     (`NonLeafAccountException` -- a group account is refused, never silently posted to),
 *     same-company accounts (`CrossTenantAccountException`), and `PeriodGuard::assertOpen()`
 *     (`PeriodLockedException`) -- so this controller adds no duplicate checks of its own; it only
 *     needs to catch the resulting {@see PostingException} and turn it into a 422/redirect-with-
 *     errors via `ValidationException`. The engine also mints the real serial (`JV-...`) via
 *     `SequenceService`, replacing the three routes' own three DIFFERENT ad-hoc numbering schemes.
 *   - Idempotency: `jv:{companyId}:{clientToken}` where `$clientToken` is the value of a hidden
 *     `client_uuid` form field (minted once per page render -- see `payable-create.blade.php` /
 *     `receivable-create.blade.php`'s own `@php(Str::uuid())` line) so a double-click/network-retry
 *     resubmit carries the SAME token and therefore the SAME key -- `PostingSeam`'s own step-1
 *     idempotency lookup (S1, see that class's docblock) then returns the already-posted document
 *     instead of writing a second one. A caller with no such field (e.g. a raw API POST, or the
 *     unrouted `storeBankPayment()` below) still gets a genuine, request-content-derived key rather
 *     than a wall-clock one -- see {@see self::manualJournalIdempotencyKey()}.
 *   - Reversal: {@see self::reverseManualJournal()} (new -- HEAD has no reverse/edit/delete action
 *     for any of these three screens at all) is the ONLY way to undo a posted manual JV;
 *     `PostingService::reverse()` is the sole reversal mechanism, matching every other engine
 *     feeder in this codebase -- there is no raw delete/undo path.
 *
 * `createBankPayment()`/`storeBankPayment()` have no registered route AND no view template
 * (`resources/views/accounting/bank-payment/create.blade.php` does not exist) -- confirmed dead
 * code, exactly as `tests/Feature/Security/BankPaymentIntegrityTest.php`'s own docblock already
 * documented for the HF-3 hotfix. Cut over anyway (the W6 gate's "sole ledger writer" audit is a
 * static grep, not a route-reachability one, and the method-level raw writes are real regardless of
 * whether anything currently calls them), but no route/view work was done for it -- see that
 * method's own docblock.
 */
class AccountingController extends Controller
{
    public function __construct(
        private readonly PostingSeam $seam,
        private readonly PostingService $postingService,
    ) {}

    /**
     * A stable idempotency key for a manual-JV POST that carries no `client_uuid` hidden-field
     * token (a raw API caller, or `storeBankPayment()`'s unrouted request shape) -- keyed off the
     * request's own stable inputs (company, branch, both accounts, amount, description, the
     * caller-supplied transaction date), NEVER a wall-clock value, matching every factory in
     * {@see \App\Services\Accounting\PaymentIdempotencyKey} (see that class's own docblock for the
     * "why never a timestamp" rationale this mirrors). Two calls with an IDENTICAL tuple collapse
     * into one post -- the same accepted tradeoff `PaymentIdempotencyKey::forClientRefundOut()`'s
     * own docblock documents for a request with no persisted id to key off yet.
     */
    private function manualJournalIdempotencyKey(
        int $companyId,
        int $branchId,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $description,
        string $transactionDate,
        ?string $clientToken,
    ): string {
        if ($clientToken !== null && $clientToken !== '') {
            return sprintf('jv:%d:%s', $companyId, $clientToken);
        }

        $decimals = (int) config('accounting.engine.base_decimals', 3);
        $normalisedAmount = number_format(round($amount, $decimals), $decimals, '.', '');

        return sprintf(
            'jv:%d:fallback:%d:%d:%d:%s:%s:%s',
            $companyId,
            $branchId,
            $debitAccountId,
            $creditAccountId,
            $normalisedAmount,
            sha1($description),
            $transactionDate
        );
    }

    /**
     * Normalises {@see PostingSeam::post()}'s three possible return shapes into the `Transaction`
     * that now carries this document, for every one of this file's manual-JV call sites -- the
     * exact `match(true)` pattern `BankPaymentController::postVoucher()` already establishes (see
     * that method's own comment for the S1/null branch's full reasoning): OFF path returns the
     * legacy closure's own `Transaction`; ON path returns a `PostedDocument`; `null` only when the
     * engine already posted this exact key before a kill-switch flip mid-flight, in which case the
     * live document is re-read by its idempotency key rather than assumed lost.
     */
    private function resolvePostedTransaction(mixed $posted, DocumentDraft $draft): Transaction
    {
        return match (true) {
            $posted instanceof PostedDocument => $posted->transaction,
            $posted instanceof Transaction => $posted,
            $posted === null => Transaction::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('company_id', $draft->companyId)
                ->where('idempotency_key', $draft->idempotencyKey)
                ->firstOrFail(),
            default => throw new \RuntimeException('Unexpected PostingSeam::post() return type: '.get_debug_type($posted)),
        };
    }


    public function index()
    {
        Gate::authorize('viewAny', Account::class);

        $user = Auth::user();

        if ($user->role_id != Role::ADMIN) {
            if ($user->role_id != Role::COMPANY) {
                return abort(403, 'Unauthorized action.');
            } else {
                $company = Company::where('user_id', $user->id)->with([
                    'branches.agents.clients.invoices.invoiceDetails.JournalEntrys' => function ($query) {}
                ])->first();
            }
        } else {
            $companies = Company::all();
        }


        $suppliers = Supplier::all();
        $accounts = Account::where('company_id', $company->id)
            ->select(['id', 'name'])
            ->get();

        //    $accountsArray = $accounts->map(function ($account) {
        //     return [
        //         'id' => $account->id,
        //         'name' => $account->name,
        //     ];
        // })->toArray(); // Convert the collection to an array
        $accountsArray = [];
        $company->load([
            'branches.agents.clients.invoices.invoiceDetails.task.supplier',
        ]);


        foreach ($accounts as $account) {
            if ($account->name === 'Accounts Receivable') {
                foreach ($company->branches as $branch) { // Loop through branches
                    foreach ($branch->agents as $agent) { // Loop through agents in each branch
                        foreach ($agent->clients as $client) {
                            $accountsArray[] = [
                                'id' => 'account-' . $account->id . ':client-' . $client->id,
                                'name' => 'Client: ' . $client->full_name,
                            ];
                        }
                    }
                }
            } elseif ($account->name === 'Accounts Payable') {
                foreach ($suppliers as $supplier) { // Loop through invoice details
                    $accountsArray[] = [
                        'id' => 'account-' . $account->id . ':supplier-' . $supplier->id, // Ensure unique key
                        'name' => 'Supplier: ' . $supplier->name, // Use supplier's name
                    ];
                }
            } elseif ($account->name === 'Income') {
                foreach ($company->branches as $branch) { // Loop through branches
                    foreach ($branch->agents as $agent) { // Loop through agents in each branch
                        $accountsArray[] = [
                            'id' => 'account-' . $account->id . ':agent-' . $agent->id, // Ensure unique key
                            'name' => 'Agent: ' . $agent->name, // Access the agent's name or other properties
                        ];
                    }
                }
            } else {
                // For other account names, you can keep them simple
                $accountsArray[] = [
                    'id' => 'account-' . $account->id, // Ensure unique key
                    'name' => $account->name,
                ];
            }
        }


        // Prepare data for JournalEntrys (to replace transactions)
        $JournalEntrys = [];
        $groupedJournalEntrys = [];

        foreach ($company->branches as $branch) {
            foreach ($branch->agents as $agent) {
                foreach ($agent->clients as $client) {
                    foreach ($client->invoices as $invoice) {
                        foreach ($invoice->invoiceDetails as $invoiceDetail) {
                            // Retrieve the task associated with this invoiceDetail
                            $task = $invoiceDetail->task; // assuming each invoiceDetail has a related task
                            $taskName = $task ? $task->reference . '-' . $task->additional_info . '-' . $task->venue . '-' . $task->type : null;
                            foreach ($invoiceDetail->JournalEntrys as $JournalEntry) {
                                $groupedJournalEntrys[$taskName][]  = [
                                    'JournalEntry_id' => $JournalEntry->id,
                                    'JournalEntry_name' => $JournalEntry->name,
                                    'client_name' => $client->full_name,
                                    'supplier_name' => $task->supplier->name,
                                    'credit' => $JournalEntry->credit,
                                    'debit' => $JournalEntry->debit,
                                    'balance' => $JournalEntry->balance,
                                    'transaction_date' => $JournalEntry->created_at,
                                    'description' => $JournalEntry->description,
                                    'branch_name' => $branch->name,
                                    'agent_name' => $agent->name,
                                    'type' => $JournalEntry->type,
                                    'invoice_number' => $invoice->invoice_number,
                                    'status' => $invoice->status,
                                    'task_name' => $taskName,

                                ];
                            }
                        }
                    }
                }
            }
        }


        // Pass the data to the view
        return view('accounting.index', [
            'groupedJournalEntrys' => $groupedJournalEntrys,
            'company' => $company,
            'accounts' => $accountsArray,
            'branches' => $company->branches,
            'JournalEntrys' => $JournalEntrys, // To display in the table
        ]);
    }

    public function filterLedgers(Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $accountIdInput = $request->input('account');
        $branchId = $request->input('branch');

        // Parse the account ID format
        $parsedAccount = [];
        if (preg_match('/^account-(\d+)(?::(client|supplier|agent)-(\d+))?$/', $accountIdInput, $matches)) {
            $parsedAccount['account_id'] = $matches[1];
            $parsedAccount['related_type'] = $matches[2] ?? null;
            $parsedAccount['related_id'] = $matches[3] ?? null;
        }

        if (empty($parsedAccount)) {
            return response()->json(['error' => 'Invalid account ID format'], 400);
        }

        // Build the query with conditional filters
        $ledgersQuery = JournalEntry::query()
            ->when($fromDate, fn($query) => $query->where('transaction_date', '>=', $fromDate))
            ->when($toDate, fn($query) => $query->where('transaction_date', '<=', $toDate))
            ->when($parsedAccount['account_id'], fn($query) => $query->where('account_id', $parsedAccount['account_id']))
            ->when($branchId, callback: fn($query) => $query->where('branch_id', $branchId));

        // Add conditions for related entities
        if ($parsedAccount['related_type'] && $parsedAccount['related_id']) {
            switch ($parsedAccount['related_type']) {
                case 'client':
                    $ledgersQuery->where('type_reference_id', $parsedAccount['related_id']);
                    break;
                case 'supplier':
                    $ledgersQuery->where('type_reference_id', $parsedAccount['related_id']);
                    break;
                case 'agent':
                    $ledgersQuery->where('type_reference_id', $parsedAccount['related_id']);
                    break;
            }
        }

        $ledgers = $ledgersQuery->get();

        // Map results with additional context if necessary
        $result = $ledgers->map(fn($ledger) => [
            'invoice_number' => $ledger->invoice ? $ledger->invoice->invoice_number : null,
            'transaction_date' => $ledger->transaction_date,
            'description' => $ledger->description,
            'agent_name' => $ledger->invoice->agent->name,
            'branch_name' => $ledger->branch->name,
            'JournalEntry_name' => $ledger->name ?? null,
            'debit' => $ledger->debit,
            'credit' => $ledger->credit,
        ]);

        return response()->json($result);
    }

    public function exportExcel(Request $request)
    {
        // Extract ledgers and totals from the request
        $ledgers = $request->input('ledgers');
        $totalDebit = $request->input('total_debit');
        $totalCredit = $request->input('total_credit');

        // Export the data along with totals to Excel
        return Excel::download(new LedgerExport($ledgers, $totalDebit, $totalCredit), 'JournalEntryReport.xlsx');
    }

    public function showCompanySummary()
    {
        Gate::authorize('viewAny', Account::class);

        $user = Auth::user();

        if ($user->role_id != Role::COMPANY && $user->company == null) {
            return abort(403, 'Unauthorized action.');
        }

        // Retrieve the company associated with the user and load its branches with agents, clients, invoices, and general ledgers
        $company = Company::where('user_id', $user->id)
            ->with([
                'branches.agents.clients.invoices.transactions' // Eager load everything in one go
            ])
            ->first();

        $accounts = Account::all(['id', 'name']);

        $JournalEntrys = JournalEntry::where('company_id', $company->id)->get();
        // Process summary for branches, agents, clients, and invoices
        $companySummary = $company->branches->map(function ($branch) {
            $branch->total_credits = 0;
            $branch->total_debits = 0;
            $branch->balance = 0;

            // Iterate over agents and clients to calculate totals
            $branch->agents->each(function ($agent) use ($branch) {
                $agent->total_credits = 0;
                $agent->total_debits = 0;
                $agent->balance = 0;

                // Iterate over clients to calculate totals
                $agent->clients->each(function ($client) use ($agent) {
                    $client->total_credits = 0;
                    $client->total_debits = 0;
                    $client->balance = 0;

                    // Iterate over invoices to calculate totals
                    $client->invoices->each(function ($invoice) use ($client) {
                        $invoice->total_credits = $invoice->transactions->where('transaction_type', 'credit')->sum('amount');
                        $invoice->total_debits =  $invoice->transactions->where('transaction_type', 'debit')->sum('amount');
                        $invoice->balance = $invoice->total_credits - $invoice->total_debits;

                        $client->total_credits += $invoice->total_credits;
                        $client->total_debits += $invoice->total_debits;
                        $client->balance += $invoice->balance;
                    });

                    $agent->total_credits += $client->total_credits;
                    $agent->total_debits += $client->total_debits;
                    $agent->balance += $client->balance;
                });

                $branch->total_credits += $agent->total_credits;
                $branch->total_debits += $agent->total_debits;
                $branch->balance += $agent->balance;
            });

            return $branch;
        });

        return view('accounting.summary', compact('company', 'accounts', 'JournalEntrys', 'companySummary'));
    }

    public function getAccountsByCompanyReceivable(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->role_id === Role::ADMIN
            ? (int) $request->company_id
            : getCompanyId($user);

        $accounts = Account::where('company_id', $companyId)
            ->whereIn('level', [4])
            ->where(function ($query) {

                $query->whereHas('parent', function ($query) {
                    $query->where('level', 1)
                        ->whereIn('name', ['Assets', 'Income']);
                })

                    ->orWhereHas('parent.parent', function ($query) {
                        $query->where('level', 1)
                            ->whereIn('name', ['Assets', 'Income']);
                    })

                    ->orWhereHas('parent.parent.parent', function ($query) {
                        $query->where('level', 1)
                            ->whereIn('name', ['Assets', 'Income']);
                    });
            })
            ->orderBy('level') // Order by level in ascending order
            ->get();

        return response()->json(['accounts' => $accounts]);
    }

    public function getAccountsByCompanyPayable(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->role_id === Role::ADMIN
            ? (int) $request->company_id
            : getCompanyId($user);

        $accounts = Account::where('company_id', $companyId)
            ->whereIn('level', [4])
            ->where(function ($query) {

                $query->whereHas('parent', function ($query) {
                    $query->where('level', 1)
                        ->whereIn('name', ['Liabilities', 'Expenses']);
                })

                    ->orWhereHas('parent.parent', function ($query) {
                        $query->where('level', 1)
                            ->whereIn('name', ['Liabilities', 'Expenses']);
                    })

                    ->orWhereHas('parent.parent.parent', function ($query) {
                        $query->where('level', 1)
                            ->whereIn('name', ['Liabilities', 'Expenses']);
                    });
            })
            ->orderBy('level') // Order by level in ascending order
            ->get();

        return response()->json(['accounts' => $accounts]);
    }

    public function getBranchByCompany(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->role_id === Role::ADMIN
            ? (int) $request->company_id
            : getCompanyId($user);

        $branches = Branch::where('company_id', $companyId)->get();

        if ($branches->isEmpty()) {
            return response()->json(['message' => 'No branches found for this company'], 404);
        }

        return response()->json(['branches' => $branches]);
    }

    public function getAgentByBranchCompany(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->role_id === Role::ADMIN
            ? (int) $request->company_id
            : getCompanyId($user);

        $agents = Agent::where('company_id', $companyId)
            ->where('branch_id', $request->branch_id)
            ->get();

        if ($agents->isEmpty()) {
            return response()->json(['message' => 'No agents found for this branch and company'], 404);
        }

        return response()->json(['agents' => $agents]);
    }

    public function getSupplierByCompany(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->role_id === Role::ADMIN
            ? (int) $request->company_id
            : getCompanyId($user);

        // Get all parent account IDs where the name contains "Payable"
        $parentIds = Account::where('name', 'LIKE', '%Payable%')->pluck('id');

        // Retrieve suppliers linked to the selected company and parent accounts
        $suppliers = Account::where('company_id', $companyId)
            ->whereIn('parent_id', $parentIds)
            ->whereNotNull('name') // Ensure name exists for suppliers
            ->get();

        if ($suppliers->isEmpty()) {
            return response()->json(['message' => 'No suppliers found for this company'], 404);
        }

        return response()->json(['suppliers' => $suppliers]);
    }

    public function getAgentClientByCompany(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->role_id === Role::ADMIN
            ? (int) $request->company_id
            : getCompanyId($user);

        // Get all parent account IDs where the name contains "Receivable"
        $parentIds = Account::where('name', 'LIKE', '%Receivable%')->pluck('id');

        // Retrieve agents linked to the selected company and parent accounts
        $agents = Account::where('company_id', $companyId)
            ->whereIn('parent_id', $parentIds)
            ->whereNotNull('name') // Ensure name exists for agents
            ->get();

        if ($agents->isEmpty()) {
            return response()->json(['message' => 'No client or agents found for this company'], 404);
        }

        return response()->json(['agents' => $agents]);
    }

    public function getBankAccountByCompany(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->role_id === Role::ADMIN
            ? (int) $request->company_id
            : getCompanyId($user);

        // Log company ID (optional)
        \Log::info("Fetching Bank Accounts for Company ID: " . $companyId);

        // Get parent account IDs where the name contains "Bank Accounts" and belongs to the selected company
        $parentIds = Account::where('name', 'LIKE', '%Bank Accounts%')
            ->where('company_id', $companyId)
            ->pluck('id');

        \Log::info("Parent Account IDs: " . json_encode($parentIds));

        // Fetch bank accounts under these parent IDs and the selected company
        $bankaccounts = Account::whereIn('parent_id', $parentIds)
            ->where('company_id', $companyId)
            ->get();

        \Log::info("Retrieved Bank Accounts: " . json_encode($bankaccounts));

        if ($bankaccounts->isEmpty()) {
            return response()->json(['message' => 'No bank account has been set for this company'], 404);
        }

        return response()->json(['bankaccounts' => $bankaccounts]);
    }


    public function getInvoicesByJournalEntry(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->role_id === Role::ADMIN
            ? (int) $request->company_id
            : getCompanyId($user);

        // Retrieve general ledger entries for the given company
        $ledgerEntries = JournalEntry::where('company_id', $companyId)
            ->pluck('invoice_id'); // Get associated invoice IDs

        if ($ledgerEntries->isEmpty()) {
            return response()->json(['message' => 'No invoice record found for this company'], 404);
        }

        // Retrieve invoices linked to the general ledger entries, re-scoped to the
        // resolved company (invoice_id values are never cross-company in practice,
        // but this keeps the endpoint self-contained even if that ever changes).
        $invoices = Invoice::whereIn('id', $ledgerEntries)
            ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                $q->where('id', $companyId);
            })
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json(['message' => 'No invoices found for this company'], 404);
        }

        return response()->json(['invoices' => $invoices]);
    }

    public function createPayableDetail()
    {
        Gate::authorize('viewAny', CoaCategory::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $companies = Company::find($companyId);
            } else {
                $companies = Company::all();
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $companies = Company::find($user->company->id);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $companies = Company::find($user->accountant->branch->company_id);
        } else {
            return abort(403, 'Unauthorized action.');
        }

        $branches = collect();
        $accounts = collect();
        $bankAccounts = collect();
        $JournalEntrysPayable = collect();

        if ($companyId) {
            $accountsPayable = Account::where('name', 'Accounts Payable')
                ->where('company_id', $companyId)
                ->first();


            if ($accountsPayable) {
                $descendantIds = $this->getAllDescendantIds($accountsPayable->id);

                $accounts = Account::where('company_id', $companyId)
                    ->whereIn('id', $descendantIds)
                    ->doesntHave('children')
                    ->orderBy('name')
                    ->get();
            }

            $JournalEntrysPayable = JournalEntry::whereIn('type', ['payable', 'expenses'])
                ->where('company_id', $companyId)
                ->where('debit', '>', 0)
                ->with(['invoice', 'referenceAccount'])
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('type');

            $branches = Branch::where('company_id', $companyId)->get();

            $assets = Account::where('name', 'Assets')->where('company_id', $companyId)->first();
            if ($assets) {
                $bankParent = Account::where('parent_id', $assets->id)
                    ->where('name', 'Bank Accounts')
                    ->where('company_id', $companyId)
                    ->first();

                if ($bankParent) {
                    $bankAccounts = Account::where('parent_id', $bankParent->id)
                        ->where('company_id', $companyId)
                        ->get();
                }
            }
        }

        return view('accounting.payable-create', compact(
            'companies',
            'JournalEntrysPayable',
            'companyId',
            'branches',
            'accounts',
            'bankAccounts'
        ));
    }

    /**
     * Get all descendant account IDs recursively
     */
    private function getAllDescendantIds($parentId): array
    {
        $ids = [];
        $children = Account::where('parent_id', $parentId)->pluck('id')->toArray();

        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getAllDescendantIds($childId));
        }

        return $ids;
    }

    /**
     * W7.A. Manual JV, money OUT: Dr the user-picked payable/expense account, Cr the user-picked
     * bank account. See class docblock for the full OFF/ON contract.
     */
    public function storePayableDetail(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::COMPANY, Role::ACCOUNTANT, Role::ADMIN])) {
            return abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'account_id' => 'required|integer',
            'branch_id' => 'required|integer',
            'bank_account' => 'required|integer',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.001',
            'type' => 'required|in:payable,expenses',
            'client_uuid' => 'nullable|string|max:64',
        ]);

        $companyId = getCompanyId($user);
        $amount = round((float) $request->amount, 3);
        $branchId = (int) $request->branch_id;

        $bankAccount = Account::find($request->bank_account);
        if (!$bankAccount) {
            return redirect()->back()->with('error', 'Bank account not found.');
        }

        $selectedAccount = Account::find($request->account_id);
        if (!$selectedAccount) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        $docDate = Carbon::parse($request->transaction_date);

        // Legacy body moved VERBATIM (own PY-/EX- reference-number mint, raw Transaction::create()
        // + two JournalEntry::create() calls) -- runs unchanged on the OFF path, never on ON.
        $legacy = function () use ($request, $companyId, $branchId, $amount, $bankAccount, $selectedAccount, $docDate) {
            $prefix = $request->type === 'payable' ? 'PY' : 'EX';
            $lastTransaction = Transaction::where('company_id', $companyId)
                ->where('reference_number', 'like', $prefix . '-%')
                ->orderByDesc('id')
                ->first();

            $nextNumber = 1;
            if ($lastTransaction) {
                $lastNumber = (int) str_replace($prefix . '-', '', $lastTransaction->reference_number);
                $nextNumber = $lastNumber + 1;
            }
            $referenceNumber = $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $transaction = Transaction::create([
                'entity_id' => $companyId,
                'entity_type' => 'company',
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'transaction_type' => 'debit', // Money going OUT
                'amount' => $amount,
                'date' => $docDate->format('Y-m-d H:i:s'),
                'description' => $request->description,
                'reference_number' => $referenceNumber,
                'reference_type' => 'Payment', // Money going OUT
                'name' => $bankAccount->name,
                'transaction_date' => $docDate->format('Y-m-d H:i:s'),
            ]);

            $baseData = [
                'transaction_id' => $transaction->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'transaction_date' => $request->transaction_date,
                'type' => $request->type,
                'type_reference_id' => $selectedAccount->id,
            ];

            JournalEntry::create(array_merge($baseData, [
                'account_id' => $selectedAccount->id,
                'description' => $request->description . ' (Sent payment from ' . strtoupper($bankAccount->name) . ' to ' . strtoupper($selectedAccount->name) . ')',
                'name' => $selectedAccount->name,
                'debit' => $amount,
                'credit' => 0,
                'balance' => $amount,
            ]));

            JournalEntry::create(array_merge($baseData, [
                'account_id' => $bankAccount->id,
                'description' => $request->description . ' (Deducted from ' . strtoupper($bankAccount->name) . ' to ' . strtoupper($selectedAccount->name) . ')',
                'name' => $bankAccount->name,
                'debit' => 0,
                'credit' => $amount,
                'balance' => 0,
            ]));

            return $transaction;
        };

        $narration = (string) $request->description;
        $idempotencyKey = $this->manualJournalIdempotencyKey(
            $companyId, $branchId, $selectedAccount->id, $bankAccount->id, $amount,
            $narration, (string) $request->transaction_date, $request->input('client_uuid'),
        );

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: 'JV',
            subType: 'JV_PAYABLE',
            docDate: $docDate,
            narration: $narration,
            lines: [
                new LineDraft(
                    purposeCode: '', accountId: $selectedAccount->id, side: 'debit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'MANUAL_JV_DEBIT', description: $narration,
                    ledgerType: (string) $request->type, partyAccountRef: $selectedAccount->id,
                ),
                new LineDraft(
                    purposeCode: '', accountId: $bankAccount->id, side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'MANUAL_JV_CREDIT', description: $narration,
                ),
            ],
            idempotencyKey: $idempotencyKey,
            userId: Auth::id(),
        );

        DB::beginTransaction();
        try {
            $posted = $this->seam->post($draft, $legacy, 'accounting.manual-jv.payable');
            $transaction = $this->resolvePostedTransaction($posted, $draft);
            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.manual_jv_payable_failed', [
                'company_id' => $companyId,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages(['amount' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payable Entry Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }

        return redirect()->route('payable-details.payable-create')
            ->with('success', 'Entry added successfully! Reference: ' . $transaction->reference_number);
    }

    public function createReceivableDetail()
    {
        Gate::authorize('viewAny', CoaCategory::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $companies = Company::find($companyId);
            } else {
                $companies = Company::all();
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $companies = Company::find($user->company->id);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $companies = Company::find($user->accountant->branch->company_id);
        } else {
            return abort(403, 'Unauthorized action.');
        }

        $suppliers = collect();
        $clients = collect();
        $branches = collect();
        $accounts = collect();
        $agentsClients = collect();
        $bankAccounts = collect();
        $invoices = collect();
        $JournalEntrysReceivable = collect();

        if ($companyId) {
            $parentIds = Account::where('name', 'Accounts Payable')
                ->where('company_id', $companyId)
                ->pluck('id');
            $suppliers = Account::whereIn('parent_id', $parentIds)->get();

            $JournalEntrysReceivable = JournalEntry::whereIn('type', ['receivable', 'income'])
                ->where('company_id', $companyId)
                ->with(['invoice', 'referenceAccount'])
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('type');

            $parentIdClients = Account::where('name', 'Accounts Receivable')
                ->where('company_id', $companyId)
                ->pluck('id');
            $clients = Account::whereIn('parent_id', $parentIdClients)->get();

            // Load branches
            $branches = Branch::where('company_id', $companyId)->get();

            $accounts = Account::where('company_id', $companyId)
                ->whereIn('level', [3, 4, 5])
                ->get();

            $agents = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->get();
            $clientsList = Client::where('company_id', $companyId)->get();

            $agentsClients = $agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'type' => 'agent'])
                ->merge($clientsList->map(fn($c) => [
                    'id' => $c->id,
                    'name' => trim($c->first_name . ' ' . $c->middle_name . ' ' . $c->last_name),
                    'type' => 'client'
                ]));

            $assets = Account::where('name', 'Assets')->where('company_id', $companyId)->first();
            $bankAccounts = collect();

            if ($assets) {
                $bankParent = Account::where('parent_id', $assets->id)
                    ->where('name', 'Bank Accounts')
                    ->where('company_id', $companyId)
                    ->first();

                if ($bankParent) {
                    $bankAccounts = Account::where('parent_id', $bankParent->id)
                        ->where('company_id', $companyId)
                        ->get();
                }
            }

            $invoices = Invoice::whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
                ->with('client')
                ->latest()
                ->get();
        }

        return view('accounting.receivable-create', compact(
            'companies',
            'suppliers',
            'clients',
            'JournalEntrysReceivable',
            'companyId',
            'branches',
            'accounts',
            'agentsClients',
            'bankAccounts',
            'invoices'
        ));
    }

    /**
     * W7.A. Manual JV, money IN: Dr the user-picked bank account, Cr the user-picked
     * receivable/income account. See class docblock for the full OFF/ON contract.
     */
    public function storeReceivableDetail(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::COMPANY, Role::ACCOUNTANT, Role::ADMIN])) {
            return abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'account_id' => 'required|integer',
            'branch_id' => 'required|integer',
            'bank_account' => 'required|integer',
            'invoice_id' => 'nullable|integer',
            'description' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.001',
            'type' => 'required|in:receivable,income',
            'client_uuid' => 'nullable|string|max:64',
        ]);

        $companyId = getCompanyId($user);
        $amount = round((float) $request->amount, 3);
        $branchId = (int) $request->branch_id;
        $invoiceId = $request->invoice_id !== null ? (int) $request->invoice_id : null;

        $bankAccount = Account::find($request->bank_account);
        if (!$bankAccount) {
            return redirect()->back()->with('error', 'Bank account not found.');
        }

        $selectedAccount = Account::find($request->account_id);
        if (!$selectedAccount) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        $docDate = Carbon::parse($request->transaction_date);

        // Legacy body moved VERBATIM (own RV-/IN- reference-number mint, raw Transaction::create()
        // + two JournalEntry::create() calls) -- runs unchanged on the OFF path, never on ON.
        $legacy = function () use ($request, $companyId, $branchId, $invoiceId, $amount, $bankAccount, $selectedAccount, $docDate) {
            $prefix = $request->type === 'receivable' ? 'RV' : 'IN';
            $lastTransaction = Transaction::where('company_id', $companyId)
                ->where('reference_number', 'like', $prefix . '-%')
                ->orderByDesc('id')
                ->first();

            $nextNumber = 1;
            if ($lastTransaction) {
                $lastNumber = (int) str_replace($prefix . '-', '', $lastTransaction->reference_number);
                $nextNumber = $lastNumber + 1;
            }
            $referenceNumber = $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $transaction = Transaction::create([
                'entity_id' => $companyId,
                'entity_type' => 'company',
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'transaction_type' => 'credit',
                'amount' => $amount,
                'date' => $docDate->format('Y-m-d H:i:s'),
                'description' => $request->description,
                'invoice_id' => $invoiceId,
                'reference_number' => $referenceNumber,
                'reference_type' => 'Receipt',
                'name' => $bankAccount->name,
                'transaction_date' => $docDate->format('Y-m-d H:i:s'),
            ]);

            $baseData = [
                'transaction_id' => $transaction->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'invoice_id' => $invoiceId,
                'transaction_date' => $request->transaction_date,
                'type' => $request->type,
                'type_reference_id' => $selectedAccount->id,
            ];

            JournalEntry::create(array_merge($baseData, [
                'account_id' => $bankAccount->id,
                'description' => $request->description . ' (Received to ' . strtoupper($bankAccount->name) . ' from ' . strtoupper($request->name) . ')',
                'name' => $bankAccount->name,
                'debit' => $amount,
                'credit' => 0,
                'balance' => $amount,
            ]));

            JournalEntry::create(array_merge($baseData, [
                'account_id' => $selectedAccount->id,
                'description' => $request->description . ' (Cleared & added to ' . strtoupper($bankAccount->name) . ' from ' . strtoupper($request->name) . ')',
                'name' => $request->name,
                'debit' => 0,
                'credit' => $amount,
                'balance' => 0,
            ]));

            return $transaction;
        };

        $narration = (string) $request->description;
        $idempotencyKey = $this->manualJournalIdempotencyKey(
            $companyId, $branchId, $bankAccount->id, $selectedAccount->id, $amount,
            $narration, (string) $request->transaction_date, $request->input('client_uuid'),
        );

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: 'JV',
            subType: 'JV_RECEIVABLE',
            docDate: $docDate,
            narration: $narration,
            lines: [
                new LineDraft(
                    purposeCode: '', accountId: $bankAccount->id, side: 'debit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'MANUAL_JV_DEBIT', description: $narration,
                ),
                new LineDraft(
                    purposeCode: '', accountId: $selectedAccount->id, side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'MANUAL_JV_CREDIT', description: $narration,
                    ledgerType: (string) $request->type, partyAccountRef: $selectedAccount->id,
                    partyName: (string) $request->name,
                ),
            ],
            idempotencyKey: $idempotencyKey,
            invoiceId: $invoiceId,
            userId: Auth::id(),
        );

        DB::beginTransaction();
        try {
            $posted = $this->seam->post($draft, $legacy, 'accounting.manual-jv.receivable');
            $transaction = $this->resolvePostedTransaction($posted, $draft);
            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.manual_jv_receivable_failed', [
                'company_id' => $companyId,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages(['amount' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Receivable Entry Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }

        return redirect()->route('receivable-details.receivable-create')
            ->with('success', 'Entry added successfully! Reference: ' . $transaction->reference_number);
    }

    public function createBankPayment()
    {
        $user = Auth::user();

        if ($user->role_id != Role::ADMIN) {
            if ($user->role_id != Role::COMPANY) {
                return abort(403, 'Unauthorized action.');
            } else {
                $companies = Company::where('user_id', $user->id)->get();
            }
        } else {
            $companies = Company::all();
        }

        $parentIds = Account::where('name', 'Accounts Payable')->pluck('id');
        $suppliers = Account::whereIn('parent_id', $parentIds)->get();

        $JournalEntrysPayable = JournalEntry::whereIn('type', ['payable', 'expenses'])
            ->orderByDesc('created_at')  // Sort by date in descending order
            ->get()
            ->groupBy('type');

        $parentIdClients = Account::where('name', 'Accounts Receivable')->pluck('id');
        $clients = Account::whereIn('parent_id', $parentIdClients)->get();

        return view('accounting.bank-payment.create', compact('companies', 'suppliers', 'clients', 'JournalEntrysPayable'));
    }

    /**
     * W7.A. Manual JV, transfer: Dr the user-picked target account, Cr the user-picked bank
     * account -- "Sent payment FROM bank TO account" (see the legacy description strings below).
     *
     * UNREACHABLE ON EITHER PROD OR LOCAL (confirmed: no route in routes/web.php names this
     * action, and `resources/views/accounting/bank-payment/create.blade.php` does not exist --
     * matching `tests/Feature/Security/BankPaymentIntegrityTest.php`'s own docblock, which already
     * documented this for the HF-3 hotfix and exercises this method by calling it directly). Cut
     * over anyway per the W6 gate's static "no raw ledger writer survives" audit; no route/view
     * work was done since there is nothing live to wire.
     *
     * PRE-EXISTING BUG preserved verbatim in the `$legacy` closure only (never on the ON path):
     * HEAD's own two `JournalEntry::create($validated)` calls both write `account_id =
     * $validated['account_id']` -- the SECOND call never overwrites it to `$validated['bank_account']`
     * despite building a debit/credit pair that reads as "one leg per account" -- so both
     * `journal_entries` rows land on the SAME (target) account and self-cancel; only the raw
     * `actual_balance` increment/decrement calls actually move money between the two accounts.
     * `BankPaymentIntegrityTest` already pins this exact `actual_balance` shape (bank debited,
     * counterparty credited) without asserting which account_id either journal_entries row carries,
     * so the legacy closure below is untouched byte-for-byte to keep that test green. The ON path
     * does NOT reproduce this bug: its two lines target the two DIFFERENT accounts the user
     * actually picked, exactly like `storePayableDetail()`/`storeReceivableDetail()` above.
     */
    public function storeBankPayment(Request $request)
    {
        $user = Auth::user();

        if ($user->role_id != Role::COMPANY) {
            return abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'account_id' => 'required|integer',
            'bank_account' => 'required|integer',
            'branch_id' => 'required|integer',
            'transaction_id' => 'nullable|integer',
            'description' => 'required|string|max:255',
            'debit' => 'nullable|numeric',
            'credit' => 'nullable|numeric',
            'balance' => 'nullable|numeric',
            'invoice_id' => 'nullable|integer',
            'voucher_number' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'invoice_detail_id' => 'nullable|integer',
            'type_reference_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0',
            'client_uuid' => 'nullable|string|max:64',
        ]);

        $encryptionService = new EncryptionService();
        $type_reference_number = $encryptionService->generateEncryptedNumber();

        $validated['type_reference_id'] = $type_reference_number;

        //Account
        // Only Role::COMPANY reaches this point (guard above), so the company is
        // always the acting user's own company. getCompanyId() also resolves the
        // ADMIN case correctly (via session company_id) should the guard above
        // ever be relaxed.
        $validated['company_id'] = getCompanyId($user);
        $companyId = (int) $validated['company_id'];
        $branchId = (int) $validated['branch_id'];

        $accountName = Account::find($validated['account_id']);
        $bankaccountId = Account::find($validated['bank_account']);
        $bankaccountName = $bankaccountId->name;

        //Account_From (company_bank)
        $companyName = Company::find($validated['company_id'])?->name;

        $amount = round((float) $validated['amount'], 3);

        // Legacy body moved VERBATIM, including the PRE-EXISTING BUG documented in this method's
        // own docblock -- runs unchanged on the OFF path, never on ON. HEAD never creates a
        // Transaction header row here at all (journal_entries.transaction_id is whatever the
        // caller supplied, or null) -- this closure's return value is therefore deliberately
        // discarded by the caller below rather than normalised through
        // self::resolvePostedTransaction(), which assumes a real header row exists.
        $legacy = function () use ($validated, $bankaccountName, $accountName, $request) {
            $legacyData = $validated;
            $legacyData['debit'] = $legacyData['amount'];
            $legacyData['credit'] = "0.00";
            $legacyData['balance'] = $legacyData['amount'];
            $legacyData['description'] = $request->description . ' (Sent payment from ' . strtoupper($bankaccountName) . ' to ' . strtoupper($accountName->name) . ')';
            $legacyData['name'] = Company::find($legacyData['company_id'])?->name;

            DB::transaction(function () use ($legacyData, $bankaccountName, $accountName, $request) {
                JournalEntry::create($legacyData);

                //update actual_balance
                Account::where('id', $legacyData['bank_account'])
                    ->decrement('actual_balance', $legacyData['amount']);

                //Account_To (supplier_name)
                $legacyData['debit'] = "0.00";
                $legacyData['credit'] = $legacyData['amount'];
                $legacyData['balance'] = "0.00";
                $legacyData['description'] = $request->description . ' (Deducted from ' . strtoupper($bankaccountName) . ' to ' . strtoupper($accountName->name) . ')';
                $legacyData['name'] = $accountName->name;

                JournalEntry::create($legacyData);

                //update actual_balance
                Account::where('id', $legacyData['account_id'])
                    ->increment('actual_balance', $legacyData['amount']);
            });

            return null;
        };

        $narration = (string) $request->description;
        $docDate = Carbon::parse($request->transaction_date);
        $idempotencyKey = $this->manualJournalIdempotencyKey(
            $companyId, $branchId, (int) $validated['account_id'], (int) $validated['bank_account'],
            $amount, $narration, (string) $request->transaction_date, $request->input('client_uuid'),
        );

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: 'JV',
            subType: 'JV_TRANSFER',
            docDate: $docDate,
            narration: $narration,
            lines: [
                new LineDraft(
                    purposeCode: '', accountId: (int) $validated['account_id'], side: 'debit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'MANUAL_JV_DEBIT', description: $narration,
                ),
                new LineDraft(
                    purposeCode: '', accountId: (int) $validated['bank_account'], side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'MANUAL_JV_CREDIT', description: $narration,
                ),
            ],
            idempotencyKey: $idempotencyKey,
            userId: Auth::id(),
        );

        DB::beginTransaction();
        try {
            // Return value deliberately discarded -- see $legacy's own comment above for why (no
            // Transaction header row exists on the OFF path to normalise via
            // self::resolvePostedTransaction(), and the success message below carries no reference
            // number, unlike the other two manual-JV routes).
            $this->seam->post($draft, $legacy, 'accounting.manual-jv.transfer');
            DB::commit();
        } catch (PostingException $e) {
            DB::rollBack();
            Log::critical('accounting.manual_jv_transfer_failed', [
                'company_id' => $companyId,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages(['amount' => [$e->getMessage()]]);
        }

        return redirect()->route('bank-payment.create')
            ->with('success', 'Entry added successfully!');
    }

    /**
     * W7.A (new -- HEAD has no reverse/edit/delete action for any of the three manual-JV screens
     * above). The only way to undo a posted manual JV: `PostingService::reverse()`, the sole
     * reversal mechanism every other engine feeder in this codebase uses -- there is no raw
     * delete/undo path, matching the brief's "Reversal of a manual JV via engine reverse()".
     *
     * Engine-only by construction: `PostingService::reverse()` calls `post()` internally for the
     * reversal document, which re-runs the same W0 kill-switch gate `PostingSeam` checks -- a
     * company with the engine OFF gets a clear refusal here rather than a raw
     * `PostingEngineDisabledException`.
     */
    public function reverseManualJournal(Request $request, int $transaction)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::COMPANY, Role::ACCOUNTANT, Role::ADMIN])) {
            return abort(403, 'Unauthorized action.');
        }

        $companyId = getCompanyId($user);

        $original = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('id', $transaction)
            ->where('company_id', $companyId)
            ->where('doc_type', 'JV')
            ->whereIn('sub_type', ['JV_PAYABLE', 'JV_RECEIVABLE', 'JV_TRANSFER'])
            ->first();

        if ($original === null) {
            return redirect()->back()->with('error', 'Manual journal voucher not found for this company.');
        }

        try {
            $this->postingService->reverse($original, now(), Auth::id());
        } catch (PostingException $e) {
            Log::critical('accounting.manual_jv_reverse_failed', [
                'company_id' => $companyId,
                'transaction_id' => $transaction,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages(['transaction' => [$e->getMessage()]]);
        }

        return redirect()->back()->with('success', 'Manual journal voucher reversed.');
    }
}
