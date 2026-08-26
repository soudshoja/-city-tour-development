<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Package line items, plan §3.2. Any task type may be an item; ordering and
// a per-item section-label override are agent-controlled (§7). A task
// belongs to at most one package (unique(task_id) — plan §3.2/§4
// recommendation: a task on two package vouchers double-promises the same
// service and confuses versioning).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_package_id')->constrained('task_packages')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks');

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('section_label')->nullable(); // agent override: "Return transfer"

            $table->timestamps();

            $table->unique('task_id', 'task_package_items_task_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_package_items');
    }
};
