<?php

namespace Database\Seeders;

use App\Http\Controllers\RoleController;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AgentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'agent']);

        $roleController = new RoleController();

        $permission = $roleController->getAllPermissionForAgent();

        $role->syncPermissions($permission);
    }
}
