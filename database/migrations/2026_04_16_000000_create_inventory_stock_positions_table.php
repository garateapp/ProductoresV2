<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->foreignId('location_id')->constrained('inventory_locations');
            $table->foreignId('logistic_unit_id')->nullable()->constrained('inventory_logistic_units');
            $table->decimal('quantity', 18, 4);
            $table->string('lot_code', 100)->nullable();
            $table->string('status', 30)->default('available');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Normalize nullable identifiers so the unique strategy still blocks true duplicates.
            $table->unsignedBigInteger('logistic_unit_key')->storedAs('coalesce(logistic_unit_id, 0)');
            $table->string('lot_code_key', 100)->storedAs("coalesce(lot_code, '')");

            $table->unique(
                ['material_id', 'location_id', 'logistic_unit_key', 'lot_code_key', 'status'],
                'ux_inventory_stock_positions_material_location_unit_lot_status'
            );
            $table->index('material_id', 'idx_inventory_stock_positions_material_id');
            $table->index('location_id', 'idx_inventory_stock_positions_location_id');
            $table->index('logistic_unit_id', 'idx_inventory_stock_positions_logistic_unit_id');
            $table->index(['material_id', 'location_id'], 'idx_inventory_stock_positions_material_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_positions');
    }
};
