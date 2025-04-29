<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['refund', 'issued', 'reissued', 'void'])->change();
            $table->date('refund_date')->nullable()->after('enabled'); // just add it, no change()
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status')->change();
            $table->dropColumn('refund_date');
        });
    }
};
