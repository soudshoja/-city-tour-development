<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add auto_process_pdf column to supplier_companies pivot table.
     * This column enables/disables automatic PDF processing via ResailAI webhook.
     */
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            $table->boolean('auto_process_pdf')
                ->default(false)
                ->after('is_active')
                ->comment('Auto-process PDF files via ResailAI webhook for this supplier/company combo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            $table->dropColumn('auto_process_pdf');
        });
    }
};
