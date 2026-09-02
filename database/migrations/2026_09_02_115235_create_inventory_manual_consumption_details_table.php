<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_manual_consumption_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manual_consumption_id');
            $table->unsignedBigInteger('material_id');
            $table->decimal('cantidad', 18, 6);
            $table->timestamps();
        });

        Schema::table('inventory_manual_consumption_details', function (Blueprint $table) {
            $table->index('manual_consumption_id', 'mc_details_consumption_idx');
            $table->index('material_id', 'mc_details_material_idx');
            $table->foreign('manual_consumption_id', 'mc_det_consumption_fk')
                ->references('id')->on('inventory_manual_consumptions')->cascadeOnDelete();
            $table->foreign('material_id', 'mc_det_material_fk')
                ->references('id')->on('inventory_materials');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_manual_consumption_details');
    }
};
