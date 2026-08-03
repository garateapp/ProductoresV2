<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movement_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_detail_id')->constrained('inventory_movement_details')->cascadeOnDelete();
            $table->foreignId('logistic_unit_id')->nullable()->constrained('inventory_logistic_units');
            $table->decimal('allocated_quantity', 18, 4);
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations');
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations');
            $table->string('allocation_type', 30);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('movement_detail_id', 'idx_inventory_allocations_detail');
            $table->index('logistic_unit_id', 'idx_inventory_allocations_lu');
            $table->index(['from_location_id', 'to_location_id'], 'idx_inventory_allocations_locations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movement_allocations');
    }
};
