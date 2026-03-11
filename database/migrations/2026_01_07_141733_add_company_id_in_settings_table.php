<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
        });

        Setting::whereNull('company_id')->update(['company_id' => 1]);

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });

    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
