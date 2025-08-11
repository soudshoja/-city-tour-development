<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
          $table->boolean('is_auto_paid')->default(false)->after('is_active');
          $table->boolean('has_url')->default(false)->after('is_auto_paid');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['is_auto_paid', 'has_url']);
        });
    }
};
