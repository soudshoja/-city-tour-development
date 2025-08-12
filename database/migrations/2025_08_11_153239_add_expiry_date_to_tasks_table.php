<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->datetime('expiry_date')->nullable()->after('issued_date');
            $table->index(['status', 'expiry_date']); // Add index for efficient querying
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status', 'expiry_date']);
            $table->dropColumn('expiry_date');
        });
    }
};
