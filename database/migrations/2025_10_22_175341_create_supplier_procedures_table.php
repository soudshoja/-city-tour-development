<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->onDelete('cascade');
            $table->string('name');
            $table->longText('procedure');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_procedures');
    }
};
