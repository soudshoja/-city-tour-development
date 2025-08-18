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
            $table->decimal('original_tax', 10, 3)->nullable()->after('tax')->comment('Original tax of the task before exchange currency');
            $table->decimal('original_surcharge', 10, 3)->nullable()->after('surcharge')->comment('Original surcharge of the task before exchange currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['original_tax', 'original_surcharge']);
        });
    }
};
