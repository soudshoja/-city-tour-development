<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate(['name' => 'create user']);
        Permission::firstOrCreate(['name' => 'read user']);
        Permission::firstOrCreate(['name' => 'update user']);
        Permission::firstOrCreate(['name' => 'delete user']);
        Permission::firstOrCreate(['name' => 'create role']);
        Permission::firstOrCreate(['name' => 'read role']);
        Permission::firstOrCreate(['name' => 'update role']);
        Permission::firstOrCreate(['name' => 'delete role']);
        Permission::firstOrCreate(['name' => 'create permission']);
        Permission::firstOrCreate(['name' => 'read permission']);
        Permission::firstOrCreate(['name' => 'update permission']);
        Permission::firstOrCreate(['name' => 'delete permission']);
        Permission::firstOrCreate(['name' => 'create company']);
        Permission::firstOrCreate(['name' => 'read company']);
        Permission::firstOrCreate(['name' => 'update company']);
        Permission::firstOrCreate(['name' => 'delete company']);
        Permission::firstOrCreate(['name' => 'create branch']);
        Permission::firstOrCreate(['name' => 'read branch']);
        Permission::firstOrCreate(['name' => 'update branch']);
        Permission::firstOrCreate(['name' => 'delete branch']);
        Permission::firstOrCreate(['name' => 'create task']);
        Permission::firstOrCreate(['name' => 'read task']);
        Permission::firstOrCreate(['name' => 'update task']);
        Permission::firstOrCreate(['name' => 'delete task']);
        Permission::firstOrCreate(['name' => 'create agent']);
        Permission::firstOrCreate(['name' => 'read agent']);
        Permission::firstOrCreate(['name' => 'update agent']);
        Permission::firstOrCreate(['name' => 'delete agent']);
    }
}
