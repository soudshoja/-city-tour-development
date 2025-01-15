<?php


namespace App\Http\Controllers;

use App\AIService;
use App\Models\Client;
use App\Models\OpenAi;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private $aiService;

    public function __construct()
    {
        $this->aiService = new AIService();
    }

    public function index()
    {

        $roles = $this->getAllRole();

        return view('role.index', compact('roles'));
    }

    public function create()
    {
        $permissions = $this->getAllPermission();
        return view('role.create', compact('permissions'));
    }

    public function store()
    {
        return redirect()->route('role.index');
    }

    public function edit($role)
    {
        $permissions = $this->getAllPermission();

        foreach($permissions as $key => $permission) dump($key);
        dd($permissions);
        return view('role.edit', compact('role', 'permissions'));
    }

    public function update($role)
    {
        return redirect()->route('role.index');
    }

    public function getAllPermission()
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

    public function getAllRole()
    {
        return Role::with('permissions')->get();
    }

    public function getRole($id)
    {
        $roles = $this->getAllRole();

        foreach ($roles as $role) {
            if ($role['id'] == $id) {
                return $role;
            }
        }

        return null;
    }
}
