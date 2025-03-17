<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create([
            'name' => 'Superadmin',
            'email' => 'it@alphia.net',
            'role_id' => 1,
            'password' => bcrypt(config('auth.admin_password')),
        ]);

        $role = Role::firstOrCreate(['name' => 'admin']);

        $user->assignRole($role);
    }
}
