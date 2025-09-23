<?php


namespace App\Http\Controllers;

use App\AIService;
use App\Models\Client;
use App\Models\OpenAi;
use Exception;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    private $aiService;

    public function __construct()
    {
        $this->aiService = new AIService();
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        if(!($user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY )){
            return abort(403, 'Unauthorized action.');
        }

        $companyId = $request->company_id ?? $user->company->id;

        $roles = $this->getAllRole($companyId);
        $user = Auth::user();
        // Log::info('Debugging User Permissions:', [
        //     'roles' => $user->getRoleNames(),
        //     'permissions' => $user->getAllPermissions()->pluck('name'),
        //     'view_companies' => $user->can('viewAny', App\Models\Company::class),
        //     'view_branches' => $user->can('viewAny', App\Models\Branch::class),
        //     'view_agents' => $user->can('viewAny', App\Models\Agent::class),
        //     'has_permission_web' => $user->can('view company', 'web'),
        //     'has_permission_api' => $user->can('view company', 'api'),
        //     'guard' => auth()->guard()->name,
        //     'user' => auth()->user(),
        // ]);

        if ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        }
        return view('role.index', compact('roles'));
    }

    public function create()
    {
        $permissions = $this->getAllPermission();
        return view('role.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if($user->role_id != Role::COMPANY ) {
            return redirect()->back()->with('error', 'Not Authorized');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'permissionsId' => 'required|array'
        ], [
            // 'name.required' => 'The role name is required.',
            // 'name.string' => 'The role name must be a string.',
            // 'name.max' => 'The role name may not be greater than 255 characters.',
            // 'description.required' => 'The description is required.',
            // 'description.string' => 'The description must be a string.',
            // 'description.max' => 'The description may not be greater than 255 characters.',
            // 'permissionsId.array' => 'The permissions must be an array.',
            'permissionsId.required' => 'Please select at least one permission.'
        ]);


        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'description' => $request->description,
            'company_id' => $user->company->id
        ]);

        if (Str::lower($role->name) === 'accountant') {
            $permissions = Permission::whereIn('id', $this->viewOnlyPermissionsIds())->get();
        } else {
    
        $permissions = Permission::whereIn('id', $request->permissionsId)->get();
        }

        $role->syncPermissions($permissions);

        return redirect()->route('role.index');
    }

    public function edit($roleId)
    {
        $user = Auth::user();

        if ($user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY) {
            $groupedPermissions = cache()->remember('permissions', 3600, function () {
                return $this->getAllPermission();
            });
        } else if ($user->role_id == Role::AGENT) {
            $groupedPermissions = cache()->remember('permissions_company', 3600, function () {
                return $this->getAllPermissionForAgent();
            });
        } else if ($user->role_id == Role::ACCOUNTANT) {
            $groupedPermissions = cache()->remember('permissions', 3600, function () {
                return $this->getAllPermission();
            });
        }else {
            return redirect()->back()->with('error', 'You do not have role, please contact your administrator');
        }

        $role = Role::with('permissions')->find($roleId);

        // compare permissions with role permissions and check if the role already has the same permission
        foreach ($groupedPermissions as $gpKey => $permissions) {
            foreach ($permissions as $pKey => $permission) {
                $groupedPermissions[$gpKey][$pKey]['checked'] = false;

                foreach ($role->permissions as $rolePermission) {
                    if ($rolePermission->id == $permission['id']) {
                        $groupedPermissions[$gpKey][$pKey]['checked'] = true;
                    }
                }
            }
        }

        $permissions = $groupedPermissions;

        return view('role.edit', compact('role', 'permissions'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'role_id' => 'required',
            'permissionsId' => 'array'
        ]);

        $role = Role::with('permissions')->find($request->role_id);

        if (!$role) {
            return redirect()->back()->with('error', 'Role not found');
        }

        if ($role->permissions->count() == 0) {
            if (count($request->permissionsId) == 0) {
                return redirect()->back()->with('error', 'Pick at least one permission');
            }
        }

        try {

            $permissions = Permission::whereIn('id', $request->permissionsId)->get();
            $role->syncPermissions($permissions);
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Role updated successfully');
    }

    public function getAllPermission()
    {
        $permissions = Permission::getGroupedByGroup();

        return $permissions;
    }

    public function getAllPermissionForAgent()
    {
        $permissions = Permission::getGroupedByGroup();

        $excludedGroups = ['company', 'branch', 'agent'];

        $permissions = $permissions->filter(function ($group, $key) use ($excludedGroups) {
            return !in_array($key, $excludedGroups);
        });

        return $permissions;
    }

    public function getAllPermissionGroupedByAI() //not used
    {
        $permissions = Permission::all();

        $message = [
            [
                'role' => 'user',
                'content' => 'Please help me categorize my permissions exist in the system based on the names such as it become group-permission > permissions'
            ],
            [
                'role' => 'user',
                'content' => "example: this is the original array = ['create user', 'read user', 'create role', 'read role', 'create permission', 'read permission']. Into this array = [
                    'user' => [ {'id' => 1, 'name' => 'user', 'guard_name' => 'web' },{ 'id' => 2, 'name' => 'role', 'guard_name' => 'web' },{ 'id' => 3, 'name' => 'permission', 'guard_name' => 'web' }],
                    'role' => [ {'id' => 1, 'name' => 'create role', 'guard_name' => 'web' },{ 'id' => 2, 'name' => 'read role', 'guard_name' => 'web' }],
                    'permission' => [ {'id' => 1, 'name' => 'create permission', 'guard_name' => 'web' },{ 'id' => 2, 'name' => 'read permission', 'guard_name' => 'web' }]
                ]"
            ],
            [
                'role' => 'user',
                'content' => 'This is the real array of permissions in the system:' . $permissions
            ]
        ];

        $response = $this->aiService->chatCompletionJsonResponse($message);

        return json_decode($response['choices'][0]['message']['content'], true);
    }

    public function getAllPermissionForAccountant() 
    {
        $permissions = Permission::getGroupedByGroup();

        foreach ($permissions as $permission => $items) {
            $permissions[$permission] = collect($items)->fileter(function ($perm) {
                $n = Str::lower($perm['name'] ?? '');
                return Str::startsWith($n, ['view', 'read', 'list', 'show', 'export']) || Str::contains($n, 'download');
            })->values()->all();

            if (empty($permissions[$permission])) {
                unset($permissions[$permission]);
            }
        }
        return $permissions;
    }

    public function viewOnlyPermissionsIds()
    {
        return Permission::query()
        ->where(function ($q) {
            $q->where('name', 'like', 'view %')
            ->orWhere('name', 'like', 'read %')
            ->orWhere('name', 'like', 'list %')
            ->orWhere('name', 'like', 'show %')
            ->orWhere('name', 'like', 'export %')
            ->orWhere('name', 'like', '% download%');
        })
        ->pluck('id')
        ->all();
    }
    
    public function getAllRole()
    {
        return Role::with('permissions')
            ->where('company_id', $companyId)
            ->get();
    }
}
