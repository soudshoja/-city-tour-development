<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('guard_name');
            $table->unsignedBigInteger('company_id')->nullable()->after('guard_name');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->dropUnique('roles_name_guard_name_unique');
            $table->unique(['name', 'guard_name', 'company_id']);
        });

        $roles = Role::all();

        foreach($roles as $role) {
            $role->company_id = 1; 
            $role->save();
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name', 'company_id']);
            $table->unique(['name', 'guard_name']);
            $table->dropColumn('description');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
