<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\LoggingHelper;
use App\Enums\SupplierAuthType;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Supplier;
use App\Models\SupplierBankDetail;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use App\Rules\ValidIban;
use App\Rules\ValidSwiftBic;
use App\Services\Accounting\AccountingLog;
use App\Models\SystemLog;
use App\Models\Task;
use App\Models\SupplierSurcharge;
use App\Models\SupplierSurchargeReference;
use DateTime;
use Exception;
use Generator;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
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
        $companyId = getCompanyId($user);

        $query = Supplier::query();

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        if ($user->role_id == Role::ADMIN) {
            $query->whereHas('companies', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->with(['country', 'supplierCompanies', 'companies' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }]);

            $otherQuery = Supplier::query()
                ->whereDoesntHave('companies', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })
                ->with(['country', 'supplierCompanies']);

            if ($request->filled('q')) {
                $otherQuery->where('name', 'like', '%' . $request->q . '%');
            }

            $otherSuppliers = $otherQuery->orderBy('name')->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $query->activeForCompany($companyId)
                ->with(['credentials', 'companies' => function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                }]);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $query->activeForCompany($companyId)
                ->with(['credentials', 'companies' => function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                }]);
        } else {
            return abort(403, 'Unauthorized action.');
        }

        if (!isset($otherSuppliers)) {
            $otherSuppliers = collect();
        }

        $suppliers = $query->orderBy('id')->paginate(20)->withQueryString();

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

        $countries = Country::all();
        $supplierAuthTypes = SupplierAuthType::cases();
        $companies = $user->role_id == Role::ADMIN ? Company::all() : collect();

        return view('suppliers.index', compact(
            'suppliers',
            'otherSuppliers',
            'countries',
            'supplierAuthTypes',
            'companyId',
            'companies'
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
        if (!in_array(Auth::user()->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])) {
            abort(403, 'Unauthorized action.');
        }
        $companyId = getCompanyId(Auth::user());

        // suppliers.show carries no module gate (suppliers are part of the
        // Task Uploader package), but its view computes real ledger
        // Total-Debit/Total-Credit/Balance figures from each task's
        // JournalEntry rows. Only eager-load ledger data — and only let the
        // view render it — when the company actually has accounting on;
        // $JournalEntry/$payableAccount are otherwise unused by the view.
        $company = $companyId ? Company::find($companyId) : null;
        $hasAccountingModule = $company && $company->hasModule(\App\Support\Modules::ACCOUNTING);

        $supplier = Supplier::with([
            'tasks' => fn($q) => $q->where('company_id', $companyId)
                ->with($hasAccountingModule ? ['agent', 'journalEntries'] : ['agent']),
            'country',
        ])
            ->when($hasAccountingModule, fn($q) => $q->with('payableAccount.childAccounts.journalEntries'))
            ->whereHas('companies', function ($q) use ($companyId) {
                $q->where('companies.id', $companyId);
            })
            ->findOrFail($suppliersId);

        $taskIds = $supplier->tasks->pluck('id');

        $JournalEntry = $hasAccountingModule
            ? JournalEntry::select('id', 'debit', 'credit', 'created_at', 'task_id', 'account_id')
                ->with(['task.agent', 'account'])
                ->whereIn('task_id', $taskIds)
                ->get()
            : collect();

        $currencies = ['USD', 'GBP', 'AED', 'EUR', 'EGP', 'SAR', 'BUD', 'QAR'];
        $filteredTasks = $supplier->tasks;
        $payableAccount = $hasAccountingModule ? ($supplier->payableAccount ?? null) : null;

        $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        // W6.U "Supplier status map" / "Supplier charge rule editor" (owner addition,
        // 2026-08-28). Loaded here (not lazily from the card partials) so the read-only-notice
        // gate and the card markup can share the exact same $canManage flag the rest of this page
        // already uses via SupplierPolicy::update.
        $statusMapRows = \App\Models\SupplierStatusMap::where('company_id', $companyId)
            ->where('supplier_id', $supplier->id)
            ->orderBy('channel')->orderByDesc('priority')
            ->get();

        $unmappedStatuses = \App\Models\TaskStatusEvent::where('company_id', $companyId)
            ->where('event', 'status_unmapped')
            ->whereJsonContains('meta->supplier_id', $supplier->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->unique(fn ($e) => $e->channel . '|' . $e->raw_status)
            ->filter(function ($e) use ($companyId, $supplier) {
                return ! \App\Models\SupplierStatusMap::where('company_id', $companyId)
                    ->where('supplier_id', $supplier->id)
                    ->where('channel', $e->channel)
                    ->where('raw_status', $e->raw_status)
                    ->where('active', true)
                    ->exists();
            });

        $chargeRuleRows = \App\Models\SupplierChargeRule::where('company_id', $companyId)
            ->where('supplier_id', $supplier->id)
            ->orderBy('charge_kind')
            ->get();

        // T14 "Supplier bank details per currency" (L18) -- every row for this supplier+company,
        // active and deactivated alike (the card partial shows both, per the status-map card's own
        // convention), newest currency first then default-first so the active default per
        // currency reads at the top of its group.
        $bankDetailRows = SupplierBankDetail::where('company_id', $companyId)
            ->where('supplier_id', $supplier->id)
            ->orderBy('currency')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->get();

        $canManageSupplier = Gate::allows('update', Supplier::class);

        return view('suppliers.show', compact(
            'supplier',
            'JournalEntry',
            'currencies',
            'filteredTasks',
            'payableAccount',
            'companyId',
            'supplierCompany',
            'statusMapRows',
            'unmappedStatuses',
            'chargeRuleRows',
            'bankDetailRows',
            'canManageSupplier',
            'hasAccountingModule'
        ));
    }

    public function ledgerByDateRange(Request $request, $supplierId)
    {
        Gate::authorize('view', Supplier::class);

        $fromDate = $request->input('fromDate');
        $toDate = $request->input('toDate');

        // Task carries no BelongsToCompany scoping of its own, so without this
        // the endpoint would let any authenticated user enumerate any other
        // company's booking/task data (agent name, price, dates) for any
        // supplier_id by simply varying it.
        $companyId = getCompanyId(Auth::user());
        abort_unless($companyId, 403);

        $tasks = Task::with(['agent', 'flightDetails', 'hotelDetails.hotel'])
            ->where('supplier_id', $supplierId)
            ->whereBetween('supplier_pay_date', [$fromDate, $toDate])
            ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                $q->where('id', $companyId);
            })
            ->get();

        return response()->json([
            'entries' => $tasks
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Supplier::class);
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        return view('suppliers.SuppliersCreate');
    }

    /**
     * Resolve a WhatsApp group name typed on the supplier form to its stable
     * group WID via the Resayil device group list (Suppliers > WhatsApp Group
     * "Verify" button). Returns up to 10 matches [{wid, name}].
     */
    public function resolveWhatsappGroup(Request $request)
    {
        if (!in_array(Auth::user()->role_id, [Role::ADMIN, Role::COMPANY])) {
            abort(403, 'Unauthorized action.');
        }

        $q = strtolower(trim((string) $request->input('q')));
        if ($q === '') {
            return response()->json(['success' => false, 'message' => 'Type the group name first.'], 422);
        }

        try {
            $groups = Cache::remember('resayil_group_list', 60, function () {
                $base = rtrim(config('services.resayil.base_url'), '/') . '/' . trim(config('services.resayil.version'), '/');
                $tok = config('services.resayil.api_token');
                $devices = Http::withHeaders(['Token' => $tok])->timeout(30)->get($base . '/devices')->json();
                $devId = $devices[0]['id'] ?? ($devices['data'][0]['id'] ?? null);
                if (!$devId) {
                    return [];
                }
                $list = Http::withHeaders(['Token' => $tok])->timeout(30)->get($base . '/devices/' . $devId . '/groups')->json();
                return is_array($list) ? $list : [];
            });
        } catch (\Throwable $e) {
            Log::warning('resolveWhatsappGroup lookup failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'WhatsApp group lookup failed. Try again.'], 500);
        }

        $matches = [];
        foreach ($groups as $g) {
            $wid = (string) ($g['wid'] ?? '');
            $name = (string) ($g['name'] ?? '');
            if ($wid === '' || !str_ends_with($wid, '@g.us')) {
                continue;
            }
            if ($q === strtolower($wid) || ($name !== '' && str_contains(strtolower($name), $q))) {
                $matches[] = ['wid' => $wid, 'name' => $name !== '' ? $name : $wid];
            }
        }

        return response()->json(['success' => true, 'matches' => array_slice($matches, 0, 10)]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Supplier::class);
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
            'whatsapp_group' => 'nullable|string|max:190',
            'agency_commission' => 'nullable|numeric|min:0|max:100',
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
            'whatsapp_group' => trim((string) $request->input('whatsapp_group')) ?: null,
            'agency_commission' => $request->input('agency_commission'),
        ]);

        if (!$supplier) {
            return redirect()->back()->with('error', 'Failed to create supplier.');
        }

        $companyId = getCompanyId(Auth::user());
        SupplierCompany::create([
            'supplier_id' => $supplier->id,
            'company_id' => $companyId,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Supplier created successfully.');
    }

    public function update($id)
    {
        Gate::authorize('update', Supplier::class);
        if (Auth::user()->role_id != Role::ADMIN && Auth::user()->role_id != Role::COMPANY) {
            abort(403, 'Unauthorized action.');
        }

        $request = request();

        $request->validate([
            'name' => 'required',
            'auth_type' => ['required', Rule::in(['basic', 'oauth'])],
            'country_id' => 'required|exists:countries,id',
            'is_online' => 'nullable|boolean',
            'is_manual' => 'nullable|boolean',
            'whatsapp_group' => 'nullable|string|max:190',
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
            'agency_commission' => 'nullable|numeric|min:0|max:100',
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
                'whatsapp_group' => trim((string) $request->input('whatsapp_group')) ?: null,
                'agency_commission' => $request->input('agency_commission'),
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
            'surcharge_label.*.*' => 'required|string|max:100',
            'surcharge_amount.*.*' => 'required|numeric|min:0',
            'deleted_surcharges' => 'nullable|string',
            'charge_mode.*.*' => ['nullable', Rule::in(['task', 'reference'])],
            'is_issued.*.*' => 'nullable|boolean',
            'is_reissued.*.*' => 'nullable|boolean',
            'is_confirmed.*.*' => 'nullable|boolean',
            'is_refund.*.*' => 'nullable|boolean',
            'is_void.*.*' => 'nullable|boolean',
            'charge_behavior.*.*' => ['nullable', Rule::in(['single', 'repetitive'])],
        ]);

        DB::transaction(function () use ($supplierCompany, $request, $user) {
            if ($request->has('surcharge_label')) {
                foreach ($request->surcharge_label as $pivotId => $labels) {
                    foreach ($labels as $surchargeKey => $label) {
                        $label = trim($label);
                        if (!$label) continue;

                        $amount = $request->surcharge_amount[$pivotId][$surchargeKey] ?? 0;
                        $chargeMode = $request->charge_mode[$pivotId][$surchargeKey] ?? 'task';
                        $surchargeId = is_numeric($surchargeKey) ? $surchargeKey : null;

                        // Get charge behavior if reference mode
                        $chargeBehavior = null;
                        if ($chargeMode === 'reference') {
                            $chargeBehavior = $request->charge_behavior[$surchargeKey][0] ?? 'single';
                        }

                        // Status flags
                        $statusFlags = [
                            'is_refund'    => isset($request->is_refund[$pivotId][$surchargeKey]),
                            'is_issued'    => isset($request->is_issued[$pivotId][$surchargeKey]),
                            'is_reissued'  => isset($request->is_reissued[$pivotId][$surchargeKey]),
                            'is_void'      => isset($request->is_void[$pivotId][$surchargeKey]),
                            'is_confirmed' => isset($request->is_confirmed[$pivotId][$surchargeKey]),
                        ];

                        $data = [
                            'label'       => $label,
                            'amount'      => $amount,
                            'charge_mode' => $chargeMode,
                        ] + $statusFlags;

                        if ($chargeBehavior !== null) {
                            $data['charge_behavior'] = $chargeBehavior;
                        }

                        if ($surchargeId && SupplierSurcharge::where('id', $surchargeId)->exists()) {
                            $surcharge = SupplierSurcharge::find($surchargeId);
                            $oldLabel = $surcharge->label;
                            $oldAmount = $surcharge->amount;

                            $surcharge->update($data);

                            SystemLog::create([
                                'user_id' => $user->id,
                                'model' => 'supplier_surcharges',
                                'current_value' => "{$oldLabel} ({$oldAmount})",
                                'new_value' => "{$label} ({$amount})",
                                'remarks' => "Updated surcharge '{$oldLabel}' → '{$label}' ({$amount})",
                            ]);
                        } else {
                            $surcharge = SupplierSurcharge::create(array_merge([
                                'supplier_company_id' => $pivotId,
                            ], $data));

                            SystemLog::create([
                                'user_id' => $user->id,
                                'model' => 'supplier_surcharges',
                                'current_value' => '-',
                                'new_value' => "{$label} ({$amount})",
                                'remarks' => "Added new surcharge '{$label}' ({$amount})",
                            ]);
                        }
                    }
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
                    'remarks' => "Deleted surcharges with IDs: " . implode(', ', $deletedIds),
                ]);
            }

            // Recalculate surcharges for tasks
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

        // P2.5.B fix (BUG-C4; p2_5-brief.md §P2.5.B): this supplier ledger cutoff used to bucket
        // by created_at (row-insert time) rather than posting_date (which accounting period the
        // entry actually belongs to) -- see ReportController::profitLoss()'s identical fix for the
        // full rationale, including why COALESCE(posting_date, transaction_date) rather than a
        // bare posting_date (a not-yet-migrated legacy writer would otherwise vanish from this sum).
        $totalDebit = JournalEntry::whereIn('task_id', $taskIds)
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $endDate)
            ->sum('debit');
        $totalCredit = JournalEntry::whereIn('task_id', $taskIds)
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $endDate)
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

    // =====================================================================================
    // W6.U -- Supplier status map (owner addition, 2026-08-28). Gated by SupplierPolicy::update
    // throughout, same read-only-notice pattern as the surcharges card this replaces alongside.
    // =====================================================================================

    private function statusMapValidationRules(): array
    {
        return [
            'channel' => ['required', Rule::in(['air', 'magic', 'webhook', 'ai_pdf', 'manual'])],
            'raw_status' => ['required', 'string', 'max:64'],
            'canonical_status' => ['required', Rule::in(['on_hold', 'confirmed', 'issued', 'reissued', 'void', 'refund', 'emd', 'cancelled', 'needs_review'])],
            'deadline_source' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /** `POST /suppliers/{supplier}/status-map` -- add a supplier-specific row. */
    public function statusMapStore(Request $request, Supplier $supplier): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        $validated = $request->validate($this->statusMapValidationRules());

        $row = \App\Models\SupplierStatusMap::create(array_merge($validated, [
            'company_id' => $companyId,
            'supplier_id' => $supplier->id,
            'priority' => $validated['priority'] ?? 0,
            'active' => true,
        ]));

        app(\App\Services\TaskStatusService::class)->recordEvent('supplier_status_map_created', $companyId, null, null, null, null, null, [
            'user_id' => Auth::id(),
            'row_id' => $row->id,
            'supplier_id' => $supplier->id,
        ]);

        return back()->with('success', 'Status mapping added.');
    }

    /** `PUT /suppliers/status-map/{statusMap}` -- edit an existing row (supplier or company-default). */
    public function statusMapUpdate(Request $request, \App\Models\SupplierStatusMap $statusMap): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        abort_unless($statusMap->company_id === $companyId, 403);

        $validated = $request->validate($this->statusMapValidationRules());
        $statusMap->update($validated);

        app(\App\Services\TaskStatusService::class)->recordEvent('supplier_status_map_updated', $companyId, null, null, null, null, null, [
            'user_id' => Auth::id(),
            'row_id' => $statusMap->id,
        ]);

        return back()->with('success', 'Status mapping updated.');
    }

    /** `POST /suppliers/status-map/{statusMap}/deactivate` -- soft toggle, never delete. */
    public function statusMapDeactivate(\App\Models\SupplierStatusMap $statusMap): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        abort_unless($statusMap->company_id === $companyId, 403);

        $statusMap->update(['active' => ! $statusMap->active]);

        app(\App\Services\TaskStatusService::class)->recordEvent(
            $statusMap->active ? 'supplier_status_map_reactivated' : 'supplier_status_map_deactivated',
            $companyId, null, null, null, null, null,
            ['user_id' => Auth::id(), 'row_id' => $statusMap->id]
        );

        return back()->with('success', $statusMap->active ? 'Mapping reactivated.' : 'Mapping deactivated.');
    }

    /**
     * `POST /suppliers/status-map/test` -- "test a raw status" preview. Never writes a task;
     * reuses {@see \App\Services\TaskStatusService::mapStatus()} verbatim (the ONE place any raw
     * status is resolved, per W6.S) so the preview can never drift from what a real import would
     * produce.
     */
    public function statusMapTest(Request $request): JsonResponse
    {
        Gate::authorize('update', Supplier::class);

        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'channel' => ['required', Rule::in(['air', 'magic', 'webhook', 'ai_pdf', 'manual'])],
            'raw_status' => ['required', 'string', 'max:64'],
        ]);

        $companyId = getCompanyId(Auth::user());
        $supplier = ! empty($validated['supplier_id']) ? Supplier::find($validated['supplier_id']) : null;

        $mapped = app(\App\Services\TaskStatusService::class)->mapStatus(
            $supplier,
            $validated['channel'],
            $validated['raw_status'],
            (int) $companyId
        );

        $resolvedByLabel = match ($mapped->resolutionLevel) {
            \App\Services\TaskStatus\MappedStatus::LEVEL_SUPPLIER => 'This company + this supplier',
            \App\Services\TaskStatus\MappedStatus::LEVEL_COMPANY_DEFAULT => 'This company\'s channel-wide default',
            \App\Services\TaskStatus\MappedStatus::LEVEL_GLOBAL_SUPPLIER => 'Codebase default for this supplier',
            \App\Services\TaskStatus\MappedStatus::LEVEL_GLOBAL_DEFAULT => 'Global default',
            \App\Services\TaskStatus\MappedStatus::LEVEL_OVERRIDE => 'Context override (e.g. Magic AM+total<=0)',
            default => 'No mapping found (needs_review)',
        };

        return response()->json([
            'success' => true,
            'canonical_status' => $mapped->canonicalStatus,
            'resolved_by' => $resolvedByLabel,
            'matched_row_id' => $mapped->row?->id,
        ]);
    }

    /**
     * `GET /suppliers/status-map/defaults` -- company-level defaults page (channel-wide rows:
     * company_id set, supplier_id null), linked from suppliers/index.blade.php.
     */
    public function statusMapDefaults(Request $request): View
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());

        $rows = \App\Models\SupplierStatusMap::where('company_id', $companyId)
            ->whereNull('supplier_id')
            ->orderBy('channel')->orderByDesc('priority')
            ->get();

        $unmapped = \App\Models\TaskStatusEvent::where('company_id', $companyId)
            ->where('event', 'status_unmapped')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->unique(fn ($e) => $e->channel.'|'.$e->raw_status);

        return view('suppliers.status-map-defaults', compact('rows', 'unmapped', 'companyId'));
    }

    /** `POST /suppliers/status-map/create-from-unmapped` -- one-click create from the unmapped list. */
    public function statusMapCreateFromUnmapped(Request $request): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'channel' => ['required', Rule::in(['air', 'magic', 'webhook', 'ai_pdf', 'manual'])],
            'raw_status' => ['required', 'string', 'max:64'],
            'canonical_status' => ['required', Rule::in(['on_hold', 'confirmed', 'issued', 'reissued', 'void', 'refund', 'emd', 'cancelled', 'needs_review'])],
        ]);

        $companyId = getCompanyId(Auth::user());

        $row = \App\Models\SupplierStatusMap::create([
            'company_id' => $companyId,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'channel' => $validated['channel'],
            'raw_status' => $validated['raw_status'],
            'canonical_status' => $validated['canonical_status'],
            'priority' => 0,
            'active' => true,
        ]);

        app(\App\Services\TaskStatusService::class)->recordEvent('supplier_status_map_created_from_unmapped', $companyId, null, null, null, $validated['channel'], $validated['raw_status'], [
            'user_id' => Auth::id(),
            'row_id' => $row->id,
        ]);

        return back()->with('success', 'Mapping created. Re-process the affected task to apply it.');
    }

    // =====================================================================================
    // W6.C.U -- Supplier charge rule editor (w6-brief.md "W6.U -- UI" "Supplier charge rule
    // editor (W6.C)"). Same SupplierPolicy::update gate, same read-only-notice/deactivate pattern.
    // =====================================================================================

    private function chargeRuleValidationRules(): array
    {
        return [
            'service_type' => ['nullable', 'string', 'max:20'],
            'channel' => ['nullable', 'string', 'max:20'],
            'charge_kind' => ['required', Rule::in(['iata_fee', 'rounding', 'service_fee', 'booking_fee', 'card_surcharge', 'resort_fee', 'other'])],
            'basis' => ['required', Rule::in(['fixed', 'percent_of_fare', 'percent_of_total', 'per_passenger', 'per_segment'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'recharge_policy' => ['required', Rule::in(['absorb', 'recharge_client', 'recharge_agent'])],
            'commissionable' => ['nullable', 'boolean'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** `POST /suppliers/{supplier}/charge-rules` -- add a supplier-specific charge rule. */
    public function chargeRuleStore(Request $request, Supplier $supplier): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        $validated = $request->validate($this->chargeRuleValidationRules());

        $row = \App\Models\SupplierChargeRule::create(array_merge($validated, [
            'company_id' => $companyId,
            'supplier_id' => $supplier->id,
            'commissionable' => $request->boolean('commissionable'),
            'active' => true,
        ]));

        app(\App\Services\TaskStatusService::class)->recordEvent('supplier_charge_rule_created', $companyId, null, null, null, null, null, [
            'user_id' => Auth::id(),
            'row_id' => $row->id,
            'supplier_id' => $supplier->id,
        ]);

        return back()->with('success', 'Charge rule added.');
    }

    /**
     * `POST /suppliers/charge-rules/create-default` -- company-wide default rule (supplier_id
     * null), used by the defaults page which has no supplier in its URL to route-bind against.
     */
    public function chargeRuleStoreDefault(Request $request): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        $validated = $request->validate($this->chargeRuleValidationRules());

        $row = \App\Models\SupplierChargeRule::create(array_merge($validated, [
            'company_id' => $companyId,
            'supplier_id' => null,
            'commissionable' => $request->boolean('commissionable'),
            'active' => true,
        ]));

        app(\App\Services\TaskStatusService::class)->recordEvent('supplier_charge_rule_created', $companyId, null, null, null, null, null, [
            'user_id' => Auth::id(),
            'row_id' => $row->id,
            'supplier_id' => null,
        ]);

        return back()->with('success', 'Company-wide charge rule added.');
    }

    /** `PUT /suppliers/charge-rules/{chargeRule}` -- edit (supplier-specific or company default). */
    public function chargeRuleUpdate(Request $request, \App\Models\SupplierChargeRule $chargeRule): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        abort_unless($chargeRule->company_id === $companyId, 403);

        $validated = $request->validate($this->chargeRuleValidationRules());
        $validated['commissionable'] = $request->boolean('commissionable');
        $chargeRule->update($validated);

        app(\App\Services\TaskStatusService::class)->recordEvent('supplier_charge_rule_updated', $companyId, null, null, null, null, null, [
            'user_id' => Auth::id(),
            'row_id' => $chargeRule->id,
        ]);

        return back()->with('success', 'Charge rule updated.');
    }

    /** `POST /suppliers/charge-rules/{chargeRule}/deactivate` -- soft toggle, never delete. */
    public function chargeRuleDeactivate(\App\Models\SupplierChargeRule $chargeRule): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        abort_unless($chargeRule->company_id === $companyId, 403);

        $chargeRule->update(['active' => ! $chargeRule->active]);

        app(\App\Services\TaskStatusService::class)->recordEvent(
            $chargeRule->active ? 'supplier_charge_rule_reactivated' : 'supplier_charge_rule_deactivated',
            $companyId, null, null, null, null, null,
            ['user_id' => Auth::id(), 'row_id' => $chargeRule->id]
        );

        return back()->with('success', $chargeRule->active ? 'Rule reactivated.' : 'Rule deactivated.');
    }

    /**
     * `POST /suppliers/charge-rules/test` -- "test a task" preview: pick a task, or enter
     * supplier/service_type/channel manually. Shows which rule(s) resolve and the computed line
     * amounts WITHOUT posting -- reuses {@see \App\Services\Accounting\SupplierChargeRuleResolver}
     * and {@see \App\Services\Accounting\SupplierChargeLineBuilder::computeAmount()} verbatim.
     */
    public function chargeRuleTestPreview(Request $request): JsonResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = (int) getCompanyId(Auth::user());

        if ($request->filled('task_id')) {
            $task = Task::where('company_id', $companyId)->findOrFail($request->input('task_id'));
            $supplierId = $task->supplier_id;
            $serviceType = (string) $task->type;
            $channel = $request->input('channel');
            $fareAmount = (float) ($task->price ?? 0);
            $totalAmount = (float) ($task->total ?? 0);
            $reference = $task->reference ?: ('task-' . $task->id);
        } else {
            $validated = $request->validate([
                'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
                'service_type' => ['required', 'string'],
                'channel' => ['nullable', 'string'],
                'fare_amount' => ['nullable', 'numeric', 'min:0'],
                'total_amount' => ['nullable', 'numeric', 'min:0'],
            ]);
            $supplierId = $validated['supplier_id'] ?? null;
            $serviceType = $validated['service_type'];
            $channel = $validated['channel'] ?? null;
            $fareAmount = (float) ($validated['fare_amount'] ?? 0);
            $totalAmount = (float) ($validated['total_amount'] ?? 0);
            $reference = 'preview-' . uniqid();
        }

        $defaults = config('accounting.posting_basis.default_by_service_type', []);
        $postingBasis = \App\Models\Setting::getByKey($companyId, "accounting.posting_basis.{$serviceType}", $defaults[$serviceType] ?? 'agent');

        $resolver = new \App\Services\Accounting\SupplierChargeRuleResolver();
        $winners = $resolver->resolveApplicable($companyId, $supplierId, $serviceType, $channel, now());

        $input = new \App\Services\Accounting\SupplierChargeLineInput(
            serviceType: $serviceType,
            postingBasis: $postingBasis,
            companyId: $companyId,
            reference: $reference,
            fareAmount: $fareAmount,
            totalAmount: $totalAmount,
            supplierId: $supplierId,
        );

        $builder = new \App\Services\Accounting\SupplierChargeLineBuilder($resolver);

        $rules = [];
        foreach ($winners as $chargeKind => $rule) {
            $rules[] = [
                'rule_id' => $rule->id,
                'charge_kind' => $chargeKind,
                'basis' => $rule->basis,
                'recharge_policy' => $rule->recharge_policy,
                'commissionable' => $rule->commissionable,
                'amount' => $builder->computeAmount($rule, $input),
                'already_fired_for_reference' => $rule->once_per_reference ? $resolver->hasAlreadyFired($rule, $reference) : false,
            ];
        }

        return response()->json(['success' => true, 'rules' => $rules]);
    }

    /**
     * `GET /suppliers/charge-rules/defaults` -- company-level default rules page (supplier_id
     * null rows), linked from suppliers/index.blade.php, mirroring statusMapDefaults() above.
     */
    public function chargeRuleDefaults(Request $request): View
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());

        $rows = \App\Models\SupplierChargeRule::where('company_id', $companyId)
            ->whereNull('supplier_id')
            ->orderBy('charge_kind')
            ->get();

        return view('suppliers.charge-rules-defaults', compact('rows', 'companyId'));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // T14 "Supplier bank details per currency" (accounting-builds PLAN.md §5 T14; L18). CRUD for
    // `supplier_bank_details`, embedded in the supplier master screen (suppliers.show), mirroring
    // the status-map / charge-rule editors' own add/edit/deactivate convention above. Master data
    // only -- never touches PostingSeam/journal_entries. Every create/update/deactivate writes an
    // AccountingAuditLog row via AccountingLog::write() (subject_type = 'supplier_bank_detail',
    // transaction_id = null -- this is master data, not a ledger document), reusing the ONE
    // audit mechanism the accounting engine already writes through rather than inventing a
    // second one. See AccountingAuditLog::subjectUrl()'s 'supplier_bank_detail' match arm for the
    // Log Center's deep link back to this supplier's page.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    private function bankDetailValidationRules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'bank_name' => ['required', 'string', 'max:191'],
            'beneficiary_name' => ['required', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:50', new ValidIban],
            'swift_bic' => ['required', 'string', 'max:20', new ValidSwiftBic],
            'bank_country' => ['required', 'string', 'max:100'],
            'intermediary_bank_name' => ['nullable', 'string', 'max:191'],
            'intermediary_swift_bic' => ['nullable', 'string', 'max:20', new ValidSwiftBic],
            'notes' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * "Setting a new default demotes the old one" (T14 spec, MP-14 mutation proof list) -- rather
     * than let a second DEFAULT+active row for the same (supplier, currency) hit the DB-level
     * `supplier_bank_details_default_group_unique` violation, this demotes whatever row currently
     * holds that slot FIRST, inside the same transaction as the write that claims it, so marking
     * a row default is a normal one-click action, not an error the user has to route around by
     * hand. The DB constraint stays the backstop for anything that reaches the table without going
     * through this path (see {@see self::friendlyBankDetailDbError()} and
     * `SupplierBankDetailTest::test_db_level_constraint_rejects_a_second_default_bypassing_the_controller_demote_flow`).
     */
    private function demoteExistingDefault(int $supplierId, string $currency, ?int $exceptId, int $companyId): void
    {
        SupplierBankDetail::where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('currency', $currency)
            ->where('is_default', true)
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => false, 'updated_by' => Auth::id()]);
    }

    /**
     * `POST /suppliers/{supplier}/bank-details` -- add a currency's remittance details.
     */
    public function bankDetailStore(Request $request, Supplier $supplier): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        $validated = $request->validate($this->bankDetailValidationRules());
        $validated['currency'] = mb_strtoupper($validated['currency']);
        $wantsDefault = (bool) ($validated['is_default'] ?? false);

        try {
            $row = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $companyId, $supplier, $wantsDefault) {
                if ($wantsDefault) {
                    $this->demoteExistingDefault($supplier->id, $validated['currency'], null, $companyId);
                }

                return SupplierBankDetail::create(array_merge($validated, [
                    'company_id' => $companyId,
                    'supplier_id' => $supplier->id,
                    'is_default' => $wantsDefault,
                    'is_active' => true,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]));
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->friendlyBankDetailDbError($e);
        }

        AccountingLog::write(
            action: 'create',
            companyId: $companyId,
            subjectType: 'supplier_bank_detail',
            subjectId: $row->id,
            after: $row->only(array_keys($this->bankDetailValidationRules())),
            actorId: Auth::id(),
            actorType: 'user',
        );

        return back()->with('success', 'Bank details added.');
    }

    /**
     * `PUT /suppliers/bank-details/{bankDetail}` -- edit an existing currency row.
     */
    public function bankDetailUpdate(Request $request, SupplierBankDetail $bankDetail): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        abort_unless($bankDetail->company_id === $companyId, 403);

        $validated = $request->validate($this->bankDetailValidationRules());
        $validated['currency'] = mb_strtoupper($validated['currency']);
        $wantsDefault = (bool) ($validated['is_default'] ?? false);

        $before = $bankDetail->only(array_keys($this->bankDetailValidationRules()));

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $wantsDefault, $bankDetail, $companyId) {
                if ($wantsDefault) {
                    $this->demoteExistingDefault($bankDetail->supplier_id, $validated['currency'], $bankDetail->id, $companyId);
                }

                $bankDetail->update(array_merge($validated, [
                    'is_default' => $wantsDefault,
                    'updated_by' => Auth::id(),
                ]));
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->friendlyBankDetailDbError($e);
        }

        AccountingLog::write(
            action: 'update',
            companyId: $companyId,
            subjectType: 'supplier_bank_detail',
            subjectId: $bankDetail->id,
            before: $before,
            after: $bankDetail->fresh()->only(array_keys($this->bankDetailValidationRules())),
            actorId: Auth::id(),
            actorType: 'user',
        );

        return back()->with('success', 'Bank details updated.');
    }

    /**
     * `POST /suppliers/bank-details/{bankDetail}/deactivate` -- soft toggle (is_active), never a
     * hard delete, per L18's own text. Reactivating a row can itself collide with the DB-level
     * default-per-currency guard if another row already holds that (supplier, currency)'s default
     * slot -- surfaced as the same friendly message as store()/update().
     */
    public function bankDetailDeactivate(SupplierBankDetail $bankDetail): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', Supplier::class);

        $companyId = getCompanyId(Auth::user());
        abort_unless($bankDetail->company_id === $companyId, 403);

        $before = ['is_active' => $bankDetail->is_active];

        try {
            $bankDetail->update([
                'is_active' => ! $bankDetail->is_active,
                'updated_by' => Auth::id(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->friendlyBankDetailDbError($e);
        }

        AccountingLog::write(
            action: $bankDetail->is_active ? 'reactivate' : 'deactivate',
            companyId: $companyId,
            subjectType: 'supplier_bank_detail',
            subjectId: $bankDetail->id,
            before: $before,
            after: ['is_active' => $bankDetail->is_active],
            actorId: Auth::id(),
            actorType: 'user',
        );

        return back()->with('success', $bankDetail->is_active ? 'Bank details reactivated.' : 'Bank details deactivated.');
    }

    /**
     * Translates the DB-level `supplier_bank_details_default_group_unique` violation (a second
     * DEFAULT+active row for the same supplier+currency) into the friendly validation message the
     * spec asks for ("surfaced as a friendly validation message rather than a raw SQL error"),
     * rather than letting the QueryException bubble up as a 500. Any other QueryException is
     * re-thrown -- this is a narrow, named translation, not a blanket error swallower.
     */
    private function friendlyBankDetailDbError(\Illuminate\Database\QueryException $e): \Illuminate\Http\RedirectResponse
    {
        if (str_contains($e->getMessage(), 'supplier_bank_details_default_group_unique')) {
            throw ValidationException::withMessages([
                'is_default' => 'This supplier already has a default bank detail for this currency. Remove the other default first.',
            ]);
        }

        throw $e;
    }
}
