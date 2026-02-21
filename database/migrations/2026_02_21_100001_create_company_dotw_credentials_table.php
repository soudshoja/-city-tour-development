<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create company_dotw_credentials table.
 *
 * Stores per-company DOTW API credentials for multi-tenant B2B hotel booking.
 * Each company has exactly one row (unique constraint on company_id).
 *
 * Credential security:
 * - dotw_username and dotw_password are stored as Laravel-encrypted blobs
 *   produced by encrypt(). They must never be returned in plaintext via API.
 * - dotw_company_code is not sensitive and stored as plain text.
 *
 * Markup:
 * - markup_percent defaults to 20.00 (20% B2C markup applied to raw DOTW fares).
 * - Each company can override this value independently.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_dotw_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->unique();
            $table->text('dotw_username');
            $table->text('dotw_password');
            $table->string('dotw_company_code');
            $table->decimal('markup_percent', 5, 2)->default(20.00);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index('company_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_dotw_credentials');
    }
};
