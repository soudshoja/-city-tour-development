<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

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
        // W6.S (w6-brief.md "Consolidation + fixes" item 3): void/reissue/bulkVoid/switchInvoice
        // abilities added to TaskPolicy. Registered as ordinary, explicitly-grantable permissions
        // -- same convention as every other 'task' permission above -- rather than an
        // accountant-default bypass, since none of the pre-existing task abilities auto-grant
        // accountant either.
        Permission::firstOrCreate(['name' => 'void task', 'group' => 'task']);
        Permission::firstOrCreate(['name' => 'reissue task', 'group' => 'task']);
        Permission::firstOrCreate(['name' => 'bulk void task', 'group' => 'task']);
        Permission::firstOrCreate(['name' => 'switch invoice task', 'group' => 'task']);
        // W6.U "Task actions" (void-with-fee / reissue-with-fee approval step) --
        // TaskPolicy::approveFeeOverride(). Same explicitly-grantable convention as the four
        // W6.S permissions immediately above.
        Permission::firstOrCreate(['name' => 'approve task fee override', 'group' => 'task']);
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
        Permission::firstOrCreate(['name' => 'create credit', 'group' => 'credit']); // W7.K: gates CreditController::creditTopup()'s new CreditPolicy::create() ability.
        Permission::firstOrCreate(['name' => 'view payment', 'group' => 'payment']);
        Permission::firstOrCreate(['name' => 'view refund', 'group' => 'refund']);
        Permission::firstOrCreate(['name' => 'view reconcile report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view profit loss', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view settlement', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view creditors', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view daily sales', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'view payment method groups', 'group' => 'charges']);
        Permission::firstOrCreate(['name' => 'manage payment method groups', 'group' => 'charges']);
        Permission::firstOrCreate(['name' => 'view task report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'setting company invoice', 'group' => 'setting']);
        Permission::firstOrCreate(['name' => 'view client report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'create auto billing', 'group' => 'auto billing']);
        Permission::firstOrCreate(['name' => 'view auto billing', 'group' => 'auto billing']);
        Permission::firstOrCreate(['name' => 'update auto billing', 'group' => 'auto billing']);
        Permission::firstOrCreate(['name' => 'delete auto billing', 'group' => 'auto billing']);
        Permission::firstOrCreate(['name' => 'view payment gateways report', 'group' => 'report']);
        Permission::firstOrCreate(['name' => 'manage locks', 'group' => 'lock management']);
        Permission::firstOrCreate(['name' => 'view agent charges', 'group' => 'setting']);
        Permission::firstOrCreate(['name' => 'manage agent charges', 'group' => 'setting']);
        Permission::firstOrCreate(['name' => 'view agent loss', 'group' => 'setting']);
        Permission::firstOrCreate(['name' => 'manage agent loss', 'group' => 'setting']);
        Permission::firstOrCreate(['name' => 'view notification', 'group' => 'setting']);
        Permission::firstOrCreate(['name' => 'manage notification', 'group' => 'setting']);

        // soud amendment (CreditPolicy::create() gate fix, W7.K): this seeder only ever
        // creates Permission rows -- role-to-permission grants for every other permission it
        // defines are done per-company through the runtime role-management UI, not here. No
        // existing 'admin'/'company'-named Role row anywhere in this codebase already holds
        // 'view credit' (or any other credit permission) to mirror, so per the fallback rule
        // ("if none hold any credit permission, assign it to the ADMIN and COMPANY roles"),
        // grant 'create credit' directly to every existing role literally named 'admin' or
        // 'company' (global and per-company rows alike -- CompanyRolesSeeder creates one pair
        // per company). Idempotent: givePermissionTo() no-ops if the role already has it.
        $createCredit = Permission::where('name', 'create credit')->first();

        if ($createCredit !== null) {
            Role::whereIn('name', ['admin', 'company'])->get()->each(
                fn (Role $role) => $role->hasPermissionTo($createCredit) ? null : $role->givePermissionTo($createCredit)
            );
        }
    }
}
