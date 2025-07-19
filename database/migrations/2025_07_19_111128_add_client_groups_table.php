<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_client_id');
            $table->unsignedBigInteger('child_client_id');
            $table->string('relation')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('parent_client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('child_client_id')->references('id')->on('clients')->onDelete('cascade');

            // Indexes for performance
            $table->index(['parent_client_id', 'child_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_groups');
    }
};
