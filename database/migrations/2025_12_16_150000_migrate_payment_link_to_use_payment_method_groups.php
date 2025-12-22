<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create new pivot table for payment method groups
        Schema::create('payment_link_payment_method_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_group_id')
                ->constrained('payment_method_groups')
                ->onDelete('cascade')
                ->name('pl_pmg_group_fk');
            $table->timestamps();
            
            $table->unique(['payment_id', 'payment_method_group_id'], 'payment_group_unique');
        });

        // Migrate existing data from payment_link_payment_method to payment_link_payment_method_group
        // Get the group_id for each payment_method and insert into new table
        DB::statement("
            INSERT INTO payment_link_payment_method_group (payment_id, payment_method_group_id, created_at, updated_at)
            SELECT DISTINCT 
                plpm.payment_id,
                pm.payment_method_group_id,
                plpm.created_at,
                plpm.updated_at
            FROM payment_link_payment_method plpm
            INNER JOIN payment_methods pm ON plpm.payment_method_id = pm.id
            WHERE pm.payment_method_group_id IS NOT NULL
        ");

        // Drop old pivot table
        Schema::dropIfExists('payment_link_payment_method');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate old pivot table
        Schema::create('payment_link_payment_method', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['payment_id', 'payment_method_id']);
        });

        // Migrate data back from groups to methods (get the first active method in each group)
        DB::statement("
            INSERT INTO payment_link_payment_method (payment_id, payment_method_id, created_at, updated_at)
            SELECT 
                plpmg.payment_id,
                (
                    SELECT pm.id 
                    FROM payment_methods pm 
                    WHERE pm.payment_method_group_id = plpmg.payment_method_group_id 
                    AND pm.is_active = 1
                    ORDER BY pm.id ASC 
                    LIMIT 1
                ) as payment_method_id,
                plpmg.created_at,
                plpmg.updated_at
            FROM payment_link_payment_method_group plpmg
        ");

        // Drop new pivot table
        Schema::dropIfExists('payment_link_payment_method_group');
    }
};
