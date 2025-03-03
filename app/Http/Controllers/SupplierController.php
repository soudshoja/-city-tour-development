<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use League\OAuth2\Client\Provider\GenericProvider;

class SupplierController extends Controller
{
    use AuthorizesRequests;

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

    public function getMagicHoliday()
    {
        
    }

    public function redirectToProvider()
    {
        $provider = new GenericProvider([
            'clientId' => config('services.magic-holiday.client-id'),
            'clientSecret' => config('services.magic-holiday.client-secret'),
            'redirectUri' => route('suppliers.magic-callback'),
            'urlAuthorize' => config('services.magic-holiday.authorization_url'),
            'urlAccessToken' => config('services.magic-holiday.token_url'),
            'urlResourceOwnerDetails' => '', // Optional
        ]);

        $authorizationUrl = $provider->getAuthorizationUrl([
            'scope' => ['read:reservations'],
        ]);

        session(['oauth2state' => $provider->getState()]);

        return redirect($authorizationUrl);
    }

    public function handleProviderCallback(Request $request)
    {
        $provider = new GenericProvider([
            'clientId' => config('services.magic-holiday.client-id'),
            'clientSecret' => config('services.magic-holiday.client-secret'),
            'redirectUri' => route('suppliers.magic-callback'),
            'urlAuthorize' => config('services.magic-holiday.authorization_url'),
            'urlAccessToken' => config('services.magic-holiday.token_url'),
            'urlResourceOwnerDetails' => '', // Optional
        ]);

        $state = $request->input('state');
        $sessionState = Session::get('oauth2state');

        if (empty($state) || ($state !== $sessionState)) {
            Session::forget('oauth2state');
            abort(401, 'Invalid state');
        }

        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->input('code'),
            ]);

            // Store the access token and refresh token (if available)
            // Example:
            session(['access_token' => $accessToken->getToken()]);
            session(['refresh_token' => $accessToken->getRefreshToken()]);
            session(['expires_at' => $accessToken->getExpires()]);

            return redirect()->route('suppliers.magic-request');
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            abort(500, 'Failed to get access token: ' . $e->getMessage());
        }
    }


    public function makeApiRequest()
    {
        $accessToken = session('access_token');

        $client = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);

        $response = $client->get('https://api.provider.com/resource');

        $data = json_decode($response->getBody(), true);

        // Process the API response
        return response()->json($data);
    }
}
