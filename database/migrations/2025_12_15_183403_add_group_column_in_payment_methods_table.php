<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->foreignId('payment_method_group_id')->after('english_name')->nullable()->constrained('payment_method_groups')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropForeign(['payment_method_group_id']);
            $table->dropColumn('payment_method_group_id');
        });
    }
};
