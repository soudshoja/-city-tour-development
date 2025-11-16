<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->string('tran_portal_id')->nullable()->after('api_key');
            $table->string('tran_portal_password')->nullable()->after('tran_portal_id');
            $table->string('terminal_resource_key')->nullable()->after('tran_portal_password');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn('tran_portal_id');
            $table->dropColumn('tran_portal_password');
            $table->dropColumn('terminal_resource_key');
        });
    }
};
