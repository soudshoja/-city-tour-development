<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Role;
use App\Models\User;
use App\Models\Accountant;
use Exception;
use Carbon\Carbon;
use Database\Seeders\CoaSeeder;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $user = Auth::user();

        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
        ]);

        if ($user->role_id == Role::ADMIN) {
            if (!$request->has('company_id') && session()->has('company_id')) {
                return redirect()->route('users.index', [
                    'company_id' => session('company_id')
                ]);
            }
            if ($request->has('company_id')) {
                session(['company_id' => $request->input('company_id')]);
            }
        } else {
            if ($request->has('company_id')) {
                return redirect()->route('users.index');
            }
        }

        $companyId = null;
        $isAdmin = false;

        if ($user->role_id == Role::ADMIN) {
            $isAdmin = true;
            $companyId = $request->input('company_id', session('company_id'));

            if ($companyId) {
                $branches = Branch::where('company_id', $companyId)->pluck('id');
                $agents = Agent::whereIn('branch_id', $branches)->pluck('id');
                $accountants = Accountant::whereIn('branch_id', $branches)->pluck('id');

                $branchUserIds = User::whereHas('branch', function ($q) use ($branches) {
                    $q->whereIn('id', $branches);
                })->pluck('id');

                $agentUserIds = User::whereHas('agent', function ($q) use ($agents) {
                    $q->whereIn('id', $agents);
                })->pluck('id');

                $accountantUserIds = User::whereHas('accountant', function ($q) use ($accountants) {
                    $q->whereIn('id', $accountants);
                })->pluck('id');

                $allUserIds = $branchUserIds->merge($agentUserIds)->merge($accountantUserIds)->unique();

                $query = User::with('roles')->whereIn('id', $allUserIds);
            } else {
                $query = User::with('roles');
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
            $branches = Branch::where('company_id', $companyId)->pluck('id');
            $agents = Agent::whereIn('branch_id', $branches)->pluck('id');
            $accountants = Accountant::whereIn('branch_id', $branches)->pluck('id');

            $branchUserIds = User::whereHas('branch', function ($q) use ($branches) {
                $q->whereIn('id', $branches);
            })->pluck('id');

            $agentUserIds = User::whereHas('agent', function ($q) use ($agents) {
                $q->whereIn('id', $agents);
            })->pluck('id');

            $accountantUserIds = User::whereHas('accountant', function ($q) use ($accountants) {
                $q->whereIn('id', $accountants);
            })->pluck('id');

            $allUserIds = $branchUserIds->merge($agentUserIds)->merge($accountantUserIds)->unique();

            $query = User::with('roles')->whereIn('id', $allUserIds);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $companyId = $user->accountant->branch->company_id;
            $branches = Branch::where('company_id', $companyId)->pluck('id');
            $agents = Agent::whereIn('branch_id', $branches)->pluck('id');
            $accountants = Accountant::whereIn('branch_id', $branches)->pluck('id');

            $branchUserIds = User::whereHas('branch', function ($q) use ($branches) {
                $q->whereIn('id', $branches);
            })->pluck('id');

            $agentUserIds = User::whereHas('agent', function ($q) use ($agents) {
                $q->whereIn('id', $agents);
            })->pluck('id');

            $accountantUserIds = User::whereHas('accountant', function ($q) use ($accountants) {
                $q->whereIn('id', $accountants);
            })->pluck('id');

            $allUserIds = $branchUserIds->merge($agentUserIds)->merge($accountantUserIds)->unique();

            $query = User::with('roles')->whereIn('id', $allUserIds);
        } else {
            abort(403, 'Unauthorized action.');
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        return view('users.index', compact(
            'users',
            'isAdmin',
            'companyId'
        ));
    }

    public function editRole($userId)
    {
        if (!in_array(Auth::user()->role_id, [Role::ADMIN, Role::COMPANY])) {
            abort(403, 'Unauthorized action.');
        }

        $user = User::find($userId);

        if ($user->role_id == Role::ADMIN && Auth::user()->role_id != Role::ADMIN) {
            abort(403, 'Cannot change role of Admin users.');
        }

        if (Auth::user()->role_id == Role::ADMIN) {
            $companyId = request('company_id', session('company_id'));
            if ($companyId) {
                $roles = Role::where('company_id', $companyId)->get();
            } else {
                $roles = Role::all();
            }
        } else {
            $roles = Role::where('company_id', Auth::user()->company->id)->get();
        }

        $userRole = null;
        $phone = null;
        $countryCode = null;

        if ($user->role_id == Role::COMPANY && $user->company) {
            $userRole = 'company';
            $phone = $user->company->phone;
        } elseif ($user->role_id == Role::BRANCH && $user->branch) {
            $userRole = 'branch';
            $phone = $user->branch->phone;
        } elseif ($user->role_id == Role::AGENT && $user->agent) {
            $userRole = 'agent';
            $phone = $user->agent->phone_number;
        } elseif ($user->role_id == Role::ACCOUNTANT && $user->accountant) {
            $userRole = 'accountant';
            $countryCode = $user->accountant->country_code;
            $phone = $user->accountant->phone_number;
        } elseif ($user->role_id == Role::CLIENT && $user->client) {
            $userRole = 'client';
        }

        return view('users.edit', compact(
            'user',
            'roles',
            'userRole',
            'phone',
            'countryCode'
        ));
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'user_id' => 'required|integer|exists:users,id',
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $user = User::find($request->user_id);
        $role = Role::where('id', $request->role_id)
            ->where('company_id', $request->company_id)
            ->first();

        try {
            $user->syncRoles($role);
        } catch (Exception $e) {
            logger($e->getMessage());
            return redirect()->route('users.index')->with('error', 'Role assignment failed.');
        }

        return redirect()->route('users.index')->with('success', 'Role assigned successfully.');
    }

    public function newCompany()
    {
        $countries = Country::all(); // Fetch all countries from the `countries` table
        return view('admin.addnewCompany', compact('countries'));
    }

    public function create()
    {
        $user = Auth::user();
        if ($user->role_id == Role::ADMIN) {
            $branches = Branch::all();
        } elseif ($user->role_id == Role::COMPANY) {
            $branches = Branch::where('company_id', Auth::user()->company->id)->get();
        } else {
            $branches = collect();
        }
        $branches_id = $branches->pluck('id');

        $agents = Agent::whereIn('branch_id', $branches_id)->get();


        $agentTypes = AgentType::all();
        $countries = Country::all();

        return view('users.create', compact('agents', 'branches', 'agentTypes', 'countries'));
    }

    public function store(Request $request)
    {

        Log::info('Store function called with request data:', $request->all());

        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:15',
            'code' => 'required|string|max:100|unique:companies,code',
            'country_id' => 'required|integer|exists:countries,id',
            'address' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        $companyController = new CompanyController();

        $response = $companyController->store($request);

        $response = json_decode($response->getContent(), true);

        if ($response['status'] !== 'success') {
            return redirect()->route('companies.index')->with('error', 'Error creating company.');
        }

        $company = Company::find($response['data']['id']);

        if (!$company) {
            logger('Company not found after creation.');
            return redirect()->route('companies.index')->with('error', 'Company not found.');
        }

        try {
            CoaSeeder::run($company->id);
        } catch (Exception $e) {
            Log::error('Error seeding COA:', ['error' => $e->getMessage()]);
            return redirect()->route('companies.index')->with('error', 'Error creating COA accounts.');
        }

        $branchName = $company->name . ' - Main Branch';
        $branchEmail = $company->email;

        $branchController = new BranchController();

        $request = new Request([
            'name' => $branchName,
            'email' => $branchEmail,
            'phone' => $company->phone,
            'address' => $company->address,
            'user_id' => $company->user_id,
            'company_id' => $company->id,
        ]);

        $branchResponse = $branchController->store($request);

        $branchResponse = json_decode($branchResponse->getContent(), true);


        return redirect()->route('companies.index')->with($branchResponse['status'], $branchResponse['message']);
    }

    public function ShowCompanies(Request $request)
    {
        // Retrieve all companies with their related nationality
        $companies = Company::with('nationality')->get(); // Eager load the nationality relationship

        // Retrieve all companies and their count
        $companiesCount = Company::count();

        // Return view with the companies data
        return view('admin.companiesList', compact('companies', 'companiesCount'));
    }

    public function updateInfo(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string',
            'country_code' => 'nullable|string',
            'info-new-password' => 'nullable|min:8|confirmed',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('info-new-password')) {
            $user->password = Hash::make($request->input('info-new-password'));
        }

        $user->save();

        $sourceRole = $request->input('source_role');

        if ($sourceRole === 'agent' && $user->agent) {
            $user->agent->update([
                'name' => $request->name,
                'email' => $request->email,
                'country_code' => $request->country_code,
                'phone_number' => $request->phone,
            ]);
        } elseif ($sourceRole === 'accountant' && $user->accountant) {
            $user->accountant->update([
                'name' => $request->name,
                'email' => $request->email,
                'country_code' => $request->country_code,
                'phone_number' => $request->phone,
            ]);
        } elseif ($sourceRole === 'branch' && $user->branch) {
            $user->branch->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);
        } elseif ($sourceRole === 'company' && $user->company) {
            $user->company->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);
        }

        return redirect()->back()->with('success', 'Information updated successfully.');
    }
}
