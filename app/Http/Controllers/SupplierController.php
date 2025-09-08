<?php

namespace App\Http\Controllers;

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
use App\Models\Task;
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

class SupplierController extends Controller
{
    use AuthorizesRequests, HttpRequestTrait;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Supplier::class);
        $user = auth()->user();

        $suppliers = Supplier::all();

        if($user->role_id == Role::ADMIN) {

            // Only get SupplierCompany which is active
            $suppliers = Supplier::with(['companies' => function($query) {
            $query->where('is_active', true);
            }])->get();
        } elseif ($user->role_id == Role::COMPANY) {

            $suppliers = Supplier::with('credentials')->whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->company->id)->where('is_active', true);
            })->with('companies')->get();

        } else {
            
            return redirect()->back()->with('error', 'Unauthorized action.');

        }

        foreach ($suppliers as $supplier) {
            if (!is_null($supplier->route)) {
                $route = Route::getRoutes()->getByName('suppliers.'. $supplier->route . '.index');
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

    $supplier = SupplierCompany::with('supplier.tasks.agent')->findOrFail($suppliersId)->supplier;
    $invoicesId = $supplier->tasks->pluck('invoiceDetail.invoice_id')->toArray();
    $invoicesId = array_values(array_filter($invoicesId));
    $taskIds = $supplier->tasks->pluck('id')->toArray();
    $JournalEntry = JournalEntry::select('id', 'debit', 'credit', 'created_at', 'task_id', 'account_id')
        ->with(['task.agent', 'account'])
        ->whereIn('task_id', $taskIds)
        ->get();
    $currencies = ['USD', 'GBP', 'AED', 'EUR', 'EGP', 'SAR', 'BUD', 'QAR']; 
    return view('suppliers.show', compact('supplier', 'JournalEntry', 'currencies'));
}
    
public function ledgerByDateRange(Request $request, $supplierId)
{
    $fromDate = $request->query('fromDate');
    $toDate = $request->query('toDate');

    $supplier = Supplier::with('tasks.agent')->findOrFail($supplierId);
    $taskIds = $supplier->tasks->pluck('id')->toArray();

    $query = JournalEntry::with(['task.agent', 'account'])->whereIn('task_id', $taskIds);

    if ($fromDate && $toDate) {
        $query->whereBetween('created_at', [$fromDate, $toDate]);
    }

    $entries = $query->orderBy('created_at', 'desc')->get();

    $totalDebit = $entries->sum('debit');
    $totalCredit = $entries->sum('credit');

    return response()->json([
        'entries' => $entries,
        'totalDebit' => $totalDebit,
        'totalCredit' => $totalCredit,
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
        ]);

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
            'auth_type' => ['required', Rule::in(['basic','oauth'])],
            'has_hotel' => 'nullable',
            'has_flight' => 'nullable',
            'has_visa' => 'nullable',
            'has_insurance' => 'nullable',
            'has_tour' => 'nullable',
            'has_cruise' => 'nullable',
            'has_car' => 'nullable',
            'has_rail' => 'nullable',
            'has_esim' => 'nullable',
            'has_event' => 'nullable',
            'has_lounge' => 'nullable',
            'has_ferry' => 'nullable',
            'country_id' => 'required|exists:countries,id',
            'is_online'   => 'exclude_unless:has_hotel,on|boolean',
        ]);

        $supplier = Supplier::findOrFail($id);
        $hasHotel = $request->has('has_hotel');
        $isOnline = $hasHotel ? (int)$request->boolean('is_online') : 0;

        $supplier->update([
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
            'is_online'    => $isOnline,
        ]);

        return redirect()->back()->with('success', 'Supplier updated successfully.');
    }

    public function getTotalDebitCredit($supplierId, $endDate)
    {
        $endDate = new DateTime($endDate);
        $supplier = Supplier::with('tasks.invoiceDetail.invoice')->findOrFail($supplierId);
        $invoicesId = $supplier->tasks->pluck('invoiceDetail.invoice_id')->toArray();
        $invoicesId = array_values(array_filter($invoicesId));
        $totalDebit = JournalEntry::whereIn('invoice_id', $invoicesId)->where('created_at', '<=', $endDate)->sum('debit');
        $totalCredit = JournalEntry::whereIn('invoice_id', $invoicesId)->where('created_at', '<=', $endDate)->sum('credit');
        
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

    public function getMagicHoliday($ref = null) : JsonResponse
    {
        if($ref) {
            $url =config('services.magic-holiday.url') . '/reservationsApi/v1/reservations/' . $ref;
        } else {
            $url =config('services.magic-holiday.url') . '/reservationsApi/v1/reservations';
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
    ) : array
    {
        
        $responseCredential = $this->getClientCredential($scopes);

        if(isset($responseCredential['error'])){
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

        // test

        // $apiUrl = config('services.open-ai.url');
        // $apiKey = config('services.open-ai.key');

        // $url = $apiUrl . '/chat/completions';
        // $header = [
        //     'Authorization: Bearer ' . config('services.open-ai.key'),
        //     'Content-Type: application/json',
        // ];

        // $message = [
        //     [
        //         'role' => 'user',
        //         'content' => 'Please respond with JSON format',
        //     ]
        // ];

        // $data = [
        //     'model' => config('services.open-ai.model'),
        //     'messages' => $message,
        //     'response_format' => [
        //         'type' => 'json_object',
        //     ]
        // ];

        // $response = Http::timeout(120)->withHeaders([
        //     'Authorization' => 'Bearer ' . $apiKey,
        //     'Content-Type' => 'application/json',
        // ])->post($url, $data);

        // dd($response->json());

        //end test
        
         
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

        if(isset($response['status']) && $response['status'] !== 200){
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

    public function getClientCredential(array $scopes) : array
    {
        $user = Auth::user();
        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company_id;
        } elseif($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company_id;
        } 
        
        $credential = SupplierCredential::query()
            ->where('company_id', $companyId)
            ->where('supplier_id', 2)
            ->first();

        $key = 'magic_holiday_client_credential_' . $credential->client_id . '_' . implode('_', $scopes);
        $ttl = 60 * 60 * 24; // seconds * minutes * hours (1 day)

        if ($companyId && $credential) {
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
                    throw new Exception('Unable to retrieve access token.');
                }

                return $response->json();
            });
            
        }

    }
    
    public function magicReserveWebhook($id)
    {

        $data = $this->getClientCredential(['write:reservations-webhooks']);


        if(isset($data['error'])){
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

        if(!$id || !$event || !$data) {
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
        if($event == 'status.change'){
            $data = json_decode($data, true);

            $currentStatus = $data['current_status'] ?? null;
            $previousStatus = $data['previous_status'] ?? null;

            if(!$currentStatus || !$previousStatus) {
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
            
            if(!$amendments){

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

            if(!$group || !$original || !$amendedBy) {
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

            if(!$magicHolidaySupplier) {
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
            
            if(!$existingReservation) {
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
}
