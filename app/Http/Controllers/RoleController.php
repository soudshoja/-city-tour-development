<?php


namespace App\Http\Controllers;

use App\Models\Client;

class RoleController extends Controller
{
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
        return view('role.edit', compact('role', 'permissions'));
    }

    public function update($role)
    {
        return redirect()->route('role.index');
    }

    private function getAllPermission()
    {

        return [
            [
                'id' => 1,
                'name' => 'User',
                'sub' => [
                    'view-user',
                    'create-user',
                    'edit-user',
                    'delete-user',
                    'edit-profile'
                ],
            ],
            [
                'id' => 2,
                'name' => 'Role',
                'sub' => [
                    'view-role',
                    'create-role',
                    'edit-role',
                    'delete-role'
                ],
            ],
            [
                'id' => 3,
                'name' => 'Profile',
                'sub' => [
                    'edit-profile'
                ],
            ],
            [
                'id' => 4,
                'name' => 'Agent',
                'sub' => [
                    'view-agent',
                    'edit-agent'
                ],
            ]
        ];
    }

    public function getAllRole()
    {
        return [
            [
                'id' => 1,
                'name' => 'Admin',
                'description' => 'Admin role',
                'permissions' => [
                    'view-user',
                    'create-user',
                    'edit-user',
                    'delete-user',
                    'view-role',
                    'create-role',
                    'edit-role',
                    'delete-role',
                    'edit-profile'
                ],
            ],
            [
                'id' => 2,
                'name' => 'User',
                'description' => 'User role',
                'permissions' => [
                    'view-user',
                    'edit-user',
                    'edit-profile'
                ],
            ],
            [
                'id' => 3,
                'name' => 'Agent',
                'description' => 'Agent role',
                'permissions' => [
                    'view-user',
                    'edit-user',
                    'edit-profile',
                    'view-agent',
                    'edit-agent'
                ],
            ],
            [
                'id' => 4,
                'name' => 'Company',
                'description' => 'Company role',
                'permissions' => [
                    'view-user',
                    'edit-user',
                    'edit-profile'
                ],
            ],
            [
                'id' => 5,
                'name' => 'Client',
                'description' => 'Client role',
                'permissions' => [
                    'view-user',
                    'edit-user',
                    'edit-profile'
                ],
            ],
            [
                'id' => 6,
                'name' => 'Guest',
                'description' => 'Guest role',
                'permissions' => [
                    'view-user',
                    'edit-user',
                    'edit-profile'
                ],
            ]
        ];
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
