<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_gds_pccs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('gds', 30);
            $table->string('pcc', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_gds_pccs');
    }
};
