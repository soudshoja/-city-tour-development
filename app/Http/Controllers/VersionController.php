<?php


namespace App\Http\Controllers;

use App\AIService;
use App\Models\Client;
use App\Models\OpenAi;
use Exception;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Version;

class VersionController extends Controller
{
    private $aiService;

    public function __construct()
    {
        $this->aiService = new AIService();
    }

    public function index()
    {
        $versions = $this->getAllVersions();

        return view('version.index', compact('versions'));
    }

    public function create()
    {
        $permissions = $this->getAllPermission();
        return view('role.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:255',
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
            'description' => $request->description
        ]);
        $permissions = Permission::whereIn('id', $request->permissionsId)->get();
        $role->syncPermissions($permissions);
        
        return redirect()->route('role.index');
    }

    public function edit($roleId)
    {
        $groupedPermissions = cache()->remember('permissions', 3600, function () {
            return $this->getAllPermission();
        });

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

        return view('role.edit', compact('role','permissions'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'role_id' => 'required',
            'permissionsId' => 'array'
        ]);

        $role = Role::with('permissions')->find($request->role_id);
        
        if(!$role) {
            return redirect()->back()->with('error', 'Role not found');
        }

        if($role->permissions->count() == 0){
            if(count($request->permissionsId) == 0){
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

    public function getAllPermissionGroupedByAI()
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
        
        return json_decode($response['choices'][0]['message']['content'],true);

    }

    public function getAllVersions()
    {
        return Version::all();
    }

}
