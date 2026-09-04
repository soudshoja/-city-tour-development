<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $branches = Branch::where('company_id', $companyId)->pluck('id');
                $agents = Agent::whereIn('branch_id', $branches)->pluck('id');
                $accountants = Accountant::whereIn('branch_id', $branches)->pluck('id');

                $branchUserIds = User::whereHas('branch', fn($q) => $q->whereIn('id', $branches))->pluck('id');
                $agentUserIds = User::whereHas('agent', fn($q) => $q->whereIn('id', $agents))->pluck('id');
                $accountantUserIds = User::whereHas('accountant', fn($q) => $q->whereIn('id', $accountants))->pluck('id');

                $allUserIds = $branchUserIds->merge($agentUserIds)->merge($accountantUserIds)->unique();

                $query = User::with('roles')->whereIn('id', $allUserIds);
            } else {
                $query = User::with('roles');
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $branches = Branch::where('company_id', $companyId)->pluck('id');
            $agents = Agent::whereIn('branch_id', $branches)->pluck('id');
            $accountants = Accountant::whereIn('branch_id', $branches)->pluck('id');

            $branchUserIds = User::whereHas('branch', fn($q) => $q->whereIn('id', $branches))->pluck('id');
            $agentUserIds = User::whereHas('agent', fn($q) => $q->whereIn('id', $agents))->pluck('id');
            $accountantUserIds = User::whereHas('accountant', fn($q) => $q->whereIn('id', $accountants))->pluck('id');

            $allUserIds = $branchUserIds->merge($agentUserIds)->merge($accountantUserIds)->unique();

            $query = User::with('roles')->whereIn('id', $allUserIds);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $branches = Branch::where('company_id', $companyId)->pluck('id');
            $agents = Agent::whereIn('branch_id', $branches)->pluck('id');
            $accountants = Accountant::whereIn('branch_id', $branches)->pluck('id');

            $branchUserIds = User::whereHas('branch', fn($q) => $q->whereIn('id', $branches))->pluck('id');
            $agentUserIds = User::whereHas('agent', fn($q) => $q->whereIn('id', $agents))->pluck('id');
            $accountantUserIds = User::whereHas('accountant', fn($q) => $q->whereIn('id', $accountants))->pluck('id');

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

        return view('users.index', compact('users'));
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

        $companyId = getCompanyId(Auth::user());

        if (Auth::user()->role_id == Role::ADMIN) {
            if ($companyId) {
                $roles = Role::where('company_id', $companyId)->get();
            } else {
                $roles = Role::all();
            }
        } else {
            $roles = Role::where('company_id', $companyId)->get();
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
        // SECURITY: this method had NO authorization check at all -- any authenticated user of
        // any role could reassign any other user's role, including promoting themselves/others to
        // Admin. Gated the same way its sibling GET (editRole(), which renders the very form that
        // posts here -- see resources/views/users/edit.blade.php) already gates itself, rather
        // than inventing a new pattern.
        if (! in_array(Auth::user()->role_id, [Role::ADMIN, Role::COMPANY])) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'user_id' => 'required|integer|exists:users,id',
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        // Mirrors editRole()'s own "cannot touch Admin users unless you are Admin" rule exactly
        // (same condition, same message) -- editRole() is the GET that renders the very form this
        // method receives, and already enforces this on the TARGET user's tier.
        $user = User::find($request->user_id);

        if ($user->role_id == Role::ADMIN && Auth::user()->role_id != Role::ADMIN) {
            abort(403, 'Cannot change role of Admin users.');
        }

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
        // SECURITY: no authorization check (found while auditing this controller for the
        // storeRole()/updateInfo() fixes above). Gated with the same CompanyPolicy::create()
        // ability ('create company' permission) as store() below, which this form posts to.
        abort_unless((int) Auth::user()->role_id === Role::ADMIN, 403);
        Gate::authorize('create', Company::class);

        $countries = Country::all(); // Fetch all countries from the `countries` table
        return view('admin.addnewCompany', compact('countries'));
    }

    public function create()
    {
        // SECURITY: no authorization check (found while auditing this controller for the
        // storeRole()/updateInfo() fixes above). This is exactly the action already gated in the
        // UI by `@can('create', App\Models\User::class)` (resources/views/layouts/sidebar.blade.php)
        // -- the sidebar hid the link from unauthorized users, but the route itself enforced
        // nothing, so it was reachable directly. Matches that existing gate rather than inventing
        // a new one.
        Gate::authorize('create', User::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($companyId) {
            $branches = Branch::where('company_id', $companyId)->get();
        } else {
            $branches = collect();
        }

        $branches_id = $branches->pluck('id');
        $agents = Agent::whereIn('branch_id', $branches_id)->get();

        $agentTypes = AgentType::all();
        $countries = Country::all();

        return view('users.create', compact('agents', 'branches', 'agentTypes', 'countries', 'companyId'));
    }

    public function store(Request $request)
    {
        // SECURITY: no authorization check (found while auditing this controller for the
        // storeRole()/updateInfo() fixes above). Creates a brand-new Company + Branch + a full COA
        // (CoaSeeder::run() below) -- unambiguously a platform/tenant-onboarding action, not
        // something any authenticated agent/client should be able to trigger. Gated with the
        // pre-existing CompanyPolicy::create() ability ('create company' permission,
        // registered in AppServiceProvider), same as newCompany() above (the display form for
        // this same action).
        abort_unless((int) Auth::user()->role_id === Role::ADMIN, 403);
        Gate::authorize('create', Company::class);

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

        // Fix 3 (pre-pilot defect list): CoaSeeder::run() used to run in its
        // own try/catch AFTER CompanyController::store() had already
        // committed the company/user rows. A seeding failure left a company
        // that exists with no chart of accounts — and since the ledger
        // auto-posts for every company, that company's books have nowhere
        // to write. CompanyController::store() below opens its own
        // DB::beginTransaction()/commit() internally; nesting it inside
        // THIS transaction turns its inner commit into a savepoint release
        // rather than a real commit, so a CoaSeeder failure here rolls the
        // company back too — the same guarantee CompanyProvisioner::
        // provision() already gives Path B (company creation + COA seeding
        // inside one DB::transaction()).
        DB::beginTransaction();

        try {
            $response = $companyController->store($request);

            $response = json_decode($response->getContent(), true);

            if ($response['status'] !== 'success') {
                DB::rollBack();
                return redirect()->route('companies.index')->with('error', 'Error creating company.');
            }

            $company = Company::find($response['data']['id']);

            if (!$company) {
                DB::rollBack();
                logger('Company not found after creation.');
                return redirect()->route('companies.index')->with('error', 'Company not found.');
            }

            CoaSeeder::run($company->id);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error seeding COA:', ['error' => $e->getMessage()]);
            return redirect()->route('companies.index')->with('error', 'Error creating COA accounts.');
        }

        // Path A (operator manual entry) is a real package-client provisioning
        // path, not internal/demo company creation — see
        // .planning/specs/PACKAGE-OVERVIEW.md §3.1/§3.2, correction PKG-2.
        // Company::create() above always inserts a brand-new row (unlike Path
        // B's CompanyProvisioner, there is no --repair variant of this method
        // that revisits an existing company), so this always applies to a
        // genuinely new company.
        try {
            app(\App\Support\Entitlements\ApplyCompanyModulePreset::class)->apply($company);
        } catch (Exception $e) {
            Log::error('Error applying module preset:', ['error' => $e->getMessage()]);

            return redirect()->route('companies.index')->with('error', 'Error applying module entitlements.');
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

        // Module 5 (plan .planning/specs/RESAYIL-ADMIN-CENTER.md section 3.1):
        // Path A creates a real package client, so it gets the same silent
        // Resayil workspace provisioning Path B (CompanyProvisioner) does.
        // Queued, and after every commit above, so a Resayil outage can never
        // affect a company that has already been created. Idempotent and
        // re-runnable via php artisan resayil:provision-company.
        try {
            \App\Jobs\ProvisionResayilWorkspace::dispatch($company->id, $company->user_id);
        } catch (\Throwable $e) {
            Log::warning('AdminUsersController: could not queue Resayil workspace provisioning', [
                'company_id' => $company->id, 'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('companies.index')->with($branchResponse['status'], $branchResponse['message']);
    }

    public function ShowCompanies(Request $request)
    {
        // SECURITY: no authorization check (found while auditing this controller for the
        // storeRole()/updateInfo() fixes above) -- lists EVERY company on the platform
        // (cross-tenant) to any authenticated user of any role. Not linked from any nav/view
        // (route name 'companies.index' has zero references anywhere in resources/views), but the
        // route itself was still live and directly reachable. Gated with the pre-existing
        // CompanyPolicy::viewAny() ability ('view company' permission) -- the closest existing
        // precedent in THIS controller for a cross-company capability is setCompany()'s
        // Role::ADMIN-only check below, but 'view company' already exists as the purpose-built
        // permission for "list companies" and is reused instead of inventing a role_id check.
        abort_unless((int) Auth::user()->role_id === Role::ADMIN, 403);
        Gate::authorize('viewAny', Company::class);

        // Retrieve all companies with their related nationality
        $companies = Company::with('nationality')->get(); // Eager load the nationality relationship

        // Retrieve all companies and their count
        $companiesCount = Company::count();

        // Return view with the companies data
        return view('admin.companiesList', compact('companies', 'companiesCount'));
    }

    public function updateInfo(Request $request, User $user)
    {
        // SECURITY: this method had NO authorization check at all -- any authenticated user of
        // any role could update any other user's name/email/phone AND reset their password
        // (line below: Hash::make($request->input('info-new-password'))), including an Admin's.
        // Gated identically to storeRole() above -- same sibling admin-only page (edit.blade.php,
        // rendered by editRole()), same check. Not a self-service profile route: `users.updateInfo`
        // is referenced from exactly one Blade view (users/edit.blade.php), which is itself only
        // reachable via editRole()'s own admin/company gate; the app's actual self-profile edit
        // flow is the separate resources/views/profile/partials/update-profile-information-form.
        if (! in_array(Auth::user()->role_id, [Role::ADMIN, Role::COMPANY])) {
            abort(403, 'Unauthorized action.');
        }

        if ($user->role_id == Role::ADMIN && Auth::user()->role_id != Role::ADMIN) {
            abort(403, 'Cannot modify Admin users.');
        }

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

    public function setCompany(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);

        if (Auth::user()->role_id !== Role::ADMIN) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        session()->forget('company_id');
        session(['company_id' => (int) $request->company_id]);
        session()->save();

        return response()->json([
            'success' => true,
            'company_id' => $request->company_id,
            'message' => 'Company switched successfully'
        ]);
    }
}
