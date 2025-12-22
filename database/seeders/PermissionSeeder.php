<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate(['name' => 'create user', 'group' => 'user']);
        Permission::firstOrCreate(['name' => 'view user', 'group' => 'user']);
        Permission::firstOrCreate(['name' => 'update user', 'group' => 'user']);
        Permission::firstOrCreate(['name' => 'delete user', 'group' => 'user']);
        Permission::firstOrCreate(['name' => 'create role', 'group' => 'role']);
        Permission::firstOrCreate(['name' => 'view role', 'group' => 'role']);
        Permission::firstOrCreate(['name' => 'update role', 'group' => 'role']);
        Permission::firstOrCreate(['name' => 'delete role', 'group' => 'role']);
        Permission::firstOrCreate(['name' => 'create permission', 'group' => 'permission']);
        Permission::firstOrCreate(['name' => 'view permission', 'group' => 'permission']);
        Permission::firstOrCreate(['name' => 'update permission', 'group' => 'permission']);
        Permission::firstOrCreate(['name' => 'delete permission', 'group' => 'permission']);
        Permission::firstOrCreate(['name' => 'create company', 'group' => 'company']);
        Permission::firstOrCreate(['name' => 'view company', 'group' => 'company']);
        Permission::firstOrCreate(['name' => 'update company', 'group' => 'company']);
        Permission::firstOrCreate(['name' => 'delete company', 'group' => 'company']);
        Permission::firstOrCreate(['name' => 'create branch', 'group' => 'branch']);
        Permission::firstOrCreate(['name' => 'view branch', 'group' => 'branch']);
        Permission::firstOrCreate(['name' => 'update branch', 'group' => 'branch']);
        Permission::firstOrCreate(['name' => 'delete branch', 'group' => 'branch']);
        Permission::firstOrCreate(['name' => 'create task', 'group' => 'task']);
        Permission::firstOrCreate(['name' => 'view task', 'group' => 'task']);
        Permission::firstOrCreate(['name' => 'update task', 'group' => 'task']);
        Permission::firstOrCreate(['name' => 'delete task', 'group' => 'task']);
        Permission::firstOrCreate(['name' => 'create agent', 'group' => 'agent']);
        Permission::firstOrCreate(['name' => 'view agent', 'group' => 'agent']);
        Permission::firstOrCreate(['name' => 'update agent', 'group' => 'agent']);
        Permission::firstOrCreate(['name' => 'delete agent', 'group' => 'agent']);
        Permission::firstOrCreate(['name' => 'view task price', 'group' => 'task']);
        Permission::firstOrCreate(['name' => 'create invoice', 'group' => 'invoice']);
        Permission::firstOrCreate(['name' => 'view invoice', 'group' => 'invoice']);
        Permission::firstOrCreate(['name' => 'update invoice', 'group' => 'invoice']);
        Permission::firstOrCreate(['name' => 'update invoice payment method', 'group' => 'invoice']);
        Permission::firstOrCreate(['name' => 'delete invoice', 'group' => 'invoice']);
        Permission::firstOrCreate(['name' => 'create coa', 'group' => 'coa']);
        Permission::firstOrCreate(['name' => 'view coa', 'group' => 'coa']);
        Permission::firstOrCreate(['name' => 'update coa', 'group' => 'coa']);
        Permission::firstOrCreate(['name' => 'delete coa', 'group' => 'coa']);
        Permission::firstOrCreate(['name' => 'create charges', 'group' => 'charges']);
        Permission::firstOrCreate(['name' => 'view charges', 'group' => 'charges']);
        Permission::firstOrCreate(['name' => 'update charges', 'group' => 'charges']);
        Permission::firstOrCreate(['name' => 'delete charges', 'group' => 'charges']);
        Permission::firstOrCreate(['name' => 'create account', 'group' => 'account']);
        Permission::firstOrCreate(['name' => 'view account', 'group' => 'account']);
        Permission::firstOrCreate(['name' => 'update account', 'group' => 'account']);
        Permission::firstOrCreate(['name' => 'delete account', 'group' => 'account']);
        Permission::firstOrCreate(['name' => 'view company summary', 'group' => 'company']);
        Permission::firstOrCreate(['name' => 'create report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'update report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'delete report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'create supplier', 'group' => 'supplier']);
        Permission::firstOrCreate(['name' => 'view supplier', 'group' => 'supplier']);
        Permission::firstOrCreate(['name' => 'update supplier', 'group' => 'supplier']);
        Permission::firstOrCreate(['name' => 'delete supplier', 'group' => 'supplier']);
        Permission::firstOrCreate(['name' => 'create client', 'group' => 'client']);
        Permission::firstOrCreate(['name' => 'view client', 'group' => 'client']);
        Permission::firstOrCreate(['name' => 'update client', 'group' => 'client']);
        Permission::firstOrCreate(['name' => 'delete client', 'group' => 'client']);
        Permission::firstOrCreate(['name' => 'create currency exchange', 'group' => 'currency exchange']);
        Permission::firstOrCreate(['name' => 'view currency exchange', 'group' => 'currency exchange']);
        Permission::firstOrCreate(['name' => 'update currency exchange', 'group' => 'currency exchange']);
        Permission::firstOrCreate(['name' => 'delete currency exchange', 'group' => 'currency exchange']);
        Permission::firstOrCreate(['name' => 'view credit', 'group' => 'credit']);
        Permission::firstOrCreate(['name' => 'view payment', 'group' => 'payment']);
        Permission::firstOrCreate(['name' => 'view refund', 'group' => 'refund']);
        Permission::firstOrCreate(['name' => 'view reconcile report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view profit loss', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view settlement', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view creditors', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view daily sales', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'manage payment method groups', 'group' => 'charges']);
    }
}
