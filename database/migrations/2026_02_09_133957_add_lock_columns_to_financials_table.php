<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['invoices', 'transactions', 'journal_entries'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'is_locked')) {
                    $t->boolean('is_locked')->default(false)->index();
                    $t->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                    $t->timestamp('locked_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['invoices', 'transactions', 'journal_entries'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('locked_by');
                $t->dropColumn(['is_locked', 'locked_at']);
            });
        }
    }
};
