<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pallet_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_inspection_id')
                ->constrained('pallet_inspections')
                ->cascadeOnDelete();
            $table->foreignId('technical_sheet_pallet_item_id')
                ->constrained('inventory_technical_sheet_pallet_items');
            $table->foreignId('material_utilizado_id')
                ->constrained('inventory_materials');
            $table->boolean('es_reemplazo')->default(false);
            $table->boolean('cumple');
            $table->string('motivo_no_cumple')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_inspection_items');
    }
};
