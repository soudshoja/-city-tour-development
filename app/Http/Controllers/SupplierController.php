<?php

namespace App\Http\Controllers;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use App\Models\Task;
use DateTime;
use Generator;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use League\OAuth2\Client\Provider\GenericProvider;

class SupplierController extends Controller
{
    use AuthorizesRequests, HttpRequestTrait;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Supplier::class);
        $user = auth()->user();

        $suppliers = Supplier::all();

        // if($user->role_id == Role::ADMIN) {
        //     $suppliers = Supplier::with('companies')->get();
        // } elseif($user->role_id == Role::COMPANY) {
        //     $suppliers = $user->company->suppliers()->get();
        // } else {
        //     return redirect()->back()->with('error', 'Unauthorized action.');
        // }
        if($user->role_id == Role::ADMIN) {
            $suppliers = Supplier::with('companies')->get();
        }elseif($user->role_id == Role::COMPANY) {
            $suppliers = Supplier::with(['credentials'], function($query) use ($user){
                $query->where('company_id', $user->company_id);
            })->get();
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
        $suppliersCount = Supplier::count();
        if(auth()->user()->company !== null){
            $supplierCompany = SupplierCompany::where('company_id', $user->company->id)->get();
            return view('suppliers.index', compact('suppliers', 'suppliersCount', 'supplierCompany'));
        }

        return view('suppliers.index', compact('suppliers', 'suppliersCount'));
    }

    public function show($suppliersId)
    {
        Gate::authorize('view', Supplier::class);
        // if (Auth::user()->role_id !== Role::ADMIN || Auth::user()->role_id !== Role::COMPANY) {
        //     abort(403, 'Unauthorized action.');
        // }

        $supplier = SupplierCompany::with('supplier.tasks.invoiceDetail.invoice')->findOrFail($suppliersId)->supplier;
        $invoicesId = $supplier->tasks->pluck('invoiceDetail.invoice_id')->toArray();
        $invoicesId = array_values(array_filter($invoicesId));
        $JournalEntry = JournalEntry::select('id', 'debit', 'credit', 'created_at')
            ->whereIn('invoice_id', $invoicesId)
            ->get();

        return view('suppliers.show', compact('supplier', 'JournalEntry'));
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
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'address' => 'required',
        ]);

        $accountPayable = Account::where('name', 'Account Payable')->first();

        $account = Account::create([
            'name' => $request->name,
            'level' => 4,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => Auth::user()->company_id,
            'parent_id' => $accountPayable->id,
            'code' => 'SUP' . $accountPayable->id . str_pad($accountPayable->children->count() + 1, 3, '0', STR_PAD_LEFT),
        ]);



        return redirect()->route('suppliers.index');
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
        $tokenUrl = config('services.magic-holiday.token_url');
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
            $response = $client->post($tokenUrl, [
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

    public function getMagicHoliday($ref = null)
    {
        if($ref) {
            $url =config('services.magic-holiday.url') . '/reservationsApi/v1/reservations/' . $ref;
        } else {
            $url =config('services.magic-holiday.url') . '/reservationsApi/v1/reservations';
        }

        $scopes = ['read:reservations'];

        $response = $this->magicApiRequest('GET', $url, [], [], $scopes);

        return response()->json($response);
    }

    public function magicApiRequest(
        string $method = 'GET',
        string $url,
        array $header = [],
        array $data = [],
        array $scopes = ['read:reservations']
    )
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
            'Authorization: ' . $accessToken,
            'Accept: application/json',
            'Content-Type: application/json',
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
            $response = $this->getRequest($url, $header);
            break;
            case 'POST':
            $response = $this->postRequest($url, $header, $data);
            break;
            case 'PUT':
            $response = $this->putRequest($url, $header, $data);
            break;
            case 'DELETE':
            $response = $this->deleteRequest($url, $header);
            break;
            default:
            throw new \InvalidArgumentException("Unsupported HTTP method: $method");
        }

        Log::channel('magic_holidays')->info('Response', $response);

        if(isset($response['status']) && $response['status'] !== 200){
            return [
                'status' => 'error',
                'data' => $response,
                'message' => $response['detail']
            ];
        }

        return [
            'status' => 'success',
            'data' => $response
        ];
    }

    public function getClientCredential(array $scopes)
    {
        $tokenUrl = config('services.magic-holiday.token_url');

        $data = [
            'client_id' => config('services.magic-holiday.client-id'),
            'client_secret' => config('services.magic-holiday.client-secret'),
            'grant_type' => 'client_credentials',
            'scope' => $scopes,
        ];

        Log::channel('magic_holidays')->info('Credential Request', [
            'token_url' => $tokenUrl,
            'data' => $data
        ]);

        $response = $this->postRequest($tokenUrl, [], $data);

        Log::channel('magic_holidays')->info('Credential Response', $response);

        return $response;
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
            'url' => route('suppliers.magic-webhook-callback'),
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
                    'type' => route('suppliers.magic-webhook-docs'),
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
