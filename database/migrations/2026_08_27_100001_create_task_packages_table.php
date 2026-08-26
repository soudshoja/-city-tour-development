<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Package entity, plan §3.2 / §4. Explicit, agent-defined, never inferred:
// zero gds_reference groups mix task types, so a package is its own entity.
// The agent selects tasks and types/picks the package label; the string is
// stored verbatim, never inferred from the tasks it contains.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->nullable()->constrained('clients'); // nullable at creation; required at send (§10.4)

            $table->string('reference')->unique(); // e.g. PKG-{company}-{seq}; own sequence, §14.6
            $table->string('name');                // "Alsaadi family — Umrah, Oct 2026"
            $table->string('package_type');        // agent-defined label, verbatim: "Hotel + Flight", "Umrah", ...
            $table->string('status', 20)->default('open')
                ->comment('open | finalized | cancelled');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status'], 'task_packages_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_packages');
    }
};
