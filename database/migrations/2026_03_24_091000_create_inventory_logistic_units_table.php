<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_logistic_units', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate_number', 100)->unique();
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->foreignId('current_location_id')->nullable()->constrained('inventory_locations');
            $table->string('status', 30)->default('active');
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('available_quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()->constrained('inventory_units');
            $table->string('lot_code', 100)->nullable();
            $table->string('supplier_lot', 100)->nullable();
            $table->string('production_batch', 100)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('last_moved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'status'], 'idx_inventory_logistic_units_material_status');
            $table->index(['current_location_id', 'status'], 'idx_inventory_logistic_units_location_status');
            $table->index('lot_code', 'idx_inventory_logistic_units_lot_code');
            $table->index(['reference_type', 'reference_id'], 'idx_inventory_logistic_units_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logistic_units');
    }
};
