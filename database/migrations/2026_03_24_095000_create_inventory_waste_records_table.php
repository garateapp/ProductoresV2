<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_waste_records', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('movement_id')->constrained('inventory_movements');
            $table->foreignId('movement_detail_id')->constrained('inventory_movement_details');
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->foreignId('logistic_unit_id')->nullable()->constrained('inventory_logistic_units');
            $table->foreignId('detected_location_id')->constrained('inventory_locations');
            $table->foreignId('quarantine_location_id')->nullable()->constrained('inventory_locations');
            $table->foreignId('waste_reason_id')->constrained('inventory_waste_reasons');
            $table->decimal('quantity', 18, 4);
            $table->string('status', 30)->default('reported');
            $table->string('severity', 20)->nullable();
            $table->boolean('requires_supervisor_review')->default(false);
            $table->string('photo_path', 255)->nullable();
            $table->json('evidence_payload')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reported_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['detected_location_id', 'reported_at'], 'idx_inventory_waste_detected_location');
            $table->index(['quarantine_location_id', 'status'], 'idx_inventory_waste_quarantine_location');
            $table->index(['material_id', 'reported_at'], 'idx_inventory_waste_material_date');
            $table->index(['waste_reason_id', 'reported_at'], 'idx_inventory_waste_reason_date');
            $table->index('logistic_unit_id', 'idx_inventory_waste_lu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_waste_records');
    }
};
