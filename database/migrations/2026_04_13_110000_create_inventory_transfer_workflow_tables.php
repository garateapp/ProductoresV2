<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_location_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['inventory_location_id', 'user_id'], 'inventory_location_user_unique');
        });

        Schema::create('inventory_transfer_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained('inventory_movements')->cascadeOnDelete();
            $table->foreignId('logistic_unit_id')->constrained('inventory_logistic_units')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->foreignId('origin_location_id')->constrained('inventory_locations');
            $table->foreignId('destination_location_id')->constrained('inventory_locations');
            $table->decimal('quantity', 18, 4);
            $table->string('status')->default('pending');
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispatched_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('returned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['movement_id', 'status']);
            $table->unique(['movement_id', 'logistic_unit_id'], 'inventory_transfer_units_movement_lpn_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_units');
        Schema::dropIfExists('inventory_location_user');
    }
};
