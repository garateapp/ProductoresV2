<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_person_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->foreignId('movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('origin_location_id')->constrained('inventory_locations');
            $table->string('person_name', 150);
            $table->string('person_position', 150);
            $table->dateTime('delivered_at');
            $table->longText('signature_data_url');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['delivered_at', 'origin_location_id'], 'idx_inventory_person_delivery_date_origin');
        });

        Schema::create('inventory_person_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_delivery_id')->constrained('inventory_person_deliveries')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->decimal('cantidad', 18, 4);
            $table->timestamps();

            $table->index(['material_id', 'person_delivery_id'], 'idx_inventory_person_delivery_item_material');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_person_delivery_items');
        Schema::dropIfExists('inventory_person_deliveries');
    }
};
