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
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('external_url');
            $table->unsignedBigInteger('locked_by')->nullable()->after('is_locked');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            $table->index('is_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropIndex(['is_locked']);
            $table->dropColumn(['is_locked', 'locked_by', 'locked_at']);
        });
    }
};
