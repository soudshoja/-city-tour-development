<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'payments',
            'credits',
            'journal_entries',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    if (!Schema::hasColumn($table, 'is_locked')) {
                        $t->boolean('is_locked')->default(false)->index();
                        $t->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                        $t->timestamp('locked_at')->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tables = ['payments', 'credits', 'journal_entries'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                // Drop foreign key using raw SQL to avoid naming issues
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = '{$table}' 
                    AND CONSTRAINT_TYPE = 'FOREIGN KEY' 
                    AND CONSTRAINT_NAME LIKE '%locked_by%'
                ");

                foreach ($foreignKeys as $fk) {
                    DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                }

                Schema::table($table, function (Blueprint $t) use ($table) {
                    $columns = ['is_locked', 'locked_by', 'locked_at'];
                    foreach ($columns as $col) {
                        if (Schema::hasColumn($table, $col)) {
                            $t->dropColumn($col);
                        }
                    }
                });
            }
        }
    }
};
