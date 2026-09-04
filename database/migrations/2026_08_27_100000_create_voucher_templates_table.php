<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Voucher Templates plan §3.1. Registry of shipped voucher designs +
// per-company defaults/toggles. The design itself lives in code (a Blade
// file we ship); this table carries identity, per-company overrides and
// toggles only — never client-supplied HTML (plan §14.8).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_templates', function (Blueprint $table) {
            $table->id();

            // NULL = system template we ship, visible to every company.
            // Non-null = a company's own row overriding defaults for that
            // design (v1: toggles only; v2+: possibly content, §14.8).
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();

            $table->string('task_type', 20); // flight|hotel|visa|insurance|generic|package
            $table->string('name');          // "Classic Hotel Voucher"
            $table->string('view_key');      // 'vouchers.hotel-classic' -> resources/views/vouchers/hotel-classic.blade.php
            $table->string('language', 3)->default('EN'); // EN | ARB (Term::LANGUAGE_* convention)

            $table->boolean('is_default')->default(false); // per company+task_type+language (Term::setAsDefault() pattern)
            $table->boolean('is_active')->default(true);
            $table->boolean('show_price')->default(false);          // §9
            $table->boolean('show_payment_status')->default(false); // §9

            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete(); // T&C block to append, §14.5
            $table->json('options')->nullable(); // accent color, section toggles (e.g. hide supplier §14.11)

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['company_id', 'task_type', 'language', 'is_active'],
                'voucher_templates_company_type_lang_active_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_templates');
    }
};
