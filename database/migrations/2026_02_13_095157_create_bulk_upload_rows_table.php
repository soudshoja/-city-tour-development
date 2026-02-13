<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bulk_upload_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bulk_upload_id');
            $table->unsignedInteger('row_number');
            $table->enum('status', ['valid', 'error', 'flagged'])->default('valid');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->json('raw_data');
            $table->json('errors')->nullable();
            $table->string('flag_reason')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('bulk_upload_id')->references('id')->on('bulk_uploads')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_upload_rows');
    }
};
