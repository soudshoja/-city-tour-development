<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\Refund;
use App\Models\Account;
use App\Models\Credit;
use App\Models\Transaction;
use App\Models\Role;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Currency;
use App\Models\Branch;
use App\Http\Controllers\JournalEntryController;
use App\Models\JournalEntry;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $allCreditRecords = Credit::with('client');

        if ($user->role_id == Role::ADMIN) {

            $allCreditRecords = $allCreditRecords; // this may seem redundant, but it allows for future modifications if needed

        } elseif ($user->role_id == Role::COMPANY) {

            $allCreditRecords = $allCreditRecords->where('company_id', $user->company->id);

        } elseif ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        if($request->has('search')) {
            $search = $request->input('search');
            $allCreditRecords = $allCreditRecords->where(function ($query) use ($search) {

                $searchTerm = '%' . strtolower($search) . '%';

                $query->whereHas('client', function ($q) use ($searchTerm) {
                    $q->where('first_name', 'like', $searchTerm)
                        ->orWhere('middle_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhere('phone', 'like', $searchTerm)
                        ->orWhereHas('agent', function ($q) use ($searchTerm) {
                            $q->where('name', 'like', $searchTerm)
                                ->orWhere('amadeus_id', 'like', $searchTerm)
                                ->orWhere('email', 'like', $searchTerm)
                                ->orWhere('phone_number', 'like', $searchTerm);
                        });
                    
                })->orWhere('description', 'like', $searchTerm);
            });
        }

        $totalCredits = $allCreditRecords->count();
        $totalCreditsAmount = $allCreditRecords->sum('amount');

        $allCreditRecords = $allCreditRecords
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Tenant isolation (security fix, W41): this feeds the /credits view's dropdowns
        // (credits.index.blade.php's agent/client topup-modal selects) but was completely
        // unscoped -- Agent::all() / Client::whereIn('agent_id', <every agent id system-wide>) /
        // Invoice::all() -- while $allCreditRecords just above IS correctly scoped for
        // Role::COMPANY. Any Role::COMPANY user opening /credits therefore received every other
        // company's agents, clients and invoices. Scope via the agent -> branch -> company chain
        // this app treats as a client/invoice's tenant (not the denormalized/nullable
        // clients.company_id column), mirroring InvoiceController::index()'s
        // `Agent::whereHas('branch', ...)` / `Invoice::whereHas('agent.branch', ...)` and
        // AccountingController.php:740/766's identical queries. $companyId is falsy only for an
        // ADMIN with no company selected (getCompanyId()'s established "unscoped admin" case --
        // see assertSameCompanyOrUnscopedAdmin() below), which keeps seeing everything, matching
        // ReceiptVoucherController::create()'s same fallback-to-`::all()` convention. `invoices`
        // is accepted by the view via compact() but never rendered by credits/index.blade.php, so
        // narrowing it changes no visible behaviour.
        $agents = $companyId
            ? Agent::whereHas('branch', fn ($q) => $q->where('company_id', $companyId))->get()
            : Agent::all();

        $clients = $companyId
            ? Client::whereHas('agent.branch', fn ($q) => $q->where('company_id', $companyId))->get()
            : Client::whereIn('agent_id', Agent::pluck('id'))->get();

        $invoices = $companyId
            ? Invoice::whereHas('agent.branch', fn ($q) => $q->where('company_id', $companyId))->get()
            : Invoice::all();

        $currencies = Currency::all();

        return view('credits.index', compact('allCreditRecords', 'totalCredits', 'totalCreditsAmount', 'agents', 'clients', 'invoices', 'currencies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'client_id' => 'required|exists:clients,id',
            'task_id' => 'nullable|exists:tasks,id',
            'type' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
        ]);

        Credit::create($request->all());

        return redirect()->route('credits.index')->with('success', 'Credit created successfully.');
    }

    /**
     * AJAX credit-ledger rows backing clients.index's statement panel
     * (resources/views/clients/index.blade.php's ledgerUrlTemplate /
     * fetch('/credits/filter?client_id=...')). 'exists:clients,id' only
     * proves the id is a real client, not that it belongs to the caller,
     * so authorize against the same ClientPolicy::view() that
     * ClientController::showCredit() already uses for the equivalent
     * server-rendered statement -- a cross-company user gets a 403 here
     * exactly as it does there. See tests/Feature/Security/
     * ClientCreditStatementAccessTest.php and
     * CreditFilterTenantIsolationTest.php.
     */
    public function filter(Request $request)
    {
        // This endpoint is only ever called via fetch() from clients/index.blade.php
        // (a plain fetch() sends neither X-Requested-With nor an Accept: */json
        // header, so Illuminate\Http\Request::expectsJson() is false here).
        // $request->validate() would therefore treat a validation failure as a
        // normal browser request and 302-redirect back() instead of returning
        // JSON — and since this route is gated module:crm while credits.index
        // (a common "back" target) is gated module:accounting, that redirect
        // could 404 for a package client with CRM but not accounting (R1/CRM-3).
        // Build the validator manually so this endpoint ALWAYS answers in JSON,
        // success or failure, and never redirects anywhere.
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid filter parameters.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = Client::findOrFail($request->client_id);
        Gate::authorize('view', $client);

        $credits = Credit::where('client_id', $request->client_id)
            ->whereDate('created_at', '>=', $request->from)
            ->whereDate('created_at', '<=', $request->to)
            ->orderBy('id', 'desc')
            ->get(['created_at', 'type', 'description', 'amount']);

        return response()->json($credits->map(function ($credit) {
            return [
                'date' => $credit->created_at->format('Y-m-d'),
                'type' => $credit->type,
                'description' => $credit->description,
                'amount' => $credit->amount,
            ];
        }));
    }

    public function creditTopup(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|exists:clients,id',
            'agent_id'     => 'required|exists:agents,id',
            'amount'        => 'required|numeric|min:0',
            'description'   => 'nullable|string|max:255',
            'invoice_id'    => 'nullable|exists:invoices,id',
            'account_id'    => 'nullable|exists:accounts,id',
        ]);

        $user = Auth::user();
        $client = Client::with('agent.branch.company')->findOrFail($request->client_id);
        $agent = Agent::with('branch.company')->findOrFail($request->agent_id);

        // Tenant isolation (security fix, W40): 'exists:clients,id' / 'exists:agents,id' only
        // prove the ids are *real* rows, not that they belong to the caller's own company -- and
        // the company_id written onto every row below is taken from $agent->branch->company->id,
        // i.e. from the ATTACKER-SUPPLIED agent. Without this check, any authenticated user could
        // post a client_id/agent_id pair belonging to a different company and inject real
        // Credit/Transaction/JournalEntry rows into that company's books. Resolve the client's
        // company the same way ClientPolicy::view() does (via its agent -> branch -> company
        // chain, not the denormalized/nullable clients.company_id column) so this matches the
        // authorization semantics already established for Client elsewhere in the app.
        $clientCompanyId = $client->agent?->branch?->company?->id;
        $agentCompanyId = $agent->branch?->company?->id;

        // Client and agent must belong to the SAME company -- this alone stops a caller from
        // mixing a legitimately-owned client with another company's agent (the company_id that
        // ends up on every row below comes from the agent side only).
        abort_unless(
            $clientCompanyId && $agentCompanyId && (int) $clientCompanyId === (int) $agentCompanyId,
            403,
            'Unauthorized action.'
        );

        // Mirrors ReceiptVoucherController::assertSameCompanyOrUnscopedAdmin() /
        // BankPaymentController's identical copy: everyone except an admin with no company
        // selected must match their own company exactly. Since client/agent company was just
        // proven equal above, checking either one here also covers the other.
        $this->assertSameCompanyOrUnscopedAdmin($user, (int) $agentCompanyId);

        $topupBy = $user->getRoleNames()->first();

        DB::beginTransaction();

        try {
            Credit::create([
                'company_id'        => $agent->branch->company->id,
                'client_id'         => $client->id,
                'branch_id'         => $agent->branch->id,
                'type'              => 'Topup',
                'description'       => 'Manual Topup for ' . $client->full_name,
                'amount'            => $request->amount,
                'topup_by'          => ucfirst($topupBy),
            ]);

            Transaction::create([
                'branch_id'         => $agent->branch->id,
                'company_id'        => $agent->branch->company->id,
                'entity_id'         => $agent->branch->company->id,
                'entity_type'       => 'Company',
                'transaction_type'  => 'credit',
                'amount'            => $request->amount,
                'description'       => 'Company Advance to Client: ' . $client->full_name,
                'reference_type'    => 'Payment',
                'transaction_date' => now(),
            ]);

            $transaction = Transaction::create([
                'branch_id'         => $agent->branch->id,
                'company_id'        => $agent->branch->company->id,
                'entity_id'         => $client->id,
                'entity_type'       => 'Client',
                'transaction_type'  => 'debit',
                'amount'            => $request->amount,
                'description'       => 'Client Credit of ' . $client->full_name,
                'reference_type'    => 'Payment',
                'transaction_date' => now(),
            ]);

            $liabilitiesAccount = Account::where('name', 'Liabilities')
                ->where('company_id', $agent->branch->company->id)
                ->first();

            $clientAdvance = Account::where('name', 'Client')
                ->where('root_id', $liabilitiesAccount->id ?? null)
                ->where('company_id', $agent->branch->company->id)
                ->first();

            $paymentGateway = Account::where('name', 'Payment Gateway')
                    ->where('company_id', $agent->branch->company_id)
                    ->where('parent_id', $clientAdvance->id)
                    ->first();
            if (!$paymentGateway) {
                throw new Exception('Payment Gateway account not found');
            }

            if ($paymentGateway) {
                JournalEntry::create([
                    'transaction_id'      => $transaction->id,
                    'branch_id'           => $agent->branch->id,
                    'company_id'          => $agent->branch->company->id,
                    'account_id'          => $paymentGateway->id,
                    'transaction_date'    => now(),
                    'description'         => 'Advance Payment for: ' . $client->full_name,
                    'debit'               => 0,
                    'credit'              => $request->amount,
                    'balance'             => $paymentGateway->actual_balance - $request->amount,
                    'name'                => $client->full_name,
                    'type'                => 'receivable',
                    'voucher_number'      => 'MTU-' . now()->timestamp,
                    'type_reference_id'   => $paymentGateway->id,
                ]);

                $paymentGateway->actual_balance -= $request->amount;
                $paymentGateway->save();
            }

            $receivableRoot = Account::where('name', 'Assets')
                ->where('company_id', $agent->branch->company->id)
                ->first();

            $clientReceivable = Account::where('name', 'Clients')
                ->where('root_id', $receivableRoot->id ?? null)
                ->where('company_id', $agent->branch->company->id)
                ->first();

            if ($clientReceivable) {
                JournalEntry::create([
                    'transaction_id'      => $transaction->id,
                    'branch_id'           => $agent->branch->id,
                    'company_id'          => $agent->branch->company->id,
                    'account_id'          => $clientReceivable->id,
                    'transaction_date'    => now(),
                    'description'         => 'Manual Topup Receivable: ' . $client->full_name,
                    'debit'               => $request->amount,
                    'credit'              => 0,
                    'balance'             => $clientReceivable->actual_balance + $request->amount,
                    'name'                => $client->full_name,
                    'type'                => 'receivable',
                    'voucher_number'      => 'MTU-' . now()->timestamp,
                    'type_reference_id'   => $clientReceivable->id,
                ]);

                $clientReceivable->actual_balance += $request->amount;
                $clientReceivable->save();
            }

            DB::commit();

            return redirect()->back()->with('success', 'Client credit successfully topped up.');
        } catch (Exception $e) {
            DB::rollBack();
            logger()->error('Topup failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Topup failed. Please try again.');
        }
    }

    /**
     * Mirrors ReceiptVoucherController::assertSameCompanyOrUnscopedAdmin() /
     * BankPaymentController's identical copy: an admin acting with no company selected
     * (getCompanyId() falsy) is the app's established "unscoped" admin case and may act across
     * companies; every other caller -- including an admin who HAS a company selected via
     * session -- must match exactly. Aborts 403 rather than returning a bool, same as the other
     * two copies.
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
}
