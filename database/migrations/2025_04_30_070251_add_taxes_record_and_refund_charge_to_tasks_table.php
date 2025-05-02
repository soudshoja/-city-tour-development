<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('taxes_record')->nullable()->after('surcharge'); 
            $table->decimal('refund_charge', 10, 2)->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['taxes_record', 'refund_charge']);
        });
    }
};
