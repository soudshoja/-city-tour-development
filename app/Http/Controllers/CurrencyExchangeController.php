<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyExchangeTrait;
use App\Models\Company;
use App\Models\Agent;
use App\Models\CurrencyExchange;
use App\Models\SystemExchangeRate;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\ExchangeRateHistory;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Currency;
use RuntimeException;
use Throwable;

class CurrencyExchangeController extends Controller
{
    use CurrencyExchangeTrait;

    public function index()
    {
        $currencyExchanges = CurrencyExchange::orderBy('company_id', 'asc')->get();
        $companies = Company::select('id', 'name')->get();

        if (auth()->user()->hasRole('company')) {
            if (auth()->user()->company == null) {

                logger()->error('User company not found', ['user_id' => auth()->id()]);
                return redirect()->back()->with('error', 'Something went wrong');
            }

            $companies = array_filter($companies->toArray(), function ($company) {
                return $company['id'] == auth()->user()->company->id;
            });
        }

        $currenciesAvailable = cache()->remember('exchangeRates', 3600, function () {
            $systemExchangeRateController = new SystemExchangeRateController();
            $response = $systemExchangeRateController->currencies();

            if (!isset($response['data']) || $response['data'] == null) {
                throw new Exception('Failed to fetch currency exchange rates');
            }

            return $response['data'];
        });

        $currenciesAvailable = array_filter($currenciesAvailable, function ($currency) {
            return in_array($currency['code'], [
                'USD',
                'SAR',
                'QAR',
                'GBP',
                'AED',
                'EUR',
                'EGP',
                'BHD',
                'KWD',
                'LKR',
                'JOD',
                'INR',
                'MAD',
                'CNY',
                'SGD',
                'IDR',
                'NPR',
                'AED',
            ]);
        });

        if (!$currenciesAvailable) {
            return redirect()->back()->with('error', 'Failed to fetch currency exchange rates');
        }

        $user = Auth::user();
        if ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        }

        return view('currency-exchange.index', compact(
            'currencyExchanges',
            'currenciesAvailable',
            'companies'
        ));
    }

    public function storeProcess(Request $request) : JsonResponse
    {
        Log::info('Starting to create new currency rate');

        $request->validate([
            'company_id' => 'required',
            'base_currency' => 'required',
            'exchange_currency' => 'required',
            'exchange_rate' => 'required_if:is_manual,1',
            'is_manual' => 'required'
        ]);

        $currencyExchange = CurrencyExchange::where([
            'company_id' => $request->company_id,
            'base_currency' => $request->base_currency,
            'exchange_currency' => $request->exchange_currency
        ])->first();

        if ($currencyExchange) {
            return response()->json([
                'status' => 'error',
                'message' => 'Currency exchange rate already exists'
            ]);
        }

        if ($request->is_manual == 0) {
            $systemExchangeRate = SystemExchangeRate::where([
                'base_currency' => $request->base_currency,
                'exchange_currency' => $request->exchange_currency
            ])->first();

            if (!$systemExchangeRate) {
                $systemExchangeRateController = new SystemExchangeRateController();
                $response = $systemExchangeRateController->updateBaseRate($request->base_currency);

                if ($response->status() !== 200) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to update currency exchange rate'
                    ]);
                }

                $systemExchangeRate = SystemExchangeRate::where([
                    'base_currency' => $request->base_currency,
                    'exchange_currency' => $request->exchange_currency
                ])->first();
            }

            $request->merge(['exchange_rate' => $systemExchangeRate->exchange_rate]);
        }

        try {
            CurrencyExchange::create($request->all());
            Log::info('Successfully created the currency rate', [
                'Data' => $request->all()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            }

        return response()->json([
            'status' => 'success',
            'message' => 'Currency exchange rate added successfully'
        ]);
    }

    public function addNewCurrency(Request $request) : JsonResponse
    {   
        Log::info('Adding new currency rate: ', [
            'Data' => $request->all()
        ]);

        $request->validate([
            'company_id' => 'required|integer',
            'base_currency' => 'required|string',
            'exchange_currency' => 'required|string',
            'is_manual' => 'required|boolean'
        ]);

        $existingRate = CurrencyExchange::where([
             'company_id'        => $request->company_id,
            'base_currency'     => $request->base_currency,
            'exchange_currency' => $request->exchange_currency
        ])->first();

        if($existingRate) {
            return response()->json([
                'status' => 'exists',
                'message'=> 'Currency exchange rate already exist',
                'data' => $existingRate
            ]);
        }    
        
        return $this->storeProcess($request);
    
    }

    public function store(Request $request) : RedirectResponse
    {
       $response = $this->storeProcess($request)->getData(true);
    
       return redirect()->back()->with($response['status'], $response['message']);
    }

    public function updateAuto(Request $request)
    {
        $request->validate([
            'id.*' => 'required',
        ]);

        $currencyExchange = CurrencyExchange::find($request->id);

        $systemExchangeRate = SystemExchangeRate::where([
            'base_currency' => $currencyExchange->base_currency,
            'exchange_currency' => $currencyExchange->exchange_currency
        ])->first();

        if (!$systemExchangeRate) {

            $systemExchangeRateController = new SystemExchangeRateController();
            $response = $systemExchangeRateController->updateBaseRate($currencyExchange->base_currency);

            if ($response->status() !== 200) {
                return response()->json(['message' => 'Failed to update currency exchange rate'], 500);
            }

            $systemExchangeRate = SystemExchangeRate::where([
                'base_currency' => $currencyExchange->base_currency,
                'exchange_currency' => $currencyExchange->exchange_currency
            ])->first();
        }

        try {
            $currencyExchange->exchange_rate = $systemExchangeRate->exchange_rate;
            $currencyExchange->updated_at = now();
            $currencyExchange->save();
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Currency exchange rate updated successfully'], 200);
    }

    public function updateManual(Request $request)
    {
        $data = $request->all();

        foreach ($data as $rateData) {
            $exchange = CurrencyExchange::findOrFail($rateData['id']);
            $oldRate = $exchange->exchange_rate;
            $exchange->exchange_rate = $rateData['exchange_rate'];
            $exchange->is_manual = true;
            $exchange->save();

            ExchangeRateHistory::create([
                'currency_exchange_id' => $exchange->id,
                'base_currency' => $exchange->base_currency,
                'exchange_currency' => $exchange->exchange_currency,
                'old_rate' => $oldRate,
                'new_rate' => $rateData['exchange_rate'],
                'method' => 'manual',
                'changed_by' => Auth::id(),
                'changed_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Exchange rates updated and history recorded.']);
    }

    public function updateMethod($id)
    {
        try {
            $currencyExchange = CurrencyExchange::find($id);
            $currencyExchange->is_manual = !$currencyExchange->is_manual;
            $currencyExchange->save();
        } catch (Exception $e) {

            logger()->error('Failed to update currency exchange rate method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Something Went Wrong'], 500);
        }

        return response()->json([
            'currencyExchange' => $currencyExchange,
            'message' => 'Currency exchange rate method updated successfully'
        ], 200);
    }

    public function histories()
    {
        return $this->hasMany(ExchangeRateHistory::class);
    }

    public function allHistories()
    {
        $currencyExchanges = \App\Models\CurrencyExchange::with(['company', 'histories.user'])
            ->orderBy('base_currency')
            ->get();

        return view('currency-exchange.all-histories', compact('currencyExchanges'));
    }

    public function convertFromSidebar(Request $request): JsonResponse
    {
        Log::info('Starting to convert an exchange currency with ' . json_encode($request->all()));
        $user = Auth::user();
        if ($user->role_id == Role::ADMIN) {
            $companyId = 1;
        } elseif ($user->role_id == Role::COMPANY) {
            $companyId = Company::where('user_id', $user->id)->value('id');
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = auth()->user()->agent->branch->company_id;
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $companyId = auth()->user()->accountant->branch->company->id;
        } else {
            return response()->json([
                'status'  => 'error',
                'message' => 'User is not authorized to access this feature. Contact Administrator for further information',
            ], 404);
        }

        if (!$companyId) {
            Log::warning('User is not found linked to any company', ['company_id' => $companyId, 'user_id' => $user->id]);
            return response()->json([
                'status'  => 'error',
                'message' => 'User is not linked to any company. Contact the Administrator.',
            ], 403);
        }

        $data = $request->validate([
            'company_id'    => ['nullable', 'integer'],
            'from_currency' => ['required', 'string'],
            'to_currency'   => ['required', 'string'],
            'amount'        => ['required', 'numeric'],
        ]);

        $fromCurrency = strtoupper($data['from_currency']);
        $toCurrency   = strtoupper($data['to_currency']);
        $amount       = (float) $data['amount'];

        try {
            $response = $this->convert($companyId, $fromCurrency, $toCurrency, $amount);

            Log::info('Conversion result', ['Data' => $response]);

            if (!isset($response['exchange_rate'], $response['converted_amount'])) {
                throw new \RuntimeException("Exchange rate missing for {$fromCurrency}→{$toCurrency}");
            }
        } catch (\Throwable $e) {
            Log::warning('Exchange rate missing', ['error' => $e->getMessage()]);

            if ($user->role_id != Role::AGENT) {
                Log::info('User is a Company. Attempting to add new currency rate');

                $createRate = new Request([
                    'company_id'        => $companyId,
                    'base_currency'     => $fromCurrency,
                    'exchange_currency' => $toCurrency,
                    'is_manual'         => '0',
                ]);

                $response = $this->addNewCurrency($createRate)->getData(true);
                Log::info('New exchange rate created', [
                    'Data' => $response
                ]);

                if (in_array($response['status'] ?? '', ['success', 'exists'], true)) {
                    return response()->json([
                        'status'  => 'success',
                        'created' => true,
                        'message' => ($response['status'] === 'exists')
                            ? 'Rate already existed and was reused.'
                            : 'Currency exchange rate added successfully. Refreshing…',
                    ]);
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => $create['message'] ?? 'Failed to create exchange rate.',
                ], 422);
            } else {
                Log::warning('User is not a Company. Revoke the access to create new rate');

                return response()->json([
                    'status' => 'error',
                    'message' => 'No such rate is found in the database. Contact the Administrator to create the rate.'
                ], 401);
            }
        }

        $inverse = $this->getExchangeRate($companyId, $toCurrency, $fromCurrency);
        if ($inverse === null) {
            Log::warning('Inverse rate missing', ['pair' => "{$toCurrency}→{$fromCurrency}"]);
            return response()->json([
                'status'           => 'success',
                'exchange_rate'    => (float) $response['exchange_rate'],
                'converted_amount' => (float) $response['converted_amount'],
                'inverse_rate'     => 'N/A',
                'message'          => "No inverse rate found for {$toCurrency} → {$fromCurrency}.",
            ]);
        }

        return response()->json([
            'status'           => 'success',
            'exchange_rate'    => (float) $response['exchange_rate'],
            'converted_amount' => (float) $response['converted_amount'],
            'inverse_rate'     => (float) $inverse,
        ]);
    }

    public function getLatestRate(Request $request): JsonResponse
    {
        Log::info('[CURRENCY EXCHANGE] Fetching latest exchange rate', [
            'request' => $request->all()
        ]);

        $data = $request->validate([
            'company_id'    => ['required', 'integer'],
            'from_currency' => ['required', 'string'],
            'to_currency'   => ['required', 'string'],
        ]);

        $fromCurrency = strtoupper($data['from_currency']);
        $toCurrency   = strtoupper($data['to_currency']);
        $companyId    = $data['company_id'];

        $exchangeRate = $this->getExchangeRate($companyId, $fromCurrency, $toCurrency);

        if ($exchangeRate === null) {
            return response()->json([
                'status'  => 'error',
                'message' => "No exchange rate found for {$fromCurrency} → {$toCurrency}.",
            ], 404);
        }

        return response()->json([
            'status'        => 'success',
            'exchange_rate' => (float) $exchangeRate,
        ]);
    }

    public function convertCurrency(Request $request): JsonResponse
    {
        Log::info('Starting to convert an exchange currency with ' . json_encode($request->all()));

        $data = $request->validate([
            'company_id'    => ['required', 'integer'],
            'from_currency' => ['required', 'string'],
            'to_currency'   => ['required', 'string'],
            'amount'        => ['required', 'numeric'],
        ]);

        $fromCurrency = strtoupper($data['from_currency']);
        $toCurrency   = strtoupper($data['to_currency']);
        $amount       = (float) $data['amount'];
        $companyId    = $data['company_id'];

        try {
            $response = $this->convert($companyId, $fromCurrency, $toCurrency, $amount);

            Log::info('Conversion result', ['Data' => $response]);

            if (!isset($response['exchange_rate'], $response['converted_amount'])) {
                throw new RuntimeException("Exchange rate missing for {$fromCurrency}→{$toCurrency}");
            }
        } catch (Throwable $e) {
            Log::warning('Exchange rate missing', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => "No such rate is found in the database. Contact the Administrator to create the rate",
            ], 404);
        }

        return response()->json([
            'status'           => 'success',
            'exchange_rate'    => (float) $response['exchange_rate'],
            'converted_amount' => (float) $response['converted_amount'],
        ]);
    }

}
