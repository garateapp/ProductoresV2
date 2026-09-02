<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_manual_consumptions', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_accion', 40);
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->decimal('cantidad', 18, 4);
            $table->date('fecha');
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations');
            $table->foreignId('movement_id')->nullable()->constrained('inventory_movements');
            $table->string('folio_nuevo', 100)->nullable();
            $table->json('folios')->nullable();
            $table->string('estado', 30)->default('borrador');
            $table->string('detalle_estado')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_manual_consumptions');
    }
};
