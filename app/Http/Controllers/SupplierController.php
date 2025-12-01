<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\LoggingHelper;
use App\Enums\SupplierAuthType;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Account;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use App\Models\SystemLog;
use App\Models\Task;
use App\Models\SupplierSurcharge;
use App\Models\SupplierSurchargeReference;
use DateTime;
use Exception;
use Generator;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use League\OAuth2\Client\Provider\GenericProvider;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Exports\SupplierTasksExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SupplierController extends Controller
{
    use AuthorizesRequests, HttpRequestTrait;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Supplier::class);
        $user = Auth::user();

        $suppliers = Supplier::all();

        if ($user->role_id == Role::ADMIN) {
            // Only get SupplierCompany which is active
            $suppliers = Supplier::with(['companies' => function ($query) {
                $query->where('is_active', true);
            }])->get();

            foreach ($suppliers as $supplier) {
                foreach ($supplier->companies as $company) {
                    if ($company->pivot) {
                        $company->pivot->setRelation(
                            'supplierSurcharges',
                            SupplierSurcharge::with('references')->where('supplier_company_id', $company->pivot->id)->get()
                        );
                    }
                }
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $suppliers = Supplier::with(['credentials', 'companies'])
                ->activeForCompany($user->company->id)
                ->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $suppliers = Supplier::with(['companies' => function ($query) {
                $query->where('is_active', true);
            }])->get();
        } else {
            return abort(403, 'Unauthorized action.');
        }

        foreach ($suppliers as $supplier) {
            if (!is_null($supplier->route)) {
                $route = Route::getRoutes()->getByName('suppliers.' . $supplier->route . '.index');
                $supplier->named_route = $route ? $route->getName() : null;
            } else {
                $supplier->named_route = null;
            }
        }

        $suppliersCount = $suppliers->count();
        $countries = Country::all();
        $supplierAuthTypes = SupplierAuthType::cases();

        return view('suppliers.index', compact(
            'suppliers',
            'suppliersCount',
            'countries',
            'supplierAuthTypes'
        ));
    }

    public function exchangeRates($supplierId)
    {
        $supplier = Supplier::with('exchangeRates')->findOrFail($supplierId);
        $currencies = ['USD', 'GBP', 'AED', 'EUR', 'EGP', 'SAR', 'BUD', 'QAR'];
        return view('suppliers.exchange_rates', compact('supplier', 'currencies'));
    }

    public function updateExchangeRates(Request $request, $supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $currencies = ['USD', 'GBP', 'AED', 'EUR', 'EGP', 'SAR', 'BUD', 'QAR'];

        foreach ($currencies as $currency) {
            $rate = $request->input(strtolower($currency));
            if ($rate !== null) {
                $supplier->exchangeRates()->updateOrCreate(
                    ['currency' => $currency],
                    ['rate' => $rate]
                );
            }
        }

        return redirect()->back()->with('success', 'Exchange rates updated.');
    }

    public function show($suppliersId)
    {
        Gate::authorize('view', Supplier::class);

        $user = Auth::user();
        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH || $user->role_id == Role::ACCOUNTANT) {
            $companyId = $user->branch->company_id;
        } else {
            abort(403, 'Unauthorized action.');
        }

        $supplier = Supplier::with([
            'tasks' => fn($q) => $q->where('company_id', $companyId)->with(['agent', 'journalEntries']),
            'payableAccount.childAccounts.journalEntries',
            'country',
        ])
            ->whereHas('companies', function ($q) use ($companyId) {
                $q->where('companies.id', $companyId);
            })
            ->findOrFail($suppliersId);

        $taskIds = $supplier->tasks->pluck('id');

        $JournalEntry = JournalEntry::select('id', 'debit', 'credit', 'created_at', 'task_id', 'account_id')
            ->with(['task.agent', 'account'])
            ->whereIn('task_id', $taskIds)
            ->get();

        $currencies = ['USD', 'GBP', 'AED', 'EUR', 'EGP', 'SAR', 'BUD', 'QAR'];
        $filteredTasks = $supplier->tasks;
        $payableAccount = $supplier->payableAccount ?? null;

        $supplierCompany = collect();

        if ($companyId) {
            $supplierCompany = \App\Models\SupplierCompany::where('supplier_id', $supplier->id)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();
        }

        return view('suppliers.show', compact(
            'supplier',
            'JournalEntry',
            'currencies',
            'filteredTasks',
            'payableAccount',
            'companyId',
            'supplierCompany'
        ));
    }

    public function ledgerByDateRange(Request $request, $supplierId)
    {
        $fromDate = $request->input('fromDate');
        $toDate = $request->input('toDate');

        $tasks = Task::with(['agent', 'flightDetails', 'hotelDetails.hotel'])
            ->where('supplier_id', $supplierId)
            ->whereBetween('supplier_pay_date', [$fromDate, $toDate])
            ->get();

        return response()->json([
            'entries' => $tasks
        ]);
    }

    public function create()
    {

        // Check if the user has an admin role
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        // Return view
        return view('suppliers.SuppliersCreate');
    }

    public function store(Request $request)
    {
        Gate::authorize('update', Supplier::class);
        if (Auth::user()->role_id != Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required',
            'auth_type' => 'required|in:basic,oauth',
            'has_hotel' => 'required_without_all:has_flight,has_visa,has_insurance,has_tour,has_cruise,has_car,has_rail,has_esim,has_event,has_lounge,has_ferry',
            'has_flight' => 'required_without_all:has_hotel,has_visa,has_insurance,has_tour,has_cruise,has_car,has_rail,has_esim,has_event,has_lounge,has_ferry',
            'has_visa' => 'required_without_all:has_hotel,has_flight,has_insurance,has_tour,has_cruise,has_car,has_rail,has_esim,has_event,has_lounge,has_ferry',
            'has_insurance' => 'required_without_all:has_hotel,has_flight,has_visa,has_tour,has_cruise,has_car,has_rail,has_esim,has_event,has_lounge,has_ferry',
            'country_id' => 'required|exists:countries,id',
            'is_online' => 'exclude_unless:has_hotel,on|boolean',
            'is_manual' => 'nullable|boolean',
        ]);

        $hasHotel = $request->has('has_hotel');
        $isOnline = $hasHotel ? (int)$request->boolean('is_online') : 0;

        $supplier = Supplier::create([
            'name' => $request->input('name'),
            'auth_type' => $request->input('auth_type'),
            'has_hotel' => $request->has('has_hotel'),
            'has_flight' => $request->has('has_flight'),
            'has_visa' => $request->has('has_visa'),
            'has_insurance' => $request->has('has_insurance'),
            'has_tour' => $request->has('has_tour'),
            'has_cruise' => $request->has('has_cruise'),
            'has_car' => $request->has('has_car'),
            'has_rail' => $request->has('has_rail'),
            'has_esim' => $request->has('has_esim'),
            'has_event' => $request->has('has_event'),
            'has_lounge' => $request->has('has_lounge'),
            'has_ferry' => $request->has('has_ferry'),
            'country_id' => $request->input('country_id'),
            'is_online' => $isOnline,
            'is_manual' => $request->boolean('is_manual'),
        ]);

        if (!$supplier) {
            return redirect()->back()->with('error', 'Failed to create supplier.');
        }

        return redirect()->back()->with('success', 'Supplier created successfully.');
    }

    public function update($id)
    {
        if (Auth::user()->role_id != Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        $request = request();

        $request->validate([
            'name' => 'required',
            'auth_type' => ['required', Rule::in(['basic', 'oauth'])],
            'country_id' => 'required|exists:countries,id',
            'is_online' => 'nullable|boolean',
            'is_manual' => 'nullable|boolean',
            'surcharge_label.*.*' => 'nullable|string|max:100',
            'surcharge_amount.*.*' => 'nullable|numeric|min:0',
            'deleted_surcharges' => 'nullable|string',
            'charge_mode.*.*' => ['nullable', Rule::in(['task', 'reference'])],
            'is_issued.*.*' => 'nullable|boolean',
            'is_reissued.*.*' => 'nullable|boolean',
            'is_confirmed.*.*' => 'nullable|boolean',
            'is_refund.*.*' => 'nullable|boolean',
            'is_void.*.*' => 'nullable|boolean',
            'reference.*.*' => 'nullable|string|max:100',
            'charge_behavior.*.*' => ['nullable', Rule::in(['single', 'repetitive'])],
        ]);

        $supplier = Supplier::findOrFail($id);
        $oldName = $supplier->name;
        $newName = trim($request->input('name'));

        DB::transaction(function () use ($supplier, $request, $oldName, $newName) {
            $supplier->update([
                'name' => $newName,
                'auth_type' => $request->input('auth_type'),
                'country_id' => $request->input('country_id'),
                'has_hotel' => $request->has('has_hotel'),
                'has_flight' => $request->has('has_flight'),
                'has_visa' => $request->has('has_visa'),
                'has_insurance' => $request->has('has_insurance'),
                'has_tour' => $request->has('has_tour'),
                'has_cruise' => $request->has('has_cruise'),
                'has_car' => $request->has('has_car'),
                'has_rail' => $request->has('has_rail'),
                'has_esim' => $request->has('has_esim'),
                'has_event' => $request->has('has_event'),
                'has_lounge' => $request->has('has_lounge'),
                'has_ferry' => $request->has('has_ferry'),
                'is_online' => $request->boolean('is_online'),
                'is_manual' => $request->boolean('is_manual'),
            ]);

            if (strcasecmp(trim($oldName), trim($newName)) !== 0) {
                Account::where('name', 'LIKE', "%{$oldName}%")->update(['name' => $newName]);

                LoggingHelper::log('supplier', $oldName, $newName, "Supplier name updated from '{$oldName}' to '{$newName}'");
            }

           if ($request->has('surcharge_label')) {
    foreach ($request->surcharge_label as $pivotId => $labels) {
        $supplierCompany = SupplierCompany::find($pivotId);
        if (!$supplierCompany) continue;

        foreach ($labels as $surchargeKey => $labelRaw) {
            $label = trim($labelRaw);
            if (!$label) continue;

            $surchargeId = is_numeric($surchargeKey) ? $surchargeKey : null;
            $amount = $request->surcharge_amount[$pivotId][$surchargeKey] ?? 0;
            $chargeMode = $request->charge_mode[$pivotId][$surchargeKey] ?? 'task';

            // ✅ Only assign charge_behavior if reference mode
            $chargeBehavior = null;
            if ($chargeMode === 'reference') {
                $chargeBehavior = $request->charge_behavior[$surchargeKey][0]
                    ?? $request->charge_behavior[$pivotId][$surchargeKey][0]
                    ?? 'single';
            }

            $statusFlags = [
                'is_refund'    => isset($request->is_refund[$pivotId][$surchargeKey]),
                'is_issued'    => isset($request->is_issued[$pivotId][$surchargeKey]),
                'is_reissued'  => isset($request->is_reissued[$pivotId][$surchargeKey]),
                'is_void'      => isset($request->is_void[$pivotId][$surchargeKey]),
                'is_confirmed' => isset($request->is_confirmed[$pivotId][$surchargeKey]),
            ];

            // ✅ Prepare common data
            $data = [
                'label'        => $label,
                'amount'       => $amount,
                'charge_mode'  => $chargeMode,
            ] + $statusFlags;

            if ($chargeBehavior !== null) {
                $data['charge_behavior'] = $chargeBehavior;
            }

            // ✅ Create or update surcharge
            if ($surchargeId && SupplierSurcharge::where('id', $surchargeId)->exists()) {
                $surcharge = SupplierSurcharge::find($surchargeId);
                $surcharge->update($data);
            } else {
                $surcharge = SupplierSurcharge::create(array_merge([
                    'supplier_company_id' => $pivotId,
                ], $data));
            }

            // ✅ Handle references (if mode is reference)
            if ($chargeMode === 'reference') {
                $references = $request->reference[$surchargeKey] ?? [];

                foreach ($references as $refIndex => $refValue) {
                    if (!trim($refValue)) continue;

                    SupplierSurchargeReference::updateOrCreate(
                        [
                            'supplier_surcharge_id' => $surcharge->id,
                            'reference'             => trim($refValue),
                        ],
                        [
                            'is_charged' => false,
                        ]
                    );
                }

                $existingRefs = collect($references)->filter()->values();
                SupplierSurchargeReference::where('supplier_surcharge_id', $surcharge->id)
                    ->whereNotIn('reference', $existingRefs)
                    ->delete();
            }

            // ✅ Log changes
            $changedStatuses = [];
            foreach ($statusFlags as $flag => $value) {
                $old = $surcharge->getOriginal($flag);
                if ((bool)$old !== (bool)$value) {
                    $changedStatuses[] = "{$flag}: " . ($old ? 'true' : 'false') . " → " . ($value ? 'true' : 'false');
                }
            }

            if ($changedStatuses) {
                LoggingHelper::log(
                    'supplier_surcharges',
                    $surcharge->getOriginal(),
                    $surcharge->getAttributes(),
                    "Status flags changed for '{$label}': " . implode(', ', $changedStatuses)
                );
            }
        }

        // ✅ Recalculate total for each task
        $tasksQuery = Task::where('supplier_id', $supplierCompany->supplier_id)
            ->where('company_id', $supplierCompany->company_id)
            ->whereDoesntHave('invoiceDetail');

        $tasks = $tasksQuery->get();

        foreach ($tasks as $task) {
            $totalSurcharge = 0;
            $surcharges = SupplierSurcharge::with('references')
                ->where('supplier_company_id', $pivotId)
                ->get();

            foreach ($surcharges as $surcharge) {
                if ($surcharge->charge_mode === 'task') {
                    if ($surcharge->canChargeForStatus($task->status)) {
                        $totalSurcharge += $surcharge->amount;
                    }
                } elseif ($surcharge->charge_mode === 'reference') {
                    foreach ($surcharge->references as $ref) {
                        if ($task->reference !== $ref->reference) continue;

                        $canCharge = true;
                        if ($surcharge->charge_behavior === 'single' && $ref->is_charged) {
                            $canCharge = false;
                        }

                        if ($canCharge) {
                            $totalSurcharge += $surcharge->amount;

                            if ($surcharge->charge_behavior === 'single') {
                                $ref->markAsCharged();
                                break;
                            }
                        }
                    }
                }
            }

            $previousSurcharge = $task->supplier_surcharge;
            $task->update(['supplier_surcharge' => $totalSurcharge]);
            if ($previousSurcharge != $totalSurcharge) {
                LoggingHelper::log(
                    'tasks',
                    $previousSurcharge,
                    $totalSurcharge,
                    "Updated task ID {$task->id} (status: {$task->status}) with new total surcharge {$totalSurcharge}"
                );
            }
        }
    }
}


            if ($request->filled('deleted_surcharges')) {
                $deletedIds = array_filter(explode(',', $request->deleted_surcharges));
                SupplierSurcharge::whereIn('id', $deletedIds)->delete();

                LoggingHelper::log('supplier_surcharges', implode(',', $deletedIds),  '-', "Admin deleted surcharges with IDs: " . implode(', ', $deletedIds));
            }
        });

        return redirect()->back()->with('success', 'Supplier updated successfully.');
    }

    public function updateSurcharges($supplierCompanyId, Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [Role::COMPANY, Role::BRANCH, Role::ACCOUNTANT])) {
            abort(403, 'Unauthorized action.');
        }

        $supplierCompany = SupplierCompany::findOrFail($supplierCompanyId);

        $request->validate([
            'surcharge_label.*' => 'required|string|max:100',
            'surcharge_amount.*' => 'required|numeric|min:0',
            'deleted_surcharges' => 'nullable|string',
        ]);

        DB::transaction(function () use ($supplierCompany, $request, $user) {
            foreach ($request->surcharge_label as $index => $label) {
                $amount = $request->surcharge_amount[$index];
                $id = $request->surcharge_id[$index] ?? null;

                if ($id) {
                    $surcharge = SupplierSurcharge::find($id);
                    if ($surcharge) {
                        $oldLabel = $surcharge->label;
                        $oldAmount = $surcharge->amount;

                        $surcharge->update([
                            'label' => $label,
                            'amount' => $amount,
                        ]);

                        SystemLog::create([
                            'user_id' => $user->id,
                            'model' => 'supplier_surcharges',
                            'current_value' => "{$oldLabel} ({$oldAmount})",
                            'new_value' => "{$label} ({$amount})",
                            'remarks' => "Company updated surcharge '{$oldLabel}' → '{$label}' ({$amount}) for supplier_company_id {$supplierCompany->id}",
                        ]);
                    }
                } else {
                    $surcharge = SupplierSurcharge::create([
                        'supplier_company_id' => $supplierCompany->id,
                        'label' => $label,
                        'amount' => $amount,
                    ]);

                    SystemLog::create([
                        'user_id' => $user->id,
                        'model' => 'supplier_surcharges',
                        'current_value' => '-',
                        'new_value' => "{$label} ({$amount})",
                        'remarks' => "Company added new surcharge '{$label}' ({$amount}) for supplier_company_id {$supplierCompany->id}",
                    ]);
                }
            }

            if ($request->filled('deleted_surcharges')) {
                $deletedIds = array_filter(explode(',', $request->deleted_surcharges));
                SupplierSurcharge::whereIn('id', $deletedIds)->delete();

                SystemLog::create([
                    'user_id' => $user->id,
                    'model' => 'supplier_surcharges',
                    'current_value' => implode(',', $deletedIds),
                    'new_value' => '-',
                    'remarks' => "Company deleted surcharges with IDs: " . implode(', ', $deletedIds),
                ]);
            }

            $totalSurcharge = SupplierSurcharge::where('supplier_company_id', $supplierCompany->id)->sum('amount');
            Task::where('supplier_id', $supplierCompany->supplier_id)
                ->where('company_id', $supplierCompany->company_id)
                ->whereDoesntHave('invoiceDetail')
                ->update(['supplier_surcharge' => $totalSurcharge]);
        });

        return back()->with('success', 'Surcharges updated successfully.');
    }

    public function getTotalDebitCredit($supplierId, $endDate)
    {
        $endDate = new DateTime($endDate);
        $supplier = Supplier::with('tasks')->findOrFail($supplierId);
        $taskIds = $supplier->tasks->pluck('id')->toArray();

        $totalDebit = JournalEntry::whereIn('task_id', $taskIds)
            ->where('created_at', '<=', $endDate)
            ->sum('debit');
        $totalCredit = JournalEntry::whereIn('task_id', $taskIds)
            ->where('created_at', '<=', $endDate)
            ->sum('credit');

        return response()->json([
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
        ]);
    }

    public function redirectToAuthorization()
    {
        $clientId = config('services.magic-holiday.client-id');
        $authorizationUrl = config('services.magic-holiday.authorization_url');
        $redirectUri = route('suppliers.magic-callback');
        $scopes = 'read:reservations';

        $state = Str::random(40);
        Session::put('oauth_state', $state);

        $url = $authorizationUrl . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => $state,
        ]);

        logger($url);

        return redirect($url);
    }

    public function handleAuthorizationCallback(Request $request)
    {
        $clientId = config('services.magic-holiday.client-id');
        $clientSecret = config('services.magic-holiday.client-secret');
        $tokenUrl = config('services.magic-holiday.token-url');
        $redirectUri = route('suppliers.magic-callback');

        $code = $request->input('code');
        $state = $request->input('state');
        $sessionState = Session::get('oauth_state');

        if ($state !== $sessionState) {
            return response('Invalid state', 401);
        }

        Session::forget('oauth_state');

        $client = new Client();
        try {
            $response = $client->post('https://example.com', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
            ]);

            $tokenData = json_decode($response->getBody(), true);

            Session::put('access_token', $tokenData['access_token']);
            Session::put('refresh_token', $tokenData['refresh_token'] ?? null);
            Session::put('expires_at', time() + ($tokenData['expires_in'] ?? 0));

            return redirect()->route('your-protected-route'); // Redirect to a protected route

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return response('Failed to get access token: ' . $e->getResponse()->getBody(), 500);
        }
    }

    public function getMagicHoliday($ref = null): JsonResponse
    {
        if ($ref) {
            $url = config('services.magic-holiday.url') . '/reservationsApi/v1/reservations/' . $ref;
        } else {
            $url = config('services.magic-holiday.url') . '/reservationsApi/v1/reservations';
        }

        $scopes = ['read:reservations'];

        $response = $this->magicApiRequest('GET', $url, [], [], $scopes, ['id' => $ref]);

        return response()->json($response);
    }

    public function magicApiRequest(
        string $method = 'GET',
        string $url,
        array $header = [],
        array $data = [],
        array $scopes = ['read:reservations'],
        array $queryParams = []
    ): array {

        $responseCredential = $this->getClientCredential($scopes);

        if (isset($responseCredential['error'])) {
            return [
                'status' => 'error',
                'data' => $responseCredential,
                'message' => $responseCredential['error']
            ];
        }

        $accessToken = $responseCredential['token_type'] . ' ' . $responseCredential['access_token'];

        $header = [
            'Authorization' => $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        Log::channel('magic_holidays')->info('Request', [
            'method' => $method,
            'url' => $url,
            'header' => $header,
            'data' => $data
        ]);

        $data = json_encode($data);

        switch ($method) {
            case 'GET':

                if (strpos($url, '?') !== false) {
                    $url .= '&' . http_build_query($queryParams);
                } else {
                    $url .= '?' . http_build_query($queryParams);
                }

                $response = Http::withoutVerifying()->withHeaders($header)->get($url);
                break;
            case 'POST':
                $response = Http::withoutVerifying()->withHeaders($header)->post($url, $data);
                break;
            case 'PUT':
                $response = Http::withoutVerifying()->withHeaders($header)->put($url, $data);
                break;
            case 'DELETE':
                $response = Http::withoutVerifying()->withHeaders($header)->delete($url);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported HTTP method: $method");
        }

        Log::channel('magic_holidays')->info('Response', $response->json());

        if (isset($response['status']) && $response['status'] !== 200) {
            return [
                'status' => 'error',
                'data' => $response->json(),
                'message' => $response['detail']
            ];
        }

        return [
            'status' => 'success',
            'data' => $response->json()
        ];
    }

    public function getClientCredential(array $scopes): array
    {
        $user = Auth::user();
        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company_id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company_id;
        }

        $credential = SupplierCredential::query()
            ->where('company_id', $companyId)
            ->where('supplier_id', 2)
            ->first();

        if (!$credential || empty($credential->client_id) || empty($credential->client_secret)) {
            throw ValidationException::withMessages([
                'credentials' => 'Magic Holiday credentials are missing for this company. Please add the client ID and client secret to proceed.',
            ]);
        }

        $key = 'magic_holiday_access_token_' . $credential->client_id . '_' . implode('_', $scopes);
        $ttl = 60 * 60 * 24; // seconds * minutes * hours (1 day)

        return Cache::remember($key, $ttl, function () use ($scopes, $credential) {
            $tokenUrl = config('services.magic-holiday.token-url');

            $data = [
                'client_id'     => $credential->client_id,
                'client_secret' => $credential->client_secret,
                'grant_type'    => 'client_credentials',
                'scope'         => $scopes,
            ];

            $response = Http::withoutVerifying()->post($tokenUrl, $data);

            Log::channel('magic_holidays')->info('Credential Response', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'Unable to retrieve access token: HTTP ' . $response->status() . ' ' . $response->body()
                );
            }

            return $response->json();
        });
    }

    public function magicReserveWebhook($id)
    {

        $data = $this->getClientCredential(['write:reservations-webhooks']);


        if (isset($data['error'])) {
            return;
        }

        $accessToken = $data['token_type'] . ' ' . $data['access_token'];

        $url = config('services.magic-holiday.url') . '/reservationsApi/v1/reservations/' . $id . '/webhooks';

        $header = [
            'Authorization: ' . $accessToken,
            'Accept: application/json',
        ];
        $data = [
            'url' => route('magic-webhook-callback'),
        ];

        Log::channel('magic_holidays')->info('Magic Holiday Webhook Request', [
            'url' => $url,
            'header' => $header,
            'data' => $data
        ]);

        $response = $this->magicApiRequest('PUT', $url, $header, $data, ['write:reservations-webhooks']);

        Log::channel('magic_holidays')->info('Magic Holiday Webhook Response', $response);

        return;
    }

    public function magicReserveWebhookCallback(Request $request)
    {
        Log::channel('magic_webhook')->info('Magic Holiday Webhook Callback', $request->all());

        $id = $request->input('id');
        $event = $request->input('event');
        $data = $request->input('data');

        if (!$id || !$event || !$data) {
            Log::channel('magic_webhook')->error('Invalid webhook data', $request->all());

            return response()
                ->json([
                    'title' => 'Invalid Webhook Data',
                    'type' => route('magic-webhook-docs'),
                    'status' => 400,
                    'detail' => 'Missing required fields: id, event, or data.'
                ], 400)
                ->header('X-RateLimit-Limit', 1000)
                ->header('X-RateLimit-Remaining', 999)
                ->header('X-RateLimit-Reset', time() + 3600)
                ->header('Content-Type', 'application/problem+json');
        }
        if ($event == 'status.change') {
            $data = json_decode($data, true);

            $currentStatus = $data['current_status'] ?? null;
            $previousStatus = $data['previous_status'] ?? null;

            if (!$currentStatus || !$previousStatus) {
                Log::channel('magic_webhook')->error('Invalid webhook data for status change', $request->all());

                return response()
                    ->json([
                        'title' => 'Invalid Webhook Data',
                        'type' => route('suppliers.magic-webhook-docs'),
                        'status' => 400,
                        'detail' => 'Missing required fields: current_status, previous_status, or amendments.'
                    ], 400)
                    ->header('X-RateLimit-Limit', 1000)
                    ->header('X-RateLimit-Remaining', 999)
                    ->header('X-RateLimit-Reset', time() + 3600)
                    ->header('Content-Type', 'application/problem+json');
            }

            Log::channel('magic_webhook')->info('Status Change Event', [
                'current_status' => $currentStatus,
                'previous_status' => $previousStatus,
                'data' => $data
            ]);

            $amendments = $data['amendments'] ?? null;

            if (!$amendments) {

                Log::channel('magic_webhook')->info('No amendments found for status change', $request->all());

                return response()
                    ->json([
                        'title' => 'No Amendments Found',
                        'type' => route('suppliers.magic-webhook-docs'),
                        'status' => 200,
                        'detail' => 'No amendments found for the status change.'
                    ], 200)
                    ->header('X-RateLimit-Limit', 1000)
                    ->header('X-RateLimit-Remaining', 999)
                    ->header('X-RateLimit-Reset', time() + 3600)
                    ->header('Content-Type', 'application/hal+json');
            }

            $group = $amendments['group'] ?? null;
            $original = $amendments['original'] ?? null;
            $amendedBy = $amendments['amendedBy'] ?? null;

            if (!$group || !$original || !$amendedBy) {
                Log::channel('magic_webhook')->error('Invalid webhook data for status change amendments', $request->all());

                return response()
                    ->json([
                        'title' => 'Invalid Webhook Data',
                        'type' => route('suppliers.magic-webhook-docs'),
                        'status' => 400,
                        'detail' => 'Missing required fields: group, original, or amendedBy.'
                    ], 400)
                    ->header('X-RateLimit-Limit', 1000)
                    ->header('X-RateLimit-Remaining', 999)
                    ->header('X-RateLimit-Reset', time() + 3600)
                    ->header('Content-Type', 'application/problem+json');
            }

            $magicHolidaySupplier = Supplier::where('name', 'Magic Holiday')->first();

            if (!$magicHolidaySupplier) {
                Log::channel('magic_webhook')->error('Magic Holiday supplier not found', $request->all());

                return response()
                    ->json([
                        'title' => 'Something went wrong, contact our support team',
                        'type' => route('suppliers.magic-webhook-docs'),
                        'status' => 500,
                        'detail' => 'Server error',
                    ], 500)
                    ->header('X-RateLimit-Limit', 1000)
                    ->header('X-RateLimit-Remaining', 999)
                    ->header('X-RateLimit-Reset', time() + 3600)
                    ->header('Content-Type', 'application/problem+json');
            }

            $existingReservation = Task::where('reference', $original)
                ->where('supplier_id', $magicHolidaySupplier->id)
                ->first();

            if (!$existingReservation) {
                Log::channel('magic_webhook')->error('Reservation not found for original reference', [
                    'original' => $original,
                    'supplier_id' => $magicHolidaySupplier->id,
                ]);

                return response()
                    ->json([
                        'title' => 'Reservation Not Found',
                        'type' => route('suppliers.magic-webhook-docs'),
                        'status' => 404,
                        'detail' => 'Reservation not found for the original reference: ' . $original
                    ], 404)
                    ->header('X-RateLimit-Limit', 1000)
                    ->header('X-RateLimit-Remaining', 999)
                    ->header('X-RateLimit-Reset', time() + 3600)
                    ->header('Content-Type', 'application/problem+json');
            }

            $existingReservation->supplier_status = $currentStatus;
            $existingReservation->save();

            Log::channel('magic_webhook')->info('Reservation updated', [
                'reference' => $existingReservation->reference,
                'supplier_status' => $currentStatus,
            ]);
        }

        return response()
            ->json(['received' => true])
            ->header('X-RateLimit-Limit', 1000)
            ->header('X-RateLimit-Remaining', 999)
            ->header('X-RateLimit-Reset', time() + 3600)
            ->header('Content-Type', 'application/hal+json');
    }

    public function magicReserveWebhookDocs()
    {
        return  view('docs.webhook.magic-holiday');
    }
    public function exportPdf(Request $request, $suppliersId)
    {
        $supplier = Supplier::with([
            'tasks.agent',
            'tasks.flightDetails',
            'tasks.hotelDetails.hotel',
            'country'
        ])->findOrFail($suppliersId);

        $dateField = $request->input('date_field', 'created_at');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $filteredTasks = $supplier->tasks;

        if ($fromDate && $toDate) {
            $filteredTasks = $filteredTasks->filter(function ($task) use ($dateField, $fromDate, $toDate) {
                $date = $task[$dateField];
                if (!$date) return false;
                $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
                return $date >= $fromDate && $date <= $toDate;
            });
        }

        // Sort by selected date field, newest first
        $filteredTasks = $filteredTasks->sortByDesc(function ($task) use ($dateField) {
            return $task[$dateField] ? \Carbon\Carbon::parse($task[$dateField])->timestamp : 0;
        });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('suppliers.pdf', compact('supplier', 'filteredTasks'));
        return $pdf->download('supplier-tasks.pdf');
    }
    public function exportExcel(Request $request, $suppliersId)
    {
        $supplier = Supplier::with([
            'tasks.agent',
            'tasks.flightDetails',
            'tasks.hotelDetails.hotel',
            'country'
        ])->findOrFail($suppliersId);

        $dateField = $request->input('date_field', 'created_at');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $filteredTasks = $supplier->tasks;

        if ($fromDate && $toDate) {
            $filteredTasks = $filteredTasks->filter(function ($task) use ($dateField, $fromDate, $toDate) {
                $date = $task[$dateField];
                if (!$date) return false;
                $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
                return $date >= $fromDate && $date <= $toDate;
            });
        }

        // Sort by selected date field, newest first
        $filteredTasks = $filteredTasks->sortByDesc(function ($task) use ($dateField) {
            return $task[$dateField] ? \Carbon\Carbon::parse($task[$dateField])->timestamp : 0;
        });

        return Excel::download(new SupplierTasksExport($supplier, $filteredTasks), 'supplier-tasks.xlsx');
    }
}
