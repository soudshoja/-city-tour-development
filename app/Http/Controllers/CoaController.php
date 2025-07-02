<?php

namespace App\Http\Controllers;
use App\Imports\AccountsImport;
use App\Exports\AccountsExport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CoaCategory;
use App\Models\Supplier;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Sequence;
use App\Models\SupplierCompany;
use App\Models\Task;
use App\Models\Transaction;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Illuminate\Support\Facades\Auth;

class CoaController extends Controller
{
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Retrieve the company associated with the user
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists before proceeding
        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }

        // Fetch all agents related to the company
        $agents = $company->agents()
            ->with('branch')
            ->get();
        $agentIds = $agents->pluck('id')->toArray();

        // Fetch invoices and clients related to the agents
        $invoices = Invoice::with('agent.branch', 'client')
            ->whereIn('agent_id', $agentIds)
            ->get();

        $clients = Client::whereIn('agent_id', $agentIds)
            ->with('agent.branch')
            ->get();

        // Fetch all suppliers
        $suppliers = Supplier::all();

        $branches = Branch::where('company_id', $company->id)->get();

        // Get  data from the privates function

        $assetsAccount = Account::where('name', 'Assets')->first();
        $liabilitiesAccount = Account::where('name', 'Liabilities')->first();
        $incomesAccount = Account::where('name', 'Income')->first();
        $expensesAccount = Account::where('name', 'Expenses')->first();
        $equitiesAccount = Account::where('name', 'Equity')->first();

        $assets = $this->childAccount($assetsAccount, 'normal');
        $liabilities = $this->childAccount($liabilitiesAccount, 'reverse');
        // dd($liabilities);
        $incomes = $this->childAccount($incomesAccount, 'reverse');
        $expenses = $this->childAccount($expensesAccount, 'normal');
        $equities = $this->childAccount($equitiesAccount, 'reverse');

        // $assets = $this->getAssets();
        // $liabilities = $this->getLiabilities();
        // $incomes = $this->getIncome();
        // $expenses = $this->getExpenses();
        // $equities = $this->getEquity();

        return view('coa.index', compact('assets',  'liabilities', 'incomes', 'expenses', 'equities', 'invoices', 'clients', 'suppliers', 'branches', 'agents'));
    }

    public function addCategory(Request $request)
    {
        if (auth()->user()->company == null) {
            return response()->json(['success' => false, 'message' => 'User not authorized'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'level' => 'required|integer',
            'root_id' => 'required|integer',
            'parent_id' => 'required|integer',
            'entity' => 'nullable| enum: client, agent, branch',
            'client' => 'required_if:entity,client|integer',
            'agent' => 'required_if:entity,agent|integer',
            'branch' => 'required_if:entity,branch|integer',
            // 'account_type' => 'required|string|max:255',
            // 'budget_balance' => 'required|numeric',
            // 'actual_balance' => 'required|numeric',
            // 'variance' => 'required|numeric',
        ]);

        $existingCode = Account::where('code', $request->code)->first();

        if ($existingCode) {
            return redirect()->back()->with('error', 'Code already exists');
        }

        try {
            $category = new Account();
            $category->name = $request->name;
            $category->code = $request->code;
            $category->level = $request->level;
            $category->parent_id = $request->parent_id;
            $category->variance = 0;
            $category->budget_balance = 0;
            $category->actual_balance = 0;
            $category->company_id = auth()->user()->company->id;
            $category->root_id  = $request->root_id;

            $category->save();
        } catch (Exception $e) {

            logger('Error creating category: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Error creating category');
        }

        return redirect()->back()->with('success', 'Category created successfully');
    }

    public function childAccount($account, $debitCreditType = 'normal')
    {
        $childAccounts = Account::where('parent_id', $account->id)->get();

        $totalDebit = 0;
        $totalCredit = 0;

        if ($childAccounts->isNotEmpty()) {
            $account->childAccounts = $childAccounts;

            foreach ($childAccounts as $child) {
                $this->childAccount($child, $debitCreditType); // Recursively get child accounts

                // Sum up the debit and credit from child accounts
                $totalDebit += $child->debit ?? 0;
                $totalCredit += $child->credit ?? 0;
            }

            // Assign the summed debit and credit to the parent account
            $account->debit = (string)$totalDebit;
            $account->credit = (string)$totalCredit;

            if ($debitCreditType == 'normal') {
                $account->balance = bcsub($totalDebit, $totalCredit, 2);
            } else if ($debitCreditType == 'reverse') {
                $account->balance = bcsub($totalCredit, $totalDebit, 2);
            } else {
                throw new Exception('Invalid debitCreditType');
            }
        } else {
            // If it's the last level, calculate debit and credit from journal entries
            $journalEntries = JournalEntry::with('transaction')->where('account_id', $account->id)->get();
            $debit = $journalEntries->sum('debit');
            $credit = $journalEntries->sum('credit');

            $account->debit = (string)$debit;
            $account->credit = (string)$credit;

            if ($debitCreditType == 'normal') {
                $account->balance = bcsub($debit, $credit, 2);
            } else {
                $account->balance = bcsub($credit, $debit, 2);
            }

            $account->journalEntries = $journalEntries; // Attach journal entries to the account
            $account->ledger = true;
        }

        return $account; // Return the account with its childAccounts populated
    }


    // create accounts
    public function createAccounts(Request $request)
    {
        // Allowed account types in lowercase for validation
        $allowedTypes = ['assets', 'liabilities', 'income', 'expenses'];

        // Validate the incoming request, allowing case-insensitive matching for 'type'
        $request->validate([
            'accountName' => 'required|string|max:255',
            'type' => ['required', 'string', function ($attribute, $value, $fail) use ($allowedTypes) {
                if (!in_array(strtolower($value), $allowedTypes)) {
                    $fail('The selected type is invalid.');
                }
            }],
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Retrieve the company associated with the user
        $company = Company::where('user_id', $user->id)->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        // Convert 'type' to capitalize the first letter (e.g., 'assets' -> 'Assets')
        $type = ucfirst(strtolower($request->type));

        // Find the parent account ID based on the type and company
        $parentAccount = Account::where('name', $type)
            ->where('company_id', $company->id)
            ->first();

        if (!$parentAccount) {
            return response()->json([
                'success' => false,
                'message' => "{$type} account not found for this company.",
            ], 404);
        }

        // Create the new account under the correct parent_id
        $newAccount = Account::create([
            'name' => $request->accountName,
            'parent_id' => $parentAccount->id,
            'company_id' => $company->id,
            'level' => 2,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => "New {$type} account created successfully with parent ID {$parentAccount->id}.",
            'account' => $newAccount,
        ], 201);
    }



    public function dstry($id)
    {
        $account = Account::find($id);

        if ($account) {
            $account->delete();
            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }
    }

    public function updateCode(Request $request, $id)
    {
        // Validate the incoming request to ensure a code is provided
        $request->validate([
            'code' => 'required|string|max:255',
        ]);

        // Find the asset and liability by ID
        $asset = Account::find($id);
        $liability = Account::find($id); // Assuming liabilities are also stored in the Account model

        if (!$asset) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found.',
            ], 404);
        }

        if (!$liability) {
            return response()->json([
                'success' => false,
                'message' => 'Liability not found.',
            ], 404);
        }

        // Update the asset's code
        $asset->code = $request->code;
        $asset->save();

        // Update the liability's code
        $liability->code = $request->code; // Assuming the same code update for liability
        $liability->save();

        return response()->json([
            'success' => true,
            'message' => 'Code updated successfully ',
        ]);
    }



    public function store(Request $request)
    {
        $request->validate([
            'account_name' => 'required|string|max:100',
            'account_description' => 'required|string'
        ]);

        $parent = Account::where('id', $request->parent_id)->first();

        $account = Account::create([
            'name' => $request->account_name,
            'level' => $parent->level + 1,
            'parent_id' => $request->parent_id,
            'company_id' => $parent->company_id,
            'description' => $request->account_description,
            'balance' => $request->balance,
        ]);


        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('documents', 'public'); // Store in storage/app/public/documents
                // Save the path to the database as needed
            }
        }

        // Handle the creation of the account, transaction, and document upload...

        return redirect()->back()->with('success', 'Item added successfully!');
    }

    public function payment(Request $request)
    {
        $user = Auth::user();

        // Retrieve the company associated with the user
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists before proceeding
        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }

        $voucherSequence = Sequence::where('sequence_for', 'VOUCHER')->lockForUpdate()->first();

        if (!$voucherSequence) {
            $voucherSequence = Sequence::create(['current_sequence' => 1]);
        }

        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);

        $voucherSequence->current_sequence++;
        $voucherSequence->save();


        return view('coa.payment', compact('company', 'voucherNumber'));
    }


    private function generateVoucherNumber($sequence)
    {
        $year = now()->year;
        return sprintf('VOU-%s-%05d', $year, $sequence);
    }


    public function getLevel1Accounts(Request $request)
    {

        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }


        // Fetch Level 1 accounts, assuming 'level' field defines the hierarchy level
        $accounts = Account::where('level', 1)
            ->whereNull('parent_id')
            ->where('company_id', $company->id)
            ->get(['id', 'name']); // Return only necessary fields (id, name)

        return response()->json($accounts);
    }

    // Get child accounts (Level 2) based on Level 1 selection
    public function getLevel2Accounts($level1Id)
    {

        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }


        // Fetch child accounts for Level 1 selection
        $accounts = Account::where('parent_id', $level1Id)
            ->where('company_id', $company->id)
            ->get(['id', 'name']);

        return response()->json($accounts);
    }

    // Get child accounts (Level 3) based on Level 2 selection
    public function getLevel3Accounts($level2Id)
    {

        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }


        // Fetch child accounts for Level 2 selection
        $accounts = Account::where('parent_id', $level2Id)
            ->where('company_id', $company->id)
            ->get(['id', 'name']);

        return response()->json($accounts);
    }

    public function getLevel4Accounts($level3Id)
    {

        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }


        $accounts = Account::where('parent_id', $level3Id)
            ->where('company_id', $company->id)
            ->get(['id', 'name', 'actual_balance']);

        return response()->json($accounts);
    }

    public function getTransactionsByLevel4(Request $request)
    {
        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();

        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }

        $level4Id = $request->query('level4_id');

        // Fetch transactions where account_id matches the selected Level 4 ID
        $transactions = JournalEntry::where('account_id', $level4Id)->get();

        return response()->json($transactions);
    }


    public function submitVoucher(Request $request)
    {
        Log::info('request', ['request' => $request]);
        $user = Auth::user();
        $company = Company::where('user_id', $user->id)->first();
        Log::info('company', ['company' => $company]);
        // Ensure the company exists
        if (!$company) {
            return response()->json(['error' => 'Company not found.'], 404);
        }

        $data = $request->validate([
            'voucher_no' => 'required|string|max:255',
            'voucher_date' => 'required|date',
            'payment_method' => 'string|max:255',
            'pay_to' => 'string|max:255',
            'entries' => 'required|array',
            'entries.*.account_id' => 'required|exists:accounts,id',
            'entries.*.particulars' => 'string|max:255',
            'entries.*.debit' => 'nullable|numeric',
            'entries.*.credit' => 'nullable|numeric',
        ]);

        $voucherNo = $data['voucher_no'];
        $voucherDate = $data['voucher_date'];
        $paymentMethod = $data['payment_method'];
        $payTo =  $data['pay_to'];

        Log::info('data', ['data' => $data]);
        // Create General Ledger entries
        foreach ($data['entries'] as $entry) {

            $account = Account::find($entry['account_id']);
            Log::info('account_id', ['account_id' => $entry['account_id']]);

            $amount = $entry['debit'] ?? $entry['credit'];
            $type = $entry['debit'] ? 'debit' : 'credit';

            $payment = Payment::create([
                'voucher_number' => $voucherNo,
                'from' => $company->name,
                'pay_to' => $payTo,
                'account_id' => $entry['account_id'],
                'currency' => 'KWD',
                'payment_date' => $voucherDate,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'status' => 'paid',
                'account_number' => NULL,
                'bank_name' => NULL,
                'swift_no' => NULL,
                'iban_no' => NULL,
                'country' => NULL,
                'tax' => NULL,
                'discount' => NULL,
                'shipping' => NULL,
                'payment_reference' => $voucherNo,
                'type' => $type,
            ]);

            $newBalance = $this->calculateNewBalance($account->actual_balance, $entry['debit'], $entry['credit']);
            Log::info('newBalance', ['newBalance' => $newBalance]);
            JournalEntry::create([
                'transaction_id' => $payment->id,
                'company_id' => $company->id,
                'account_id' => $entry['account_id'],
                'transaction_date' => $data['voucher_date'],
                'voucher_number' => $voucherNo,
                'description' => $entry['particulars'],
                'debit' => $entry['debit'] ?? 0,
                'credit' => $entry['credit'] ?? 0,
                'balance' => $newBalance
            ]);

            // Update the actual_balance of the Level 4 account

            $account->actual_balance = $newBalance;
            $account->save();
        }

        return response()->json(['message' => 'Voucher submitted successfully']);
    }

    private function calculateNewBalance($currentBalance, $debit, $credit)
    {
        // Implement your logic for updating the balance
        return $currentBalance + ($debit - $credit);
    }

    public function transaction(Request $request)
    {
        $user = Auth::user();
    
        $company = Company::where('user_id', $user->id)->first();
    
        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }
    
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
    
        $query = Transaction::with('journalEntries')
            ->where('company_id', $company->id);
    
        if ($startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }
    
        if ($endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }
    
        $transactions = $query
            ->orderBy('created_at', 'desc')
            ->get();
    
        $grouped = $transactions->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->format('Y-m-d');
        });
    
        $perPage = 5;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $dateKeys = $grouped->keys();
        $paginatedKeys = $dateKeys->slice(($currentPage - 1) * $perPage, $perPage);
    
        $paginatedGroups = $paginatedKeys->mapWithKeys(function ($date) use ($grouped) {
            return [$date => $grouped[$date]];
        });
    
        $paginated = new LengthAwarePaginator(
            $paginatedGroups,
            $grouped->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    
        return view('coa.transaction', [
            'company' => $company,
            'transactionsByDate' => $paginated
        ]);
    }

    public function importAccounts(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            if (!$request->hasFile('file')) {
                return back()->with('error', 'No file was uploaded.');
            }

            $file = $request->file('file');
            $rows = Excel::toCollection(null, $file);
            $dataRows = $rows->first()->skip(1); // Skip header row

            // Prepare caches
            $rootAccounts = Account::where('level', 1)
                ->pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $accountNameToId = Account::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $companies = Company::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $branches = Branch::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $agents = Agent::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $clients = Client::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $supplierNameToId = DB::table('suppliers')
                ->pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $supplierCompanyMap = DB::table('supplier_companies')
                ->select('id', 'supplier_id', 'company_id')
                ->get()
                ->mapWithKeys(fn($item) => [$item->supplier_id . '-' . $item->company_id => $item->id]);

            $duplicatesInFile = collect();
            $existingInDb = collect();
            $rowsToImport = collect();

            foreach ($dataRows as $row) {
                $rootName = strtolower(trim((string)($row[1] ?? '')));
                $accountName = trim((string)($row[4] ?? ''));
                $parentName = strtolower(trim((string)($row[9] ?? '')));

                $companyName = strtolower(trim((string)($row[10] ?? '')));
                $branchName  = strtolower(trim((string)($row[11] ?? '')));
                $agentName   = strtolower(trim((string)($row[12] ?? '')));
                $clientName  = strtolower(trim((string)($row[13] ?? '')));

                $rootId = $rootAccounts[$rootName] ?? null;
                $parentId = $accountNameToId[$parentName] ?? null;

                $companyId = $companies[$companyName] ?? $user->company_id;
                $branchId = $branches[$branchName] ?? $user->branch_id;
                $agentId = $agents[$agentName] ?? null;
                $clientId = $clients[$clientName] ?? null;

                // Supplier and supplier_company_id mapping
                $supplierNameRaw = (string)($row[14] ?? '');
                $supplierName = strtolower(trim($supplierNameRaw));
                $supplierId = $supplierNameToId[$supplierName] ?? null;

                $supplierCompanyId = null;
                if ($supplierId) {
                    $supplierCompanyKey = $supplierId . '-' . $companyId;
                    $supplierCompanyId = $supplierCompanyMap[$supplierCompanyKey] ?? null;

                    if (!$supplierCompanyId) {
                        Log::warning("No supplier_company_id found", [
                            'supplier_name_raw' => $supplierNameRaw,
                            'normalized_supplier' => $supplierName,
                            'supplier_id' => $supplierId,
                            'company_id' => $companyId,
                            'expected_key' => $supplierCompanyKey,
                            'available_keys_sample' => $supplierCompanyMap->keys()->take(5),
                        ]);
                    }
                } elseif (!empty($supplierNameRaw)) {
                    Log::warning("Supplier name not found in DB", [
                        'supplier_name_raw' => $supplierNameRaw,
                        'normalized' => $supplierName,
                    ]);
                }

                if (!$rootId || $accountName === '') {
                    Log::warning('Skipping row due to invalid root or account name', [
                        'root_name' => $rootName,
                        'account_name' => $accountName
                    ]);
                    continue;
                }

                $key = $rootId . '|' . $accountName;
                if ($duplicatesInFile->contains($key)) {
                    return back()->with('error', "Duplicate row in file found for account: $accountName.");
                }
                $duplicatesInFile->push($key);

                $exists = Account::where('root_id', $rootId)
                    ->where('name', $accountName)
                    ->exists();

                if ($exists) {
                    $existingInDb->push($key);
                    continue;
                }

                $disabledRaw = strtolower(trim((string)($row[21] ?? '0')));
                $isDisabled = in_array($disabledRaw, ['1', 'yes', 'true']) ? 1 : 0;

                $balanceMustBe = strtolower(trim((string)($row[22] ?? '')));
                $balanceMustBe = in_array($balanceMustBe, ['debit', 'credit']) ? $balanceMustBe : null;

                $referenceId = is_numeric($row[17]) ? (int)$row[17] : null;

                $rowsToImport->push([
                    'serial_number'       => $row[0],
                    'root_id'             => $rootId,
                    'account_type'        => $row[2] ?? null,
                    'report_type'         => $row[3] ?? null,
                    'name'                => $accountName,
                    'level'               => $row[5] ?? 2,
                    'actual_balance'      => $row[6] ?? 0,
                    'budget_balance'      => $row[7] ?? 0,
                    'variance'            => $row[8] ?? 0,
                    'parent_id'           => $parentId,
                    'company_id'          => $companyId,
                    'branch_id'           => $branchId,
                    'agent_id'            => $agentId,
                    'client_id'           => $clientId,
                    'supplier_id'         => $supplierId,
                    'supplier_company_id' => $supplierCompanyId ? (int) $supplierCompanyId : null,
                    'reference_id'        => $referenceId,
                    'code'                => $row[16] ?? null,
                    'currency'            => $row[17] ?? 'KWD',
                    'is_group'            => (int)($row[18] ?? 1),
                    'disabled'            => $isDisabled,
                    'balance_must_be'     => $balanceMustBe,
                    'created_at'          => $row[21] ?? now(),
                    'updated_at'          => $row[22] ?? now(),
                    'account_type_id'     => $row[23] ?? null,
                ]);
            }

            $totalImportRow = $rowsToImport->count();

            if ($totalImportRow === 0) {
                return back()->with('error', 'No new record to import due to data invalid or duplicates.');
            }

            foreach ($rowsToImport as $data) {
                Account::create($data);
            }

            return back()->with('success', "$totalImportRow record(s) imported successfully.");
        } catch (\Exception $e) {
            Log::error('Account import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Import failed. Please check the file format or server configuration.');
        }
    }

    public function exportAccounts(): BinaryFileResponse
    {
        // Root account names (level 1)
        $rootAccounts = Account::where('level', 1)->pluck('name', 'id'); // [id => name]

        // Account names for parent lookup
        $allAccountNames = Account::pluck('name', 'id');

        // Foreign key mappings
        $companyNames = Company::pluck('name', 'id');           // Company ID → Company Name
        $branchNames = Branch::pluck('name', 'id');             // Branch ID → Branch Name
        $agentNames = Agent::pluck('name', 'id');               // Agent ID → Agent Name
        $clientNames = Client::pluck('name', 'id');             // Client ID → Client Name

        // SupplierCompanyID → SupplierID
        $supplierCompanies = DB::table('supplier_companies')->pluck('supplier_id', 'id');
        // SupplierID → Supplier Name
        $supplierNames = DB::table('suppliers')->pluck('name', 'id');
        // SupplierCompanyID → Supplier Name
        $supplierCompanyIdToName = $supplierCompanies->mapWithKeys(function ($supplierId, $companyId) use ($supplierNames) {
            return [$companyId => $supplierNames[$supplierId] ?? null];
        });

        // Fetch all accounts
        $accounts = Account::all();

        // Header row
        $data = collect();
        $data->push([
            'Serial Number', 'Root Name', 'Account Type', 'Report Type', 'Name', 'Level',
            'Actual Balance', 'Budget Balance', 'Variance', 'Parent Name', 'Company Name',
            'Branch Name', 'Agent Name', 'Client Name', 'Supplier Name', 'Reference ID', 'Code',
            'Currency', 'Is Group', 'Disabled', 'Balance Must Be', 'Created At', 'Updated At',
            'Account Type ID'
        ]);

        foreach ($accounts as $account) {
            $data->push([
                $account->serial_number,
                $rootAccounts[$account->root_id] ?? '',                    // Root Name
                $account->account_type,
                $account->report_type,
                $account->name,
                $account->level,
                $account->actual_balance,
                $account->budget_balance,
                $account->variance,
                $allAccountNames[$account->parent_id] ?? '',               // Parent Name
                $companyNames[$account->company_id] ?? '',                 // Company Name
                $branchNames[$account->branch_id] ?? '',                   // Branch Name
                $agentNames[$account->agent_id] ?? '',                     // Agent Name
                $clientNames[$account->client_id] ?? '',                   // Client Name
                $supplierCompanyIdToName[$account->supplier_company_id] ?? '', // Supplier Name only
                $account->reference_id,
                $account->code,
                $account->currency,
                $account->is_group,
                $account->disabled,
                $account->balance_must_be,
                $account->created_at,
                $account->updated_at,
                $account->account_type_id,
            ]);
        }

        return Excel::download(new class($data) implements \Maatwebsite\Excel\Concerns\FromCollection {
            protected $data;
            public function __construct(Collection $data) { $this->data = $data; }
            public function collection() { return $this->data; }
        }, 'accounts_export.xlsx');
    }


    public function delegatePriceAmadeus(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'code' => 'required|integer',
        ]);

        $accountId = $request->input('account_id');
        $code = $request->input('code');

        $account = Account::with('journalEntries')
            ->where('id', $accountId)
            ->where('company_id', auth()->user()->company->id)
            ->first();

        $debit = $account->journalEntries->sum('debit');
        $credit = $account->journalEntries->sum('credit');
        $isDebit = $debit > 0;

        $totalAmount = 0;

        // if ($debit === 0 && $credit === 0) {
        //     return redirect()->back()->with('error', 'No debit or credit entries found for this account');
        // } else if ($debit > 0 && $credit > 0) {
        //     return redirect()->back()->with('error', 'Both debit and credit entries found for this account, contact your support');
        // }

        if ($isDebit) {
            $totalAmount = $debit;
        } else {
            $totalAmount = $credit;
        }
        if (!$account) {
            return redirect()->back()->with('error', 'Account not found');
        }

        if($account->name !== 'Amadeus'){
            return redirect()->back()->with('error', 'This account is not Amadeus');
        }

        $journalEntries = $account->journalEntries;
        $tasks = collect();
        foreach ($journalEntries as $journalEntry) {
            $task = $journalEntry->task;
            if ($task && $task->type === 'flight') {
                $tasks->push($task);
            }
        }

        $issuedBy = $tasks->whereNotNull('issued_by')
            ->pluck('issued_by')
            ->unique()
            ->toArray();
        
        if (empty($issuedBy)) {
            return redirect()->back()->with('error', 'No issued tasks found for this account');
        }

        // Get sum of total grouped by issued_by for all tasks
        $taskSummary = $tasks->groupBy('issued_by')
            ->map(function ($groupedTasks) {
                return $groupedTasks->sum('total');
            });

        // Debug: show the grouped sums
        // dd($taskSummary);

        $notIssuedTask = $tasks->whereNull('issued_by')
            ->values();

        // $issuedBy = Task::where('company_id', $account->company_id)
        //     ->where('type', 'flight')
        //     ->whereNotNull('issued_by')
        //     ->pluck('issued_by')
        //     ->unique()
        //     ->toArray();
        
        // $notIssued = Task::where('company_id', $account->company_id)
        //     ->where('type', 'flight')
        //     ->whereNull('issued_by')
        //     ->get();

        // dump($notIssued);
        $cumulativeTaskTotal = 0;

        DB::beginTransaction();

        try {
            foreach ($issuedBy as $company) {
                if (Account::where('code', $code)->where('company_id', $account->company_id)->exists()) {
                    throw new Exception('Account with this code already exists');
                }

                if (Account::where('name', $company)->where('parent_id', $account->id)->where('company_id', $account->company_id)->exists()) {
                    throw new Exception('Account with this name already exists under the parent account');
                }

                // Get the sum for this specific company from our pre-calculated summary
                $sumTotal = $taskSummary[$company] ?? 0;

                if ($sumTotal <= 0) {
                    continue; // Skip tasks with zero or negative amounts
                }


                $data = [
                    'name' => $company,
                    'parent_id' => $account->id,
                    'company_id' => $account->company_id,
                    'level' => $account->level + 1,
                    'root_id' => $account->root_id,
                    'account_type' => $account->account_type,
                    'report_type' => $account->report_type,
                    'code' =>  $code,
                    'actual_balance' => 0,
                    'budget_balance' => 0,
                    'variance' => 0,
                ];

                if ($isDebit) {
                    $data['debit'] = $sumTotal;
                    $data['credit'] = 0;
                } else {
                    $data['debit'] = 0;
                    $data['credit'] = $sumTotal;
                }

                $childAccount = Account::create($data);
                
                // Get tasks for this specific company from our collection
                $companyTasks = $tasks->where('issued_by', $company);
                
                foreach($companyTasks as $task){
                   foreach($task->journalEntries->where('account_id',$account->id) as $journalEntry){
                        $journalEntry->account_id = $childAccount->id;
                        $journalEntry->update();
                    }
                }

                $cumulativeTaskTotal += $sumTotal;

                $code++;
            }
           
            if($notIssuedTask->isNotEmpty()){
                $notIssuedAccount = Account::create([
                    'name' => 'Not Issued',
                    'parent_id' => $account->id,
                    'company_id' => $account->company_id,
                    'level' => $account->level + 1,
                    'root_id' => $account->root_id,
                    'account_type' => $account->account_type,
                    'report_type' => $account->report_type,
                    'code' =>  $code,
                    'actual_balance' => 0,
                    'budget_balance' => 0,
                    'variance' => 0,
                ]);

                foreach($notIssuedTask as $task){
                    foreach($task->journalEntries->where('account_id',$account->id) as $journalEntry){
                        $journalEntry->account_id = $notIssuedAccount->id;
                        $journalEntry->update();
                    }
                }
                $cumulativeTaskTotal += $notIssuedTask->sum('total');
            }

        } catch (Exception $e) {
            Log::error('Error creating account', [
                'error' => $e->getMessage(),
                'code' => $code,
                'company_gds' => $company,
            ]);
            DB::rollBack();
            return redirect()->back()->with('error', 'Error creating account: contact you support ');
        }
        // dd($cumulativeTaskTotal, $totalAmount);
        if(number_format($cumulativeTaskTotal, 2) !== number_format($totalAmount, 2)){
            Log::error('Cumulative task price does not match account balance', [
                'cumulative_task_total' => $cumulativeTaskTotal,
                'total_amount' => $totalAmount,
            ]);
            DB::rollBack();
            return redirect()->back()->with('error', 'Cumulative task price does not match account balance');
        }

        DB::commit();

        return redirect()->back()->with('success', 'Account has been delegated successfully');
        // Get all companies
        // $companies = Company::all();

        // foreach ($companies as $company) {
        //     // Clone the account for each company
        //     $newAccount = $account->replicate();
        //     $newAccount->company_id = $company->id;
        //     $newAccount->save();
        // }

        // return response()->json(['success' => 'Account has been relegated to all companies successfully']);
    }



}