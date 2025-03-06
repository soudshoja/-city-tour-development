<?php

namespace App\Http\Controllers;

use App\Http\Traits\HttpRequestTrait;
use App\Models\GeneralLedger;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Supplier;
use App\Models\SupplierCredential;
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
        Gate::authorize('view supplier');
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
        return view('suppliers.index', compact('suppliers', 'suppliersCount'));
    }

    public function show($suppliersId)
    {
        if (Auth::user()->role_id !== Role::ADMIN && Auth::user()->role_id !== Role::COMPANY) {
            abort(403, 'Unauthorized action.');
        }

        $supplier = Supplier::with('tasks.invoiceDetail.invoice')->findOrFail($suppliersId);
        $invoicesId = $supplier->tasks->pluck('invoiceDetail.invoice_id')->toArray();
        $invoicesId = array_values(array_filter($invoicesId));

        $generalLedger = GeneralLedger::select('id', 'debit', 'credit', 'created_at')
            ->whereIn('invoice_id', $invoicesId)
            ->get();

        return view('suppliers.show', compact('supplier', 'generalLedger'));
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
        // Check if the user has an admin role
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        // Validate the request
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'address' => 'required',
        ]);

        // Create a new supplier
        Supplier::create($request->all());

        // Redirect to the suppliers list
        return redirect()->route('suppliers.index');
    }

    public function getTotalDebitCredit($supplierId, $endDate)
    {
        $endDate = new DateTime($endDate);
        $supplier = Supplier::with('tasks.invoiceDetail.invoice')->findOrFail($supplierId);
        $invoicesId = $supplier->tasks->pluck('invoiceDetail.invoice_id')->toArray();
        $invoicesId = array_values(array_filter($invoicesId));
        $totalDebit = GeneralLedger::whereIn('invoice_id', $invoicesId)->where('created_at', '<=', $endDate)->sum('debit');
        $totalCredit = GeneralLedger::whereIn('invoice_id', $invoicesId)->where('created_at', '<=', $endDate)->sum('credit');
        
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
        $data = $this->getClientCredential();

        $data = json_decode($data->content(), true);

        $accessToken = $data['token_type'] . ' ' . $data['access_token'];

        if($ref) {
            $url =config('services.magic-holiday.url') . '/reservationsApi/v1/reservations/' . $ref;
        } else {
            $url =config('services.magic-holiday.url') . '/reservationsApi/v1/reservations?page=1';
        }

        $response = $this->makeApiRequest($accessToken, $url);

        return response()->json($response);
    }

    public function makeApiRequest($accessToken, $url)
    {
        $client = new Client([
            'headers' => [
            'Authorization' => $accessToken,
            'Accept' => 'application/json',
            ],
        ]);

        try {
            $response = $client->get($url);

            logger($response->getBody());

            $data = json_decode($response->getBody(), true);
            return $data;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return ['error' => 'API request failed: ' . $e->getResponse()->getBody()];
        }
    }

    public function getClientCredential()
    {
        $tokenUrl = config('services.magic-holiday.token_url');
        $client = new Client();
        $response = $client->post($tokenUrl, [
            'form_params' => [
                'client_id' => config('services.magic-holiday.client-id'),
                'client_secret' => config('services.magic-holiday.client-secret'),
                'grant_type' => 'client_credentials',
                'scope' => 'read:reservations',
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        logger($data);
        return response()->json($data);
    }

    public function magicReserveWebhook($id)
    {

        $data = $this->getClientCredential();

        $data = json_decode($data->content(), true);

        $accessToken = $data['token_type'] . ' ' . $data['access_token'];

        $url = config('services.magic-holiday.url') . '/reservationsApi/v1/reservations/' . $id . '/webhook';
        // Use the access token to make API requests
        $response = $this->makeApiRequest($accessToken, $url);

        Log::channel('magic-holiday')->info('Magic Holiday Webhook', $response);

        return response()->json($response);
    }
}
