<?php

use App\Enums\TaskRuleEnum;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\TaskRules;
use Database\Seeders\TaskRuleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('column')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'supplier_id', 'name', 'column']);
        });

        // No default seeding - rules are created per supplier as needed

    }

    public function down(): void
    {
        Schema::dropIfExists('task_rules');
    }
};
