<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierCompanyController extends Controller
{
    public function edit($id)
    {
        $companies = Company::all();
        $supplier = Supplier::findOrFail($id);

        // Check if the supplier is already activated for any company
        $activatedCompanies = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->pluck('company_id')
            ->toArray();
        $companies = $companies->map(function ($company) use ($activatedCompanies) {
            $company->is_active = in_array($company->id, $activatedCompanies);
            return $company;
        });

        // $companies = $companies->map(function ($company) use ($supplier) {
        //     $company->is_active = $company->suppliers->contains('id', $supplier->id);
        //     return $company;
        // });

        return view('supplier-company.index', compact( 'supplier', 'companies'));
    }

    public function activateSupplierProcess(Supplier $supplier, Company $company)
    {
        DB::beginTransaction();
        try {
            // Check if supplier is already activated
            $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
                ->where('company_id', $company->id)
                ->first();

            if ($supplierCompany) {
                
                    try{

                    $supplierCompany->is_active = true;
                    $supplierCompany->update();
                    
                } catch (Exception $e) {

                    Log::error('Failed to activate supplier: ' . $e->getMessage());
                    DB::rollBack();
                    return [
                        'status' => 'error',
                        'message' => 'Failed to activate supplier'
                    ];
                }

                DB::commit();
                return [
                    'status' => 'success',
                    'message' => 'Supplier is already activated for this company.'
                ];

            }

            // Check if credentials exist
            $credentials = SupplierCredential::where('supplier_id', $supplier->id)
                ->where('company_id', $company->id)
                ->exists();


            if (!$credentials) {
                SupplierCredential::create([
                    'supplier_id' => $supplier->id,
                    'company_id' => $company->id,
                    'environment' => env('APP_ENV') == 'production' ? 'production' : 'sandbox',
                    'type' => 'basic',
                    'username' => 'test',
                    'password' => 'test',
                    'client_id' => null,
                    'client_secret' => null,
                    'access_token' => null,
                    'refresh_token' => null,
                    'expires_at' => null,

                ]);
            }

            $types = [
                'has_flight' => ['payable' => 'Suppliers (Flights)', 'cost' => 'Flights Cost'],
                'has_hotel' => ['payable' => 'Suppliers (Hotels)', 'cost' => 'Hotels Cost'],
                'has_visa' => ['payable' => 'Suppliers (Visas)', 'cost' => 'Visa Cost'],
                'has_insurance' => ['payable' => 'Suppliers (Insurance)', 'cost' => 'Insurance Cost'],
                'has_tour' => ['payable' => 'Suppliers (Tour)', 'cost' => 'Tour Cost'],
                'has_cruise' => ['payable' => 'Suppliers (Cruise)', 'cost' => 'Cruise Cost'],
                'has_car' => ['payable' => 'Suppliers (Car)', 'cost' => 'Car Cost'],
                'has_rail' => ['payable' => 'Suppliers (Rail)', 'cost' => 'Rail Cost'],
                'has_esim' => ['payable' => 'Suppliers (Esim)', 'cost' => 'Esim Cost'],
                'has_event' => ['payable' => 'Suppliers (Event)', 'cost' => 'Event Cost'],
                'has_lounge' => ['payable' => 'Suppliers (Lounge)', 'cost' => 'Lounge Cost'],
                'has_ferry' => ['payable' => 'Suppliers (Ferry)', 'cost' => 'Ferry Cost'],
            ];

            $hasAtLeastOne = false;

            foreach ($types as $field => $accounts) {
                if (!$supplier->$field) {
                    continue;
                }

                $hasAtLeastOne = true;

                $accountPayable = Account::where('name', $accounts['payable'])
                    ->where('company_id', $company->id)
                    ->first();

                if (!$accountPayable) {
                    throw new \Exception("Account Payable group '{$accounts['payable']}' not found.");
                }

                $costAccount = Account::where('name', $accounts['cost'])
                    ->where('company_id', $company->id)
                    ->first();

                if (!$costAccount) {
                    throw new \Exception("Supplier cost account '{$accounts['cost']}' not found.");
                }

                $supplierCompany = SupplierCompany::firstOrCreate([
                    'supplier_id' => $supplier->id,
                    'company_id' => $company->id,
                    'is_active' => true,
                ]);

                $data = [
                    'name' => $supplier->name,
                    'level' => 4,
                    'actual_balance' => 0,
                    'budget_balance' => 0,
                    'variance' => 0,
                    'company_id' => $company->id,
                    'supplier_company_id' => $supplierCompany->id,
                ];

                // Generate unique code under each account
                $newPayableCode = (int)$accountPayable->code + 1;
                $newCostCode = (int)$costAccount->code + 1;

                Account::create($data + [
                    'parent_id' => $accountPayable->id,
                    'root_id' => $accountPayable->root_id,
                    'code' => (string)$newPayableCode,
                ]);

                Account::create($data + [
                    'parent_id' => $costAccount->id,
                    'root_id' => $costAccount->root_id,
                    'code' => (string)$newCostCode,
                ]);
            }

            if (!$hasAtLeastOne) {
                throw new \Exception('Supplier must have at least one category checked.');
            }

            // $parentAccountName = $supplier->has_flight
            //     ? 'Suppliers (Flights)'
            //     : ($supplier->has_hotel
            //         ? 'Suppliers (Hotels)'
            //         : ($supplier->has_visa
            //             ? 'Suppliers (Visas)'
            //             : ($supplier->has_insurance
            //                 ? 'Suppliers (Insurance)'
            //                 : ($supplier->has_tour
            //                     ? 'Suppliers (Tour)'
            //                     : ($supplier->has_cruise
            //                         ? 'Suppliers (Cruise)'
            //                         : ($supplier->has_car
            //                             ? 'Suppliers (Car)'
            //                             : ($supplier->has_rail
            //                                 ? 'Suppliers (Rail)'
            //                                 : ($supplier->has_esim
            //                                     ? 'Suppliers (Esim)'
            //                                     : ($supplier->has_event
            //                                         ? 'Suppliers (Event)'
            //                                         : ($supplier->has_lounge
            //                                             ? 'Suppliers (Lounge)'
            //                                             : ($supplier->has_ferry
            //                                                 ? 'Suppliers (Ferry)'
            //                                                 : 'Accounts Payable')))))))))));

            // $accountPayable = Account::where([
            //     'name' => $parentAccountName,
            //     'company_id' => $company->id,
            // ])->first();

            // if (!$accountPayable) {
            //     throw new Exception("Account Payable group '$parentAccountName' not found.");
            // }

            // $supplierCostAccount = collect();

            // if ($supplier->has_flight) {
            //     $supplierCostAccount = Account::where('name', 'Flights Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_hotel) {
            //     $supplierCostAccount = Account::where('name', 'Hotels Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_visa) {
            //     $supplierCostAccount = Account::where('name', 'Visa Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_insurance) {
            //     $supplierCostAccount = Account::where('name', 'Insurance Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_tour) {
            //     $supplierCostAccount = Account::where('name', 'Tour Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_cruise) {
            //     $supplierCostAccount = Account::where('name', 'Cruise Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_car) {
            //     $supplierCostAccount = Account::where('name', 'Car Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_rail) {
            //     $supplierCostAccount = Account::where('name', 'Rail Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_esim) {
            //     $supplierCostAccount = Account::where('name', 'Esim Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_event) {
            //     $supplierCostAccount = Account::where('name', 'Event Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_lounge) {
            //     $supplierCostAccount = Account::where('name', 'Lounge Cost')->where('company_id', $company->id)->first();
            // } elseif ($supplier->has_ferry) {
            //     $supplierCostAccount = Account::where('name', 'Ferry Cost')->where('company_id', $company->id)->first();
            // } else {
            //     throw new \Exception('Supplier is not a flight, hotel, visa, insurance, or supported type.');
            // }


            // if (!$supplierCostAccount) {
            //     throw new Exception("Supplier cost account not found.");
            // }

            // SupplierCompany::firstOrCreate([
            //     'supplier_id' => $supplier->id,
            //     'company_id' => $company->id,
            //     'is_active' => true,
            // ]);


            // $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
            //     ->where('company_id', $company->id)
            //     ->first();

            // $data = [
            //     'name' => $supplier->name,
            //     'level' => 4,
            //     'actual_balance' => 0,
            //     'budget_balance' => 0,
            //     'variance' => 0,
            //     'company_id' => $company->id,
            //     'supplier_company_id' => $supplierCompany->id,
            // ];


            // $accountPayableCode = (int)$accountPayable->code + 1;

            // Account::create(
            //     $data + [
            //         'parent_id' => $accountPayable->id,
            //         'root_id' => $accountPayable->root_id,
            //         'code' => (string)$accountPayableCode,
            //     ]
            // );

            // Account::create(
            //     $data + [
            //         'parent_id' => $supplierCostAccount->id,
            //         'root_id' => $supplierCostAccount->root_id,
            //         'code' => (string)$supplierCostAccount->code,
            //     ]
            // );

            // $account = Account::create([
            //     'name' => $supplier->name,
            //     'level' => 4,
            //     'actual_balance' => 0,
            //     'budget_balance' => 0,
            //     'variance' => 0,
            //     'company_id' => $company->id,
            //     'parent_id' => $accountPayable->id,
            //     'code' => 'SUP' . $accountPayable->id . str_pad($accountPayable->children()->count() + 1, 3, '0', STR_PAD_LEFT),
            // ]);
        } catch (Exception $e) {
            DB::rollBack();
            logger('Created Supplier Company Account Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to activate supplier: ' . $e->getMessage()
            ];
        }

        DB::commit();

        return [
            'status' => 'success',
            'message' => 'Supplier activated successfully.'
        ];

    }

    public function activateSupplier(Request $request, ?Supplier $supplier = null, ?Company $company = null)
    {
        if ($request->has('supplier_id')) {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
            ]);

            try{
                $supplier = Supplier::findOrFail($request->input('supplier_id'));
                $company = Company::findOrFail($request->input('company_id'));

                $response = $this->activateSupplierProcess($supplier, $company);

                if($response['status'] === 'error') {
                    return redirect()->back()->with('error', $response['message']);
                }

            } catch (Exception $e) {
                return redirect()->back()->with('error', 'Failed to activate supplier: ' . $e->getMessage());
            }
         
            return redirect()->back()->with('success', 'Supplier activated successfully.'); 
        } else if($supplier && $company) {
            try {
                $this->activateSupplierProcess($supplier, $company);
            } catch (Exception $e) {
                return redirect()->back()->with('error', 'Failed to activate supplier: ' . $e->getMessage());
            }

            return redirect()->back()->with('success', 'Supplier activated successfully.');
        }
    }


    public function deactivateSupplier(Request $request, Supplier $supplier, Company $company)
    {
        if(isset($request->supplier_id)) {

            try{
                $supplierCompany = SupplierCompany::where('supplier_id', $request->supplier_id)
                    ->where('company_id', $request->company_id)
                    ->first();
                if (!$supplierCompany) {
                    return redirect()->back()->with('error', 'Supplier is not activated for this company.');
                }
                $supplierCompany->is_active = false;
                $supplierCompany->update();

            } catch (Exception $e) {

                Log::error('Failed to deactivate supplier: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to deactivate supplier');

            }
        }

        return redirect()->back()->with('success', 'Supplier deactivated successfully.');
    }

    private function createData(Supplier $supplier, int $companyId, Account $accountPayable)
    {
        try {
            $account = Account::create([
                'name' => $supplier->name,
                'level' => 4,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'company_id' => $companyId,
                'parent_id' => $accountPayable->id,
                'code' => 'SUP' . $accountPayable->id . str_pad($accountPayable->children->count() + 1, 3, '0', STR_PAD_LEFT),
            ]);

            SupplierCompany::firstOrCreate([
                'supplier_id' => $supplier->id,
                'company_id' => $companyId,
                // 'account_id' => $account->id
            ]);
        } catch (Exception $e) {
            logger('Created Supplier Company Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to create supplier company.'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Supplier activated successfully.'
        ];
    }
}
