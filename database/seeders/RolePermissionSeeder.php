<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        $users->each(function ($user) {
            
            if($user->role_id == 1){
                $user->syncRoles('admin');
            } elseif($user->role_id == 2){
                $user->syncRoles('company');
            } elseif($user->role_id == 3){
                $user->syncRoles('branch');
            } elseif($user->role_id == 4){
                $user->syncRoles('agent');
            } elseif($user->role_id == 5){
                $user->syncRoles('accountant');
            } elseif($user->role_id == 6){
                $user->syncRoles('client');
            }
        });
    }
}
