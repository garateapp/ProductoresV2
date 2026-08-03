<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_ledger_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence')->unique();
            $table->uuid('event_uuid')->unique();
            $table->string('event_type', 50);
            $table->uuid('correlation_uuid')->nullable();
            $table->foreignId('movement_id')->nullable()->constrained('inventory_movements');
            $table->foreignId('movement_detail_id')->nullable()->constrained('inventory_movement_details');
            $table->foreignId('allocation_id')->nullable()->constrained('inventory_movement_allocations');
            $table->foreignId('material_id')->nullable()->constrained('inventory_materials');
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations');
            $table->foreignId('logistic_unit_id')->nullable()->constrained('inventory_logistic_units');
            $table->decimal('signed_quantity', 18, 4)->default(0);
            $table->string('stock_effect', 10)->default('none');
            $table->char('previous_hash', 64);
            $table->char('event_hash', 64);
            $table->json('payload');
            $table->dateTime('occurred_at');
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('actor_name_snapshot', 150)->nullable();
            $table->string('device_code', 100)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at'], 'idx_inventory_ledger_type_date');
            $table->index('movement_id', 'idx_inventory_ledger_movement');
            $table->index('movement_detail_id', 'idx_inventory_ledger_detail');
            $table->index(['location_id', 'material_id'], 'idx_inventory_ledger_location_material');
            $table->index(['logistic_unit_id', 'occurred_at'], 'idx_inventory_ledger_lu_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger_events');
    }
};
